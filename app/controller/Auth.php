<?php
declare (strict_types = 1);

namespace app\controller;

use app\BaseController;
use app\exception\BizException;
use app\model\User;
use app\service\AuditService;
use app\service\PasswordPolicy;
use app\validate\LoginValidate;
use app\validate\PasswordValidate;
use think\facade\Cache;
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
        $ip       = (string) $this->request->ip();

        // 登录失败限流：同一账号 + IP、同一 IP 双维度计数
        $maxAttempts   = max(1, (int) env('LOGIN_MAX_ATTEMPTS', 5));
        $maxIpAttempts = max(1, (int) env('LOGIN_MAX_IP_ATTEMPTS', 20));
        $lockSeconds   = max(60, (int) env('LOGIN_LOCK_SECONDS', 900));
        $failKey       = 'login_fail:' . md5(mb_strtolower($username) . '|' . $ip);
        $ipFailKey     = 'login_fail_ip:' . md5($ip);
        $failCount     = (int) Cache::get($failKey, 0);
        $ipFailCount   = (int) Cache::get($ipFailKey, 0);

        if ($failCount >= $maxAttempts || $ipFailCount >= $maxIpAttempts) {
            throw new BizException(
                sprintf('登录失败次数过多，请 %d 分钟后再试', (int) ceil($lockSeconds / 60)),
                429,
                429
            );
        }

        $user = User::where('username', $username)->find();

        if (!$user || !$user->checkPassword((string) $data['password'])) {
            Cache::set($failKey, $failCount + 1, $lockSeconds);
            Cache::set($ipFailKey, $ipFailCount + 1, $lockSeconds);

            throw new BizException('账号或密码错误');
        }

        // 登录成功清除该账号的失败计数
        Cache::delete($failKey);

        if ((int) $user->status !== User::STATUS_ENABLED) {
            throw new BizException('账号已被禁用，请联系管理员', 403, 403);
        }

        // 签发 JWT：uid 用于鉴权，ver 用于改密后吊销旧 token
        $token = JWTAuth::builder([
            'uid'  => (int) $user->id,
            'role' => (int) $user->role,
            'ver'  => (int) $user->token_version,
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

        PasswordPolicy::assert((string) $data['new_password'], '新密码');

        // 递增 token 版本号：改密后所有已签发的 token 立即失效
        $newVersion = (int) $user->token_version + 1;
        $user->save([
            'password'      => (string) $data['new_password'],
            'token_version' => $newVersion,
        ]);

        // 给当前设备补发新 token，其余设备需重新登录
        $token = JWTAuth::builder([
            'uid'  => (int) $user->id,
            'role' => (int) $user->role,
            'ver'  => $newVersion,
        ]);

        AuditService::record((int) $user->id, 'password_change', 0, ['content' => '修改登录密码']);

        return $this->success(['token' => $token], '密码修改成功，其他设备需重新登录');
    }

    /**
     * 刷新 token（滑动续期，旧 token 加入黑名单）
     * @return Json
     * @throws BizException
     */
    public function refresh(): Json
    {
        try {
            $token   = JWTAuth::refresh();
            $payload = JWTAuth::setToken($token)->getPayload();
        } catch (\Throwable $e) {
            throw new BizException('登录状态已失效，请重新登录', 401, 401);
        }

        $user = User::find((int) ($payload['uid'] ?? 0));

        if (!$user || (int) $user->status !== User::STATUS_ENABLED) {
            throw new BizException('账号已被禁用，请联系管理员', 403, 403);
        }

        // 改密后旧 token 的版本号不再匹配
        if ((int) ($payload['ver'] ?? 0) !== (int) $user->token_version) {
            throw new BizException('登录状态已失效，请重新登录', 401, 401);
        }

        return $this->success([
            'token' => $token,
            'user'  => $user->toArray(),
        ], 'token 已刷新');
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
