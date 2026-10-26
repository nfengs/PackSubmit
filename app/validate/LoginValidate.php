<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

/**
 * 登录参数验证器
 */
class LoginValidate extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'username|账号' => 'require|max:50',
        'password|密码' => 'require|min:6|max:32',
    ];

    /**
     * 提示信息
     * @var array
     */
    protected $message = [
        'username.require' => '请输入登录账号',
        'password.require' => '请输入登录密码',
        'password.min'     => '密码长度不能少于 6 位',
    ];
}
