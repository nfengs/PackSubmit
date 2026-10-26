<?php
declare (strict_types = 1);

namespace app\middleware;

use app\exception\BizException;
use Closure;
use think\Request;
use think\Response;

/**
 * 角色校验中间件
 * 用法：->middleware(RoleAuth::class, '3')，不传参数时默认仅管理员(role=3)可访问
 */
class RoleAuth
{
    /**
     * @param Request $request
     * @param Closure $next
     * @param mixed   ...$roles 允许的角色，默认 3
     * @return Response
     * @throws BizException
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user;

        if (!$user) {
            throw new BizException('请先登录', 401, 401);
        }

        $roles    = $roles ? array_map('intval', $roles) : [3];
        $userRole = (int) $user->role;

        if (!in_array($userRole, $roles, true)) {
            throw new BizException('无权限访问该接口', 403, 403);
        }

        return $next($request);
    }
}
