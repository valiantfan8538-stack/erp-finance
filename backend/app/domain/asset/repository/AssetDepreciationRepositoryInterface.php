<?php

namespace app\domain\asset\repository;

interface AssetDepreciationRepositoryInterface
{
    public function findByCardAndPeriod(int $bookId, int $cardId, int $year, int $period): ?array;

    public function findByBookAndPeriod(int $bookId, int $year, int $period): array;

    public function create(array $data): int;

    public function hasBeenDepreciated(int $cardId, int $year, int $period): bool;
}
