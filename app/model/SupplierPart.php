<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 供应商指定零件号模型
 * @property int    $id
 * @property string $supplier_name
 * @property string $project_name
 * @property string $part_no
 * @property string $part_name
 */
class SupplierPart extends Model
{
    /** 表名 */
    protected $name = 'supplier_part';
}
