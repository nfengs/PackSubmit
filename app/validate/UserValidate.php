<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

/**
 * 用户管理参数验证器
 */
class UserValidate extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'username|账号'   => 'require|alphaDash|max:50',
        'password|密码'   => 'require|min:8|max:32',
        'real_name|姓名'  => 'require|max:50',
        'role|角色'       => 'require|in:1,2,3,4',
        'dept|部门'       => 'max:100',
        'phone|联系电话'  => 'max:30',
        'status|状态'     => 'in:0,1',
    ];

    /**
     * 验证场景
     * @var array
     */
    protected $scene = [
        'create' => ['username', 'password', 'real_name', 'role', 'dept', 'phone'],
        'update' => ['real_name', 'role', 'dept', 'phone', 'status'],
        'reset'  => ['password'],
    ];

    /**
     * 提示信息
     * @var array
     */
    protected $message = [
        'username.require'   => '请输入登录账号',
        'username.alphaDash' => '账号只能由字母、数字、下划线组成',
        'password.require'   => '请输入密码',
        'password.min'       => '密码长度不能少于 8 位',
        'real_name.require'  => '请输入姓名',
        'role.require'       => '请选择角色',
        'role.in'            => '角色不合法',
    ];
}
