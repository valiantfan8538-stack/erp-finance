<?php

namespace app\domain\system\repository;

interface AccountingPeriodRepositoryInterface
{
    public function findByBookAndPeriod(int $bookId, int $year, int $period): ?array;

    public function findByBook(int $bookId): array;

    public function findCurrentPeriod(int $bookId): ?array;

    public function isPeriodClosed(int $bookId, int $year, int $period): bool;

    public function closePeriod(int $bookId, int $year, int $period, int $closedBy): void;

    public function openPeriod(int $bookId, int $year, int $period): void;
}
