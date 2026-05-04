<?php

namespace app\domain\arap\repository;

interface VerifyRecordRepositoryInterface
{
    public function findBySource(string $sourceType, int $sourceId): array;

    public function findByPartner(int $partnerId, string $sourceType): array;

    public function create(array $data): int;

    public function getVerifiedTotal(string $sourceType, int $sourceId): string;
}
