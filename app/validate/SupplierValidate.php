<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

/**
 * 供应商档案参数验证器
 */
class SupplierValidate extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'name|供应商名称'  => 'require|max:100',
        'code|供应商代码'  => 'max:50',
        'address|供应商地址' => 'max:255',
        'contact|联系人'   => 'max:50',
        'phone|联系电话'   => 'max:30',
        'email|电子邮箱'   => 'email|max:100',
        'fax|传真'        => 'max:30',
        'status|状态'      => 'in:0,1',
    ];

    /**
     * 提示信息
     * @var array
     */
    protected $message = [
        'name.require' => '请输入供应商名称',
        'email.email'  => '供应商邮箱格式不正确',
    ];
}
