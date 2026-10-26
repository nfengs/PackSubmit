<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 数据字典模型
 * @property int    $id
 * @property string $dict_type
 * @property string $dict_label
 * @property string $dict_value
 * @property int    $sort
 * @property int    $status
 */
class Dict extends Model
{
    /** 状态：禁用 */
    public const STATUS_DISABLED = 0;
    /** 状态：启用 */
    public const STATUS_ENABLED = 1;

    /** 表名 */
    protected $name = 'dict';

    /** 类型转换 */
    protected $type = [
        'sort'   => 'integer',
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
