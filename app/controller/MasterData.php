<?php
declare (strict_types = 1);

namespace app\controller;

use app\BaseController;
use app\model\Customer;
use app\model\Supplier;
use app\model\SupplierPart;
use app\model\User;
use think\response\Json;

/**
 * 主数据读取控制器（客户 / 供应商档案，登录用户可用）
 * 表单选择客户或供应商后，直接用返回的资料回填地址、联系人、电话、邮箱、传真等
 */
class MasterData extends BaseController
{
    /**
     * 客户档案列表
     * @return Json
     */
    public function customers(): Json
    {
        $list = Customer::where('status', Customer::STATUS_ENABLED)
            ->field('id,name,address,contact,phone,email,fax')
            ->order('name')
            ->select()
            ->toArray();

        return $this->success($list);
    }

    /**
     * 供应商档案列表
     * @return Json
     */
    public function suppliers(): Json
    {
        $list = Supplier::where('status', Supplier::STATUS_ENABLED)
            ->field('id,name,code,address')
            ->order('name')
            ->select()
            ->toArray();

        return $this->success($list);
    }

    /**
     * 供应商指定零件号（供应商未指定 supplier 时默认取自己所属供应商）
     * @return Json
     */
    public function parts(): Json
    {
        $user     = $this->currentUser();
        $supplier = trim((string) $this->request->param('supplier', ''));

        if ($supplier === '' && (int) $user->role === User::ROLE_SUBMITTER) {
            $own      = Supplier::findByUser($user);
            $supplier = $own?->name ?? trim((string) $user->dept);
        }

        if ($supplier === '') {
            return $this->success([]);
        }

        $list = SupplierPart::where('supplier_name', $supplier)
            ->field('id,supplier_name,project_name,part_no,part_name')
            ->order('project_name')
            ->order('part_no')
            ->select()
            ->toArray();

        return $this->success($list);
    }

    /**
     * 当前登录用户所属供应商档案
     * 供应商账号的「部门/供应商」(pf_user.dept) 即供应商名称；未匹配到档案时仅返回名称
     * @return Json
     */
    public function mySupplier(): Json
    {
        $user     = $this->currentUser();
        $supplier = Supplier::findByUser($user);

        if ($supplier) {
            return $this->success($supplier->toArray());
        }

        return $this->success([
            'id'      => 0,
            'name'    => trim((string) $user->dept),
            'code'    => '',
            'address' => '',
        ]);
    }
}
