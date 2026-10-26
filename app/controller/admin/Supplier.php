<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\exception\BizException;
use app\model\MasterLog;
use app\model\PackProposal;
use app\model\Supplier as SupplierModel;
use app\model\User;
use app\service\AuditService;
use app\service\MasterLogService;
use app\validate\SupplierValidate;
use think\facade\Db;
use think\response\Json;

/**
 * 管理员 - 供应商档案维护
 */
class Supplier extends BaseController
{
    /**
     * 供应商档案列表
     * @return Json
     */
    public function index(): Json
    {
        $page     = max(1, (int) $this->request->param('page', 1));
        $pageSize = min(100, max(1, (int) $this->request->param('page_size', 10)));

        $query = SupplierModel::field('id,name,code,address,contact,phone,email,fax,status,create_time,update_time');

        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $like = '%' . $keyword . '%';
                $q->whereLike('name', $like)
                    ->whereOr('code', 'like', $like)
                    ->whereOr('contact', 'like', $like);
            });
        }

        $status = $this->request->param('status', '');
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }

        $total = (clone $query)->count();
        $list  = $query->order('name')->order('id')->page($page, $pageSize)->select();

        return $this->success([
            'list'      => $list->toArray(),
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 新增供应商
     * @return Json
     * @throws BizException
     * @throws \think\exception\ValidateException
     */
    public function save(): Json
    {
        $data = $this->request->only(['name', 'code', 'address', 'contact', 'phone', 'email', 'fax', 'status']);
        $this->validate($data, SupplierValidate::class);

        $name = $this->normalizeName((string) $data['name']);
        $this->assertNameUnique($name);

        try {
            $supplier = SupplierModel::create([
                'name'    => $name,
                'code'    => (string) ($data['code'] ?? ''),
                'address' => (string) ($data['address'] ?? ''),
                'contact' => (string) ($data['contact'] ?? ''),
                'phone'   => (string) ($data['phone'] ?? ''),
                'email'   => (string) ($data['email'] ?? ''),
                'fax'     => (string) ($data['fax'] ?? ''),
                'status'  => (int) ($data['status'] ?? SupplierModel::STATUS_ENABLED),
            ]);
        } catch (\Throwable $e) {
            $this->rethrowIfDuplicate($e, '供应商名称已存在');
            throw $e;
        }

        AuditService::record($this->userId(), 'supplier_create', 0, [
            'content' => '新增供应商：' . $supplier->name,
        ]);

        // 首次录入写入维护日志
        MasterLogService::record(
            MasterLog::BIZ_SUPPLIER,
            (int) $supplier->id,
            (string) $supplier->name,
            $this->userId(),
            'supplier_create',
            MasterLogService::createContent(MasterLog::BIZ_SUPPLIER, $supplier->toArray())
        );

        return $this->success($supplier->toArray(), '供应商新增成功');
    }

    /**
     * 编辑供应商
     * 名称变更时同步提交人账号的「部门/供应商」，保证供应商归属不断链
     * @param int $id
     * @return Json
     * @throws BizException
     * @throws \think\exception\ValidateException
     * @throws \Throwable
     */
    public function update(int $id): Json
    {
        $supplier = $this->findSupplier($id);
        $data     = $this->request->only(['name', 'code', 'address', 'contact', 'phone', 'email', 'fax', 'status']);
        $this->validate($data, SupplierValidate::class);

        $name     = $this->normalizeName((string) $data['name']);
        $oldName  = (string) $supplier->name;
        $this->assertNameUnique($name, $id);

        $userId = $this->userId();

        try {
            Db::transaction(function () use ($supplier, $data, $name, $oldName, $userId) {
                $before = $supplier->toArray();

                $supplier->save([
                    'name'    => $name,
                    'code'    => (string) ($data['code'] ?? ''),
                    'address' => (string) ($data['address'] ?? ''),
                    'contact' => (string) ($data['contact'] ?? ''),
                    'phone'   => (string) ($data['phone'] ?? ''),
                    'email'   => (string) ($data['email'] ?? ''),
                    'fax'     => (string) ($data['fax'] ?? ''),
                    'status'  => (int) ($data['status'] ?? $supplier->status),
                ]);

                if ($oldName !== $name) {
                    User::where('dept', $oldName)->update(['dept' => $name]);
                }

                $changes = MasterLogService::changes(MasterLog::BIZ_SUPPLIER, $before, $supplier->toArray());

                if ($changes !== []) {
                    MasterLogService::record(
                        MasterLog::BIZ_SUPPLIER,
                        (int) $supplier->id,
                        (string) $supplier->name,
                        $userId,
                        'supplier_update',
                        implode('；', $changes)
                    );
                }
            });
        } catch (\Throwable $e) {
            $this->rethrowIfDuplicate($e, '供应商名称已存在');
            throw $e;
        }

        AuditService::record($this->userId(), 'supplier_update', 0, [
            'content' => '编辑供应商：' . $supplier->name,
        ]);

        return $this->success($supplier->toArray(), '供应商更新成功');
    }

    /**
     * 供应商维护日志
     * @param int $id
     * @return Json
     * @throws BizException
     */
    public function logs(int $id): Json
    {
        $supplier = $this->findSupplier($id);

        $logs = MasterLog::with(['operator'])
            ->where('biz_type', MasterLog::BIZ_SUPPLIER)
            ->where('target_id', (int) $supplier->id)
            ->order('id', 'desc')
            ->limit(100)
            ->select();

        return $this->success($logs->toArray());
    }

    /**
     * 删除供应商
     * @param int $id
     * @return Json
     * @throws BizException
     */
    public function delete(int $id): Json
    {
        $supplier = $this->findSupplier($id);

        if (PackProposal::where('supplier', $supplier->name)->count() > 0) {
            throw new BizException('该供应商已被方案使用，不能删除');
        }

        if (User::where('dept', $supplier->name)->count() > 0) {
            throw new BizException('该供应商仍有提交人账号在用，不能删除');
        }

        $name = (string) $supplier->name;

        Db::transaction(function () use ($supplier, $name, $id) {
            // 软删除并改写名称，释放唯一索引，便于重新创建同名供应商
            $supplier->save(['name' => $this->deletedName($name, $id)]);
            $supplier->delete();
        });

        AuditService::record($this->userId(), 'supplier_delete', 0, [
            'content' => '删除供应商：' . $name,
        ]);

        return $this->success([], '供应商已删除');
    }

    /**
     * 软删除后的名称（原名称#ID），避免占用唯一索引
     * @param string $name
     * @param int    $id
     * @return string
     */
    protected function deletedName(string $name, int $id): string
    {
        $suffix = '#' . $id;
        $base   = mb_substr($name, 0, 100 - mb_strlen($suffix));

        return $base . $suffix;
    }

    /**
     * 名称查重（前端输入时实时校验）
     * @return Json
     */
    public function checkName(): Json
    {
        $name = $this->normalizeName((string) $this->request->param('name', ''));
        $id   = (int) $this->request->param('id', 0);

        return $this->success([
            'exists' => $name !== '' && $this->existsName($name, $id),
        ]);
    }

    /**
     * 规范名称：去掉首尾空白（含全角空格），中间连续空白压缩为一个空格
     * @param string $name
     * @return string
     */
    protected function normalizeName(string $name): string
    {
        $name = (string) preg_replace('/^[\s\x{3000}]+|[\s\x{3000}]+$/u', '', $name);
        $name = (string) preg_replace('/[\s\x{3000}]+/u', ' ', $name);

        return $name;
    }

    /**
     * 名称唯一性校验（忽略大小写与多余空白）
     * @param string $name
     * @param int    $excludeId
     * @return void
     * @throws BizException
     */
    protected function assertNameUnique(string $name, int $excludeId = 0): void
    {
        if ($name === '') {
            throw new BizException('请输入供应商名称');
        }

        if ($this->existsName($name, $excludeId)) {
            throw new BizException('供应商名称已存在');
        }
    }

    /**
     * 名称是否已存在
     * @param string $name
     * @param int    $excludeId
     * @return bool
     */
    protected function existsName(string $name, int $excludeId = 0): bool
    {
        $lower = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
        $query = SupplierModel::whereRaw('LOWER(`name`) = ?', [$lower]);

        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }

        return (bool) $query->find();
    }

    /**
     * 唯一索引冲突时转换为友好提示
     * @param \Throwable $e
     * @param string     $message
     * @return void
     * @throws BizException
     */
    protected function rethrowIfDuplicate(\Throwable $e, string $message): void
    {
        if (str_contains($e->getMessage(), 'Duplicate entry') || (string) $e->getCode() === '23000') {
            throw new BizException($message);
        }
    }

    /**
     * 查询供应商
     * @param int $id
     * @return SupplierModel
     * @throws BizException
     */
    protected function findSupplier(int $id): SupplierModel
    {
        $supplier = SupplierModel::find($id);

        if (!$supplier) {
            throw new BizException('供应商不存在', 0, 404);
        }

        return $supplier;
    }
}
