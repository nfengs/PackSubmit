<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;
use think\model\relation\BelongsTo;
use think\model\relation\HasMany;

/**
 * 包装方案模型
 * @property int    $id
 * @property string $proposal_no
 * @property string $customer
 * @property string $part_name
 * @property int    $version
 * @property int    $status
 * @property string $supplier 所属供应商名称，方案归属以此为准（不归属个人）
 * @property int    $user_id  创建人ID，仅用于留痕，不参与归属/权限判断
 * @property int    $source_proposal_id 变更来源方案ID，0 表示非变更方案
 */
class PackProposal extends Model
{
    use SoftDelete;

    /** 状态：草稿 */
    public const STATUS_DRAFT = 0;
    /** 状态：待评审 */
    public const STATUS_PENDING = 1;
    /** 状态：评审通过 */
    public const STATUS_APPROVED = 2;
    /** 状态：驳回 */
    public const STATUS_REJECTED = 3;
    /** 状态：已归档 */
    public const STATUS_ARCHIVED = 4;
    /** 状态：已归档-已变更-作废 */
    public const STATUS_ARCHIVED_OBSOLETE = 5;

    /** 未归档（生效中）的状态：同一供应商同一零件同时只允许一条 */
    public const ACTIVE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    /** 状态文字 */
    public const STATUS_TEXT = [
        self::STATUS_DRAFT             => '草稿',
        self::STATUS_PENDING           => '待评审',
        self::STATUS_APPROVED          => '评审通过',
        self::STATUS_REJECTED          => '驳回',
        self::STATUS_ARCHIVED          => '已归档',
        self::STATUS_ARCHIVED_OBSOLETE => '已归档-已变更-作废',
    ];

    /** 包装箱材料 */
    public const PACK_MATERIAL_TEXT = [
        'plastic' => '塑料',
        'metal'   => '金属',
        'carton'  => '纸箱',
        'other'   => '其他',
    ];

    /** 表名 */
    protected $name = 'pack_proposal';

    /** 软删除字段 */
    protected $deleteTime = 'delete_time';

    /** 类型转换 */
    protected $type = [
        'status'    => 'integer',
        'version'   => 'integer',
        'source_proposal_id' => 'integer',
        'quantity'  => 'integer',
        'user_id'   => 'integer',
        'packing_qty'         => 'integer',
        'pallet_layer_qty'    => 'integer',
        'pallet_layer_count'  => 'integer',
        'pallet_parts_qty'    => 'integer',
        'returnable'          => 'integer',
        'dunnage_returnable'  => 'integer',
        'with_pallet'         => 'integer',
        'part_weight'  => 'float',
        'gross_weight' => 'float',
        'part_size_l'  => 'float',
        'part_size_w'  => 'float',
        'part_size_h'  => 'float',
        'box_size_l'   => 'float',
        'box_size_w'   => 'float',
        'box_size_h'   => 'float',
        'pallet_size_l' => 'float',
        'pallet_size_w' => 'float',
        'pallet_size_h' => 'float',
    ];

    /**
     * 包装箱材料文字
     * @param string|null $value
     * @return string
     */
    public static function packMaterialLabel(?string $value): string
    {
        return self::PACK_MATERIAL_TEXT[(string) $value] ?? '-';
    }

    /** 输出追加字段 */
    protected $append = ['status_text'];

    /**
     * 状态文字
     * @param mixed $value
     * @param array $data
     * @return string
     */
    public function getStatusTextAttr($value, array $data): string
    {
        return self::getStatusLabel((int) ($data['status'] ?? 0));
    }

    /**
     * 状态码转文字
     * @param int $status
     * @return string
     */
    public static function getStatusLabel(int $status): string
    {
        return self::STATUS_TEXT[$status] ?? '未知';
    }

    /**
     * 关联：供应商
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->field('id,username,real_name,dept,role,status');
    }

    /**
     * 关联：最近评审人
     * @return BelongsTo
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id')->field('id,username,real_name,role,status');
    }

    /**
     * 关联：附件
     * @return HasMany
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'proposal_id');
    }

    /**
     * 关联：评审/审计日志
     * @return HasMany
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ReviewLog::class, 'proposal_id')->order('id', 'desc');
    }
}
