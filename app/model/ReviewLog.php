<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;
use think\model\relation\BelongsTo;

/**
 * 评审与审计日志模型
 * @property int    $id
 * @property int    $proposal_id
 * @property int    $user_id
 * @property string $action
 * @property string $action_name
 * @property int    $version
 * @property string $content
 */
class ReviewLog extends Model
{
    /** 表名 */
    protected $name = 'review_log';

    /** 类型转换 */
    protected $type = [
        'proposal_id' => 'integer',
        'user_id'     => 'integer',
        'from_status' => 'integer',
        'to_status'   => 'integer',
        'version'     => 'integer',
    ];

    /**
     * 关联：操作人
     * @return BelongsTo
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->field('id,username,real_name,role,status');
    }

    /**
     * 关联：所属方案
     * @return BelongsTo
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(PackProposal::class, 'proposal_id')->field('id,proposal_no,part_name');
    }
}
