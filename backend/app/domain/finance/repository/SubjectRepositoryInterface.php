<?php

namespace app\domain\finance\repository;

interface SubjectRepositoryInterface
{
    public function findByBook(int $bookId): array;

    public function findById(int $id): ?array;

    public function findByCode(int $bookId, string $code): ?array;

    public function findChildren(int $parentId): array;

    public function isLeaf(int $id): bool;

    public function hasChildren(int $id): bool;

    public function hasEntries(int $id): bool;

    public function create(array $data): int;

    public function update(int $id, array $data): void;

    public function delete(int $id): void;

    public function setLeafStatus(int $id, bool $isLeaf): void;
}
