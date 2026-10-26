<?php
declare (strict_types = 1);

namespace app\exception;

use Exception;

/**
 * 业务异常
 * 统一由 ExceptionHandle 渲染为 {"code":..,"msg":..,"data":[]}
 */
class BizException extends Exception
{
    /**
     * HTTP 状态码
     * @var int
     */
    protected int $httpStatus;

    /**
     * @param string $message    提示文字
     * @param int    $code       业务码：0 业务错误、401 未登录、403 权限不足、404 资源不存在、429 请求过频
     * @param int    $httpStatus HTTP 状态码；传 0 时按业务码自动映射（401/403/404/429），其余为 200
     */
    public function __construct(string $message = '操作失败', int $code = 0, int $httpStatus = 0)
    {
        if ($httpStatus === 0) {
            $httpStatus = in_array($code, [401, 403, 404, 429], true) ? $code : 200;
        }

        $this->httpStatus = $httpStatus;
        parent::__construct($message, $code);
    }

    /**
     * 获取 HTTP 状态码
     * @return int
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
