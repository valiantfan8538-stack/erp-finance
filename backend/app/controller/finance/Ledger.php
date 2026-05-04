<?php
namespace app\controller\finance;

use app\BaseController;
use think\facade\Db;

class Ledger extends BaseController
{
    public function subjectBalance()
    {
        $bookId = $this->request->param('book_id');
        $year   = $this->request->param('year');
        $period = $this->request->param('period');
        if (!$bookId || !$year || !$period) {
            return json(['code' => 400, 'msg' => 'book_id,year,period必填'])->code(400);
        }

        $list = Db::table('finance_subject_balance')
            ->where('book_id', $bookId)
            ->where('year', $year)
            ->where('period', $period)
            ->select();

        return json(['code' => 200, 'data' => $list]);
    }

    public function general()
    {
        $bookId  = $this->request->param('book_id');
        $subjectId = $this->request->param('subject_id');
        $year    = $this->request->param('year');
        $period  = $this->request->param('period');

        $query = Db::table('finance_voucher_entry')
            ->alias('e')
            ->join('finance_voucher v', 'e.voucher_id = v.id')
            ->where('v.book_id', $bookId)
            ->where('e.subject_id', $subjectId)
            ->where('v.status', 2)
            ->when($year, fn($q) => $q->where('v.year', $year))
            ->when($period, fn($q) => $q->where('v.period', $period))
            ->field('v.date, v.voucher_no, e.summary, e.debit_amount, e.credit_amount')
            ->order('v.date')->order('v.id');

        $list = $query->paginate(50);
        return json(['code' => 200, 'data' => $list]);
    }
}
