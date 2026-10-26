<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

/**
 * 字典参数验证器
 */
class DictValidate extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'dict_type|字典类型'  => 'require|alphaDash|max:50',
        'dict_label|显示名称' => 'require|max:100',
        'dict_value|字典值'   => 'require|max:100',
        'sort|排序'           => 'integer',
        'status|状态'         => 'in:0,1',
        'remark|备注'         => 'max:255',
    ];

    /**
     * 提示信息
     * @var array
     */
    protected $message = [
        'dict_type.require'  => '请输入字典类型',
        'dict_type.alphaDash' => '字典类型只能由字母、数字、下划线组成',
        'dict_label.require' => '请输入显示名称',
        'dict_value.require' => '请输入字典值',
    ];
}
