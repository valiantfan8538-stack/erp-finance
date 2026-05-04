<?php
namespace app\controller\system;

use app\BaseController;
use app\application\system\PeriodAppService;
use think\App;
use think\facade\Db;

class Period extends BaseController
{
    private PeriodAppService $periodService;

    public function __construct(App $app, PeriodAppService $periodService)
    {
        parent::__construct($app);
        $this->periodService = $periodService;
    }

    public function index()
    {
        $bookId = $this->request->param('book_id');
        if (!$bookId) {
            return json(['code' => 400, 'msg' => 'book_id不能为空'])->code(400);
        }
        $list = $this->periodService->listPeriods((int)$bookId);
        return json(['code' => 200, 'data' => $list]);
    }

    public function read($id)
    {
        $period = Db::table('sys_accounting_period')->find($id);
        return json(['code' => 200, 'data' => $period]);
    }

    public function close($id)
    {
        $period = Db::table('sys_accounting_period')->find($id);
        if (!$period) {
            return json(['code' => 404, 'msg' => '期间不存在'])->code(404);
        }

        $result = $this->periodService->closePeriod(
            $period['book_id'],
            (int)$period['year'],
            (int)$period['period'],
            $this->request->userId
        );

        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }

    public function open($id)
    {
        $period = Db::table('sys_accounting_period')->find($id);
        if (!$period) {
            return json(['code' => 404, 'msg' => '期间不存在'])->code(404);
        }

        $result = $this->periodService->openPeriod(
            $period['book_id'],
            (int)$period['year'],
            (int)$period['period']
        );

        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }
}
