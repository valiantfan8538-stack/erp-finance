<?php
namespace app\controller\finance;

use app\BaseController;
use app\application\finance\VoucherAppService;
use think\App;

class Voucher extends BaseController
{
    private VoucherAppService $voucherService;

    public function __construct(App $app, VoucherAppService $voucherService)
    {
        parent::__construct($app);
        $this->voucherService = $voucherService;
    }

    public function index()
    {
        $filters = [
            'book_id' => $this->request->param('book_id'),
            'year'    => $this->request->param('year'),
            'period'  => $this->request->param('period'),
            'status'  => $this->request->param('status'),
        ];

        $result = $this->voucherService->listVouchers(
            $filters,
            (int)$this->request->param('page', 1),
            (int)$this->request->param('per_page', 20)
        );

        return json(['code' => 200, 'data' => $result]);
    }

    public function read($id)
    {
        $voucher = $this->voucherService->getVoucher((int)$id);
        if (!$voucher) {
            return json(['code' => 404, 'msg' => '凭证不存在'])->code(404);
        }
        return json(['code' => 200, 'data' => $voucher]);
    }

    public function save()
    {
        $data = $this->request->post();
        $result = $this->voucherService->createVoucher($data, $this->request->userId);
        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }

    public function audit($id)
    {
        $result = $this->voucherService->audit((int)$id, $this->request->userId);
        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }

    public function unaudit($id)
    {
        $result = $this->voucherService->unaudit((int)$id);
        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }

    public function post($id)
    {
        $result = $this->voucherService->post((int)$id, $this->request->userId);
        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }

    public function unpost($id)
    {
        $result = $this->voucherService->unpost((int)$id);
        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }

    public function delete($id)
    {
        $result = $this->voucherService->delete((int)$id);
        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }
}
