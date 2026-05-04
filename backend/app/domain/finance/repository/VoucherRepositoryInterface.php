<?php

namespace app\domain\finance\repository;

interface VoucherRepositoryInterface
{
    public function findByBook(array $filters, int $page, int $perPage): array;

    public function findById(int $id): ?array;

    public function findEntries(int $voucherId): array;

    public function getNextVoucherNo(int $bookId, int $voucherTypeId, int $year, int $period): int;

    public function create(array $voucherData, array $entries): int;

    public function updateStatus(int $id, int $status, ?int $userId, string $auditField): void;

    public function delete(int $id): void;

    public function lockForPosting(int $id): ?array;
}
