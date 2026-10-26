<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\exception\BizException;
use app\model\Customer as CustomerModel;
use app\model\MasterLog;
use app\model\PackProposal;
use app\service\AuditService;
use app\service\MasterLogService;
use app\validate\CustomerValidate;
use think\facade\Db;
use think\response\Json;

/**
 * 管理员 - 客户档案维护
 */
class Customer extends BaseController
{
    /**
     * 客户档案列表
     * @return Json
     */
    public function index(): Json
    {
        $page     = max(1, (int) $this->request->param('page', 1));
        $pageSize = min(100, max(1, (int) $this->request->param('page_size', 10)));

        $query = CustomerModel::field('id,name,address,contact,phone,email,fax,status,create_time,update_time');

        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $like = '%' . $keyword . '%';
                $q->whereLike('name', $like)
                    ->whereOr('contact', 'like', $like)
                    ->whereOr('phone', 'like', $like);
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
     * 新增客户
     * @return Json
     * @throws BizException
     * @throws \think\exception\ValidateException
     */
    public function save(): Json
    {
        $data = $this->request->only(['name', 'address', 'contact', 'phone', 'email', 'fax', 'status']);
        $this->validate($data, CustomerValidate::class);

        $name = $this->normalizeName((string) $data['name']);
        $this->assertNameUnique($name);

        try {
            $customer = CustomerModel::create([
                'name'    => $name,
                'address' => (string) ($data['address'] ?? ''),
                'contact' => (string) ($data['contact'] ?? ''),
                'phone'   => (string) ($data['phone'] ?? ''),
                'email'   => (string) ($data['email'] ?? ''),
                'fax'     => (string) ($data['fax'] ?? ''),
                'status'  => (int) ($data['status'] ?? CustomerModel::STATUS_ENABLED),
            ]);
        } catch (\Throwable $e) {
            $this->rethrowIfDuplicate($e, '客户名称已存在');
            throw $e;
        }

        AuditService::record($this->userId(), 'customer_create', 0, [
            'content' => '新增客户：' . $customer->name,
        ]);

        // 首次录入写入维护日志
        MasterLogService::record(
            MasterLog::BIZ_CUSTOMER,
            (int) $customer->id,
            (string) $customer->name,
            $this->userId(),
            'customer_create',
            MasterLogService::createContent(MasterLog::BIZ_CUSTOMER, $customer->toArray())
        );

        return $this->success($customer->toArray(), '客户新增成功');
    }

    /**
     * 编辑客户
     * @param int $id
     * @return Json
     * @throws BizException
     * @throws \think\exception\ValidateException
     */
    public function update(int $id): Json
    {
        $customer = $this->findCustomer($id);
        $data     = $this->request->only(['name', 'address', 'contact', 'phone', 'email', 'fax', 'status']);
        $this->validate($data, CustomerValidate::class);

        $name = $this->normalizeName((string) $data['name']);
        $this->assertNameUnique($name, $id);

        $before = $customer->toArray();

        try {
            $customer->save([
                'name'    => $name,
                'address' => (string) ($data['address'] ?? ''),
                'contact' => (string) ($data['contact'] ?? ''),
                'phone'   => (string) ($data['phone'] ?? ''),
                'email'   => (string) ($data['email'] ?? ''),
                'fax'     => (string) ($data['fax'] ?? ''),
                'status'  => (int) ($data['status'] ?? $customer->status),
            ]);
        } catch (\Throwable $e) {
            $this->rethrowIfDuplicate($e, '客户名称已存在');
            throw $e;
        }

        AuditService::record($this->userId(), 'customer_update', 0, [
            'content' => '编辑客户：' . $customer->name,
        ]);

        $changes = MasterLogService::changes(MasterLog::BIZ_CUSTOMER, $before, $customer->toArray());

        if ($changes !== []) {
            MasterLogService::record(
                MasterLog::BIZ_CUSTOMER,
                (int) $customer->id,
                (string) $customer->name,
                $this->userId(),
                'customer_update',
                implode('；', $changes)
            );
        }

        return $this->success($customer->toArray(), '客户更新成功');
    }

    /**
     * 客户维护日志
     * @param int $id
     * @return Json
     * @throws BizException
     */
    public function logs(int $id): Json
    {
        $customer = $this->findCustomer($id);

        $logs = MasterLog::with(['operator'])
            ->where('biz_type', MasterLog::BIZ_CUSTOMER)
            ->where('target_id', (int) $customer->id)
            ->order('id', 'desc')
            ->limit(100)
            ->select();

        return $this->success($logs->toArray());
    }

    /**
     * 删除客户
     * @param int $id
     * @return Json
     * @throws BizException
     */
    public function delete(int $id): Json
    {
        $customer = $this->findCustomer($id);

        if (PackProposal::where('customer', $customer->name)->count() > 0) {
            throw new BizException('该客户已被方案使用，不能删除');
        }

        $name = (string) $customer->name;

        Db::transaction(function () use ($customer, $name, $id) {
            // 软删除并改写名称，释放唯一索引，便于重新创建同名客户
            $customer->save(['name' => $this->deletedName($name, $id)]);
            $customer->delete();
        });

        AuditService::record($this->userId(), 'customer_delete', 0, [
            'content' => '删除客户：' . $name,
        ]);

        return $this->success([], '客户已删除');
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
            throw new BizException('请输入客户名称');
        }

        if ($this->existsName($name, $excludeId)) {
            throw new BizException('客户名称已存在');
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
        $query = CustomerModel::whereRaw('LOWER(`name`) = ?', [$lower]);

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
     * 查询客户
     * @param int $id
     * @return CustomerModel
     * @throws BizException
     */
    protected function findCustomer(int $id): CustomerModel
    {
        $customer = CustomerModel::find($id);

        if (!$customer) {
            throw new BizException('客户不存在', 0, 404);
        }

        return $customer;
    }
}
