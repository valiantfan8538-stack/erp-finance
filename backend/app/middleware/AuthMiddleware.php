<?php
namespace app\middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\Request;

class AuthMiddleware
{
    public function handle(Request $request, \Closure $next)
    {
        $token = $request->header('Authorization');
        if (!$token || !str_starts_with($token, 'Bearer ')) {
            return json(['code' => 401, 'msg' => '未登录'])->code(401);
        }

        $token = substr($token, 7);
        try {
            $secret = $this->getSecret();
            $payload = JWT::decode($token, new Key($secret, 'HS256'));
            $request->userId = $payload->data->user_id ?? 0;
            $request->isAdmin = $payload->data->is_admin ?? false;
        } catch (\Exception $e) {
            return json(['code' => 401, 'msg' => 'Token无效或已过期'])->code(401);
        }

        return $next($request);
    }

    private function getSecret(): string
    {
        return env('JWT_SECRET', 'erp-finance-secret-key-change-in-production');
    }
}
