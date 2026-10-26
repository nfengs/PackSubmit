<?php
declare (strict_types = 1);

namespace app\controller;

use app\BaseController;
use app\exception\BizException;
use app\model\User;
use app\service\AuditService;
use app\validate\LoginValidate;
use app\validate\PasswordValidate;
use think\response\Json;
use thans\jwt\facade\JWTAuth;

/**
 * 认证控制器
 */
class Auth extends BaseController
{
    /**
     * 登录
     * @return Json
     * @throws BizException
     * @throws \think\exception\ValidateException
     */
    public function login(): Json
    {
        $data = $this->request->only(['username', 'password']);
        $this->validate($data, LoginValidate::class);

        $username = trim((string) $data['username']);
        $user     = User::where('username', $username)->find();

        if (!$user || !$user->checkPassword((string) $data['password'])) {
            throw new BizException('账号或密码错误');
        }

        if ((int) $user->status !== User::STATUS_ENABLED) {
            throw new BizException('账号已被禁用，请联系管理员', 403, 403);
        }

        // 签发 JWT，uid 用于后续鉴权
        $token = JWTAuth::builder([
            'uid'  => (int) $user->id,
            'role' => (int) $user->role,
        ]);

        AuditService::record((int) $user->id, 'login', 0, ['content' => '用户登录']);

        return $this->success([
            'token' => $token,
            'user'  => $user->toArray(),
        ], '登录成功');
    }

    /**
     * 退出登录（token 加入黑名单）
     * @return Json
     */
    public function logout(): Json
    {
        $token = JWTAuth::token();

        if ($token) {
            try {
                JWTAuth::invalidate($token);
            } catch (\Throwable $e) {
                // token 已失效时忽略
            }
        }

        AuditService::record($this->userId(), 'logout', 0, ['content' => '退出登录']);

        return $this->success([], '已退出登录');
    }

    /**
     * 修改自己的登录密码
     * @return Json
     * @throws BizException
     * @throws \think\exception\ValidateException
     */
    public function password(): Json
    {
        $data = $this->request->only(['old_password', 'new_password', 'confirm_password']);
        $this->validate($data, PasswordValidate::class);

        $user = $this->currentUser();

        if (!$user->checkPassword((string) $data['old_password'])) {
            throw new BizException('原密码不正确');
        }

        if ((string) $data['old_password'] === (string) $data['new_password']) {
            throw new BizException('新密码不能与原密码相同');
        }

        $user->save(['password' => (string) $data['new_password']]);

        AuditService::record((int) $user->id, 'password_change', 0, ['content' => '修改登录密码']);

        return $this->success([], '密码修改成功');
    }

    /**
     * 当前登录用户信息
     * @return Json
     */
    public function profile(): Json
    {
        return $this->success($this->currentUser()->toArray());
    }
}
