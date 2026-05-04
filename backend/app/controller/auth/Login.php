<?php
namespace app\controller\auth;

use app\BaseController;
use Firebase\JWT\JWT;
use think\facade\Db;

class Login extends BaseController
{
    public function login()
    {
        $username = $this->request->post('username', '');
        $password = $this->request->post('password', '');

        if (!$username || !$password) {
            return json(['code' => 400, 'msg' => '账号和密码不能为空'])->code(400);
        }

        $user = Db::table('sys_user')
            ->where('username', $username)
            ->whereNull('deleted_at')
            ->find();

        if (!$user || !password_verify($password, $user['password'])) {
            return json(['code' => 400, 'msg' => '账号或密码错误'])->code(400);
        }

        if ((int)$user['status'] !== 1) {
            return json(['code' => 403, 'msg' => '账号已禁用'])->code(403);
        }

        $token = $this->generateToken($user);

        Db::table('sys_user')->where('id', $user['id'])->update([
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip'  => $this->request->ip(),
        ]);

        return json([
            'code' => 200,
            'msg'  => '登录成功',
            'data' => [
                'token' => $token,
                'user'  => [
                    'id'        => $user['id'],
                    'username'  => $user['username'],
                    'real_name' => $user['real_name'],
                    'is_admin'  => (int)$user['is_admin'],
                ],
            ],
        ]);
    }

    public function me()
    {
        $user = Db::table('sys_user')
            ->where('id', $this->request->userId)
            ->whereNull('deleted_at')
            ->field('id, username, real_name, phone, email, is_admin, status, last_login_at')
            ->find();

        if (!$user) {
            return json(['code' => 404, 'msg' => '用户不存在'])->code(404);
        }

        $roleIds = Db::table('sys_user_role')->where('user_id', $user['id'])->column('role_id');
        $permissions = [];
        if ($roleIds) {
            $permissions = Db::table('sys_role_permission')
                ->alias('rp')
                ->join('sys_permission p', 'rp.permission_id = p.id')
                ->whereIn('rp.role_id', $roleIds)
                ->where('p.status', 1)
                ->column('p.code');
        }

        $user['roles'] = $roleIds;
        $user['permissions'] = $permissions;

        return json(['code' => 200, 'data' => $user]);
    }

    public function logout()
    {
        return json(['code' => 200, 'msg' => '退出成功']);
    }

    private function generateToken(array $user): string
    {
        $secret = env('JWT_SECRET', 'erp-finance-secret-key-change-in-production');
        $expire = (int)env('JWT_EXPIRE', 7200);

        $payload = [
            'iss'  => 'erp-finance',
            'aud'  => 'erp-finance',
            'iat'  => time(),
            'exp'  => time() + $expire,
            'data' => [
                'user_id'  => $user['id'],
                'is_admin' => (int)$user['is_admin'],
            ],
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }
}
