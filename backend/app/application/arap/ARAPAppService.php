<?php

namespace app\application\arap;

use app\domain\arap\repository\ReceivableRepositoryInterface;
use app\domain\arap\repository\PayableRepositoryInterface;
use app\domain\arap\repository\VerifyRecordRepositoryInterface;
use app\domain\arap\service\VerificationEngine;

class ARAPAppService
{
    private ReceivableRepositoryInterface $receivableRepo;
    private PayableRepositoryInterface $payableRepo;
    private VerifyRecordRepositoryInterface $verifyRepo;
    private VerificationEngine $verificationEngine;

    public function __construct(
        ReceivableRepositoryInterface $receivableRepo,
        PayableRepositoryInterface $payableRepo,
        VerifyRecordRepositoryInterface $verifyRepo,
        VerificationEngine $verificationEngine
    ) {
        $this->receivableRepo     = $receivableRepo;
        $this->payableRepo        = $payableRepo;
        $this->verifyRepo         = $verifyRepo;
        $this->verificationEngine = $verificationEngine;
    }

    public function listReceivables(array $filters, int $page = 1, int $perPage = 20): array
    {
        return $this->receivableRepo->findByBook($filters, $page, $perPage);
    }

    public function getReceivable(int $id): ?array
    {
        $receivable = $this->receivableRepo->findById($id);
        if ($receivable) {
            $receivable['verify_records'] = $this->verifyRepo->findBySource('receivable', $id);
        }
        return $receivable;
    }

    public function createReceivable(array $data, int $userId): array
    {
        if (empty($data['book_id']) || empty($data['partner_id']) || empty($data['amount'])) {
            return ['code' => 400, 'msg' => '参数不完整: book_id, partner_id, amount 必填'];
        }

        $amount = (string)$data['amount'];
        $taxAmount = (string)($data['tax_amount'] ?? '0.00');
        $totalAmount = bcadd($amount, $taxAmount, 2);

        $id = $this->receivableRepo->create([
            'book_id'           => (int)$data['book_id'],
            'bill_no'           => $data['bill_no'] ?? ('AR' . date('YmdHis')),
            'partner_id'        => (int)$data['partner_id'],
            'bill_date'         => $data['bill_date'] ?? date('Y-m-d'),
            'business_date'     => $data['business_date'] ?? date('Y-m-d'),
            'subject_id'        => (int)($data['subject_id'] ?? 0),
            'amount'            => $amount,
            'tax_amount'        => $taxAmount,
            'total_amount'      => $totalAmount,
            'received_amount'   => '0.00',
            'unreceived_amount' => $totalAmount,
            'due_date'          => $data['due_date'] ?? null,
            'remark'            => $data['remark'] ?? '',
            'created_by'        => $userId,
            'status'            => 0,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        return ['code' => 200, 'msg' => '应收单创建成功', 'data' => ['id' => $id]];
    }

    public function deleteReceivable(int $id): array
    {
        $receivable = $this->receivableRepo->findById($id);
        if (!$receivable) {
            return ['code' => 404, 'msg' => '应收单不存在'];
        }
        if ((int)$receivable['status'] >= 1) {
            return ['code' => 400, 'msg' => '已核销的应收单不能删除'];
        }
        $this->receivableRepo->delete($id);
        return ['code' => 200, 'msg' => '删除成功'];
    }

    public function listPayables(array $filters, int $page = 1, int $perPage = 20): array
    {
        return $this->payableRepo->findByBook($filters, $page, $perPage);
    }

    public function getPayable(int $id): ?array
    {
        $payable = $this->payableRepo->findById($id);
        if ($payable) {
            $payable['verify_records'] = $this->verifyRepo->findBySource('payable', $id);
        }
        return $payable;
    }

    public function createPayable(array $data, int $userId): array
    {
        if (empty($data['book_id']) || empty($data['partner_id']) || empty($data['amount'])) {
            return ['code' => 400, 'msg' => '参数不完整: book_id, partner_id, amount 必填'];
        }

        $amount = (string)$data['amount'];
        $taxAmount = (string)($data['tax_amount'] ?? '0.00');
        $totalAmount = bcadd($amount, $taxAmount, 2);

        $id = $this->payableRepo->create([
            'book_id'        => (int)$data['book_id'],
            'bill_no'        => $data['bill_no'] ?? ('AP' . date('YmdHis')),
            'partner_id'     => (int)$data['partner_id'],
            'bill_date'      => $data['bill_date'] ?? date('Y-m-d'),
            'business_date'  => $data['business_date'] ?? date('Y-m-d'),
            'subject_id'     => (int)($data['subject_id'] ?? 0),
            'amount'         => $amount,
            'tax_amount'     => $taxAmount,
            'total_amount'   => $totalAmount,
            'paid_amount'    => '0.00',
            'unpaid_amount'  => $totalAmount,
            'due_date'       => $data['due_date'] ?? null,
            'remark'         => $data['remark'] ?? '',
            'created_by'     => $userId,
            'status'         => 0,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        return ['code' => 200, 'msg' => '应付单创建成功', 'data' => ['id' => $id]];
    }

    public function deletePayable(int $id): array
    {
        $payable = $this->payableRepo->findById($id);
        if (!$payable) {
            return ['code' => 404, 'msg' => '应付单不存在'];
        }
        if ((int)$payable['status'] >= 1) {
            return ['code' => 400, 'msg' => '已核销的应付单不能删除'];
        }
        $this->payableRepo->delete($id);
        return ['code' => 200, 'msg' => '删除成功'];
    }

    public function verifyReceivable(int $receivableId, string $amount, int $userId): array
    {
        $receivable = $this->receivableRepo->findById($receivableId);
        if (!$receivable) {
            return ['code' => 404, 'msg' => '应收单不存在'];
        }
        return $this->verificationEngine->verifyReceivable(
            (int)$receivable['book_id'],
            (int)$receivable['partner_id'],
            $amount,
            $userId
        );
    }

    public function verifyPayable(int $payableId, string $amount, int $userId): array
    {
        $payable = $this->payableRepo->findById($payableId);
        if (!$payable) {
            return ['code' => 404, 'msg' => '应付单不存在'];
        }
        return $this->verificationEngine->verifyPayable(
            (int)$payable['book_id'],
            (int)$payable['partner_id'],
            $amount,
            $userId
        );
    }

    public function agingAnalysis(int $partnerId, string $type): array
    {
        return $this->verificationEngine->getAgingAnalysis($partnerId, $type);
    }
}
