<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\exception\BizException;
use app\model\PackProposal;
use app\service\ProposalService;
use think\response\Json;

/**
 * 管理员 - 全量方案汇总
 */
class Proposal extends BaseController
{
    /**
     * 全量方案列表（不加数据范围过滤）
     * @return Json
     */
    public function index(): Json
    {
        $page     = max(1, (int) $this->request->param('page', 1));
        $pageSize = min(500, max(1, (int) $this->request->param('page_size', 10)));

        $query = PackProposal::with(['user', 'reviewer']);

        $status = $this->request->param('status', '');
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }

        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $like = '%' . $keyword . '%';
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

        $userId = (int) $this->request->param('user_id', 0);
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        $startDate = trim((string) $this->request->param('start_date', ''));
        if ($startDate !== '') {
            $query->where('create_time', '>=', $startDate . ' 00:00:00');
        }

        $endDate = trim((string) $this->request->param('end_date', ''));
        if ($endDate !== '') {
            $query->where('create_time', '<=', $endDate . ' 23:59:59');
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
     * 全量详细方案（全字段输出，供明细筛选与导出）
     * @return Json
     */
    public function detail(): Json
    {
        $page     = max(1, (int) $this->request->param('page', 1));
        $pageSize = min(500, max(1, (int) $this->request->param('page_size', 20)));

        $query = PackProposal::with(['user', 'reviewer']);

        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $like = '%' . $keyword . '%';
                $q->whereLike('proposal_no', $like)
                    ->whereOr('part_name', 'like', $like)
                    ->whereOr('part_no', 'like', $like)
                    ->whereOr('project_model', 'like', $like);
            });
        }

        $customer = trim((string) $this->request->param('customer', ''));
        if ($customer !== '') {
            $query->where('customer', $customer);
        }

        $supplier = trim((string) $this->request->param('supplier', ''));
        if ($supplier !== '') {
            $query->where('supplier', $supplier);
        }

        $project = trim((string) $this->request->param('project', ''));
        if ($project !== '') {
            $query->where('project_model', $project);
        }

        $partNo = trim((string) $this->request->param('part_no', ''));
        if ($partNo !== '') {
            $query->whereLike('part_no', '%' . $partNo . '%');
        }

        // 状态支持多选：数组或逗号分隔
        $status  = $this->request->param('status', '');
        $statuses = is_array($status)
            ? array_map('intval', $status)
            : array_map('intval', array_filter(explode(',', (string) $status), 'strlen'));
        $statuses = array_values(array_filter($statuses, static fn ($item) => $item >= 0 && $item <= 5));

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        $userId = (int) $this->request->param('user_id', 0);
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        // 是否带托盘交付：'' 不限，'0' / '1' 精确匹配
        $withPallet = $this->request->param('with_pallet', '');
        if ($withPallet !== '' && $withPallet !== null) {
            $query->where('with_pallet', (int) $withPallet);
        }

        // 日期筛选字段白名单，默认按创建时间
        $dateField = (string) $this->request->param('date_field', 'create_time');
        if (!in_array($dateField, ['create_time', 'submit_time', 'review_time', 'archive_time'], true)) {
            $dateField = 'create_time';
        }

        $startDate = trim((string) $this->request->param('start_date', ''));
        if ($startDate !== '') {
            $query->where($dateField, '>=', $startDate . ' 00:00:00');
        }

        $endDate = trim((string) $this->request->param('end_date', ''));
        if ($endDate !== '') {
            $query->where($dateField, '<=', $endDate . ' 23:59:59');
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
     * 批量归档（仅已通过的方案）
     * @return Json
     * @throws BizException
     * @throws \Throwable
     */
    public function archive(): Json
    {
        $ids = $this->request->param('ids', []);

        if (!is_array($ids)) {
            throw new BizException('参数格式不正确');
        }

        $count = ProposalService::archive($ids, $this->currentUser());

        return $this->success(['count' => $count], '已归档 ' . $count . ' 条方案');
    }
}
