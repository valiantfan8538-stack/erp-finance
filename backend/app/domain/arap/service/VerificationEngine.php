<?php

namespace app\domain\arap\service;

use app\domain\arap\repository\ReceivableRepositoryInterface;
use app\domain\arap\repository\PayableRepositoryInterface;
use app\domain\arap\repository\VerifyRecordRepositoryInterface;
use think\facade\Db;
use think\facade\Log;

class VerificationEngine
{
    private ReceivableRepositoryInterface $receivableRepo;
    private PayableRepositoryInterface $payableRepo;
    private VerifyRecordRepositoryInterface $verifyRepo;

    public function __construct(
        ReceivableRepositoryInterface $receivableRepo,
        PayableRepositoryInterface $payableRepo,
        VerifyRecordRepositoryInterface $verifyRepo
    ) {
        $this->receivableRepo = $receivableRepo;
        $this->payableRepo    = $payableRepo;
        $this->verifyRepo     = $verifyRepo;
    }

    public function verifyReceivable(int $bookId, int $partnerId, string $verifyAmount, int $userId): array
    {
        Db::startTrans();
        try {
            $bills = $this->receivableRepo->lockUnverifiedByPartner($partnerId);

            if (empty($bills)) {
                Db::commit();
                return ['code' => 400, 'msg' => '该客户没有未核销的应收单据'];
            }

            $remaining = $verifyAmount;
            foreach ($bills as $bill) {
                if (bccomp($remaining, '0.00', 2) <= 0) break;

                $unreceived = (string)$bill['unreceived_amount'];
                $applyAmount = bccomp($remaining, $unreceived, 2) >= 0 ? $unreceived : $remaining;

                $newReceived   = bcadd((string)$bill['received_amount'], $applyAmount, 2);
                $newUnreceived = bcsub($unreceived, $applyAmount, 2);

                $this->receivableRepo->updateReceivedAmount($bill['id'], $newReceived, $newUnreceived);
                $newStatus = bccomp($newUnreceived, '0.00', 2) <= 0 ? 2 : 1;
                $this->receivableRepo->updateStatus($bill['id'], $newStatus);

                $this->verifyRepo->create([
                    'book_id'     => $bookId,
                    'partner_id'  => $partnerId,
                    'verify_date' => date('Y-m-d'),
                    'source_type' => 'receivable',
                    'source_id'   => $bill['id'],
                    'amount'      => $applyAmount,
                    'created_by'  => $userId,
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);

                $remaining = bcsub($remaining, $applyAmount, 2);
            }

            Db::commit();
            $verified = bcsub($verifyAmount, $remaining, 2);
            return ['code' => 200, 'msg' => "核销成功，本次核销金额: {$verified}"];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('VerificationEngine.verifyReceivable failed: ' . $e->getMessage());
            return ['code' => 500, 'msg' => '核销失败，请稍后重试'];
        }
    }

    public function verifyPayable(int $bookId, int $partnerId, string $verifyAmount, int $userId): array
    {
        Db::startTrans();
        try {
            $bills = $this->payableRepo->lockUnverifiedByPartner($partnerId);

            if (empty($bills)) {
                Db::commit();
                return ['code' => 400, 'msg' => '该供应商没有未核销的应付单据'];
            }

            $remaining = $verifyAmount;
            foreach ($bills as $bill) {
                if (bccomp($remaining, '0.00', 2) <= 0) break;

                $unpaid = (string)$bill['unpaid_amount'];
                $applyAmount = bccomp($remaining, $unpaid, 2) >= 0 ? $unpaid : $remaining;

                $newPaid   = bcadd((string)$bill['paid_amount'], $applyAmount, 2);
                $newUnpaid = bcsub($unpaid, $applyAmount, 2);

                $this->payableRepo->updatePaidAmount($bill['id'], $newPaid, $newUnpaid);
                $newStatus = bccomp($newUnpaid, '0.00', 2) <= 0 ? 2 : 1;
                $this->payableRepo->updateStatus($bill['id'], $newStatus);

                $this->verifyRepo->create([
                    'book_id'     => $bookId,
                    'partner_id'  => $partnerId,
                    'verify_date' => date('Y-m-d'),
                    'source_type' => 'payable',
                    'source_id'   => $bill['id'],
                    'amount'      => $applyAmount,
                    'created_by'  => $userId,
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);

                $remaining = bcsub($remaining, $applyAmount, 2);
            }

            Db::commit();
            $verified = bcsub($verifyAmount, $remaining, 2);
            return ['code' => 200, 'msg' => "核销成功，本次核销金额: {$verified}"];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('VerificationEngine.verifyPayable failed: ' . $e->getMessage());
            return ['code' => 500, 'msg' => '核销失败，请稍后重试'];
        }
    }

    public function getAgingAnalysis(int $partnerId, string $type): array
    {
        $repo = $type === 'receivable' ? $this->receivableRepo : $this->payableRepo;
        $bills = $repo->findByPartner($partnerId);

        $aging = [
            'current' => ['count' => 0, 'amount' => '0.00'],
            '30days'  => ['count' => 0, 'amount' => '0.00'],
            '60days'  => ['count' => 0, 'amount' => '0.00'],
            '90days'  => ['count' => 0, 'amount' => '0.00'],
            'over90'  => ['count' => 0, 'amount' => '0.00'],
        ];

        $now = time();
        foreach ($bills as $bill) {
            $amountField = $type === 'receivable' ? 'unreceived_amount' : 'unpaid_amount';
            $amount = (string)($bill[$amountField] ?? '0.00');
            if (bccomp($amount, '0.00', 2) <= 0) continue;

            $dueDate = strtotime($bill['due_date'] ?? $bill['bill_date']);
            $daysOverdue = (int)(($now - $dueDate) / 86400);

            $bucket = match(true) {
                $daysOverdue <= 0  => 'current',
                $daysOverdue <= 30 => '30days',
                $daysOverdue <= 60 => '60days',
                $daysOverdue <= 90 => '90days',
                default            => 'over90',
            };

            $aging[$bucket]['count']++;
            $aging[$bucket]['amount'] = bcadd($aging[$bucket]['amount'], $amount, 2);
        }

        return $aging;
    }
}
