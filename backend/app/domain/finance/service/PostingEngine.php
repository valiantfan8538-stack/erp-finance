<?php

namespace app\domain\finance\service;

use app\domain\finance\repository\SubjectBalanceRepositoryInterface;
use app\domain\finance\repository\SubjectRepositoryInterface;
use think\facade\Db;

class PostingEngine
{
    private SubjectBalanceRepositoryInterface $balanceRepo;
    private SubjectRepositoryInterface $subjectRepo;

    public function __construct(
        SubjectBalanceRepositoryInterface $balanceRepo,
        SubjectRepositoryInterface $subjectRepo
    ) {
        $this->balanceRepo = $balanceRepo;
        $this->subjectRepo = $subjectRepo;
    }

    public function post(array $voucher, array $entries): void
    {
        Db::startTrans();
        try {
            foreach ($entries as $entry) {
                $this->applyEntry(
                    $voucher['book_id'],
                    $entry['subject_id'],
                    $voucher['year'],
                    $voucher['period'],
                    (string)($entry['debit_amount'] ?? '0.00'),
                    (string)($entry['credit_amount'] ?? '0.00')
                );
            }

            Db::table('finance_voucher')->where('id', $voucher['id'])->update([
                'status'    => 2,
                'posted_by' => $voucher['posted_by'] ?? null,
                'posted_at' => date('Y-m-d H:i:s'),
            ]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function unpost(array $voucher, array $entries): void
    {
        Db::startTrans();
        try {
            foreach ($entries as $entry) {
                $this->reverseEntry(
                    $voucher['book_id'],
                    $entry['subject_id'],
                    $voucher['year'],
                    $voucher['period'],
                    (string)($entry['debit_amount'] ?? '0.00'),
                    (string)($entry['credit_amount'] ?? '0.00')
                );
            }

            Db::table('finance_voucher')->where('id', $voucher['id'])->update([
                'status'    => 1,
                'posted_by' => null,
                'posted_at' => null,
            ]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    private function applyEntry(int $bookId, int $subjectId, int $year, int $period, string $debit, string $credit): void
    {
        $balance = $this->balanceRepo->findOrCreate($bookId, $subjectId, $year, $period);
        $balance = $this->balanceRepo->lockForUpdate($bookId, $subjectId, $year, $period);

        $newDebitOcc  = bcadd((string)$balance['debit_occurrence'], $debit, 2);
        $newCreditOcc = bcadd((string)$balance['credit_occurrence'], $credit, 2);

        $subject = $this->subjectRepo->findById($subjectId);
        $direction = $subject['direction'] ?? 'debit';

        if ($direction === 'debit') {
            $net = bcsub(bcadd((string)$balance['initial_debit'], $newDebitOcc, 2), bcadd((string)$balance['initial_credit'], $newCreditOcc, 2), 2);
            $finalDebit  = bccomp($net, '0.00', 2) >= 0 ? $net : '0.00';
            $finalCredit = bccomp($net, '0.00', 2) < 0 ? bcsub('0.00', $net, 2) : '0.00';
        } else {
            $net = bcsub(bcadd((string)$balance['initial_credit'], $newCreditOcc, 2), bcadd((string)$balance['initial_debit'], $newDebitOcc, 2), 2);
            $finalCredit = bccomp($net, '0.00', 2) >= 0 ? $net : '0.00';
            $finalDebit  = bccomp($net, '0.00', 2) < 0 ? bcsub('0.00', $net, 2) : '0.00';
        }

        $this->balanceRepo->updateBalance($balance['id'], [
            'debit_occurrence'  => $newDebitOcc,
            'credit_occurrence' => $newCreditOcc,
            'final_debit'       => $finalDebit,
            'final_credit'      => $finalCredit,
        ]);
    }

    private function reverseEntry(int $bookId, int $subjectId, int $year, int $period, string $debit, string $credit): void
    {
        $balance = $this->balanceRepo->lockForUpdate($bookId, $subjectId, $year, $period);
        if (!$balance) {
            return;
        }

        $newDebitOcc  = bcsub((string)$balance['debit_occurrence'], $debit, 2);
        $newCreditOcc = bcsub((string)$balance['credit_occurrence'], $credit, 2);

        $subject = $this->subjectRepo->findById($subjectId);
        $direction = $subject['direction'] ?? 'debit';

        if ($direction === 'debit') {
            $net = bcsub(bcadd((string)$balance['initial_debit'], $newDebitOcc, 2), bcadd((string)$balance['initial_credit'], $newCreditOcc, 2), 2);
            $finalDebit  = bccomp($net, '0.00', 2) >= 0 ? $net : '0.00';
            $finalCredit = bccomp($net, '0.00', 2) < 0 ? bcsub('0.00', $net, 2) : '0.00';
        } else {
            $net = bcsub(bcadd((string)$balance['initial_credit'], $newCreditOcc, 2), bcadd((string)$balance['initial_debit'], $newDebitOcc, 2), 2);
            $finalCredit = bccomp($net, '0.00', 2) >= 0 ? $net : '0.00';
            $finalDebit  = bccomp($net, '0.00', 2) < 0 ? bcsub('0.00', $net, 2) : '0.00';
        }

        $this->balanceRepo->updateBalance($balance['id'], [
            'debit_occurrence'  => $newDebitOcc,
            'credit_occurrence' => $newCreditOcc,
            'final_debit'       => $finalDebit,
            'final_credit'      => $finalCredit,
        ]);
    }
}
