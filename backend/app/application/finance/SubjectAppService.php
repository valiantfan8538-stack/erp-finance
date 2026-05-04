<?php

namespace app\application\finance;

use app\domain\finance\repository\SubjectRepositoryInterface;

class SubjectAppService
{
    private SubjectRepositoryInterface $subjectRepo;

    public function __construct(SubjectRepositoryInterface $subjectRepo)
    {
        $this->subjectRepo = $subjectRepo;
    }

    public function listSubjects(int $bookId): array
    {
        $subjects = $this->subjectRepo->findByBook($bookId);
        return ['code' => 200, 'data' => $subjects];
    }

    public function getTree(int $bookId): array
    {
        $subjects = $this->subjectRepo->findByBook($bookId);
        return ['code' => 200, 'data' => $this->buildTree($subjects)];
    }

    public function getSubject(int $id): ?array
    {
        return $this->subjectRepo->findById($id);
    }

    public function createSubject(array $data): array
    {
        if (empty($data['book_id']) || empty($data['code']) || empty($data['name']) || empty($data['category'])) {
            return ['code' => 400, 'msg' => '参数不完整:book_id,code,name,category必填'];
        }

        $exists = $this->subjectRepo->findByCode($data['book_id'], $data['code']);
        if ($exists) {
            return ['code' => 400, 'msg' => '科目编码已存在'];
        }

        $level = 1;
        $parentId = 0;
        if (!empty($data['parent_id'])) {
            $parent = $this->subjectRepo->findById($data['parent_id']);
            if (!$parent) {
                return ['code' => 400, 'msg' => '父级科目不存在'];
            }
            if ($parent['is_leaf'] == 1) {
                $this->subjectRepo->setLeafStatus($parent['id'], false);
            }
            $level = $parent['level'] + 1;
            $parentId = $parent['id'];
        }

        $id = $this->subjectRepo->create([
            'book_id'             => $data['book_id'],
            'code'                => $data['code'],
            'name'                => $data['name'],
            'parent_id'           => $parentId,
            'category'            => $data['category'],
            'direction'           => $data['direction'] ?? 'debit',
            'level'               => $level,
            'is_leaf'             => 1,
            'is_cash_account'     => $data['is_cash_account'] ?? 0,
            'is_bank_account'     => $data['is_bank_account'] ?? 0,
            'is_quantity_account' => $data['is_quantity_account'] ?? 0,
            'unit'                => $data['unit'] ?? null,
            'sort'                => $data['sort'] ?? 0,
            'status'              => 1,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        return ['code' => 200, 'msg' => '创建成功', 'data' => ['id' => $id]];
    }

    public function updateSubject(int $id, array $data): array
    {
        $subject = $this->subjectRepo->findById($id);
        if (!$subject) {
            return ['code' => 404, 'msg' => '科目不存在'];
        }

        $this->subjectRepo->update($id, [
            'name'       => $data['name'] ?? $subject['name'],
            'direction'  => $data['direction'] ?? $subject['direction'],
            'status'     => $data['status'] ?? $subject['status'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['code' => 200, 'msg' => '更新成功'];
    }

    public function deleteSubject(int $id): array
    {
        $subject = $this->subjectRepo->findById($id);
        if (!$subject) {
            return ['code' => 404, 'msg' => '科目不存在'];
        }

        if ($this->subjectRepo->hasChildren($id)) {
            return ['code' => 400, 'msg' => '存在下级科目，不可删除'];
        }

        if ($this->subjectRepo->hasEntries($id)) {
            return ['code' => 400, 'msg' => '科目已有发生额，不可删除'];
        }

        $this->subjectRepo->delete($id);

        return ['code' => 200, 'msg' => '删除成功'];
    }

    private function buildTree(array $subjects, int $parentId = 0): array
    {
        $tree = [];
        foreach ($subjects as $s) {
            if ($s['parent_id'] == $parentId) {
                $s['children'] = $this->buildTree($subjects, $s['id']);
                $tree[] = $s;
            }
        }
        return $tree;
    }
}
