<?php
declare (strict_types = 1);

namespace app;

/**
 * 应用请求对象类
 */
class Request extends \think\Request
{
    /**
     * 当前登录用户，由 JwtAuth 中间件注入
     * @var \app\model\User|null
     */
    public ?\app\model\User $user = null;
}
