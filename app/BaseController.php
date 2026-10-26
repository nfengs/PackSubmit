<?php
declare (strict_types = 1);

namespace app;

use app\model\User;
use think\App;
use think\exception\ValidateException;
use think\response\Json;
use think\Validate;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    /**
     * Request实例
     * @var \app\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    /**
     * 构造方法
     * @param App $app 应用对象
     */
    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;

        $this->initialize();
    }

    /**
     * 初始化
     * @return void
     */
    protected function initialize()
    {
    }

    /**
     * 成功响应
     * @param mixed  $data 业务数据
     * @param string $msg  提示文字
     * @return Json
     */
    protected function success(mixed $data = [], string $msg = '操作成功'): Json
    {
        return json(['code' => 1, 'msg' => $msg, 'data' => $data]);
    }

    /**
     * 失败响应
     * @param string $msg        提示文字
     * @param int    $code       业务码：0 业务错误、401 未登录、403 权限不足
     * @param int    $httpStatus HTTP 状态码
     * @return Json
     */
    protected function fail(string $msg = '操作失败', int $code = 0, int $httpStatus = 200): Json
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => []], $httpStatus);
    }

    /**
     * 当前登录用户
     * @return User
     * @throws \app\exception\BizException
     */
    protected function currentUser(): User
    {
        if (!$this->request->user) {
            throw new \app\exception\BizException('请先登录', 401, 401);
        }

        return $this->request->user;
    }

    /**
     * 当前登录用户ID
     * @return int
     */
    protected function userId(): int
    {
        return (int) $this->currentUser()->id;
    }

    /**
     * 当前登录用户角色
     * @return int
     */
    protected function role(): int
    {
        return (int) $this->currentUser()->role;
    }

    /**
     * 是否管理员
     * @return bool
     */
    protected function isAdmin(): bool
    {
        return $this->role() === User::ROLE_ADMIN;
    }

    /**
     * 验证数据
     * @param array        $data     数据
     * @param string|array $validate 验证器名或者验证规则数组
     * @param array        $message  提示信息
     * @param bool         $batch    是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, string|array $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v     = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }
}
