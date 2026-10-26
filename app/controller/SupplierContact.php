<?php
declare (strict_types = 1);

namespace app\controller;

use app\BaseController;
use app\exception\BizException;
use app\model\Supplier;
use app\model\SupplierContact as SupplierContactModel;
use app\model\User;
use app\validate\SupplierContactValidate;
use think\facade\Db;
use think\response\Json;

/**
 * 供应商联系人（供应商自行维护，可增删改；供应商只能维护自己所属供应商）
 */
class SupplierContact extends BaseController
{
    /**
     * 联系人列表
     * 供应商：固定返回自己所属供应商的联系人
     * 管理员：可按 supplier_id / supplier 名称过滤，不传则返回全部
     * @return Json
     */
    public function index(): Json
    {
        $user = $this->currentUser();

        if ((int) $user->role === User::ROLE_REVIEWER) {
            throw new BizException('评审员无权访问供应商联系人', 403, 403);
        }

        $query = SupplierContactModel::with(['supplier']);
        $owner = null;

        if ((int) $user->role === User::ROLE_SUBMITTER) {
            $owner = Supplier::findByUser($user);

            if (!$owner) {
                return $this->success(['list' => [], 'supplier' => null]);
            }

            $query->where('supplier_id', (int) $owner->id);
        } else {
            $supplierId = (int) $this->request->param('supplier_id', 0);
            $supplierName = trim((string) $this->request->param('supplier', ''));

            if ($supplierId > 0) {
                $query->where('supplier_id', $supplierId);
                $owner = Supplier::find($supplierId);
            } elseif ($supplierName !== '') {
                $owner = Supplier::where('name', $supplierName)->find();
                $query->where('supplier_id', $owner ? (int) $owner->id : 0);
            }

            $keyword = trim((string) $this->request->param('keyword', ''));
            if ($keyword !== '') {
                $like = '%' . $keyword . '%';
                $query->where(function ($q) use ($like) {
                    $q->whereLike('name', $like)
                        ->whereOr('phone', 'like', $like)
                        ->whereOr('email', 'like', $like);
                });
            }
        }

        $list = $query->order('is_default', 'desc')->order('id', 'asc')->select()->toArray();

        return $this->success([
            'list'     => $list,
            'supplier' => $owner ? [
                'id'   => (int) $owner->id,
                'name' => (string) $owner->name,
                'code' => (string) $owner->code,
            ] : null,
        ]);
    }

    /**
     * 新增联系人
     * @return Json
     */
    public function save(): Json
    {
        $user = $this->currentUser();
        $this->assertManageable($user);

        $data = $this->request->only(['supplier_id', 'supplier', 'name', 'phone', 'email', 'fax', 'is_default']);
        $this->validate($data, SupplierContactValidate::class);

        $supplier = $this->resolveSupplier($data, $user);

        $count = SupplierContactModel::where('supplier_id', (int) $supplier->id)->count();
        // 首个联系人自动设为默认；显式指定默认时取消其他默认
        $isDefault = !empty($data['is_default']) || $count === 0 ? 1 : 0;

        $contact = Db::transaction(function () use ($data, $supplier, $isDefault) {
            if ($isDefault === 1) {
                SupplierContactModel::where('supplier_id', (int) $supplier->id)
                    ->update(['is_default' => SupplierContactModel::DEFAULT_NO]);
            }

            return SupplierContactModel::create([
                'supplier_id' => (int) $supplier->id,
                'name'        => trim((string) ($data['name'] ?? '')),
                'phone'       => trim((string) ($data['phone'] ?? '')),
                'email'       => trim((string) ($data['email'] ?? '')),
                'fax'         => trim((string) ($data['fax'] ?? '')),
                'is_default'  => $isDefault,
            ]);
        });

        return $this->success($contact->toArray(), '联系人已新增');
    }

    /**
     * 编辑联系人
     * @param int $id
     * @return Json
     */
    public function update(int $id): Json
    {
        $user = $this->currentUser();
        $this->assertManageable($user);

        $contact = SupplierContactModel::find($id);

        if (!$contact) {
            throw new BizException('联系人不存在');
        }

        $data = $this->request->only(['supplier_id', 'supplier', 'name', 'phone', 'email', 'fax', 'is_default']);
        $this->validate($data, SupplierContactValidate::class);

        $supplier = $this->resolveSupplier($data, $user);

        if ((int) $contact->supplier_id !== (int) $supplier->id) {
            throw new BizException('无权操作该联系人', 403, 403);
        }

        $isDefault = !empty($data['is_default']) ? 1 : 0;

        Db::transaction(function () use ($contact, $data, $supplier, $isDefault) {
            if ($isDefault === 1) {
                SupplierContactModel::where('supplier_id', (int) $supplier->id)
                    ->where('id', '<>', (int) $contact->id)
                    ->update(['is_default' => SupplierContactModel::DEFAULT_NO]);
            }

            $contact->save([
                'name'       => trim((string) ($data['name'] ?? '')),
                'phone'      => trim((string) ($data['phone'] ?? '')),
                'email'      => trim((string) ($data['email'] ?? '')),
                'fax'        => trim((string) ($data['fax'] ?? '')),
                'is_default' => $isDefault,
            ]);

            $this->ensureDefault((int) $supplier->id);
        });

        return $this->success($contact->toArray(), '联系人已更新');
    }

    /**
     * 删除联系人（删除默认联系人后自动把最早一条设为默认）
     * @param int $id
     * @return Json
     */
    public function delete(int $id): Json
    {
        $user = $this->currentUser();
        $this->assertManageable($user);

        $contact = SupplierContactModel::find($id);

        if (!$contact) {
            throw new BizException('联系人不存在');
        }

        $supplier = $this->resolveSupplier(['supplier_id' => (int) $contact->supplier_id], $user);

        if ((int) $contact->supplier_id !== (int) $supplier->id) {
            throw new BizException('无权操作该联系人', 403, 403);
        }

        Db::transaction(function () use ($contact, $supplier) {
            $contact->delete();

            $this->ensureDefault((int) $supplier->id);
        });

        return $this->success([], '联系人已删除');
    }

    /**
     * 保证每个供应商至少有一条默认联系人
     * @param int $supplierId
     * @return void
     */
    private function ensureDefault(int $supplierId): void
    {
        $exists = SupplierContactModel::where('supplier_id', $supplierId)
            ->where('is_default', SupplierContactModel::DEFAULT_YES)
            ->count();

        if ($exists > 0) {
            return;
        }

        $first = SupplierContactModel::where('supplier_id', $supplierId)
            ->order('id', 'asc')
            ->find();

        if ($first) {
            $first->save(['is_default' => SupplierContactModel::DEFAULT_YES]);
        }
    }

    /**
     * 仅供应商与管理员可维护联系人
     * @param User $user
     * @return void
     * @throws BizException
     */
    private function assertManageable(User $user): void
    {
        if ((int) $user->role === User::ROLE_REVIEWER) {
            throw new BizException('评审员无权维护供应商联系人', 403, 403);
        }
    }

    /**
     * 解析本次操作对应的供应商
     * 供应商强制为自己所属供应商；管理员需显式指定 supplier_id 或 supplier 名称
     * @param array $data
     * @param User  $user
     * @return Supplier
     * @throws BizException
     */
    private function resolveSupplier(array $data, User $user): Supplier
    {
        if ((int) $user->role === User::ROLE_SUBMITTER) {
            $own = Supplier::findByUser($user);

            if (!$own) {
                throw new BizException('当前账号未关联供应商档案，请联系管理员');
            }

            return $own;
        }

        $supplierId = (int) ($data['supplier_id'] ?? 0);

        if ($supplierId > 0) {
            $supplier = Supplier::find($supplierId);

            if (!$supplier) {
                throw new BizException('供应商不存在');
            }

            return $supplier;
        }

        $name = trim((string) ($data['supplier'] ?? ''));

        if ($name !== '') {
            $supplier = Supplier::where('name', $name)->find();

            if ($supplier) {
                return $supplier;
            }
        }

        throw new BizException('请选择供应商');
    }
}
