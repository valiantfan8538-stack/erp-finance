<?php

namespace app\domain\finance\repository;

interface SubjectBalanceRepositoryInterface
{
    public function findBySubjectAndPeriod(int $bookId, int $subjectId, int $year, int $period): ?array;

    public function findOrCreate(int $bookId, int $subjectId, int $year, int $period): array;

    public function lockForUpdate(int $bookId, int $subjectId, int $year, int $period): ?array;

    public function updateOccurrence(int $id, string $debitAmount, string $creditAmount): void;

    public function updateBalance(int $id, array $balanceData): void;

    public function findByBookAndPeriod(int $bookId, int $year, int $period): array;
}
