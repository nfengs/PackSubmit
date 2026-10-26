<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

/**
 * 修改密码参数验证器
 */
class PasswordValidate extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'old_password|原密码'     => 'require',
        'new_password|新密码'     => 'require|min:8|max:32',
        'confirm_password|确认密码' => 'require|confirm:new_password',
    ];

    /**
     * 提示信息
     * @var array
     */
    protected $message = [
        'old_password.require'     => '请输入原密码',
        'new_password.require'     => '请输入新密码',
        'new_password.min'         => '新密码长度不能少于 8 位',
        'new_password.max'         => '新密码长度不能超过 32 位',
        'confirm_password.require' => '请再次输入新密码',
        'confirm_password.confirm' => '两次输入的新密码不一致',
    ];
}
