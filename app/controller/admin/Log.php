<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\model\ReviewLog;
use think\response\Json;

/**
 * 管理员 - 全局审计日志
 */
class Log extends BaseController
{
    /**
     * 日志列表（支持时间、操作人、动作、方案筛选）
     * @return Json
     */
    public function index(): Json
    {
        $page     = max(1, (int) $this->request->param('page', 1));
        $pageSize = min(100, max(1, (int) $this->request->param('page_size', 10)));

        $query = ReviewLog::with(['operator', 'proposal']);

        $startDate = trim((string) $this->request->param('start_date', ''));
        if ($startDate !== '') {
            $query->where('create_time', '>=', $startDate . ' 00:00:00');
        }

        $endDate = trim((string) $this->request->param('end_date', ''));
        if ($endDate !== '') {
            $query->where('create_time', '<=', $endDate . ' 23:59:59');
        }

        $userId = (int) $this->request->param('user_id', 0);
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        // 按操作人姓名/账号筛选
        $operator = trim((string) $this->request->param('operator', ''));
        if ($operator !== '') {
            $userIds = \app\model\User::whereLike('username', '%' . $operator . '%')
                ->whereOr('real_name', 'like', '%' . $operator . '%')
                ->column('id');

            $query->whereIn('user_id', $userIds ?: [-1]);
        }

        $action = trim((string) $this->request->param('action', ''));
        if ($action !== '') {
            $query->where('action', $action);
        }

        $proposalId = (int) $this->request->param('proposal_id', 0);
        if ($proposalId > 0) {
            $query->where('proposal_id', $proposalId);
        }

        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query->whereLike('content', '%' . $keyword . '%');
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
}
