<?php
declare (strict_types = 1);

namespace app;

use app\exception\BizException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;
use thans\jwt\exception\JWTException;

/**
 * 应用异常处理类
 * /api 下的异常统一渲染为 {"code":..,"msg":..,"data":[]}
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
        BizException::class,
        JWTException::class,
    ];

    /**
     * 记录异常信息
     * @param Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     * @param \think\Request $request
     * @param Throwable      $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        [$code, $msg, $status] = $this->resolveException($e);

        if (!str_starts_with(ltrim($request->pathinfo(), '/'), 'api')) {
            return $this->renderHtml($status);
        }

        return json(['code' => $code, 'msg' => $msg, 'data' => []], $status);
    }

    /**
     * 非 API 请求的简洁错误页（不输出堆栈 / 服务器路径 / 调试面板）
     * @param int $status
     * @return Response
     */
    protected function renderHtml(int $status): Response
    {
        $title = match ($status) {
            401     => '请先登录',
            403     => '无权访问',
            404     => '页面不存在',
            429     => '请求过于频繁',
            default => '服务器开小差了',
        };

        $status = in_array($status, [401, 403, 404, 429, 500], true) ? $status : 500;

        $html = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $title . '</title><style>'
            . 'html,body{height:100%;margin:0}body{display:flex;align-items:center;justify-content:center;'
            . 'background:#f5f7fa;color:#303133;font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif}'
            . '.box{text-align:center;padding:40px 56px;background:#fff;border-radius:12px;box-shadow:0 6px 24px rgba(0,0,0,.06)}'
            . 'h1{margin:0;font-size:56px;color:#909399}p{margin:12px 0 24px;font-size:16px}'
            . 'a{color:#409eff;text-decoration:none;font-size:14px}a:hover{text-decoration:underline}'
            . '</style></head><body><div class="box"><h1>' . $status . '</h1><p>' . $title . '</p>'
            . '<a href="/">返回首页</a></div></body></html>';

        return response($html, $status)->contentType('text/html');
    }

    /**
     * 解析异常为 [业务码, 提示文字, HTTP状态码]
     * @param Throwable $e
     * @return array{0:int,1:string,2:int}
     */
    protected function resolveException(Throwable $e): array
    {
        if ($e instanceof BizException) {
            return [(int) $e->getCode(), $e->getMessage(), $e->getHttpStatus()];
        }

        if ($e instanceof ValidateException) {
            $error = $e->getError();
            $msg   = is_array($error) ? (string) reset($error) : (string) $error;
            return [0, $msg ?: '参数校验失败', 200];
        }

        if ($e instanceof JWTException) {
            return [401, '登录状态已失效，请重新登录', 401];
        }

        if ($e instanceof HttpException) {
            $status = $e->getStatusCode();
            $msg    = match ($status) {
                404     => '接口不存在',
                405     => '请求方法不允许',
                429     => '请求过于频繁，请稍后再试',
                default => $status >= 500 ? '服务器内部错误' : '请求异常',
            };
            return [0, $msg, $status];
        }

        if ($e instanceof DataNotFoundException || $e instanceof ModelNotFoundException) {
            return [0, '数据不存在或已被删除', 404];
        }

        $msg = env('APP_DEBUG', false) ? $e->getMessage() : '服务器内部错误';

        return [0, $msg ?: '服务器内部错误', 500];
    }
}
