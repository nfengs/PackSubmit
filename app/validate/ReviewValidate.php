<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

/**
 * 评审参数验证器
 */
class ReviewValidate extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'status|评审结果' => 'require|in:2,3',
        'remark|评审意见' => 'max:500',
    ];

    /**
     * 提示信息
     * @var array
     */
    protected $message = [
        'status.require' => '请选择评审结果',
        'status.in'      => '评审结果只能是通过(2)或驳回(3)',
    ];
}
