<?php
declare (strict_types = 1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;

/**
 * 安全响应头中间件
 * 移除 PHP 版本标识，补充常用安全头（CSP 仅对 /api 响应下发，避免影响前端静态页面）
 */
class SecurityHeaders
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 移除 SAPI 自动输出的 PHP 版本信息
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }

        $response = $next($request);

        $response->header([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'SAMEORIGIN',
            'Referrer-Policy'        => 'no-referrer',
            'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=()',
        ]);

        // 仅对 JSON 接口下发 CSP；图片 / PDF 预览下载响应不加，避免影响浏览器内预览
        if (
            str_starts_with(ltrim($request->pathinfo(), '/'), 'api')
            && str_contains((string) $response->getHeader('Content-Type'), 'application/json')
        ) {
            $response->header([
                'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'",
            ]);
        }

        if ($request->isSsl()) {
            $response->header(['Strict-Transport-Security' => 'max-age=31536000; includeSubDomains']);
        }

        return $response;
    }
}
