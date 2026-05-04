<?php

namespace app\domain\finance\service;

use think\facade\Db;

class AuditService
{
    public function log(int $userId, string $action, string $tableName, int $recordId, array $changes): void
    {
        Db::table('audit_logs')->insert([
            'user_id'    => $userId,
            'action'     => $action,
            'table_name' => $tableName,
            'record_id'  => $recordId,
            'changes'    => json_encode($changes, JSON_UNESCAPED_UNICODE),
            'ip'         => request()->ip(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function query(array $filters, int $page = 1, int $perPage = 30): array
    {
        $query = Db::table('audit_logs')
            ->when(!empty($filters['table_name']), fn($q) => $q->where('table_name', $filters['table_name']))
            ->when(!empty($filters['record_id']), fn($q) => $q->where('record_id', $filters['record_id']))
            ->when(!empty($filters['user_id']), fn($q) => $q->where('user_id', $filters['user_id']))
            ->when(!empty($filters['action']), fn($q) => $q->where('action', $filters['action']))
            ->when(!empty($filters['date_from']), fn($q) => $q->where('created_at', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']), fn($q) => $q->where('created_at', '<=', $filters['date_to'] . ' 23:59:59'))
            ->order('created_at', 'desc');

        return $query->paginate($perPage)->toArray();
    }
}
