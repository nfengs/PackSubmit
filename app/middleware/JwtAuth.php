<?php
declare (strict_types = 1);

namespace app\middleware;

use app\exception\BizException;
use app\model\User;
use Closure;
use think\Request;
use think\Response;
use thans\jwt\facade\JWTAuth as JwtFacade;

/**
 * JWT 登录校验中间件
 * 校验 Authorization: Bearer {token}，成功后把用户对象注入 Request::$user
 */
class JwtAuth
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return Response
     * @throws BizException
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $payload = JwtFacade::auth();
        } catch (\Throwable $e) {
            throw new BizException('登录状态已失效，请重新登录', 401, 401);
        }

        $uid  = (int) ($payload['uid'] ?? 0);
        $user = $uid > 0 ? User::find($uid) : null;

        if (!$user) {
            throw new BizException('登录用户不存在，请重新登录', 401, 401);
        }

        if ((int) $user->status !== User::STATUS_ENABLED) {
            throw new BizException('账号已被禁用，请联系管理员', 403, 403);
        }

        // 注入当前登录用户
        $request->user = $user;

        return $next($request);
    }
}
