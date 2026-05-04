<?php

namespace app\infrastructure\arap;

use app\domain\arap\repository\ReceivableRepositoryInterface;
use think\facade\Db;

class ThinkOrmReceivableRepository implements ReceivableRepositoryInterface
{
    public function findByBook(array $filters, int $page, int $perPage): array
    {
        $query = Db::table('finance_ar_receivable')
            ->where('book_id', $filters['book_id'] ?? 0)
            ->when(!empty($filters['partner_id']), fn($q) => $q->where('partner_id', $filters['partner_id']))
            ->when(isset($filters['status']) && $filters['status'] !== '', fn($q) => $q->where('status', (int)$filters['status']))
            ->order('bill_date', 'desc');

        return $query->paginate($perPage)->toArray();
    }

    public function findById(int $id): ?array
    {
        $result = Db::table('finance_ar_receivable')->find($id);
        return $result ?: null;
    }

    public function findByPartner(int $partnerId): array
    {
        return Db::table('finance_ar_receivable')
            ->where('partner_id', $partnerId)
            ->order('bill_date', 'desc')
            ->select()->toArray();
    }

    public function findUnverifiedByPartner(int $partnerId): array
    {
        return Db::table('finance_ar_receivable')
            ->where('partner_id', $partnerId)
            ->where('status', '<', 2)
            ->order('bill_date', 'asc')
            ->select()->toArray();
    }

    public function create(array $data): int
    {
        return Db::table('finance_ar_receivable')->insertGetId($data);
    }

    public function update(int $id, array $data): void
    {
        Db::table('finance_ar_receivable')->where('id', $id)->update($data);
    }

    public function delete(int $id): void
    {
        Db::table('finance_ar_receivable')->where('id', $id)->delete();
    }

    public function updateReceivedAmount(int $id, string $receivedAmount, string $unreceivedAmount): void
    {
        Db::table('finance_ar_receivable')->where('id', $id)->update([
            'received_amount'   => $receivedAmount,
            'unreceived_amount' => $unreceivedAmount,
        ]);
    }

    public function updateStatus(int $id, int $status): void
    {
        Db::table('finance_ar_receivable')->where('id', $id)->update(['status' => $status]);
    }

    public function getUnverifiedTotal(int $partnerId): string
    {
        $result = Db::table('finance_ar_receivable')
            ->where('partner_id', $partnerId)
            ->where('status', '<', 2)
            ->sum('unreceived_amount');
        return (string)($result ?? '0.00');
    }

    public function lockUnverifiedByPartner(int $partnerId): array
    {
        return Db::table('finance_ar_receivable')
            ->where('partner_id', $partnerId)
            ->where('status', '<', 2)
            ->order('bill_date', 'asc')
            ->lock(true)
            ->select()->toArray();
    }
}
