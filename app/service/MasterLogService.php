<?php
declare (strict_types = 1);

namespace app\service;

use app\model\MasterLog;
use app\model\User;

/**
 * 主数据（客户 / 供应商）维护日志服务
 * 首次录入与后续修改都写日志，供维护页面按行查询
 */
class MasterLogService
{
    /** 动作名称 */
    public const ACTION_TEXT = [
        'customer_create' => '新增客户',
        'customer_update' => '修改客户',
        'supplier_create' => '新增供应商',
        'supplier_update' => '修改供应商',
        'user_create'     => '新增用户',
        'user_update'     => '修改用户',
        'user_status'     => '启用/禁用用户',
        'user_reset'      => '重置密码',
    ];

    /** 字段名称映射 */
    public const FIELD_LABELS = [
        MasterLog::BIZ_CUSTOMER => [
            'name'    => '客户名称',
            'address' => '客户地址',
            'contact' => '联系人',
            'phone'   => '联系电话',
            'email'   => '电子邮箱',
            'fax'     => '传真',
            'status'  => '状态',
        ],
        MasterLog::BIZ_SUPPLIER => [
            'name'    => '供应商名称',
            'code'    => '供应商代码',
            'address' => '供应商地址',
            'status'  => '状态',
        ],
        MasterLog::BIZ_USER => [
            'username'  => '登录账号',
            'real_name' => '姓名',
            'role'      => '角色',
            'dept'      => '部门/供应商',
            'phone'     => '联系电话',
            'status'    => '状态',
        ],
    ];

    /**
     * 写入一条维护日志
     * @param string $bizType    业务类型 customer / supplier
     * @param int    $targetId   客户/供应商ID
     * @param string $targetName 名称快照
     * @param int    $userId     操作人ID
     * @param string $action     动作标识
     * @param string $content    变更内容
     * @return MasterLog
     */
    public static function record(
        string $bizType,
        int $targetId,
        string $targetName,
        int $userId,
        string $action,
        string $content
    ): MasterLog {
        return MasterLog::create([
            'biz_type'    => $bizType,
            'target_id'   => $targetId,
            'target_name' => $targetName,
            'user_id'     => $userId,
            'action'      => $action,
            'action_name' => self::ACTION_TEXT[$action] ?? $action,
            'content'     => mb_substr($content, 0, 1000),
        ]);
    }

    /**
     * 首次录入内容
     * @param string $bizType
     * @param array  $data
     * @return string
     */
    public static function createContent(string $bizType, array $data): string
    {
        $labels = self::FIELD_LABELS[$bizType] ?? [];
        $parts  = [];

        foreach ($labels as $field => $label) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $parts[] = $label . '：' . self::display($field, $data[$field]);
        }

        return '首次录入：' . implode('；', $parts);
    }

    /**
     * 修改内容（字段：旧值 → 新值）
     * @param string $bizType
     * @param array  $before 修改前数据
     * @param array  $after  修改后数据
     * @return array<int, string>
     */
    public static function changes(string $bizType, array $before, array $after): array
    {
        $labels  = self::FIELD_LABELS[$bizType] ?? [];
        $changes = [];

        foreach ($labels as $field => $label) {
            if (!array_key_exists($field, $after)) {
                continue;
            }

            $old = $before[$field] ?? '';
            $new = $after[$field];

            if ((string) $old === (string) $new) {
                continue;
            }

            $changes[] = $label . '：' . self::display($field, $old)
                . ' → ' . self::display($field, $new);
        }

        return $changes;
    }

    /**
     * 展示值（状态转文字、空值展示为空）
     * @param string $field
     * @param mixed  $value
     * @return string
     */
    protected static function display(string $field, $value): string
    {
        if ($field === 'status') {
            return (int) $value === User::STATUS_ENABLED ? '启用' : '禁用';
        }

        if ($field === 'role') {
            return User::ROLE_TEXT[(int) $value] ?? '未知';
        }

        $value = trim((string) $value);

        return $value === '' ? '（空）' : $value;
    }
}
