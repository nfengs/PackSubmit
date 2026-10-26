<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;
use think\model\relation\BelongsTo;

/**
 * 供应商联系人模型（一个供应商可维护多个联系人，包含电话 / 邮箱 / 传真）
 * @property int    $id
 * @property int    $supplier_id
 * @property string $name
 * @property string $phone
 * @property string $email
 * @property string $fax
 * @property int    $is_default
 */
class SupplierContact extends Model
{
    /** 默认联系人 */
    public const DEFAULT_YES = 1;
    /** 非默认联系人 */
    public const DEFAULT_NO = 0;

    /** 表名 */
    protected $name = 'supplier_contact';

    /** 类型转换 */
    protected $type = [
        'supplier_id' => 'integer',
        'is_default'  => 'integer',
    ];

    /**
     * 关联：所属供应商
     * @return BelongsTo
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id')->field('id,name,code,status');
    }
}
