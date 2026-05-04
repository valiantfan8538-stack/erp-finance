<?php

namespace app\domain\asset\service;

use app\domain\asset\repository\AssetCardRepositoryInterface;
use app\domain\asset\repository\AssetDepreciationRepositoryInterface;
use think\facade\Db;
use think\facade\Log;

class DepreciationEngine
{
    private AssetCardRepositoryInterface $cardRepo;
    private AssetDepreciationRepositoryInterface $depreciationRepo;

    public function __construct(
        AssetCardRepositoryInterface $cardRepo,
        AssetDepreciationRepositoryInterface $depreciationRepo
    ) {
        $this->cardRepo         = $cardRepo;
        $this->depreciationRepo = $depreciationRepo;
    }

    public function calculateMonthlyDepreciation(array $card): string
    {
        $method   = $card['depreciation_method'] ?? 'straight_line';
        $original = (string)$card['original_value'];
        $residual = (string)$card['residual_value'];
        $months   = (int)$card['useful_months'];

        return match($method) {
            'straight_line'  => $this->straightLine($original, $residual, $months),
            'double_decline' => $this->doubleDecline($original, (string)$card['accumulated_depreciation'], $residual, $months, (int)$card['used_months']),
            'sum_years'      => $this->sumYears($original, $residual, $months, (int)$card['used_months']),
            default          => $this->straightLine($original, $residual, $months),
        };
    }

    private function straightLine(string $original, string $residual, int $months): string
    {
        if ($months <= 0) return '0.00';
        return bcdiv(bcsub($original, $residual, 2), (string)$months, 2);
    }

    private function doubleDecline(string $original, string $accumulated, string $residual, int $months, int $usedMonths): string
    {
        $netValue = bcsub($original, $accumulated, 2);
        if ($months <= 0) return '0.00';
        $rate = bcdiv('2', (string)$months, 6);
        $depreciation = bcmul($netValue, $rate, 2);

        $remaining = $months - $usedMonths;
        if ($remaining <= 2) {
            return bcdiv(bcsub($netValue, $residual, 2), (string)max(1, $remaining), 2);
        }

        $afterDepreciation = bcsub($netValue, $depreciation, 2);
        if (bccomp($afterDepreciation, $residual, 2) < 0) {
            return bcsub($netValue, $residual, 2);
        }
        return $depreciation;
    }

    private function sumYears(string $original, string $residual, int $months, int $usedMonths): string
    {
        $depreciableBase = bcsub($original, $residual, 2);
        $yearsTotal = (int)ceil($months / 12);
        $sumYears = ($yearsTotal * ($yearsTotal + 1)) / 2;
        $currentYear = (int)floor($usedMonths / 12) + 1;
        $remainingYears = $yearsTotal - $currentYear + 1;
        $yearlyDepreciation = bcmul($depreciableBase, bcdiv((string)$remainingYears, (string)$sumYears, 6), 2);
        return bcdiv($yearlyDepreciation, '12', 2);
    }

    public function depreciateCard(int $bookId, array $card, int $year, int $period): array
    {
        $lockedCard = $this->cardRepo->lockForDepreciation($card['id']);
        if (!$lockedCard) {
            return ['skipped' => true, 'msg' => "资产 {$card['asset_name']} 不可折旧"];
        }

        if ($this->depreciationRepo->hasBeenDepreciated($card['id'], $year, $period)) {
            return ['skipped' => true, 'msg' => "资产 {$card['asset_name']} 本期已折旧"];
        }

        $monthlyDepreciation = $card['monthly_depreciation'];
        if (bccomp($monthlyDepreciation, '0.00', 2) <= 0) {
            $monthlyDepreciation = $this->calculateMonthlyDepreciation($card);
        }

        $newAccumulated = bcadd((string)$card['accumulated_depreciation'], $monthlyDepreciation, 2);
        $newNetValue    = bcsub((string)$card['original_value'], $newAccumulated, 2);
        $newUsedMonths  = (int)$card['used_months'] + 1;
        $newRemaining   = max(0, (int)$card['useful_months'] - $newUsedMonths);

        if ($newRemaining <= 0) {
            $finalDepreciation = bcadd($monthlyDepreciation, bcsub((string)$card['net_value'], $newNetValue, 2), 2);
            $this->cardRepo->updateDepreciation($card['id'], $finalDepreciation, $newAccumulated, (string)$card['residual_value'], $newUsedMonths, 0, $year, $period);
        } else {
            $this->cardRepo->updateDepreciation($card['id'], $monthlyDepreciation, $newAccumulated, $newNetValue, $newUsedMonths, $newRemaining, $year, $period);
        }

        $this->depreciationRepo->create([
            'book_id'                  => $bookId,
            'card_id'                  => $card['id'],
            'year'                     => $year,
            'period'                   => $period,
            'original_value'           => $card['original_value'],
            'monthly_depreciation'     => $monthlyDepreciation,
            'accumulated_depreciation' => $newAccumulated,
            'net_value'                => $newNetValue,
            'remaining_months'         => $newRemaining,
            'created_at'               => date('Y-m-d H:i:s'),
        ]);

        return ['skipped' => false, 'amount' => $monthlyDepreciation];
    }

    public function batchDepreciate(int $bookId, int $year, int $period): array
    {
        $cards = $this->cardRepo->findDepreciableCards($bookId, $year, $period);
        $results = [];
        $totalAmount = '0.00';

        Db::startTrans();
        try {
            foreach ($cards as $card) {
                $result = $this->depreciateCard($bookId, $card, $year, $period);
                $results[] = $result;
                if (!$result['skipped']) {
                    $totalAmount = bcadd($totalAmount, $result['amount'], 2);
                }
            }
            Db::commit();
            return ['code' => 200, 'msg' => "计提完成，总额: {$totalAmount}", 'data' => [
                'total_cards'  => count($cards),
                'skipped'      => count(array_filter($results, fn($r) => $r['skipped'])),
                'total_amount' => $totalAmount,
            ]];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('DepreciationEngine.batchDepreciate failed: ' . $e->getMessage());
            return ['code' => 500, 'msg' => '计提失败，请稍后重试'];
        }
    }
}
