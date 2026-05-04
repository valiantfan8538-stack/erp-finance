<?php
namespace app\controller\finance;

use app\BaseController;
use app\application\finance\SubjectAppService;
use think\App;

class AccountSubject extends BaseController
{
    private SubjectAppService $subjectService;

    public function __construct(App $app, SubjectAppService $subjectService)
    {
        parent::__construct($app);
        $this->subjectService = $subjectService;
    }

    public function index()
    {
        $bookId = $this->request->param('book_id');
        if (!$bookId) {
            return json(['code' => 400, 'msg' => 'book_id不能为空'])->code(400);
        }
        $result = $this->subjectService->listSubjects((int)$bookId);
        return json($result);
    }

    public function tree()
    {
        $bookId = $this->request->param('book_id');
        if (!$bookId) {
            return json(['code' => 400, 'msg' => 'book_id不能为空'])->code(400);
        }
        return json($this->subjectService->getTree((int)$bookId));
    }

    public function read($id)
    {
        $subject = $this->subjectService->getSubject((int)$id);
        if (!$subject) {
            return json(['code' => 404, 'msg' => '科目不存在'])->code(404);
        }
        return json(['code' => 200, 'data' => $subject]);
    }

    public function save()
    {
        $data = $this->request->post();
        $result = $this->subjectService->createSubject($data);
        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }

    public function update($id)
    {
        $data = $this->request->post();
        $result = $this->subjectService->updateSubject((int)$id, $data);
        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }

    public function delete($id)
    {
        $result = $this->subjectService->deleteSubject((int)$id);
        $code = $result['code'] === 200 ? 200 : 400;
        return json($result)->code($code);
    }
}
