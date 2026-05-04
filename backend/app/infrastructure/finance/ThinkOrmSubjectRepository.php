<?php

namespace app\infrastructure\finance;

use app\domain\finance\repository\SubjectRepositoryInterface;
use think\facade\Db;

class ThinkOrmSubjectRepository implements SubjectRepositoryInterface
{
    public function findByBook(int $bookId): array
    {
        return Db::table('finance_subject')
            ->where('book_id', $bookId)
            ->order('code')
            ->select()
            ->toArray();
    }

    public function findById(int $id): ?array
    {
        $result = Db::table('finance_subject')->find($id);
        return $result ?: null;
    }

    public function findByCode(int $bookId, string $code): ?array
    {
        $result = Db::table('finance_subject')
            ->where('book_id', $bookId)
            ->where('code', $code)
            ->find();
        return $result ?: null;
    }

    public function findChildren(int $parentId): array
    {
        return Db::table('finance_subject')
            ->where('parent_id', $parentId)
            ->order('code')
            ->select()
            ->toArray();
    }

    public function isLeaf(int $id): bool
    {
        $subject = Db::table('finance_subject')->find($id);
        return $subject && (int)$subject['is_leaf'] === 1;
    }

    public function hasChildren(int $id): bool
    {
        return Db::table('finance_subject')
            ->where('parent_id', $id)
            ->count() > 0;
    }

    public function hasEntries(int $id): bool
    {
        return Db::table('finance_voucher_entry')
            ->where('subject_id', $id)
            ->count() > 0;
    }

    public function create(array $data): int
    {
        return Db::table('finance_subject')->insertGetId($data);
    }

    public function update(int $id, array $data): void
    {
        Db::table('finance_subject')->where('id', $id)->update($data);
    }

    public function delete(int $id): void
    {
        Db::table('finance_subject')->where('id', $id)->delete();
    }

    public function setLeafStatus(int $id, bool $isLeaf): void
    {
        Db::table('finance_subject')->where('id', $id)->update([
            'is_leaf' => $isLeaf ? 1 : 0,
        ]);
    }
}
