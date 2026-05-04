<?php

namespace app\infrastructure\arap;

use app\domain\arap\repository\VerifyRecordRepositoryInterface;
use think\facade\Db;

class ThinkOrmVerifyRecordRepository implements VerifyRecordRepositoryInterface
{
    public function findBySource(string $sourceType, int $sourceId): array
    {
        return Db::table('finance_verify_record')
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->order('verify_date', 'desc')
            ->select()->toArray();
    }

    public function findByPartner(int $partnerId, string $sourceType): array
    {
        return Db::table('finance_verify_record')
            ->where('partner_id', $partnerId)
            ->where('source_type', $sourceType)
            ->order('verify_date', 'desc')
            ->select()->toArray();
    }

    public function create(array $data): int
    {
        return Db::table('finance_verify_record')->insertGetId($data);
    }

    public function getVerifiedTotal(string $sourceType, int $sourceId): string
    {
        $result = Db::table('finance_verify_record')
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->sum('amount');
        return (string)($result ?? '0.00');
    }
}
