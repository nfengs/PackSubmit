<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 客户档案模型
 * @property int    $id
 * @property string $name
 * @property string $address
 * @property string $contact
 * @property string $phone
 * @property string $email
 * @property string $fax
 * @property int    $status
 */
class Customer extends Model
{
    use SoftDelete;

    /** 软删除字段 */
    protected $deleteTime = 'delete_time';

    /** 状态：禁用 */
    public const STATUS_DISABLED = 0;
    /** 状态：启用 */
    public const STATUS_ENABLED = 1;

    /** 表名 */
    protected $name = 'customer';

    /** 类型转换 */
    protected $type = [
        'status' => 'integer',
    ];

    /**
     * 作用域：仅启用
     * @param mixed $query
     * @return void
     */
    public function scopeEnabled($query): void
    {
        $query->where('status', self::STATUS_ENABLED);
    }
}
