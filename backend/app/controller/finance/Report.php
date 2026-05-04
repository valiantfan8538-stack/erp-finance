<?php
namespace app\controller\finance;

use app\BaseController;
use app\domain\finance\service\ReportService;
use think\App;

class Report extends BaseController
{
    private ReportService $reportService;

    public function __construct(App $app, ReportService $reportService)
    {
        parent::__construct($app);
        $this->reportService = $reportService;
    }

    public function balanceSheet()
    {
        $bookId = $this->request->param('book_id');
        $year   = $this->request->param('year');
        $period = $this->request->param('period');
        if (!$bookId || !$year || !$period) {
            return json(['code' => 400, 'msg' => 'book_id,year,period必填'])->code(400);
        }
        $data = $this->reportService->balanceSheet((int)$bookId, (int)$year, (int)$period);
        return json(['code' => 200, 'data' => $data]);
    }

    public function incomeStatement()
    {
        $bookId = $this->request->param('book_id');
        $year   = $this->request->param('year');
        $period = $this->request->param('period');
        if (!$bookId || !$year || !$period) {
            return json(['code' => 400, 'msg' => 'book_id,year,period必填'])->code(400);
        }
        $data = $this->reportService->incomeStatement((int)$bookId, (int)$year, (int)$period);
        return json(['code' => 200, 'data' => $data]);
    }

    public function cashFlow()
    {
        $bookId = $this->request->param('book_id');
        $year   = $this->request->param('year');
        $period = $this->request->param('period');
        if (!$bookId || !$year || !$period) {
            return json(['code' => 400, 'msg' => 'book_id,year,period必填'])->code(400);
        }
        $data = $this->reportService->cashFlow((int)$bookId, (int)$year, (int)$period);
        return json(['code' => 200, 'data' => $data]);
    }
}
