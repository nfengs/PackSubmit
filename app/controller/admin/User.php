<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\exception\BizException;
use app\model\MasterLog;
use app\model\Supplier;
use app\model\User as UserModel;
use app\service\AuditService;
use app\service\MasterLogService;
use app\validate\UserValidate;
use think\response\Json;

/**
 * 管理员 - 用户管理
 */
class User extends BaseController
{
    /**
     * 用户列表
     * @return Json
     */
    public function index(): Json
    {
        $page     = max(1, (int) $this->request->param('page', 1));
        $pageSize = min(100, max(1, (int) $this->request->param('page_size', 10)));

        $query = UserModel::field('id,username,real_name,role,dept,phone,status,create_time,update_time');

        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $like = '%' . $keyword . '%';
                $q->whereLike('username', $like)
                    ->whereOr('real_name', 'like', $like)
                    ->whereOr('dept', 'like', $like);
            });
        }

        $role = $this->request->param('role', '');
        if ($role !== '' && $role !== null) {
            $query->where('role', (int) $role);
        }

        $status = $this->request->param('status', '');
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
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
     * 新增用户
     * @return Json
     * @throws BizException
     * @throws \think\exception\ValidateException
     */
    public function save(): Json
    {
        $data = $this->request->only(['username', 'password', 'real_name', 'role', 'dept', 'phone']);
        $this->validate($data, UserValidate::class . '.create');

        if (UserModel::where('username', $data['username'])->find()) {
            throw new BizException('登录账号已存在');
        }

        $this->assertSupplierDept($data);

        $user = UserModel::create([
            'username'  => $data['username'],
            'password'  => $data['password'],
            'real_name' => $data['real_name'],
            'role'      => (int) $data['role'],
            'dept'      => (string) ($data['dept'] ?? ''),
            'phone'     => (string) ($data['phone'] ?? ''),
            'status'    => UserModel::STATUS_ENABLED,
        ]);

        AuditService::record($this->userId(), 'user_create', 0, [
            'content' => '新增用户：' . $user->username . '（' . $user->real_name . '）',
        ]);

        // 首次录入写入维护日志（不记录密码）
        MasterLogService::record(
            MasterLog::BIZ_USER,
            (int) $user->id,
            (string) $user->username,
            $this->userId(),
            'user_create',
            MasterLogService::createContent(MasterLog::BIZ_USER, $user->toArray())
        );

        return $this->success($user->toArray(), '用户创建成功');
    }

    /**
     * 编辑用户
     * @param int $id
     * @return Json
     * @throws BizException
     * @throws \think\exception\ValidateException
     */
    public function update(int $id): Json
    {
        $user = $this->findUser($id);
        $data = $this->request->only(['real_name', 'role', 'dept', 'phone', 'status']);
        $this->validate($data, UserValidate::class . '.update');

        $this->assertSupplierDept($data);

        if ($id === $this->userId()) {
            if (isset($data['role']) && (int) $data['role'] !== (int) $user->role) {
                throw new BizException('不能修改自己的角色');
            }
            if (isset($data['status']) && (int) $data['status'] !== (int) $user->status) {
                throw new BizException('不能修改自己的启用状态');
            }
        }

        $before = $user->toArray();

        $user->save([
            'real_name' => $data['real_name'],
            'role'      => (int) $data['role'],
            'dept'      => (string) ($data['dept'] ?? ''),
            'phone'     => (string) ($data['phone'] ?? ''),
            'status'    => (int) ($data['status'] ?? $user->status),
        ]);

        AuditService::record($this->userId(), 'user_update', 0, [
            'content' => '编辑用户：' . $user->username,
        ]);

        $changes = MasterLogService::changes(MasterLog::BIZ_USER, $before, $user->toArray());

        if ($changes !== []) {
            MasterLogService::record(
                MasterLog::BIZ_USER,
                (int) $user->id,
                (string) $user->username,
                $this->userId(),
                'user_update',
                implode('；', $changes)
            );
        }

        return $this->success($user->toArray(), '用户更新成功');
    }

    /**
     * 启用/禁用用户
     * @param int $id
     * @return Json
     * @throws BizException
     */
    public function status(int $id): Json
    {
        $status = (int) $this->request->param('status', -1);

        if (!in_array($status, [UserModel::STATUS_DISABLED, UserModel::STATUS_ENABLED], true)) {
            throw new BizException('状态参数不合法');
        }

        if ($id === $this->userId() && $status === UserModel::STATUS_DISABLED) {
            throw new BizException('不能禁用当前登录账号');
        }

        $user         = $this->findUser($id);
        $beforeStatus = (int) $user->status;
        $user->save(['status' => $status]);

        AuditService::record($this->userId(), 'user_status', 0, [
            'content' => ($status === UserModel::STATUS_ENABLED ? '启用' : '禁用') . '用户：' . $user->username,
        ]);

        if ($beforeStatus !== $status) {
            MasterLogService::record(
                MasterLog::BIZ_USER,
                (int) $user->id,
                (string) $user->username,
                $this->userId(),
                'user_status',
                '状态：' . ($beforeStatus === UserModel::STATUS_ENABLED ? '启用' : '禁用')
                    . ' → ' . ($status === UserModel::STATUS_ENABLED ? '启用' : '禁用')
            );
        }

        return $this->success($user->toArray(), $status === UserModel::STATUS_ENABLED ? '已启用' : '已禁用');
    }

    /**
     * 重置密码
     * @param int $id
     * @return Json
     * @throws BizException
     * @throws \think\exception\ValidateException
     */
    public function resetPassword(int $id): Json
    {
        $data = $this->request->only(['password']);
        $this->validate($data, UserValidate::class . '.reset');

        $user = $this->findUser($id);
        $user->save(['password' => $data['password']]);

        AuditService::record($this->userId(), 'user_reset', 0, [
            'content' => '重置用户密码：' . $user->username,
        ]);

        MasterLogService::record(
            MasterLog::BIZ_USER,
            (int) $user->id,
            (string) $user->username,
            $this->userId(),
            'user_reset',
            '重置登录密码'
        );

        return $this->success([], '密码重置成功');
    }

    /**
     * 用户维护日志
     * @param int $id
     * @return Json
     * @throws BizException
     */
    public function logs(int $id): Json
    {
        $user = $this->findUser($id);

        $logs = MasterLog::with(['operator'])
            ->where('biz_type', MasterLog::BIZ_USER)
            ->where('target_id', (int) $user->id)
            ->order('id', 'desc')
            ->limit(100)
            ->select();

        return $this->success($logs->toArray());
    }

    /**
     * 供应商/提交人账号的供应商必须来自供应商档案
     * @param array $data
     * @return void
     * @throws BizException
     */
    protected function assertSupplierDept(array $data): void
    {
        if ((int) ($data['role'] ?? 0) !== UserModel::ROLE_SUBMITTER) {
            return;
        }

        $dept = trim((string) ($data['dept'] ?? ''));

        if ($dept === '') {
            throw new BizException('请选择提交人所属供应商');
        }

        if (!Supplier::where('name', $dept)->find()) {
            throw new BizException('供应商不存在，请从供应商档案中选择');
        }
    }

    /**
     * 查询用户
     * @param int $id
     * @return UserModel
     * @throws BizException
     */
    protected function findUser(int $id): UserModel
    {
        $user = UserModel::find($id);

        if (!$user) {
            throw new BizException('用户不存在', 0, 404);
        }

        return $user;
    }
}
