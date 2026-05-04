<?php

namespace app\infrastructure\finance;

use app\domain\finance\repository\VoucherRepositoryInterface;
use think\facade\Db;

class ThinkOrmVoucherRepository implements VoucherRepositoryInterface
{
    public function findByBook(array $filters, int $page, int $perPage): array
    {
        $query = Db::table('finance_voucher');

        if (!empty($filters['book_id'])) {
            $query->where('book_id', $filters['book_id']);
        }
        if (!empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }
        if (!empty($filters['period'])) {
            $query->where('period', $filters['period']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int)$filters['status']);
        }

        $paginator = $query
            ->order('date', 'desc')
            ->order('id', 'desc')
            ->paginate($perPage);

        return $paginator->toArray();
    }

    public function findById(int $id): ?array
    {
        $result = Db::table('finance_voucher')->find($id);
        return $result ?: null;
    }

    public function findEntries(int $voucherId): array
    {
        return Db::table('finance_voucher_entry')
            ->where('voucher_id', $voucherId)
            ->order('entry_no')
            ->select()
            ->toArray();
    }

    public function getNextVoucherNo(int $bookId, int $voucherTypeId, int $year, int $period): int
    {
        $maxNo = Db::table('finance_voucher')
            ->where('book_id', $bookId)
            ->where('voucher_type_id', $voucherTypeId)
            ->where('year', $year)
            ->where('period', $period)
            ->max('voucher_no');

        return ($maxNo ?? 0) + 1;
    }

    public function create(array $voucherData, array $entries): int
    {
        Db::startTrans();
        try {
            $voucherId = Db::table('finance_voucher')->insertGetId($voucherData);

            foreach ($entries as $entry) {
                $entry['voucher_id'] = $voucherId;
                Db::table('finance_voucher_entry')->insert($entry);
            }

            Db::commit();
            return $voucherId;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function updateStatus(int $id, int $status, ?int $userId, string $auditField): void
    {
        $allowed = ['audit_by', 'posted_by'];
        if (!in_array($auditField, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid audit field: {$auditField}");
        }

        $updateData = ['status' => $status];

        $timestampField = ($auditField === 'audit_by') ? 'audit_at' : 'posted_at';

        if ($userId !== null) {
            $updateData[$auditField] = $userId;
            $updateData[$timestampField] = date('Y-m-d H:i:s');
        } else {
            $updateData[$auditField] = null;
            $updateData[$timestampField] = null;
        }

        Db::table('finance_voucher')->where('id', $id)->update($updateData);
    }

    public function delete(int $id): void
    {
        Db::table('finance_voucher_entry')->where('voucher_id', $id)->delete();
        Db::table('finance_voucher')->where('id', $id)->delete();
    }

    public function lockForPosting(int $id): ?array
    {
        $voucher = Db::table('finance_voucher')
            ->lock(true)
            ->find($id);

        if (!$voucher || (int)$voucher['status'] !== 1) {
            return null;
        }

        return $voucher;
    }
}
