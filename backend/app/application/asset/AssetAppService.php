<?php

namespace app\application\asset;

use app\domain\asset\repository\AssetCardRepositoryInterface;
use app\domain\asset\repository\AssetDepreciationRepositoryInterface;
use app\domain\asset\service\DepreciationEngine;
use think\facade\Db;

class AssetAppService
{
    private AssetCardRepositoryInterface $cardRepo;
    private AssetDepreciationRepositoryInterface $depreciationRepo;
    private DepreciationEngine $engine;

    public function __construct(
        AssetCardRepositoryInterface $cardRepo,
        AssetDepreciationRepositoryInterface $depreciationRepo,
        DepreciationEngine $engine
    ) {
        $this->cardRepo         = $cardRepo;
        $this->depreciationRepo = $depreciationRepo;
        $this->engine           = $engine;
    }

    public function listCards(array $filters, int $page = 1, int $perPage = 20): array
    {
        return $this->cardRepo->findByBook($filters, $page, $perPage);
    }

    public function getCard(int $id): ?array
    {
        return $this->cardRepo->findById($id);
    }

    public function createCard(array $data, int $userId): array
    {
        if (empty($data['book_id']) || empty($data['asset_name']) || empty($data['category_id']) || empty($data['original_value'])) {
            return ['code' => 400, 'msg' => '参数不完整: book_id, asset_name, category_id, original_value 必填'];
        }

        $originalValue = (string)$data['original_value'];
        $residualRate  = (string)($data['residual_rate'] ?? '5.00');
        $residualValue = bcmul($originalValue, bcdiv($residualRate, '100', 4), 2);
        $usefulMonths  = (int)($data['useful_months'] ?? 60);

        $category = Db::table('asset_category')->find((int)$data['category_id']);
        $method = $category['depreciation_method'] ?? 'straight_line';
        $depreciableBase = bcsub($originalValue, $residualValue, 2);
        $monthlyDepreciation = bcdiv($depreciableBase, (string)$usefulMonths, 2);

        $id = $this->cardRepo->create([
            'book_id'                  => (int)$data['book_id'],
            'card_no'                  => $data['card_no'] ?? ('FA' . date('YmdHis')),
            'asset_name'               => $data['asset_name'],
            'asset_code'               => $data['asset_code'] ?? '',
            'category_id'              => (int)$data['category_id'],
            'specification'            => $data['specification'] ?? '',
            'quantity'                 => $data['quantity'] ?? 1,
            'unit'                     => $data['unit'] ?? '台',
            'original_value'           => $originalValue,
            'accumulated_depreciation' => '0.00',
            'net_value'                => $originalValue,
            'residual_value'           => $residualValue,
            'residual_rate'            => $residualRate,
            'depreciation_method'      => $method,
            'useful_months'            => $usefulMonths,
            'used_months'              => 0,
            'remaining_months'         => $usefulMonths,
            'monthly_depreciation'     => $monthlyDepreciation,
            'purchase_date'            => $data['purchase_date'] ?? date('Y-m-d'),
            'use_date'                 => $data['use_date'] ?? date('Y-m-d'),
            'department_id'            => $data['department_id'] ?? null,
            'responsible_person'       => $data['responsible_person'] ?? '',
            'location'                 => $data['location'] ?? '',
            'supplier_id'              => $data['supplier_id'] ?? null,
            'subject_original'         => $data['subject_original'] ?? null,
            'subject_depreciation'     => $data['subject_depreciation'] ?? null,
            'status'                   => 'in_use',
            'created_by'               => $userId,
            'created_at'               => date('Y-m-d H:i:s'),
            'updated_at'               => date('Y-m-d H:i:s'),
        ]);

        return ['code' => 200, 'msg' => '资产卡片创建成功', 'data' => ['id' => $id]];
    }

    public function deleteCard(int $id): array
    {
        $card = $this->cardRepo->findById($id);
        if (!$card) return ['code' => 404, 'msg' => '资产卡片不存在'];
        if ($card['status'] === 'dispose') return ['code' => 400, 'msg' => '已处置资产不可删除'];

        $this->cardRepo->delete($id);
        return ['code' => 200, 'msg' => '删除成功'];
    }

    public function depreciateCard(int $id, int $year, int $period): array
    {
        $card = $this->cardRepo->findById($id);
        if (!$card) return ['code' => 404, 'msg' => '资产卡片不存在'];
        $result = $this->engine->depreciateCard((int)$card['book_id'], $card, $year, $period);
        if ($result['skipped']) return ['code' => 200, 'msg' => $result['msg']];
        return ['code' => 200, 'msg' => "计提折旧: {$result['amount']}"];
    }

    public function batchDepreciate(int $bookId, int $year, int $period): array
    {
        return $this->engine->batchDepreciate($bookId, $year, $period);
    }

    public function disposeCard(int $id, array $data): array
    {
        $card = $this->cardRepo->findById($id);
        if (!$card) return ['code' => 404, 'msg' => '资产卡片不存在'];
        if ($card['status'] === 'dispose') return ['code' => 400, 'msg' => '资产已处置'];

        $this->cardRepo->updateDisposal($id, date('Y-m-d'), $data['method'] ?? 'scrap', (string)($data['income'] ?? '0.00'));
        return ['code' => 200, 'msg' => '资产处置成功'];
    }

    public function depreciationSummary(int $bookId, int $year, int $period): array
    {
        return $this->depreciationRepo->findByBookAndPeriod($bookId, $year, $period);
    }
}
