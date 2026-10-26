<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

/**
 * 客户档案参数验证器
 */
class CustomerValidate extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'name|客户名称'   => 'require|max:100',
        'address|客户地址' => 'max:255',
        'contact|联系人'  => 'max:50',
        'phone|联系电话'  => 'max:30',
        'email|电子邮箱'  => 'email|max:100',
        'fax|传真'       => 'max:30',
        'status|状态'     => 'in:0,1',
    ];

    /**
     * 提示信息
     * @var array
     */
    protected $message = [
        'name.require' => '请输入客户名称',
        'email.email'  => '客户邮箱格式不正确',
    ];
}
