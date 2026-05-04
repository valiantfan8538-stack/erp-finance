<?php

namespace app\infrastructure\asset;

use app\domain\asset\repository\AssetDepreciationRepositoryInterface;
use think\facade\Db;

class ThinkOrmAssetDepreciationRepository implements AssetDepreciationRepositoryInterface
{
    public function findByCardAndPeriod(int $bookId, int $cardId, int $year, int $period): ?array
    {
        $result = Db::table('asset_depreciation')
            ->where('book_id', $bookId)
            ->where('card_id', $cardId)
            ->where('year', $year)
            ->where('period', $period)
            ->find();
        return $result ?: null;
    }

    public function findByBookAndPeriod(int $bookId, int $year, int $period): array
    {
        return Db::table('asset_depreciation')
            ->where('book_id', $bookId)
            ->where('year', $year)
            ->where('period', $period)
            ->select()->toArray();
    }

    public function create(array $data): int
    {
        return Db::table('asset_depreciation')->insertGetId($data);
    }

    public function hasBeenDepreciated(int $cardId, int $year, int $period): bool
    {
        return Db::table('asset_depreciation')
            ->where('card_id', $cardId)
            ->where('year', $year)
            ->where('period', $period)
            ->count() > 0;
    }
}
