<?php
namespace app;

use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

class ExceptionHandle extends Handle
{
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ValidateException::class,
    ];

    public function report(Throwable $exception): void
    {
        parent::report($exception);
    }

    public function render($request, Throwable $e): Response
    {
        if ($e instanceof ValidateException) {
            return json(['code' => 422, 'msg' => $e->getMessage()]);
        }

        if ($e instanceof HttpException) {
            return json(['code' => $e->getStatusCode(), 'msg' => $e->getMessage() ?: '请求错误']);
        }

        if ($e instanceof \think\db\exception\DataNotFoundException) {
            return json(['code' => 404, 'msg' => '数据不存在']);
        }

        $debug = env('APP_DEBUG', false);
        if ($debug) {
            return parent::render($request, $e);
        }

        return json(['code' => 500, 'msg' => '服务器内部错误']);
    }
}
