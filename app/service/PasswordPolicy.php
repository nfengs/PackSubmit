<?php
declare (strict_types = 1);

namespace app\service;

use app\exception\BizException;

/**
 * 密码强度策略
 * 统一约束：自助改密、管理员新建 / 重置密码、Excel 导入初始密码
 */
class PasswordPolicy
{
    public const MIN_LENGTH = 8;
    public const MAX_LENGTH = 32;

    /** 常见弱口令（小写比较） */
    private const WEAK_PASSWORDS = [
        '12345678', '123456789', '1234567890', 'password', 'password1', 'password123',
        'passw0rd', 'admin123', 'admin888', 'adminadmin', 'qwerty123', 'abc12345',
        'a1234567', 'abcd1234', '1qaz2wsx', '11111111', '88888888', '00000000',
    ];

    /**
     * 校验密码强度，不合规则抛出业务异常
     * @param string $password 明文密码
     * @param string $label    字段名称（用于提示）
     * @return void
     * @throws BizException
     */
    public static function assert(string $password, string $label = '密码'): void
    {
        $length = mb_strlen($password);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new BizException(sprintf('%s长度需为 %d-%d 位', $label, self::MIN_LENGTH, self::MAX_LENGTH));
        }

        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            throw new BizException($label . '需同时包含字母和数字');
        }

        if (in_array(strtolower($password), self::WEAK_PASSWORDS, true)) {
            throw new BizException($label . '过于简单，请更换更复杂的密码');
        }
    }
}
