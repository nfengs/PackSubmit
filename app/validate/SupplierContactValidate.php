<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

/**
 * 供应商联系人参数验证器
 */
class SupplierContactValidate extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'supplier_id|供应商' => 'integer',
        'name|联系人姓名'     => 'require|max:50',
        'phone|联系电话'      => 'max:30',
        'email|电子邮箱'      => 'email|max:100',
        'fax|传真'           => 'max:30',
        'is_default|默认联系人' => 'in:0,1',
    ];

    /**
     * 提示信息
     * @var array
     */
    protected $message = [
        'name.require' => '请输入联系人姓名',
        'email.email'  => '联系人邮箱格式不正确',
    ];
}
