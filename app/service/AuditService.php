<?php
declare (strict_types = 1);

namespace app\service;

use app\model\ReviewLog;

/**
 * 审计日志服务
 * 所有写操作统一通过该服务落库到 pf_review_log
 */
class AuditService
{
    /** 动作名称映射 */
    public const ACTION_TEXT = [
        'create'          => '新增方案',
        'update'          => '编辑方案',
        'submit'          => '提交评审',
        'withdraw'        => '撤回提交',
        'review_pass'     => '评审通过',
        'review_reject'   => '评审驳回',
        'archive'         => '方案归档',
        'change'          => '发起变更',
        'obsolete'        => '变更作废原方案',
        'delete'          => '删除方案',
        'upload'          => '上传附件',
        'delete_attachment' => '删除附件',
        'login'           => '登录系统',
        'logout'          => '退出登录',
        'password_change' => '修改密码',
        'user_create'     => '新增用户',
        'user_update'     => '编辑用户',
        'user_status'     => '启用/禁用用户',
        'user_reset'      => '重置密码',
        'dict_create'     => '新增字典',
        'dict_update'     => '编辑字典',
        'dict_delete'     => '删除字典',
        'customer_create' => '新增客户',
        'customer_update' => '编辑客户',
        'customer_delete' => '删除客户',
        'supplier_create' => '新增供应商',
        'supplier_update' => '编辑供应商',
        'supplier_delete' => '删除供应商',
        'part_import'     => '导入指定零件号',
        'part_update'     => '修改指定零件号',
        'part_delete'     => '删除指定零件号',
    ];

    /**
     * 写入一条审计日志
     * @param int    $userId     操作人ID
     * @param string $action     动作标识
     * @param int    $proposalId 方案ID，非方案操作传 0
     * @param array  $extra      附加字段：from_status/to_status/version/content
     * @return ReviewLog
     */
    public static function record(int $userId, string $action, int $proposalId = 0, array $extra = []): ReviewLog
    {
        return ReviewLog::create([
            'proposal_id' => $proposalId,
            'user_id'     => $userId,
            'action'      => $action,
            'action_name' => self::ACTION_TEXT[$action] ?? $action,
            'from_status' => $extra['from_status'] ?? null,
            'to_status'   => $extra['to_status'] ?? null,
            'version'     => (int) ($extra['version'] ?? 1),
            'content'     => mb_substr((string) ($extra['content'] ?? ''), 0, 1000),
            'ip'          => request()->ip(),
        ]);
    }
}
