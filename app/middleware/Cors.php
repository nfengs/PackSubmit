<?php
declare (strict_types = 1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;

/**
 * 跨域中间件（前后端分离部署时使用）
 *
 * 默认不放开跨域（同源部署 / Vite 代理无需 CORS）；
 * 需要跨域时在 .env 配置 CORS_ALLOW_ORIGIN，多个来源用英文逗号分隔，
 * 例如：CORS_ALLOW_ORIGIN = http://localhost:5173,https://pack.example.com
 */
class Cors
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin  = (string) $request->header('origin', '');
        $allowed = $this->resolveAllowedOrigin($origin);

        // 未配置 / 来源不在白名单：不下发 CORS 头，浏览器会拦截跨域读取
        if ($allowed === null) {
            return $request->method(true) === 'OPTIONS'
                ? response('', 204)->header(['Vary' => 'Origin'])
                : $next($request)->header(['Vary' => 'Origin']);
        }

        $headers = [
            'Access-Control-Allow-Origin'   => $allowed,
            'Access-Control-Allow-Methods'  => 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
            'Access-Control-Allow-Headers'  => 'Authorization,Content-Type,X-Requested-With,Accept,Origin',
            'Access-Control-Expose-Headers' => 'Authorization,Content-Disposition',
            'Access-Control-Max-Age'        => '86400',
            'Vary'                          => 'Origin',
        ];

        // 预检请求直接返回 204
        if ($request->method(true) === 'OPTIONS') {
            return response('', 204)->header($headers);
        }

        return $next($request)->header($headers);
    }

    /**
     * 解析允许的 Origin
     * @param string $origin 请求头 Origin
     * @return string|null null 表示不允许跨域
     */
    protected function resolveAllowedOrigin(string $origin): ?string
    {
        $configured = trim((string) env('CORS_ALLOW_ORIGIN', ''));

        if ($configured === '') {
            return null;
        }

        // 显式配置 * 时放开（仅建议本地调试使用）
        if ($configured === '*') {
            return '*';
        }

        if ($origin === '') {
            return null;
        }

        foreach (explode(',', $configured) as $item) {
            $item = rtrim(trim($item), '/');

            if ($item !== '' && strcasecmp($item, rtrim($origin, '/')) === 0) {
                return $origin;
            }
        }

        return null;
    }
}
