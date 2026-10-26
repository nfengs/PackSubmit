<?php
declare (strict_types = 1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;

/**
 * 跨域中间件（前后端分离部署时使用）
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
        $allowOrigin = env('CORS_ALLOW_ORIGIN', '*');
        // 允许携带凭证时不能使用 *，此处未使用 Cookie 认证，直接按配置下发
        $headers = [
            'Access-Control-Allow-Origin'      => $allowOrigin,
            'Access-Control-Allow-Methods'     => 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
            'Access-Control-Allow-Headers'     => 'Authorization,Content-Type,X-Requested-With,Accept,Origin',
            'Access-Control-Expose-Headers'    => 'Authorization,Content-Disposition',
            'Access-Control-Max-Age'           => '86400',
            'Vary'                             => 'Origin',
        ];

        // 预检请求直接返回 204
        if ($request->method(true) === 'OPTIONS') {
            return response('', 204)->header($headers);
        }

        return $next($request)->header($headers);
    }
}
