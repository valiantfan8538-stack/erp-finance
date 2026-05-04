<?php

namespace app\application\system;

use app\domain\system\repository\AccountingPeriodRepositoryInterface;

class PeriodAppService
{
    private AccountingPeriodRepositoryInterface $periodRepo;

    public function __construct(AccountingPeriodRepositoryInterface $periodRepo)
    {
        $this->periodRepo = $periodRepo;
    }

    public function listPeriods(int $bookId): array
    {
        return $this->periodRepo->findByBook($bookId);
    }

    public function closePeriod(int $bookId, int $year, int $period, int $userId): array
    {
        if ($this->periodRepo->isPeriodClosed($bookId, $year, $period)) {
            return ['code' => 400, 'msg' => '该期间已结账'];
        }
        // Check that all previous periods in this year are closed
        for ($p = 1; $p < $period; $p++) {
            if (!$this->periodRepo->isPeriodClosed($bookId, $year, $p)) {
                return ['code' => 400, 'msg' => "请先结账第{$p}期"];
            }
        }
        $this->periodRepo->closePeriod($bookId, $year, $period, $userId);
        return ['code' => 200, 'msg' => '结账成功'];
    }

    public function openPeriod(int $bookId, int $year, int $period): array
    {
        if (!$this->periodRepo->isPeriodClosed($bookId, $year, $period)) {
            return ['code' => 400, 'msg' => '该期间未结账'];
        }
        // Cannot open if next period is already closed
        $nextPeriod = ($period === 12) ? ['year' => $year + 1, 'period' => 1] : ['year' => $year, 'period' => $period + 1];
        if ($this->periodRepo->isPeriodClosed($bookId, $nextPeriod['year'], $nextPeriod['period'])) {
            return ['code' => 400, 'msg' => '请先反结账后续期间'];
        }
        $this->periodRepo->openPeriod($bookId, $year, $period);
        return ['code' => 200, 'msg' => '反结账成功'];
    }
}
