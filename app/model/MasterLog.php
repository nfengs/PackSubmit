<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;
use think\model\relation\BelongsTo;

/**
 * 主数据（客户 / 供应商）维护日志模型
 * @property int    $id
 * @property string $biz_type
 * @property int    $target_id
 * @property string $target_name
 * @property int    $user_id
 * @property string $action
 * @property string $action_name
 * @property string $content
 * @property string $create_time
 */
class MasterLog extends Model
{
    /** 业务类型：客户 */
    public const BIZ_CUSTOMER = 'customer';
    /** 业务类型：供应商 */
    public const BIZ_SUPPLIER = 'supplier';
    /** 业务类型：用户 */
    public const BIZ_USER = 'user';

    /** 表名 */
    protected $name = 'master_log';

    /** 类型转换 */
    protected $type = [
        'target_id' => 'integer',
        'user_id'   => 'integer',
    ];

    /**
     * 关联：操作人
     * @return BelongsTo
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->field('id,username,real_name,role,status');
    }
}
