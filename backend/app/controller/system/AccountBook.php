<?php
namespace app\controller\system;

use app\BaseController;
use think\facade\Db;

class AccountBook extends BaseController
{
    public function index()
    {
        $list = Db::table('sys_account_book')
            ->whereNull('deleted_at')
            ->order('id', 'desc')
            ->paginate($this->request->param('per_page', 20));
        return json(['code' => 200, 'data' => $list]);
    }

    public function read($id)
    {
        $book = Db::table('sys_account_book')->whereNull('deleted_at')->find($id);
        if (!$book) return json(['code' => 404, 'msg' => '账套不存在'])->code(404);
        return json(['code' => 200, 'data' => $book]);
    }

    public function save()
    {
        $data = $this->request->post();
        if (empty($data['name']) || empty($data['company_name']) || empty($data['start_year']) || empty($data['start_period'])) {
            return json(['code' => 400, 'msg' => '参数不完整:name,company_name,start_year,start_period必填'])->code(400);
        }

        $bookCode = $data['book_code'] ?? ('BOOK' . date('YmdHis'));
        $exists = Db::table('sys_account_book')->where('book_code', $bookCode)->whereNull('deleted_at')->find();
        if ($exists) return json(['code' => 400, 'msg' => '账套编码已存在'])->code(400);

        Db::startTrans();
        try {
            $bookId = Db::table('sys_account_book')->insertGetId([
                'name'            => $data['name'],
                'company_name'    => $data['company_name'],
                'short_name'      => $data['short_name'] ?? null,
                'credit_standard' => $data['credit_standard'] ?? 'enterprise',
                'start_year'      => (int)$data['start_year'],
                'start_period'    => (int)$data['start_period'],
                'currency'        => $data['currency'] ?? 'CNY',
                'book_code'       => $bookCode,
                'status'          => 1,
                'remark'          => $data['remark'] ?? null,
                'created_by'      => $this->request->userId ?? null,
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

            // Auto-generate 12 accounting periods for the start year
            $this->generatePeriods($bookId, (int)$data['start_year'], (int)$data['start_period']);

            Db::commit();
            return json(['code' => 200, 'msg' => '创建成功', 'data' => ['id' => $bookId]]);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 500, 'msg' => $e->getMessage()])->code(500);
        }
    }

    public function update($id)
    {
        $book = Db::table('sys_account_book')->whereNull('deleted_at')->find($id);
        if (!$book) return json(['code' => 404, 'msg' => '账套不存在'])->code(404);

        $data = $this->request->post();
        Db::table('sys_account_book')->where('id', $id)->update([
            'name'            => $data['name'] ?? $book['name'],
            'company_name'    => $data['company_name'] ?? $book['company_name'],
            'short_name'      => $data['short_name'] ?? $book['short_name'],
            'credit_standard' => $data['credit_standard'] ?? $book['credit_standard'],
            'currency'        => $data['currency'] ?? $book['currency'],
            'status'          => $data['status'] ?? $book['status'],
            'remark'          => $data['remark'] ?? $book['remark'],
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
        return json(['code' => 200, 'msg' => '更新成功']);
    }

    public function delete($id)
    {
        $book = Db::table('sys_account_book')->whereNull('deleted_at')->find($id);
        if (!$book) return json(['code' => 404, 'msg' => '账套不存在'])->code(404);

        // Check if book has any data
        $hasVouchers = Db::table('finance_voucher')->where('book_id', $id)->count();
        if ($hasVouchers > 0) return json(['code' => 400, 'msg' => '账套已有凭证数据，不可删除'])->code(400);

        Db::table('sys_account_book')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        Db::table('sys_accounting_period')->where('book_id', $id)->delete();
        return json(['code' => 200, 'msg' => '删除成功']);
    }

    private function generatePeriods(int $bookId, int $year, int $startPeriod): void
    {
        for ($p = 1; $p <= 12; $p++) {
            $startDate = date('Y-m-d', strtotime("$year-" . str_pad((string)$p, 2, '0', STR_PAD_LEFT) . "-01"));
            $endDate   = date('Y-m-t', strtotime($startDate));
            Db::table('sys_accounting_period')->insert([
                'book_id'    => $bookId,
                'year'       => $year,
                'period'     => $p,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'status'     => 0,
                'is_current' => ($p === $startPeriod) ? 1 : 0,
            ]);
        }
    }
}
