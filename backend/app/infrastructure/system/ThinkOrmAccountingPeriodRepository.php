<?php

namespace app\infrastructure\system;

use app\domain\system\repository\AccountingPeriodRepositoryInterface;
use think\facade\Db;

class ThinkOrmAccountingPeriodRepository implements AccountingPeriodRepositoryInterface
{
    public function findByBookAndPeriod(int $bookId, int $year, int $period): ?array
    {
        $result = Db::table('sys_accounting_period')
            ->where('book_id', $bookId)
            ->where('year', $year)
            ->where('period', $period)
            ->find();
        return $result ?: null;
    }

    public function findByBook(int $bookId): array
    {
        return Db::table('sys_accounting_period')
            ->where('book_id', $bookId)
            ->order('year', 'desc')
            ->order('period', 'desc')
            ->select()
            ->toArray();
    }

    public function findCurrentPeriod(int $bookId): ?array
    {
        $result = Db::table('sys_accounting_period')
            ->where('book_id', $bookId)
            ->where('status', 0)
            ->order('year', 'desc')
            ->order('period', 'desc')
            ->find();
        return $result ?: null;
    }

    public function isPeriodClosed(int $bookId, int $year, int $period): bool
    {
        $result = Db::table('sys_accounting_period')
            ->where('book_id', $bookId)
            ->where('year', $year)
            ->where('period', $period)
            ->where('status', '>=', 1)
            ->find();
        return $result !== null;
    }

    public function closePeriod(int $bookId, int $year, int $period, int $closedBy): void
    {
        Db::table('sys_accounting_period')
            ->where('book_id', $bookId)
            ->where('year', $year)
            ->where('period', $period)
            ->update([
                'status'    => 1,
                'closed_at' => date('Y-m-d H:i:s'),
                'closed_by' => $closedBy,
            ]);
    }

    public function openPeriod(int $bookId, int $year, int $period): void
    {
        Db::table('sys_accounting_period')
            ->where('book_id', $bookId)
            ->where('year', $year)
            ->where('period', $period)
            ->update([
                'status'    => 0,
                'closed_at' => null,
                'closed_by' => null,
            ]);
    }
}
