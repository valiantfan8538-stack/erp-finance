<?php

namespace app\application\finance;

use app\domain\finance\repository\VoucherRepositoryInterface;
use app\domain\finance\repository\SubjectRepositoryInterface;
use app\domain\finance\service\PostingEngine;
use think\facade\Db;
use think\facade\Log;

class VoucherAppService
{
    private VoucherRepositoryInterface $voucherRepo;
    private SubjectRepositoryInterface $subjectRepo;
    private PostingEngine $postingEngine;

    public function __construct(
        VoucherRepositoryInterface $voucherRepo,
        SubjectRepositoryInterface $subjectRepo,
        PostingEngine $postingEngine
    ) {
        $this->voucherRepo   = $voucherRepo;
        $this->subjectRepo   = $subjectRepo;
        $this->postingEngine = $postingEngine;
    }

    public function listVouchers(array $filters, int $page = 1, int $perPage = 20): array
    {
        return $this->voucherRepo->findByBook($filters, $page, $perPage);
    }

    public function getVoucher(int $id): ?array
    {
        $voucher = $this->voucherRepo->findById($id);
        if ($voucher) {
            $voucher['entries'] = $this->voucherRepo->findEntries($id);
        }
        return $voucher;
    }

    public function createVoucher(array $data, int $userId): array
    {
        if (empty($data['book_id']) || empty($data['entries'])) {
            return ['code' => 400, 'msg' => '参数不完整'];
        }

        $entries = $data['entries'];
        if (count($entries) < 2) {
            return ['code' => 400, 'msg' => '至少需要一借一贷两条分录'];
        }

        $totalDebit  = '0.00';
        $totalCredit = '0.00';
        foreach ($entries as $entry) {
            $totalDebit  = bcadd($totalDebit, (string)($entry['debit_amount'] ?? '0.00'), 2);
            $totalCredit = bcadd($totalCredit, (string)($entry['credit_amount'] ?? '0.00'), 2);
        }
        if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
            return ['code' => 400, 'msg' => "借贷不平衡: 借方{$totalDebit} ≠ 贷方{$totalCredit}"];
        }

        $bookId = (int)$data['book_id'];
        $year   = (int)($data['year'] ?? date('Y'));
        $period = (int)($data['period'] ?? 1);

        $periodClosed = Db::table('sys_accounting_period')
            ->where('book_id', $bookId)
            ->where('year', $year)
            ->where('period', $period)
            ->lock(true)
            ->find();
        $periodClosed = $periodClosed && $periodClosed['status'] >= 1;
        if ($periodClosed) {
            return ['code' => 400, 'msg' => '该会计期间已结账，不可新增凭证'];
        }

        $voucherTypeId = (int)($data['voucher_type_id'] ?? 1);
        $voucherNo = $this->voucherRepo->getNextVoucherNo($bookId, $voucherTypeId, $year, $period);

        $voucherData = [
            'book_id'          => $bookId,
            'voucher_type_id'  => $voucherTypeId,
            'voucher_no'       => $voucherNo,
            'year'             => $year,
            'period'           => $period,
            'date'             => $data['date'] ?? date('Y-m-d'),
            'attachment_count' => (int)($data['attachment_count'] ?? 0),
            'prepared_by'      => $userId,
            'status'           => 0,
            'total_debit'      => $totalDebit,
            'total_credit'     => $totalCredit,
            'remark'           => $data['remark'] ?? '',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        $entryRows = [];
        foreach ($entries as $entry) {
            $entryRows[] = [
                'subject_id'    => (int)$entry['subject_id'],
                'subject_code'  => $entry['subject_code'] ?? '',
                'summary'       => $entry['summary'] ?? '',
                'debit_amount'  => (string)($entry['debit_amount'] ?? '0.00'),
                'credit_amount' => (string)($entry['credit_amount'] ?? '0.00'),
            ];
        }

        try {
            $voucherId = $this->voucherRepo->create($voucherData, $entryRows);
            return ['code' => 200, 'msg' => '凭证保存成功', 'data' => ['id' => $voucherId]];
        } catch (\Exception $e) {
            Log::error('VoucherAppService.createVoucher failed: ' . $e->getMessage());
            return ['code' => 500, 'msg' => '凭证保存失败，请稍后重试'];
        }
    }

    public function audit(int $id, int $userId): array
    {
        $voucher = $this->voucherRepo->findById($id);
        if (!$voucher) {
            return ['code' => 404, 'msg' => '凭证不存在'];
        }
        if ((int)$voucher['status'] >= 1) {
            return ['code' => 400, 'msg' => '凭证已审核'];
        }
        if ((int)$voucher['prepared_by'] === $userId) {
            return ['code' => 400, 'msg' => '不能审核自己制单的凭证'];
        }

        $this->voucherRepo->updateStatus($id, 1, $userId, 'audit_by');
        return ['code' => 200, 'msg' => '审核成功'];
    }

    public function unaudit(int $id): array
    {
        $voucher = $this->voucherRepo->findById($id);
        if (!$voucher) {
            return ['code' => 404, 'msg' => '凭证不存在'];
        }
        if ((int)$voucher['status'] >= 2) {
            return ['code' => 400, 'msg' => '已过账凭证不能反审核'];
        }

        $this->voucherRepo->updateStatus($id, 0, null, 'audit_by');
        return ['code' => 200, 'msg' => '反审核成功'];
    }

    public function post(int $id, int $userId): array
    {
        $voucher = $this->voucherRepo->lockForPosting($id);
        if (!$voucher) {
            return ['code' => 404, 'msg' => '凭证不存在或状态异常'];
        }

        $entries = $this->voucherRepo->findEntries($id);
        $voucher['posted_by'] = $userId;

        try {
            $this->postingEngine->post($voucher, $entries);
            return ['code' => 200, 'msg' => '过账成功'];
        } catch (\Exception $e) {
            Log::error('VoucherAppService.post failed: ' . $e->getMessage());
            return ['code' => 500, 'msg' => '过账失败，请稍后重试'];
        }
    }

    public function unpost(int $id): array
    {
        $voucher = $this->voucherRepo->findById($id);
        if (!$voucher) {
            return ['code' => 404, 'msg' => '凭证不存在'];
        }
        if ((int)$voucher['status'] !== 2) {
            return ['code' => 400, 'msg' => '只有已过账凭证才能反过账'];
        }

        $entries = $this->voucherRepo->findEntries($id);

        try {
            $this->postingEngine->unpost($voucher, $entries);
            return ['code' => 200, 'msg' => '反过账成功'];
        } catch (\Exception $e) {
            Log::error('VoucherAppService.unpost failed: ' . $e->getMessage());
            return ['code' => 500, 'msg' => '反过账失败，请稍后重试'];
        }
    }

    public function delete(int $id): array
    {
        $voucher = $this->voucherRepo->findById($id);
        if (!$voucher) {
            return ['code' => 404, 'msg' => '凭证不存在'];
        }
        if ((int)$voucher['status'] >= 1) {
            return ['code' => 400, 'msg' => '已审核凭证不能删除，请先反审核'];
        }

        $this->voucherRepo->delete($id);
        return ['code' => 200, 'msg' => '删除成功'];
    }
}
