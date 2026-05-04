<?php

namespace app\controller\finance;

use app\BaseController;
use app\application\arap\ARAPAppService;
use think\App;

class ARAP extends BaseController
{
    private ARAPAppService $arapService;

    public function __construct(App $app, ARAPAppService $arapService)
    {
        parent::__construct($app);
        $this->arapService = $arapService;
    }

    public function index()
    {
        $type = $this->request->param('type', 'receivable');
        $filters = [
            'book_id'    => $this->request->param('book_id'),
            'partner_id' => $this->request->param('partner_id'),
            'status'     => $this->request->param('status'),
        ];

        if ($type === 'payable') {
            $result = $this->arapService->listPayables($filters, (int)$this->request->param('page', 1), (int)$this->request->param('per_page', 20));
        } else {
            $result = $this->arapService->listReceivables($filters, (int)$this->request->param('page', 1), (int)$this->request->param('per_page', 20));
        }

        return json(['code' => 200, 'data' => $result]);
    }

    public function read($id)
    {
        $type = $this->request->param('type', 'receivable');
        $data = $type === 'payable'
            ? $this->arapService->getPayable((int)$id)
            : $this->arapService->getReceivable((int)$id);

        if (!$data) {
            return json(['code' => 404, 'msg' => '单据不存在'])->code(404);
        }
        return json(['code' => 200, 'data' => $data]);
    }

    public function save()
    {
        $data = $this->request->post();
        $type = $data['type'] ?? 'receivable';

        $result = $type === 'payable'
            ? $this->arapService->createPayable($data, $this->request->userId)
            : $this->arapService->createReceivable($data, $this->request->userId);

        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }

    public function delete($id)
    {
        $type = $this->request->param('type', 'receivable');
        $result = $type === 'payable'
            ? $this->arapService->deletePayable((int)$id)
            : $this->arapService->deleteReceivable((int)$id);

        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }

    public function verifyReceivable($id)
    {
        $amount = $this->request->post('amount', '0');
        $result = $this->arapService->verifyReceivable((int)$id, (string)$amount, $this->request->userId);
        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }

    public function verifyPayable($id)
    {
        $amount = $this->request->post('amount', '0');
        $result = $this->arapService->verifyPayable((int)$id, (string)$amount, $this->request->userId);
        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }

    public function agingAnalysis()
    {
        $partnerId = $this->request->param('partner_id');
        $type = $this->request->param('type', 'receivable');

        if (!$partnerId) {
            return json(['code' => 400, 'msg' => 'partner_id不能为空'])->code(400);
        }

        $result = $this->arapService->agingAnalysis((int)$partnerId, $type);
        return json(['code' => 200, 'data' => $result]);
    }
}
