<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 供应商档案模型
 * @property int    $id
 * @property string $name
 * @property string $code
 * @property string $address
 * @property int    $status
 */
class Supplier extends Model
{
    use SoftDelete;

    /** 软删除字段 */
    protected $deleteTime = 'delete_time';

    /** 状态：禁用 */
    public const STATUS_DISABLED = 0;
    /** 状态：启用 */
    public const STATUS_ENABLED = 1;

    /** 表名 */
    protected $name = 'supplier';

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

    /**
     * 按用户所属供应商（pf_user.dept）查询档案
     * @param User $user
     * @return Supplier|null
     */
    public static function findByUser(User $user): ?self
    {
        $dept = trim((string) $user->dept);

        if ($dept === '') {
            return null;
        }

        return self::where('name', $dept)->find();
    }
}
