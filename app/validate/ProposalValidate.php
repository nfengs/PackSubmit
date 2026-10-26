<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

/**
 * 包装方案参数验证器
 */
class ProposalValidate extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'customer|客户'     => 'require|max:100',
        'spec|规格尺寸'     => 'max:255',
        'quantity|数量'     => 'integer|egt:0',
        'supplier|供应商'   => 'max:100',
        'contact|联系人'    => 'max:50',
        'phone|联系电话'    => 'max:30',
        'description|方案说明' => 'max:5000',

        // 包装确认单
        'write_date|填写日期'        => 'dateFormat:Y-m-d',
        'project_model|项目车型'     => 'max:100',
        'part_name|零件名称'         => 'max:200',
        'part_no|零件号'             => 'max:100',
        'part_material|零件材料'     => 'max:100',
        'customer_address|客户地址'  => 'max:255',
        'customer_contact|客户联系人' => 'max:50',
        'customer_phone|客户电话'    => 'max:30',
        'customer_email|客户邮箱'    => 'email|max:100',
        'customer_fax|客户传真'      => 'max:30',
        'supplier_code|供应商代码'   => 'max:50',
        'supplier_address|供应商地址' => 'max:255',
        'supplier_email|供应商邮箱'  => 'email|max:100',
        'supplier_fax|供应商传真'    => 'max:30',
        'pack_box_no|包装箱编号'     => 'max:50',
        'pack_material|包装箱材料'   => 'in:plastic,metal,carton,other',
        'part_weight|产品净重'       => 'float|egt:0',
        'part_size_l|产品外形长'     => 'float|egt:0',
        'part_size_w|产品外形宽'     => 'float|egt:0',
        'part_size_h|产品外形高'     => 'float|egt:0',
        'box_size_l|包装箱外长'      => 'float|egt:0',
        'box_size_w|包装箱外宽'      => 'float|egt:0',
        'box_size_h|包装箱外高'      => 'float|egt:0',
        'gross_weight|毛重'          => 'float|egt:0',
        'returnable|包装箱是否返回'  => 'in:0,1',
        'dunnage_returnable|内衬是否返回' => 'in:0,1',
        'with_pallet|是否带托盘交付' => 'in:0,1',
        'packing_qty|存放数量'       => 'integer|egt:0',
        'pallet_size_l|满托盘长'     => 'float|egt:0',
        'pallet_size_w|满托盘宽'     => 'float|egt:0',
        'pallet_size_h|满托盘高'     => 'float|egt:0',
        'pallet_layer_qty|每层包装箱数量' => 'integer|egt:0',
        'pallet_layer_count|堆码层数' => 'integer|egt:0',
        'pallet_parts_qty|每托盘零件数量' => 'integer|egt:0',
        'packaging_instruction|包装操作说明' => 'max:5000',
    ];

    /**
     * 提示信息
     * @var array
     */
    protected $message = [
        'customer.require'    => '请选择客户',
        'quantity.integer'    => '数量必须为整数',
        'quantity.egt'        => '数量不能小于 0',
    ];
}
