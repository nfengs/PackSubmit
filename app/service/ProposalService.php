<?php
declare (strict_types = 1);

namespace app\service;

use app\exception\BizException;
use app\model\Attachment;
use app\model\PackProposal;
use app\model\Supplier;
use app\model\SupplierPart;
use app\model\User;
use think\facade\Db;

/**
 * 包装方案业务服务
 * 涉及多表写入的操作全部使用数据库事务
 */
class ProposalService
{
    /** 可写入方案的字段白名单 */
    public const EDITABLE_FIELDS = [
        'customer', 'spec',
        'quantity', 'supplier', 'contact', 'phone', 'description',
        // 包装确认单：基础信息
        'write_date', 'project_model', 'part_name', 'part_no', 'part_material',
        // 客户资料
        'customer_address', 'customer_contact', 'customer_phone', 'customer_email', 'customer_fax',
        // 供应商资料
        'supplier_code', 'supplier_address', 'supplier_email', 'supplier_fax',
        // 包装信息
        'pack_box_no', 'pack_material',
        'part_weight', 'part_size_l', 'part_size_w', 'part_size_h',
        'box_size_l', 'box_size_w', 'box_size_h', 'gross_weight',
        'returnable', 'dunnage_returnable', 'packing_qty',
        // 托盘信息
        'with_pallet',
        'pallet_size_l', 'pallet_size_w', 'pallet_size_h',
        'pallet_layer_qty', 'pallet_layer_count', 'pallet_parts_qty',
        // 说明
        'packaging_instruction',
    ];

    /** 提交评审必填的文本字段（操作说明不在此列，允许为空） */
    public const SUBMIT_REQUIRED_FIELDS = [
        'write_date'       => '填写日期',
        'customer'         => '客户名称',
        'customer_address' => '客户地址',
        'customer_contact' => '客户联系人',
        'customer_phone'   => '客户电话',
        'customer_email'   => '客户邮箱',
        'customer_fax'     => '客户传真',
        'supplier'         => '供应商名称',
        'supplier_code'    => '供应商代码',
        'supplier_address' => '供应商地址',
        'contact'          => '供应商联系人',
        'phone'            => '供应商电话',
        'supplier_email'   => '供应商邮箱',
        'supplier_fax'     => '供应商传真',
        'part_no'          => '零件号',
        'part_name'        => '零件名称',
        'project_model'    => '项目车型',
        'part_material'    => '零件材料',
        'pack_box_no'      => '包装箱编号',
        'pack_material'    => '包装箱材料',
    ];

    /** 提交评审必须大于 0 的数值字段 */
    public const SUBMIT_POSITIVE_FIELDS = [
        'part_weight'  => '产品净重',
        'packing_qty'  => '存放数量',
        'gross_weight' => '毛重',
    ];

    /** 提交评审必须大于 0 的尺寸字段（长宽高缺一不可） */
    public const SUBMIT_SIZE_FIELDS = [
        '产品外形尺寸' => ['part_size_l', 'part_size_w', 'part_size_h'],
        '包装箱外尺寸' => ['box_size_l', 'box_size_w', 'box_size_h'],
    ];

    /** 带托盘交付时必填的托盘字段 */
    public const SUBMIT_PALLET_REQUIRED = [
        'pallet_size_l'     => '满托盘外尺寸',
        'pallet_size_w'     => '满托盘外尺寸',
        'pallet_size_h'     => '满托盘外尺寸',
        'pallet_layer_qty'  => '每层包装箱数量',
        'pallet_layer_count' => '堆码层数',
        'pallet_parts_qty'  => '每托盘零件数量',
    ];

    /**
     * 提交评审前校验：必填项（操作说明除外）+ 四类包装图片
     * @param PackProposal $proposal
     * @throws BizException
     */
    public static function assertSubmitReady(PackProposal $proposal): void
    {
        $missing = [];

        foreach (self::SUBMIT_REQUIRED_FIELDS as $field => $label) {
            if (trim((string) $proposal->getData($field)) === '') {
                $missing[] = $label;
            }
        }

        foreach (self::SUBMIT_POSITIVE_FIELDS as $field => $label) {
            if ((float) $proposal->getData($field) <= 0) {
                $missing[] = $label;
            }
        }

        foreach (self::SUBMIT_SIZE_FIELDS as $label => $fields) {
            foreach ($fields as $field) {
                if ((float) $proposal->getData($field) <= 0) {
                    $missing[] = $label;
                    break;
                }
            }
        }

        if ((int) $proposal->getData('with_pallet') === 1) {
            foreach (self::SUBMIT_PALLET_REQUIRED as $field => $label) {
                if ((float) $proposal->getData($field) <= 0) {
                    $missing[] = $label;
                }
            }
        }

        // 是/否单选项必须做出选择（允许 0，只拦未选择）
        foreach ([
            'returnable'         => '包装箱是否返回',
            'dunnage_returnable' => '内衬是否返回',
            'with_pallet'        => '是否带托盘交付',
        ] as $field => $label) {
            $value = $proposal->getData($field);

            if ($value === null || $value === '') {
                $missing[] = $label;
            }
        }

        $missing = array_values(array_unique($missing));

        // 毛重必须大于 单件净重 × 存放件数
        $gross    = (float) $proposal->getData('gross_weight');
        $minGross = (float) $proposal->getData('part_weight') * (int) $proposal->getData('packing_qty');
        $grossProblem = '';

        if ($gross > 0 && $minGross > 0 && $gross <= $minGross) {
            $grossProblem = '毛重必须大于单件净重 × 存放件数（即大于 ' . self::trimNumber($minGross) . ' kg）';
        }

        $missingImages = [];

        foreach (Attachment::IMAGE_TYPES as $type) {
            $count = Attachment::where('proposal_id', (int) $proposal->id)
                ->where('attach_type', $type)
                ->count();

            if (!$count) {
                $missingImages[] = Attachment::TYPE_TEXT[$type];
            }
        }

        $problems = [];

        if ($missing !== []) {
            $problems[] = '以下必填项未填写：' . implode('、', $missing);
        }

        if ($missingImages !== []) {
            $problems[] = '以下图片未上传：' . implode('、', $missingImages);
        }

        if ($grossProblem !== '') {
            $problems[] = $grossProblem;
        }

        if ($problems !== []) {
            throw new BizException(implode('；', $problems));
        }
    }

    /**
     * 数字去除多余的小数位 0（如 3.000 → 3、3.500 → 3.5）
     * @param float $number
     * @return string
     */
    private static function trimNumber(float $number): string
    {
        $formatted = number_format($number, 3, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    /**
     * 生成方案编号：PS + 年月日 + 4位流水
     * @return string
     */
    public static function generateNo(): string
    {
        $prefix = 'PS' . date('Ymd');
        // 取当日最后一条编号（包含软删除数据），序号 +1
        $last = PackProposal::withTrashed()
            ->whereLike('proposal_no', $prefix . '%')
            ->order('id', 'desc')
            ->value('proposal_no');

        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 新增方案
     * @param array $data 已过滤的表单数据
     * @param User  $user 提交人
     * @return PackProposal
     * @throws \Throwable
     */
    public static function create(array $data, User $user): PackProposal
    {
        $data = self::applySupplierScope($data, $user);
        $data = self::applyPartScope($data, $user);

        return Db::transaction(function () use ($data, $user) {
            // 同一供应商同一零件，未归档期间只允许存在一种方案
            self::assertPartUnique($data);

            $proposal = PackProposal::create(array_merge(
                self::filterFields($data),
                [
                    'proposal_no' => self::generateNo(),
                    'version'     => 1,
                    'status'      => PackProposal::STATUS_DRAFT,
                    'user_id'     => $user->id,
                ]
            ));

            AuditService::record((int) $user->id, 'create', (int) $proposal->id, [
                'to_status' => PackProposal::STATUS_DRAFT,
                'version'   => 1,
                'content'   => '创建方案：' . self::displayName($proposal),
            ]);

            return $proposal;
        });
    }

    /**
     * 是否存在该供应商 + 零件的有效方案（不含「已归档-已变更-作废」）
     * @param string $supplier
     * @param string $partNo
     * @return bool
     */
    public static function hasPartProposal(string $supplier, string $partNo): bool
    {
        $supplier = trim($supplier);
        $partNo   = trim($partNo);

        if ($supplier === '' || $partNo === '') {
            return false;
        }

        return PackProposal::where('supplier', $supplier)
            ->where('part_no', $partNo)
            ->where('status', '<>', PackProposal::STATUS_ARCHIVED_OBSOLETE)
            ->count() > 0;
    }

    /**
     * 零件主数据（项目名称/零件名称）变更后，同步更新所有关联方案
     * 包含已归档、审批中、已作废等全部状态
     * @param string $supplier    供应商名称
     * @param string $partNo      零件号
     * @param string $projectName 项目名称（方案字段 project_model）
     * @param string $partName    零件名称
     * @return int 同步的方案条数
     */
    public static function syncPartInfo(string $supplier, string $partNo, string $projectName, string $partName): int
    {
        $supplier = trim($supplier);
        $partNo   = trim($partNo);

        if ($supplier === '' || $partNo === '') {
            return 0;
        }

        return PackProposal::where('supplier', $supplier)
            ->where('part_no', $partNo)
            ->where(function ($query) use ($projectName, $partName) {
                $query->where('project_model', '<>', $projectName)
                    ->whereOr('part_name', '<>', $partName);
            })
            ->update([
                'project_model' => $projectName,
                'part_name'     => $partName,
                'update_time'   => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 管理员导入指定零件号时，直接生成方案草稿
     * @param SupplierPart $part     指定零件号
     * @param User         $owner    方案归属的供应商提交人
     * @param User         $operator 操作人（管理员）
     * @return PackProposal|null 已存在有效方案时返回 null
     * @throws \Throwable
     */
    public static function createDraftFromPart(SupplierPart $part, User $owner, User $operator): ?PackProposal
    {
        $supplier = trim((string) $part->supplier_name);
        $partNo   = trim((string) $part->part_no);

        if ($supplier === '' || $partNo === '') {
            return null;
        }

        if (self::hasPartProposal($supplier, $partNo)) {
            return null;
        }

        $archive = Supplier::where('name', $supplier)->find();

        $data = [
            // 填写日期默认当天，其余资料由供应商后续补全
            'write_date'       => date('Y-m-d'),
            'supplier'         => $supplier,
            'supplier_code'    => (string) ($archive?->code ?? ''),
            'supplier_address' => (string) ($archive?->address ?? ''),
            'contact'          => (string) ($archive?->contact ?? ''),
            'phone'            => (string) ($archive?->phone ?? ''),
            'supplier_email'   => (string) ($archive?->email ?? ''),
            'supplier_fax'     => (string) ($archive?->fax ?? ''),
            'part_no'          => $partNo,
            'part_name'        => (string) $part->part_name,
            'project_model'    => (string) $part->project_name,
        ];

        return Db::transaction(function () use ($data, $owner, $operator, $supplier, $partNo) {
            $proposal = PackProposal::create(array_merge(self::filterFields($data), [
                'proposal_no' => self::generateNo(),
                'version'     => 1,
                'status'      => PackProposal::STATUS_DRAFT,
                'user_id'     => (int) $owner->id,
            ]));

            AuditService::record((int) $operator->id, 'create', (int) $proposal->id, [
                'to_status' => PackProposal::STATUS_DRAFT,
                'version'   => 1,
                'content'   => '导入零件号自动生成方案草稿：' . $supplier . ' / ' . $partNo,
            ]);

            return $proposal;
        });
    }

    /**
     * 同供应商 + 同零件唯一性校验
     *   - 未归档（草稿/待评审/评审通过/驳回）同时只允许一条
     *   - 已归档方案存在时，新增需走「发起变更」
     * @param array $data          含 supplier / part_no 的数据
     * @param bool  $allowArchived 允许已归档方案共存（发起变更时为 true）
     * @param int   $excludeId     需要排除的方案ID（编辑自身时使用）
     * @return void
     * @throws BizException
     */
    private static function assertPartUnique(array $data, bool $allowArchived = false, int $excludeId = 0): void
    {
        $supplier = trim((string) ($data['supplier'] ?? ''));
        $partNo   = trim((string) ($data['part_no'] ?? ''));

        if ($supplier === '' || $partNo === '') {
            return;
        }

        $query = PackProposal::where('supplier', $supplier)->where('part_no', $partNo);

        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }

        $existing = $query->order('id')->select();

        if ($existing->isEmpty()) {
            return;
        }

        $active = $existing->first(
            fn ($item) => in_array((int) $item->status, PackProposal::ACTIVE_STATUSES, true)
        );

        if ($active) {
            throw new BizException(
                '该供应商的零件「' . $partNo . '」已有未归档方案（'
                . $active->proposal_no . '，' . PackProposal::getStatusLabel((int) $active->status)
                . '），未归档前只能存在一种方案'
            );
        }

        if ($allowArchived) {
            return;
        }

        $archived = $existing->first(
            fn ($item) => (int) $item->status === PackProposal::STATUS_ARCHIVED
        );

        if ($archived) {
            throw new BizException(
                '该供应商的零件「' . $partNo . '」已有归档方案（'
                . $archived->proposal_no . '），请从归档方案发起变更'
            );
        }
    }

    /**
     * 复制方案（兼容旧入口）：等价于对已归档方案发起变更
     * @param PackProposal $source
     * @param User         $user
     * @return PackProposal
     * @throws BizException
     * @throws \Throwable
     */
    public static function copy(PackProposal $source, User $user): PackProposal
    {
        return self::change($source, $user);
    }

    /**
     * 发起变更：以已归档方案为源，新建一份变更草稿（版本 +1、复制附件）
     * 变更审批中允许旧归档方案与新变更方案同时存在
     * @param PackProposal $source
     * @param User         $user
     * @return PackProposal
     * @throws BizException
     * @throws \Throwable
     */
    public static function change(PackProposal $source, User $user): PackProposal
    {
        if ((int) $source->status !== PackProposal::STATUS_ARCHIVED) {
            throw new BizException('仅已归档的方案可以发起变更');
        }

        if ((int) $user->role === User::ROLE_REVIEWER) {
            throw new BizException('评审员不能发起变更', 403, 403);
        }

        if ((int) $user->role !== User::ROLE_ADMIN && (int) $source->user_id !== (int) $user->id) {
            throw new BizException('只能对自己的方案发起变更', 403, 403);
        }

        return Db::transaction(function () use ($source, $user) {
            // 同一来源方案同时只允许存在一条变更方案
            $changing = PackProposal::where('source_proposal_id', (int) $source->id)
                ->whereIn('status', PackProposal::ACTIVE_STATUSES)
                ->find();

            if ($changing) {
                throw new BizException(
                    '该方案已有变更在审批中（' . $changing->proposal_no . '），请勿重复发起'
                );
            }

            $data = [];

            foreach (self::EDITABLE_FIELDS as $field) {
                $data[$field] = $source->getAttr($field);
            }

            $data = self::applySupplierScope($data, $user);

            // 跨供应商复制时，供应商资料以当前用户所属供应商档案为准
            if ((int) $user->role === User::ROLE_SUBMITTER && (string) $source->supplier !== (string) $data['supplier']) {
                $own = Supplier::findByUser($user);

                if ($own) {
                    $data['supplier_code']    = $own->code ?? '';
                    $data['supplier_address'] = $own->address ?? '';
                    $data['contact']          = $own->contact ?? '';
                    $data['phone']            = $own->phone ?? '';
                    $data['supplier_email']   = $own->email ?? '';
                    $data['supplier_fax']     = $own->fax ?? '';
                }
            }

            $data = self::applyPartScope($data, $user, $source);

            // 变更方案与已归档的源方案共存，其余未归档方案仍不允许存在
            self::assertPartUnique($data, true);

            $version = (int) $source->version + 1;

            // 变更方案沿用原方案的归属人（管理员代发起时也保持提交人不变）
            $ownerId = (int) $source->user_id > 0 ? (int) $source->user_id : (int) $user->id;

            $proposal = PackProposal::create(array_merge($data, [
                'proposal_no'        => self::generateNo(),
                'version'            => $version,
                'status'             => PackProposal::STATUS_DRAFT,
                'user_id'            => $ownerId,
                'source_proposal_id' => (int) $source->id,
                'reviewer_id'        => null,
                'review_remark'      => '',
                'submit_time'        => null,
                'review_time'        => null,
                'archive_time'       => null,
            ]));

            self::copyAttachments($source, $proposal, $user);

            AuditService::record((int) $user->id, 'change', (int) $proposal->id, [
                'to_status' => PackProposal::STATUS_DRAFT,
                'version'   => $version,
                'content'   => '发起变更：' . self::displayName($proposal)
                    . '（源 ' . $source->proposal_no . ' V' . (int) $source->version . '）',
            ]);

            // 源方案留痕：便于在旧方案的评审日志中追溯变更
            AuditService::record((int) $user->id, 'change', (int) $source->id, [
                'from_status' => PackProposal::STATUS_ARCHIVED,
                'to_status'   => PackProposal::STATUS_ARCHIVED,
                'version'     => (int) $source->version,
                'content'     => '发起变更：新方案 ' . $proposal->proposal_no,
            ]);

            return $proposal;
        });
    }

    /**
     * 复制源方案的附件（图片等物理文件同步复制一份）
     * @param PackProposal $source
     * @param PackProposal $target
     * @param User         $user
     * @return void
     */
    private static function copyAttachments(PackProposal $source, PackProposal $target, User $user): void
    {
        $attachments = Attachment::where('proposal_id', (int) $source->id)->order('id')->select();

        if ($attachments->isEmpty()) {
            return;
        }

        $relativeDir = 'storage/attachments/' . date('Ym');
        $saveDir     = public_path() . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

        if (!is_dir($saveDir) && !mkdir($saveDir, 0755, true) && !is_dir($saveDir)) {
            return;
        }

        foreach ($attachments as $item) {
            $srcPath = public_path() . str_replace('/', DIRECTORY_SEPARATOR, (string) $item->file_path);

            if (!is_file($srcPath)) {
                continue;
            }

            $ext = strtolower((string) $item->file_ext);

            if ($ext === '') {
                $ext = strtolower((string) pathinfo((string) $item->file_name, PATHINFO_EXTENSION));
            }

            $fileName = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $savePath = $saveDir . DIRECTORY_SEPARATOR . $fileName;

            if (!@copy($srcPath, $savePath)) {
                continue;
            }

            Attachment::create([
                'proposal_id' => (int) $target->id,
                'attach_type' => (string) $item->attach_type,
                'user_id'     => (int) $user->id,
                'file_name'   => (string) $item->file_name,
                'file_path'   => $relativeDir . '/' . $fileName,
                'file_ext'    => $ext,
                'file_size'   => (int) filesize($savePath),
                'mime_type'   => (string) $item->mime_type,
            ]);
        }
    }

    /**
     * 编辑方案（仅草稿/驳回状态，且本人或管理员）
     * @param PackProposal $proposal
     * @param array        $data
     * @param User         $user
     * @return PackProposal
     * @throws BizException
     * @throws \Throwable
     */
    public static function update(PackProposal $proposal, array $data, User $user): PackProposal
    {
        self::assertEditable($proposal, $user);
        $data = self::applySupplierScope($data, $user);
        $data = self::applyPartScope($data, $user, $proposal);

        // 仅当供应商/零件号发生变化时校验唯一性，避免历史重复数据无法编辑
        $supplierChanged = array_key_exists('supplier', $data)
            && trim((string) $data['supplier']) !== trim((string) $proposal->supplier);
        $partChanged = array_key_exists('part_no', $data)
            && trim((string) $data['part_no']) !== trim((string) $proposal->part_no);

        if ($supplierChanged || $partChanged) {
            self::assertPartUnique([
                'supplier' => $data['supplier'] ?? $proposal->supplier,
                'part_no'  => $data['part_no'] ?? $proposal->part_no,
            ], false, (int) $proposal->id);
        }

        return Db::transaction(function () use ($proposal, $data, $user) {
            $proposal->save(self::filterFields($data));

            AuditService::record((int) $user->id, 'update', (int) $proposal->id, [
                'from_status' => (int) $proposal->status,
                'to_status'   => (int) $proposal->status,
                'version'     => (int) $proposal->version,
                'content'     => '编辑方案：' . self::displayName($proposal),
            ]);

            return $proposal;
        });
    }

    /**
     * 提交评审：草稿 -> 待评审；驳回 -> 待评审且版本号 +1
     * @param PackProposal $proposal
     * @param User         $user
     * @return PackProposal
     * @throws BizException
     * @throws \Throwable
     */
    public static function submit(PackProposal $proposal, User $user): PackProposal
    {
        $fromStatus = (int) $proposal->status;

        if (!in_array($fromStatus, [PackProposal::STATUS_DRAFT, PackProposal::STATUS_REJECTED], true)) {
            throw new BizException('仅草稿或驳回状态的方案可以提交评审');
        }

        if ((int) $user->role !== User::ROLE_ADMIN && (int) $proposal->user_id !== (int) $user->id) {
            throw new BizException('只能提交自己的方案', 403, 403);
        }

        self::assertSubmitReady($proposal);

        return Db::transaction(function () use ($proposal, $user, $fromStatus) {
            // 驳回后重新提交，版本号自动 +1
            $version = $fromStatus === PackProposal::STATUS_REJECTED
                ? (int) $proposal->version + 1
                : (int) $proposal->version;

            $proposal->save([
                'status'      => PackProposal::STATUS_PENDING,
                'version'     => $version,
                'submit_time' => date('Y-m-d H:i:s'),
            ]);

            AuditService::record((int) $user->id, 'submit', (int) $proposal->id, [
                'from_status' => $fromStatus,
                'to_status'   => PackProposal::STATUS_PENDING,
                'version'     => $version,
                'content'     => '第 ' . $version . ' 版提交评审',
            ]);

            return $proposal;
        });
    }

    /**
     * 撤回提交：待评审 -> 草稿
     * @param PackProposal $proposal
     * @param User         $user
     * @return PackProposal
     * @throws BizException
     * @throws \Throwable
     */
    public static function withdraw(PackProposal $proposal, User $user): PackProposal
    {
        if ((int) $proposal->status !== PackProposal::STATUS_PENDING) {
            throw new BizException('仅待评审状态的方案可以撤回');
        }

        if ((int) $user->role !== User::ROLE_ADMIN && (int) $proposal->user_id !== (int) $user->id) {
            throw new BizException('只能撤回自己提交的方案', 403, 403);
        }

        if (!empty($proposal->review_time)) {
            throw new BizException('方案已完成评审，无法撤回');
        }

        return Db::transaction(function () use ($proposal, $user) {
            $version = (int) $proposal->version;

            $proposal->save([
                'status'      => PackProposal::STATUS_DRAFT,
                'submit_time' => null,
            ]);

            AuditService::record((int) $user->id, 'withdraw', (int) $proposal->id, [
                'from_status' => PackProposal::STATUS_PENDING,
                'to_status'   => PackProposal::STATUS_DRAFT,
                'version'     => $version,
                'content'     => '撤回提交，退回草稿',
            ]);

            return $proposal;
        });
    }

    /**
     * 评审：待评审 -> 通过/驳回
     * @param PackProposal $proposal
     * @param int          $status   2 通过 / 3 驳回
     * @param string       $remark   评审意见
     * @param User         $reviewer 评审人
     * @return PackProposal
     * @throws BizException
     * @throws \Throwable
     */
    public static function review(PackProposal $proposal, int $status, string $remark, User $reviewer): PackProposal
    {
        if ((int) $proposal->status !== PackProposal::STATUS_PENDING) {
            throw new BizException('仅待评审状态的方案可以评审');
        }

        if (!in_array($status, [PackProposal::STATUS_APPROVED, PackProposal::STATUS_REJECTED], true)) {
            throw new BizException('评审结果不合法');
        }

        if ($status === PackProposal::STATUS_REJECTED && $remark === '') {
            throw new BizException('驳回时必须填写评审意见');
        }

        return Db::transaction(function () use ($proposal, $status, $remark, $reviewer) {
            $proposal->save([
                'status'        => $status,
                'reviewer_id'   => (int) $reviewer->id,
                'review_remark' => mb_substr($remark, 0, 500),
                'review_time'   => date('Y-m-d H:i:s'),
            ]);

            $action = $status === PackProposal::STATUS_APPROVED ? 'review_pass' : 'review_reject';

            AuditService::record((int) $reviewer->id, $action, (int) $proposal->id, [
                'from_status' => PackProposal::STATUS_PENDING,
                'to_status'   => $status,
                'version'     => (int) $proposal->version,
                'content'     => $remark !== '' ? $remark : '评审通过',
            ]);

            return $proposal;
        });
    }

    /**
     * 删除方案（仅草稿状态，软删除）
     * 导入生成的方案供应商不能删除，只有变更草稿（source_proposal_id > 0）允许删除，用于终止变更
     * @param PackProposal $proposal
     * @param User         $user
     * @return void
     * @throws BizException
     * @throws \Throwable
     */
    public static function delete(PackProposal $proposal, User $user): void
    {
        if ((int) $proposal->status !== PackProposal::STATUS_DRAFT) {
            throw new BizException('仅草稿状态的方案可以删除');
        }

        $isAdmin = (int) $user->role === User::ROLE_ADMIN;

        if (!$isAdmin && (int) $proposal->user_id !== (int) $user->id) {
            throw new BizException('只能删除自己的方案', 403, 403);
        }

        if (!$isAdmin && (int) $proposal->source_proposal_id <= 0) {
            throw new BizException('由零件号导入生成的方案不能删除，仅变更草稿可删除（终止变更）');
        }

        Db::transaction(function () use ($proposal, $user) {
            AuditService::record((int) $user->id, 'delete', (int) $proposal->id, [
                'from_status' => (int) $proposal->status,
                'version'     => (int) $proposal->version,
                'content'     => '删除方案：' . self::displayName($proposal),
            ]);

            $proposal->delete();
        });
    }

    /**
     * 批量归档（仅评审通过的方案）
     * @param array $ids
     * @param User  $user
     * @return int 归档数量
     * @throws BizException
     * @throws \Throwable
     */
    public static function archive(array $ids, User $user): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (empty($ids)) {
            throw new BizException('请选择要归档的方案');
        }

        return Db::transaction(function () use ($ids, $user) {
            $proposals = PackProposal::whereIn('id', $ids)
                ->where('status', PackProposal::STATUS_APPROVED)
                ->select();

            if ($proposals->isEmpty()) {
                throw new BizException('所选方案中没有可归档的已通过方案');
            }

            $count = 0;
            foreach ($proposals as $proposal) {
                $proposal->save([
                    'status'       => PackProposal::STATUS_ARCHIVED,
                    'archive_time' => date('Y-m-d H:i:s'),
                ]);

                AuditService::record((int) $user->id, 'archive', (int) $proposal->id, [
                    'from_status' => PackProposal::STATUS_APPROVED,
                    'to_status'   => PackProposal::STATUS_ARCHIVED,
                    'version'     => (int) $proposal->version,
                    'content'     => '方案归档：' . self::displayName($proposal),
                ]);

                // 变更方案归档生效后，原归档方案标记为「已归档-已变更-作废」
                $sourceId = (int) $proposal->source_proposal_id;

                if ($sourceId > 0) {
                    $sourceProposal = PackProposal::find($sourceId);

                    if ($sourceProposal && (int) $sourceProposal->status === PackProposal::STATUS_ARCHIVED) {
                        $sourceProposal->save([
                            'status' => PackProposal::STATUS_ARCHIVED_OBSOLETE,
                        ]);

                        AuditService::record((int) $user->id, 'obsolete', $sourceId, [
                            'from_status' => PackProposal::STATUS_ARCHIVED,
                            'to_status'   => PackProposal::STATUS_ARCHIVED_OBSOLETE,
                            'version'     => (int) $sourceProposal->version,
                            'content'     => '变更方案 ' . $proposal->proposal_no . ' 已归档生效，原方案作废',
                        ]);
                    }
                }

                $count++;
            }

            return $count;
        });
    }

    /**
     * 是否存在审批中的变更方案（未归档的变更草稿/待评审/已通过）
     * @param int $sourceId 源（已归档）方案ID
     * @return bool
     */
    public static function hasActiveChange(int $sourceId): bool
    {
        return PackProposal::where('source_proposal_id', $sourceId)
            ->whereIn('status', PackProposal::ACTIVE_STATUSES)
            ->count() > 0;
    }

    /**
     * 校验查看权限
     * @param PackProposal $proposal
     * @param User         $user
     * @return void
     * @throws BizException
     */
    public static function assertViewable(PackProposal $proposal, User $user): void
    {
        if ((int) $user->role === User::ROLE_ADMIN) {
            return;
        }

        if ((int) $proposal->user_id === (int) $user->id) {
            return;
        }

        // 评审员可以查看所有已提交的方案，但不能查看他人草稿
        if ((int) $user->role === User::ROLE_REVIEWER && (int) $proposal->status !== PackProposal::STATUS_DRAFT) {
            return;
        }

        throw new BizException('无权查看该方案', 403, 403);
    }

    /**
     * 校验编辑权限与状态
     * @param PackProposal $proposal
     * @param User         $user
     * @return void
     * @throws BizException
     */
    public static function assertEditable(PackProposal $proposal, User $user): void
    {
        if ((int) $user->role === User::ROLE_REVIEWER) {
            throw new BizException('评审员不能编辑方案', 403, 403);
        }

        if ((int) $user->role !== User::ROLE_ADMIN && (int) $proposal->user_id !== (int) $user->id) {
            throw new BizException('只能编辑自己的方案', 403, 403);
        }

        if (!in_array((int) $proposal->status, [PackProposal::STATUS_DRAFT, PackProposal::STATUS_REJECTED], true)) {
            throw new BizException('当前状态的方案不允许编辑');
        }
    }

    /**
     * 补齐不在表单中展示的字段（规格尺寸/数量）
     * @param array $data  请求数据
     * @param bool  $force 缺失字段是否也补齐：新增 true，编辑 false
     * @return array
     * @throws BizException 尺寸带小数时拒绝写入
     */
    public static function deriveFields(array $data, bool $force = true): array
    {
        // 填写日期固定为当天，不允许修改
        $data['write_date'] = date('Y-m-d');

        // 未选择（null）或选择不带托盘时，托盘信息一律清零
        if (array_key_exists('with_pallet', $data) && $data['with_pallet'] === '') {
            $data['with_pallet'] = null;
        }

        if (array_key_exists('with_pallet', $data) && $data['with_pallet'] !== 1) {
            if ($data['with_pallet'] !== null) {
                $data['with_pallet'] = 0;
            }

            foreach ([
                'pallet_size_l', 'pallet_size_w', 'pallet_size_h',
                'pallet_layer_qty', 'pallet_layer_count', 'pallet_parts_qty',
            ] as $field) {
                $data[$field] = 0;
            }
        }

        // 尺寸单位为 mm，只允许整数：带小数直接拒绝，不做取整
        foreach ([
            '产品外形尺寸' => ['part_size_l', 'part_size_w', 'part_size_h'],
            '包装箱外尺寸' => ['box_size_l', 'box_size_w', 'box_size_h'],
            '满托盘外尺寸' => ['pallet_size_l', 'pallet_size_w', 'pallet_size_h'],
        ] as $label => $fields) {
            foreach ($fields as $field) {
                if (!array_key_exists($field, $data)) {
                    continue;
                }

                $value = (float) $data[$field];

                if ($value != (int) $value) {
                    throw new BizException($label . '不能带小数，请填写整数（单位 mm）');
                }

                $data[$field] = (int) $value;
            }
        }

        if (($force || array_key_exists('spec', $data)) && trim((string) ($data['spec'] ?? '')) === '') {
            $length = (float) ($data['box_size_l'] ?? 0);
            $width  = (float) ($data['box_size_w'] ?? 0);
            $height = (float) ($data['box_size_h'] ?? 0);

            if ($length > 0 && $width > 0 && $height > 0) {
                $data['spec'] = self::trimZero($length) . '*'
                    . self::trimZero($width) . '*'
                    . self::trimZero($height) . 'mm';
            }
        }

        if (($force || array_key_exists('quantity', $data)) && (int) ($data['quantity'] ?? 0) <= 0) {
            $quantity = (int) ($data['packing_qty'] ?? 0);

            if ($quantity > 0) {
                $data['quantity'] = $quantity;
            }
        }

        // 每托盘零件数量 = 每层包装箱数量 × 堆码层数 × 存放数量（件/箱），不允许手工填写
        if ((int) ($data['with_pallet'] ?? 0) === 1
            && isset($data['pallet_layer_qty'], $data['pallet_layer_count'], $data['packing_qty'])) {
            $data['pallet_parts_qty'] = (int) $data['pallet_layer_qty']
                * (int) $data['pallet_layer_count']
                * (int) $data['packing_qty'];
        }

        return $data;
    }

    /**
     * 去掉小数末尾多余的 0
     * @param float $value
     * @return string
     */
    private static function trimZero(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /**
     * 方案展示名称：优先零件名称，缺省用方案编号
     * @param PackProposal $proposal
     * @return string
     */
    private static function displayName(PackProposal $proposal): string
    {
        $name = trim((string) $proposal->part_name);

        return $name !== '' ? $name : (string) $proposal->proposal_no;
    }

    /**
     * 供应商名称 / 代码只读：
     *   - 提交人名称固定为自己所属供应商（pf_user.dept）
     *   - 供应商代码一律以档案为准，忽略前端传入值
     * 其余供应商资料（地址、联系人、电话、邮箱、传真）允许自行修改
     * @param array $data
     * @param User  $user
     * @return array
     */
    private static function applySupplierScope(array $data, User $user): array
    {
        if ((int) $user->role === User::ROLE_SUBMITTER) {
            $supplier = Supplier::findByUser($user);
            $data['supplier'] = $supplier?->name ?? trim((string) $user->dept);
        }

        if (array_key_exists('supplier', $data)) {
            $name = trim((string) $data['supplier']);

            $data['supplier_code'] = $name === ''
                ? ''
                : (string) (Supplier::where('name', $name)->value('code') ?? '');
        }

        return $data;
    }

    /**
     * 提交人只能使用供应商指定零件号，零件名称/项目名称以指定清单为准
     * 评审员 / 管理员不做限制
     * @param array $data
     * @param User  $user
     * @return array
     * @throws BizException
     */
    private static function applyPartScope(array $data, User $user, ?PackProposal $current = null): array
    {
        if ((int) $user->role !== User::ROLE_SUBMITTER) {
            return $data;
        }

        $supplier = Supplier::findByUser($user);

        if (!$supplier) {
            throw new BizException('当前账号未绑定供应商，请联系管理员');
        }

        $partNo = trim((string) ($data['part_no'] ?? ''));

        if ($partNo === '') {
            throw new BizException('请选择零件号');
        }

        $part = SupplierPart::where('supplier_name', (string) $supplier->name)
            ->where('part_no', $partNo)
            ->find();

        if (!$part) {
            // 历史单据沿用原零件号时不做限制，避免旧数据无法保存
            if ($current && trim((string) $current->part_no) === $partNo) {
                return $data;
            }

            throw new BizException('零件号不在指定范围内，请从下拉列表选择');
        }

        $data['part_no'] = (string) $part->part_no;

        if (trim((string) $part->part_name) !== '') {
            $data['part_name'] = (string) $part->part_name;
        }

        if (trim((string) $part->project_name) !== '') {
            $data['project_model'] = (string) $part->project_name;
        }

        return $data;
    }

    /**
     * 过滤表单可写字段
     * @param array $data
     * @return array
     */
    public static function filterFields(array $data): array
    {
        $fields = [];

        foreach (self::EDITABLE_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = $data[$field];
            }
        }

        return $fields;
    }

    /**
     * 版本记录（基于审计日志）
     * @param int $proposalId
     * @return array
     */
    public static function versionHistory(int $proposalId): array
    {
        $logs = \app\model\ReviewLog::with(['operator'])
            ->where('proposal_id', $proposalId)
            ->whereIn('action', ['change', 'submit', 'review_pass', 'review_reject', 'archive', 'obsolete'])
            ->order('id', 'desc')
            ->select();

        $versions = [];
        foreach ($logs as $log) {
            $versions[] = [
                'version'     => (int) $log->version,
                'action'      => $log->action,
                'action_name' => $log->action_name,
                'to_status'   => $log->to_status,
                'content'     => $log->content,
                'operator'    => $log->operator?->real_name ?: '-',
                'create_time' => (string) $log->create_time,
            ];
        }

        return $versions;
    }
}
