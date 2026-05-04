<?php

namespace app\infrastructure\asset;

use app\domain\asset\repository\AssetCardRepositoryInterface;
use think\facade\Db;

class ThinkOrmAssetCardRepository implements AssetCardRepositoryInterface
{
    public function findByBook(array $filters, int $page, int $perPage): array
    {
        $query = Db::table('asset_card')
            ->where('book_id', $filters['book_id'] ?? 0)
            ->when(!empty($filters['category_id']), fn($q) => $q->where('category_id', $filters['category_id']))
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->order('use_date', 'desc');

        return $query->paginate($perPage)->toArray();
    }

    public function findById(int $id): ?array
    {
        $result = Db::table('asset_card')->find($id);
        return $result ?: null;
    }

    public function findDepreciableCards(int $bookId, int $year, int $period): array
    {
        return Db::table('asset_card')
            ->where('book_id', $bookId)
            ->where('status', 'in_use')
            ->where('remaining_months', '>', 0)
            ->where(function ($query) use ($year, $period) {
                $query->whereNull('last_depreciation_year')
                    ->whereOr('last_depreciation_year', '<', $year)
                    ->whereOr(function ($q) use ($year, $period) {
                        $q->where('last_depreciation_year', $year)
                            ->where('last_depreciation_period', '<', $period);
                    });
            })
            ->select()->toArray();
    }

    public function lockForDepreciation(int $id): ?array
    {
        $result = Db::table('asset_card')
            ->where('status', 'in_use')
            ->where('remaining_months', '>', 0)
            ->lock(true)
            ->find($id);
        return $result ?: null;
    }

    public function create(array $data): int
    {
        return Db::table('asset_card')->insertGetId($data);
    }

    public function update(int $id, array $data): void
    {
        Db::table('asset_card')->where('id', $id)->update($data);
    }

    public function delete(int $id): void
    {
        Db::table('asset_card')->where('id', $id)->delete();
    }

    public function updateDepreciation(int $id, string $monthlyDepreciation, string $accumulatedDepreciation, string $netValue, int $usedMonths, int $remainingMonths, int $lastYear, int $lastPeriod): void
    {
        Db::table('asset_card')->where('id', $id)->update([
            'monthly_depreciation'      => $monthlyDepreciation,
            'accumulated_depreciation'  => $accumulatedDepreciation,
            'net_value'                 => $netValue,
            'used_months'               => $usedMonths,
            'remaining_months'          => $remainingMonths,
            'last_depreciation_year'    => $lastYear,
            'last_depreciation_period'  => $lastPeriod,
        ]);
    }

    public function updateStatus(int $id, string $status): void
    {
        Db::table('asset_card')->where('id', $id)->update(['status' => $status]);
    }

    public function updateDisposal(int $id, string $disposeDate, string $disposeMethod, string $disposeIncome): void
    {
        Db::table('asset_card')->where('id', $id)->update([
            'status'         => 'dispose',
            'dispose_date'   => $disposeDate,
            'dispose_method' => $disposeMethod,
            'dispose_income' => $disposeIncome,
        ]);
    }
}
