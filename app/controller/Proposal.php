<?php
declare (strict_types = 1);

namespace app\controller;

use app\BaseController;
use app\exception\BizException;
use app\model\PackProposal;
use app\model\ReviewLog;
use app\model\User;
use app\service\AuditService;
use app\service\PackExportService;
use app\service\ProposalService;
use app\validate\ProposalValidate;
use app\validate\ReviewValidate;
use think\response\Json;

/**
 * 包装方案控制器
 */
class Proposal extends BaseController
{
    /**
     * 方案列表
     * 供应商自动追加 user_id 过滤；评审员排除他人草稿；管理员查看全量
     * @return Json
     */
    public function index(): Json
    {
        $user     = $this->currentUser();
        $page     = max(1, (int) $this->request->param('page', 1));
        $pageSize = min(100, max(1, (int) $this->request->param('page_size', 10)));

        $query = $this->applyRoleScope(PackProposal::with(['user', 'reviewer']), $user);

        $status = $this->request->param('status', '');
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }

        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $like = '%' . like_escape($keyword) . '%';
                $q->whereLike('proposal_no', $like)
                    ->whereOr('part_name', 'like', $like)
                    ->whereOr('part_no', 'like', $like);
            });
        }

        $customer = trim((string) $this->request->param('customer', ''));
        if ($customer !== '') {
            $query->where('customer', $customer);
        }

        $project = trim((string) $this->request->param('project', ''));
        if ($project !== '') {
            $query->where('project_model', $project);
        }

        $total = (clone $query)->count();
        $list  = $query->order('id', 'desc')->page($page, $pageSize)->select();

        return $this->success([
            'list'      => $list->toArray(),
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 项目列表（筛选下拉数据源，按当前用户可查看范围去重）
     * @return Json
     */
    public function projects(): Json
    {
        $projects = $this->applyRoleScope(
            PackProposal::where('project_model', '<>', ''),
            $this->currentUser()
        )
            ->group('project_model')
            ->order('project_model')
            ->column('project_model');

        return $this->success(array_values($projects));
    }

    /**
     * 方案详情（含附件、日志、版本记录、当前用户可执行操作）
     * @param int $id
     * @return Json
     * @throws BizException
     */
    public function read(int $id): Json
    {
        $user     = $this->currentUser();
        $proposal = PackProposal::with(['user', 'reviewer', 'attachments.uploader'])->find($id);

        if (!$proposal) {
            throw new BizException('方案不存在或已被删除', 0, 404);
        }

        ProposalService::assertViewable($proposal, $user);

        AuditService::record((int) $user->id, 'view', (int) $proposal->id, [
            'version' => (int) $proposal->version,
            'content' => '查看方案：' . (trim((string) $proposal->part_name) !== '' ? $proposal->part_name : $proposal->proposal_no),
        ]);

        // 附件上传/删除、查看/导出只做操作留痕，不进入评审日志展示
        $logs = ReviewLog::with(['operator'])
            ->where('proposal_id', $id)
            ->whereNotIn('action', ['upload', 'delete_attachment', 'view', 'export'])
            ->order('id', 'desc')
            ->select();

        $data                = $proposal->toArray();
        $data['logs']        = $logs->toArray();
        $data['versions']    = ProposalService::versionHistory($id);
        $data['permissions'] = $this->permissions($proposal, $user);
        $data['change_source'] = $this->changeSource($proposal);
        $data['change_target'] = $this->changeTarget($proposal);

        return $this->success($data);
    }

    /**
     * 新增方案
     * @return Json
     * @throws BizException
     * @throws \think\exception\ValidateException
     */
    public function save(): Json
    {
        $user = $this->currentUser();

        if ((int) $user->role === User::ROLE_REVIEWER) {
            throw new BizException('评审员不能新增方案', 403, 403);
        }

        if ((int) $user->role === User::ROLE_VIEWER) {
            throw new BizException('查看者只能查看，不能新增方案', 403, 403);
        }

        // 方案草稿统一在管理员导入零件号时生成，供应商不再手动新增
        if ((int) $user->role === User::ROLE_SUBMITTER) {
            throw new BizException('方案由管理员导入零件号时自动生成，无需手动新增', 403, 403);
        }

        // 表单不再录入方案名称/物料号/包装类型/规格尺寸/数量，由其他字段自动推导
        $data = ProposalService::deriveFields(
            $this->request->only(ProposalService::EDITABLE_FIELDS)
        );
        $this->validate($data, ProposalValidate::class);

        $proposal = ProposalService::create($data, $user);

        return $this->success($proposal->toArray(), '方案创建成功');
    }

    /**
     * 复制方案（以源方案内容生成当前用户的新草稿）
     * @param int $id
     * @return Json
     * @throws BizException
     * @throws \Throwable
     */
    public function copy(int $id): Json
    {
        $user = $this->currentUser();

        if ((int) $user->role === User::ROLE_REVIEWER) {
            throw new BizException('评审员不能新增方案', 403, 403);
        }

        if ((int) $user->role === User::ROLE_VIEWER) {
            throw new BizException('查看者不能复制方案', 403, 403);
        }

        $source = PackProposal::find($id);

        if (!$source) {
            throw new BizException('方案不存在或已被删除', 0, 404);
        }

        ProposalService::assertViewable($source, $user);

        $proposal = ProposalService::copy($source, $user);

        return $this->success($proposal->toArray(), '方案已复制');
    }

    /**
     * 发起变更（仅已归档方案）
     * @param int $id
     * @return Json
     * @throws BizException
     * @throws \Throwable
     */
    public function change(int $id): Json
    {
        $user = $this->currentUser();

        if ((int) $user->role === User::ROLE_REVIEWER) {
            throw new BizException('评审员不能发起变更', 403, 403);
        }

        if ((int) $user->role === User::ROLE_VIEWER) {
            throw new BizException('查看者不能发起变更', 403, 403);
        }

        $source = PackProposal::find($id);

        if (!$source) {
            throw new BizException('方案不存在或已被删除', 0, 404);
        }

        ProposalService::assertViewable($source, $user);

        $proposal = ProposalService::change($source, $user);

        return $this->success($proposal->toArray(), '变更方案已创建');
    }

    /**
     * 编辑方案
     * @param int $id
     * @return Json
     * @throws BizException
     * @throws \think\exception\ValidateException
     */
    public function update(int $id): Json
    {
        $user     = $this->currentUser();
        $proposal = PackProposal::find($id);

        if (!$proposal) {
            throw new BizException('方案不存在或已被删除', 0, 404);
        }

        // 以已有数据为基准重算规格尺寸 / 数量 / 每托盘零件数
        $data = ProposalService::deriveFields(
            $this->request->only(ProposalService::EDITABLE_FIELDS),
            false,
            $proposal->toArray()
        );
        $this->validate($data, ProposalValidate::class);

        ProposalService::update($proposal, $data, $user);

        return $this->success($proposal->toArray(), '方案更新成功');
    }

    /**
     * 删除方案（仅草稿，软删除）
     * @param int $id
     * @return Json
     * @throws BizException
     * @throws \Throwable
     */
    public function delete(int $id): Json
    {
        $proposal = PackProposal::find($id);

        if (!$proposal) {
            throw new BizException('方案不存在或已被删除', 0, 404);
        }

        ProposalService::delete($proposal, $this->currentUser());

        return $this->success([], '方案已删除');
    }

    /**
     * 提交评审
     * @param int $id
     * @return Json
     * @throws BizException
     * @throws \Throwable
     */
    public function submit(int $id): Json
    {
        $proposal = PackProposal::find($id);

        if (!$proposal) {
            throw new BizException('方案不存在或已被删除', 0, 404);
        }

        $proposal = ProposalService::submit($proposal, $this->currentUser());

        return $this->success($proposal->toArray(), '提交成功，等待评审');
    }

    /**
     * 撤回提交（待评审 -> 草稿）
     * @param int $id
     * @return Json
     * @throws BizException
     * @throws \Throwable
     */
    public function withdraw(int $id): Json
    {
        $proposal = PackProposal::find($id);

        if (!$proposal) {
            throw new BizException('方案不存在或已被删除', 0, 404);
        }

        ProposalService::withdraw($proposal, $this->currentUser());

        return $this->success(['id' => (int) $proposal->id], '已撤回提交');
    }

    /**
     * 评审方案（评审员/管理员）
     * @param int $id
     * @return Json
     * @throws BizException
     * @throws \think\exception\ValidateException
     * @throws \Throwable
     */
    public function review(int $id): Json
    {
        $user = $this->currentUser();

        if (!in_array((int) $user->role, [User::ROLE_REVIEWER, User::ROLE_ADMIN], true)) {
            throw new BizException('无评审权限', 403, 403);
        }

        $data = $this->request->only(['status', 'remark']);
        $this->validate($data, ReviewValidate::class);

        $proposal = PackProposal::find($id);

        if (!$proposal) {
            throw new BizException('方案不存在或已被删除', 0, 404);
        }

        $proposal = ProposalService::review(
            $proposal,
            (int) $data['status'],
            trim((string) ($data['remark'] ?? '')),
            $user
        );

        return $this->success($proposal->toArray(), '评审完成');
    }

    /**
     * 导出包装确认单（Excel）
     * @param int $id
     * @return \think\Response
     * @throws BizException
     */
    public function exportExcel(int $id): \think\Response
    {
        return $this->export($id, 'xlsx');
    }

    /**
     * 导出包装确认单（PDF）
     * @param int $id
     * @return \think\Response
     * @throws BizException
     */
    public function exportPdf(int $id): \think\Response
    {
        return $this->export($id, 'pdf');
    }

    /**
     * 导出包装确认单
     * @param int    $id
     * @param string $format xlsx / pdf
     * @return \think\Response
     * @throws BizException
     */
    protected function export(int $id, string $format): \think\Response
    {
        $proposal = PackProposal::with(['user', 'reviewer', 'attachments'])->find($id);

        if (!$proposal) {
            throw new BizException('方案不存在或已被删除', 0, 404);
        }

        $user = $this->currentUser();
        ProposalService::assertViewable($proposal, $user);

        AuditService::record((int) $user->id, 'export', (int) $proposal->id, [
            'version' => (int) $proposal->version,
            'content' => '导出方案（' . strtoupper($format) . '）：'
                . (trim((string) $proposal->part_name) !== '' ? $proposal->part_name : $proposal->proposal_no),
        ]);

        try {
            $path = $format === 'pdf'
                ? PackExportService::pdf($proposal)
                : PackExportService::excel($proposal);
        } catch (\Throwable $e) {
            throw new BizException('导出失败：' . $e->getMessage());
        }

        $content = @file_get_contents($path);
        @unlink($path);

        if ($content === false || $content === '') {
            throw new BizException('导出失败，请稍后重试');
        }

        $mime = $format === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response($content)
            ->contentType($mime)
            ->header([
                'Content-Disposition' => "attachment; filename*=UTF-8''"
                    . rawurlencode(PackExportService::fileName($proposal, $format)),
                'Content-Length'      => (string) strlen($content),
            ]);
    }

    /**
     * 工作台统计
     * @return Json
     */
    public function statistics(): Json
    {
        $user = $this->currentUser();
        $base = $this->applyRoleScope(PackProposal::field('id,status,user_id'), $user);

        $counts = [];
        foreach (array_keys(PackProposal::STATUS_TEXT) as $status) {
            $counts[$status] = (clone $base)->where('status', $status)->count();
        }

        return $this->success([
            'draft'    => $counts[PackProposal::STATUS_DRAFT] ?? 0,
            'pending'  => $counts[PackProposal::STATUS_PENDING] ?? 0,
            'approved' => $counts[PackProposal::STATUS_APPROVED] ?? 0,
            'rejected' => $counts[PackProposal::STATUS_REJECTED] ?? 0,
            'archived' => $counts[PackProposal::STATUS_ARCHIVED] ?? 0,
            'obsolete' => $counts[PackProposal::STATUS_ARCHIVED_OBSOLETE] ?? 0,
            'total'    => array_sum($counts),
        ]);
    }

    /**
     * 变更来源方案（当前方案为变更方案时）
     * @param PackProposal $proposal
     * @return array|null
     */
    protected function changeSource(PackProposal $proposal): ?array
    {
        $sourceId = (int) $proposal->source_proposal_id;

        if ($sourceId <= 0) {
            return null;
        }

        $source = PackProposal::field('id,proposal_no,version,status')->find($sourceId);

        return $source ? $source->toArray() : null;
    }

    /**
     * 审批中的变更方案（当前方案为已归档原方案时）
     * @param PackProposal $proposal
     * @return array|null
     */
    protected function changeTarget(PackProposal $proposal): ?array
    {
        if ((int) $proposal->status !== PackProposal::STATUS_ARCHIVED) {
            return null;
        }

        $target = PackProposal::field('id,proposal_no,version,status')
            ->where('source_proposal_id', (int) $proposal->id)
            ->whereIn('status', PackProposal::ACTIVE_STATUSES)
            ->order('id', 'desc')
            ->find();

        return $target ? $target->toArray() : null;
    }

    /**
     * 按角色追加数据范围
     * @param mixed $query
     * @param User  $user
     * @return mixed
     */
    protected function applyRoleScope($query, User $user)
    {
        $role = (int) $user->role;

        if ($role === User::ROLE_SUBMITTER) {
            // 供应商内数据互通：可查看本供应商所有人员的方案
            $supplierName = ProposalService::supplierNameOf($user);

            if ($supplierName !== '') {
                $query->where('supplier', $supplierName);
            } else {
                $query->where('user_id', (int) $user->id);
            }
        } elseif ($role === User::ROLE_REVIEWER) {
            // 评审员可查看所有已提交的方案，但不能查看他人草稿
            $query->where('status', '<>', PackProposal::STATUS_DRAFT);
        }

        return $query;
    }

    /**
     * 当前用户对方案的可执行操作
     * @param PackProposal $proposal
     * @param User         $user
     * @return array
     */
    protected function permissions(PackProposal $proposal, User $user): array
    {
        $role             = (int) $user->role;
        $status           = (int) $proposal->status;
        $isAdmin          = $role === User::ROLE_ADMIN;
        $isSupplierMember = ProposalService::canOperateAsSupplier($proposal, $user);

        return [
            'can_edit'    => $role !== User::ROLE_REVIEWER
                && ($isSupplierMember || $isAdmin)
                && in_array($status, [PackProposal::STATUS_DRAFT, PackProposal::STATUS_REJECTED], true),
            // 导入生成的方案供应商不能删除，只有变更草稿允许删除（终止变更）；管理员不受限
            'can_delete'  => $status === PackProposal::STATUS_DRAFT
                && ($isSupplierMember || $isAdmin)
                && ($isAdmin || (int) $proposal->source_proposal_id > 0),
            'can_submit'  => in_array($status, [PackProposal::STATUS_DRAFT, PackProposal::STATUS_REJECTED], true)
                && ($isSupplierMember || $isAdmin),
            'can_withdraw' => $status === PackProposal::STATUS_PENDING
                && ($isSupplierMember || $isAdmin)
                && empty($proposal->review_time),
            'can_review'  => in_array($role, [User::ROLE_REVIEWER, User::ROLE_ADMIN], true)
                && $status === PackProposal::STATUS_PENDING,
            'can_archive' => $isAdmin && $status === PackProposal::STATUS_APPROVED,
            'can_change'  => $role !== User::ROLE_REVIEWER
                && ($isSupplierMember || $isAdmin)
                && $status === PackProposal::STATUS_ARCHIVED
                && !ProposalService::hasActiveChange((int) $proposal->id),
        ];
    }
}
