<?php

namespace app\domain\arap\repository;

interface ReceivableRepositoryInterface
{
    public function findByBook(array $filters, int $page, int $perPage): array;

    public function findById(int $id): ?array;

    public function findByPartner(int $partnerId): array;

    public function findUnverifiedByPartner(int $partnerId): array;

    public function create(array $data): int;

    public function update(int $id, array $data): void;

    public function delete(int $id): void;

    public function updateReceivedAmount(int $id, string $receivedAmount, string $unreceivedAmount): void;

    public function updateStatus(int $id, int $status): void;

    public function getUnverifiedTotal(int $partnerId): string;

    public function lockUnverifiedByPartner(int $partnerId): array;
}
