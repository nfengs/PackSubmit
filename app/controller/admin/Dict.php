<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\exception\BizException;
use app\model\Dict as DictModel;
use app\model\PackProposal;
use app\service\AuditService;
use app\validate\DictValidate;
use think\response\Json;

/**
 * 管理员 - 字典管理
 */
class Dict extends BaseController
{
    /**
     * 字典列表
     * @return Json
     */
    public function index(): Json
    {
        $page     = max(1, (int) $this->request->param('page', 1));
        $pageSize = min(100, max(1, (int) $this->request->param('page_size', 10)));

        $query = DictModel::field('id,dict_type,dict_label,dict_value,sort,status,remark,create_time,update_time');

        $type = trim((string) $this->request->param('dict_type', ''));
        if ($type !== '') {
            $query->where('dict_type', $type);
        }

        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $like = '%' . $keyword . '%';
                $q->whereLike('dict_label', $like)
                    ->whereOr('dict_value', 'like', $like);
            });
        }

        $status = $this->request->param('status', '');
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }

        $total = (clone $query)->count();
        $list  = $query->order('dict_type')->order('sort')->order('id')
            ->page($page, $pageSize)->select();

        return $this->success([
            'list'      => $list->toArray(),
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 新增字典
     * @return Json
     * @throws BizException
     * @throws \think\exception\ValidateException
     */
    public function save(): Json
    {
        $data = $this->request->only(['dict_type', 'dict_label', 'dict_value', 'sort', 'status', 'remark']);
        $this->validate($data, DictValidate::class);

        $exists = DictModel::where('dict_type', $data['dict_type'])
            ->where('dict_value', $data['dict_value'])
            ->find();

        if ($exists) {
            throw new BizException('同一字典类型下已存在相同的字典值');
        }

        $dict = DictModel::create([
            'dict_type'  => $data['dict_type'],
            'dict_label' => $data['dict_label'],
            'dict_value' => $data['dict_value'],
            'sort'       => (int) ($data['sort'] ?? 0),
            'status'     => (int) ($data['status'] ?? DictModel::STATUS_ENABLED),
            'remark'     => (string) ($data['remark'] ?? ''),
        ]);

        AuditService::record($this->userId(), 'dict_create', 0, [
            'content' => '新增字典：' . $dict->dict_type . ' / ' . $dict->dict_label,
        ]);

        return $this->success($dict->toArray(), '字典新增成功');
    }

    /**
     * 编辑字典
     * @param int $id
     * @return Json
     * @throws BizException
     * @throws \think\exception\ValidateException
     */
    public function update(int $id): Json
    {
        $dict = $this->findDict($id);
        $data = $this->request->only(['dict_type', 'dict_label', 'dict_value', 'sort', 'status', 'remark']);
        $this->validate($data, DictValidate::class);

        $exists = DictModel::where('dict_type', $data['dict_type'])
            ->where('dict_value', $data['dict_value'])
            ->where('id', '<>', $id)
            ->find();

        if ($exists) {
            throw new BizException('同一字典类型下已存在相同的字典值');
        }

        $dict->save([
            'dict_type'  => $data['dict_type'],
            'dict_label' => $data['dict_label'],
            'dict_value' => $data['dict_value'],
            'sort'       => (int) ($data['sort'] ?? 0),
            'status'     => (int) ($data['status'] ?? $dict->status),
            'remark'     => (string) ($data['remark'] ?? ''),
        ]);

        AuditService::record($this->userId(), 'dict_update', 0, [
            'content' => '编辑字典：' . $dict->dict_type . ' / ' . $dict->dict_label,
        ]);

        return $this->success($dict->toArray(), '字典更新成功');
    }

    /**
     * 删除字典
     * @param int $id
     * @return Json
     * @throws BizException
     */
    public function delete(int $id): Json
    {
        $dict = $this->findDict($id);

        // 已被方案引用的字典项不允许删除
        if ($dict->dict_type === 'customer' && PackProposal::where('customer', $dict->dict_value)->count() > 0) {
            throw new BizException('该客户已被方案使用，不能删除');
        }

        $dict->delete();

        AuditService::record($this->userId(), 'dict_delete', 0, [
            'content' => '删除字典：' . $dict->dict_type . ' / ' . $dict->dict_label,
        ]);

        return $this->success([], '字典已删除');
    }

    /**
     * 查询字典
     * @param int $id
     * @return DictModel
     * @throws BizException
     */
    protected function findDict(int $id): DictModel
    {
        $dict = DictModel::find($id);

        if (!$dict) {
            throw new BizException('字典项不存在', 0, 404);
        }

        return $dict;
    }
}
