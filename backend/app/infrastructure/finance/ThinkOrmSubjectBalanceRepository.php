<?php

namespace app\infrastructure\finance;

use app\domain\finance\repository\SubjectBalanceRepositoryInterface;
use think\facade\Db;

class ThinkOrmSubjectBalanceRepository implements SubjectBalanceRepositoryInterface
{
    public function findBySubjectAndPeriod(int $bookId, int $subjectId, int $year, int $period): ?array
    {
        $result = Db::table('finance_subject_balance')
            ->where('book_id', $bookId)
            ->where('subject_id', $subjectId)
            ->where('year', $year)
            ->where('period', $period)
            ->find();
        return $result ?: null;
    }

    public function findOrCreate(int $bookId, int $subjectId, int $year, int $period): array
    {
        $result = Db::table('finance_subject_balance')
            ->where('book_id', $bookId)
            ->where('subject_id', $subjectId)
            ->where('year', $year)
            ->where('period', $period)
            ->find();

        if ($result) {
            return $result;
        }

        $data = [
            'book_id'          => $bookId,
            'subject_id'       => $subjectId,
            'year'             => $year,
            'period'           => $period,
            'initial_debit'    => '0.00',
            'initial_credit'   => '0.00',
            'debit_occurrence' => '0.00',
            'credit_occurrence' => '0.00',
            'final_debit'      => '0.00',
            'final_credit'     => '0.00',
            'is_initial'       => 0,
        ];

        $id = Db::table('finance_subject_balance')->insertGetId($data);
        $data['id'] = $id;
        return $data;
    }

    public function lockForUpdate(int $bookId, int $subjectId, int $year, int $period): ?array
    {
        $result = Db::table('finance_subject_balance')
            ->where('book_id', $bookId)
            ->where('subject_id', $subjectId)
            ->where('year', $year)
            ->where('period', $period)
            ->lock(true)
            ->find();
        return $result ?: null;
    }

    public function updateOccurrence(int $id, string $debitAmount, string $creditAmount): void
    {
        Db::table('finance_subject_balance')
            ->where('id', $id)
            ->update([
                'debit_occurrence'  => $debitAmount,
                'credit_occurrence' => $creditAmount,
            ]);
    }

    public function updateBalance(int $id, array $balanceData): void
    {
        Db::table('finance_subject_balance')
            ->where('id', $id)
            ->update($balanceData);
    }

    public function findByBookAndPeriod(int $bookId, int $year, int $period): array
    {
        return Db::table('finance_subject_balance')
            ->where('book_id', $bookId)
            ->where('year', $year)
            ->where('period', $period)
            ->order('subject_id')
            ->select()
            ->toArray();
    }
}
