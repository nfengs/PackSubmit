<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;
use think\model\relation\HasMany;

/**
 * 用户模型
 * @property int    $id
 * @property string $username
 * @property string $password
 * @property string $real_name
 * @property int    $role
 * @property string $dept
 * @property string $phone
 * @property int    $status
 * @property int    $token_version
 */
class User extends Model
{
    /** 角色：供应商 */
    public const ROLE_SUBMITTER = 1;
    /** 角色：评审员 */
    public const ROLE_REVIEWER = 2;
    /** 角色：管理员 */
    public const ROLE_ADMIN = 3;
    /** 角色：查看者（内部只读） */
    public const ROLE_VIEWER = 4;

    /** 状态：禁用 */
    public const STATUS_DISABLED = 0;
    /** 状态：启用 */
    public const STATUS_ENABLED = 1;

    /** 角色名称映射 */
    public const ROLE_TEXT = [
        self::ROLE_SUBMITTER => '供应商',
        self::ROLE_REVIEWER  => '评审员',
        self::ROLE_ADMIN     => '管理员',
        self::ROLE_VIEWER    => '查看者',
    ];

    /** 表名 */
    protected $name = 'user';

    /** 隐藏字段 */
    protected $hidden = ['password'];

    /** 类型转换 */
    protected $type = [
        'role'          => 'integer',
        'status'        => 'integer',
        'token_version' => 'integer',
    ];

    /** 输出追加字段 */
    protected $append = ['role_name', 'status_text'];

    /**
     * 角色名称
     * @param mixed $value
     * @param array $data
     * @return string
     */
    public function getRoleNameAttr($value, array $data): string
    {
        return self::ROLE_TEXT[(int) ($data['role'] ?? 0)] ?? '未知';
    }

    /**
     * 状态文字
     * @param mixed $value
     * @param array $data
     * @return string
     */
    public function getStatusTextAttr($value, array $data): string
    {
        return (int) ($data['status'] ?? 0) === self::STATUS_ENABLED ? '启用' : '禁用';
    }

    /**
     * 密码自动哈希
     * @param mixed $value
     * @return string
     */
    public function setPasswordAttr($value): string
    {
        return password_hash((string) $value, PASSWORD_DEFAULT);
    }

    /**
     * 校验密码
     * @param string $password 明文密码
     * @return bool
     */
    public function checkPassword(string $password): bool
    {
        return password_verify($password, (string) $this->getData('password'));
    }

    /**
     * 关联：本人创建（提交）的方案
     * 方案归属供应商，此关联仅用于留痕查询，不代表归属
     * @return HasMany
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(PackProposal::class, 'user_id');
    }
}
