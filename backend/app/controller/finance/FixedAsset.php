<?php
namespace app\controller\finance;

use app\BaseController;
use app\application\asset\AssetAppService;
use think\App;

class FixedAsset extends BaseController
{
    private AssetAppService $assetService;

    public function __construct(App $app, AssetAppService $assetService)
    {
        parent::__construct($app);
        $this->assetService = $assetService;
    }

    public function index()
    {
        $filters = [
            'book_id'     => $this->request->param('book_id'),
            'category_id' => $this->request->param('category_id'),
            'status'      => $this->request->param('status'),
        ];
        $result = $this->assetService->listCards($filters, (int)$this->request->param('page', 1), (int)$this->request->param('per_page', 20));
        return json(['code' => 200, 'data' => $result]);
    }

    public function read($id)
    {
        $card = $this->assetService->getCard((int)$id);
        if (!$card) return json(['code' => 404, 'msg' => '资产卡片不存在'])->code(404);
        return json(['code' => 200, 'data' => $card]);
    }

    public function save()
    {
        $result = $this->assetService->createCard($this->request->post(), $this->request->userId);
        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }

    public function delete($id)
    {
        $result = $this->assetService->deleteCard((int)$id);
        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }

    public function depreciate($id)
    {
        $year   = (int)$this->request->param('year', date('Y'));
        $period = (int)$this->request->param('period', date('n'));
        $result = $this->assetService->depreciateCard((int)$id, $year, $period);
        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }

    public function batchDepreciate()
    {
        $bookId = (int)$this->request->post('book_id', 0);
        $year   = (int)$this->request->post('year', date('Y'));
        $period = (int)$this->request->post('period', date('n'));
        $result = $this->assetService->batchDepreciate($bookId, $year, $period);
        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }

    public function dispose($id)
    {
        $result = $this->assetService->disposeCard((int)$id, $this->request->post());
        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }

    public function depreciationSummary()
    {
        $bookId = (int)$this->request->param('book_id');
        $year   = (int)$this->request->param('year', date('Y'));
        $period = (int)$this->request->param('period', date('n'));
        $result = $this->assetService->depreciationSummary($bookId, $year, $period);
        return json(['code' => 200, 'data' => $result]);
    }
}
