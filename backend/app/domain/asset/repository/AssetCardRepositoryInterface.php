<?php

namespace app\domain\asset\repository;

interface AssetCardRepositoryInterface
{
    public function findByBook(array $filters, int $page, int $perPage): array;

    public function findById(int $id): ?array;

    public function findDepreciableCards(int $bookId, int $year, int $period): array;

    public function create(array $data): int;

    public function update(int $id, array $data): void;

    public function delete(int $id): void;

    public function updateDepreciation(int $id, string $monthlyDepreciation, string $accumulatedDepreciation, string $netValue, int $usedMonths, int $remainingMonths, int $lastYear, int $lastPeriod): void;

    public function lockForDepreciation(int $id): ?array;

    public function updateStatus(int $id, string $status): void;

    public function updateDisposal(int $id, string $disposeDate, string $disposeMethod, string $disposeIncome): void;
}
