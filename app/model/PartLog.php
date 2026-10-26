<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;
use think\model\relation\BelongsTo;

/**
 * 指定零件号修改日志模型
 * @property int    $id
 * @property string $supplier_name
 * @property string $part_no
 * @property int    $user_id
 * @property string $action
 * @property string $action_name
 * @property string $content
 * @property string $create_time
 */
class PartLog extends Model
{
    /** 表名 */
    protected $name = 'part_log';

    /** 类型转换 */
    protected $type = [
        'user_id' => 'integer',
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
