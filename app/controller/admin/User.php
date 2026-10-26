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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\response\Json;

/**
 * 管理员 - 用户管理
 */
class User extends BaseController
{
    /** 导入模板表头 */
    protected const HEADERS = ['登录账号', '姓名', '角色', '部门/供应商', '联系电话', '初始密码'];
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
     * Excel 批量导入用户
     * 列顺序：登录账号、姓名、角色、部门/供应商、联系电话、初始密码（首行表头自动识别）
     * 账号已存在则更新（可选重置密码），不存在则新增（必须提供初始密码）
     * @return Json
     * @throws BizException
     * @throws \Throwable
     */
    public function import(): Json
    {
        $rows = $this->readUserRows($this->request->file('file'));

        if ($rows === []) {
            throw new BizException('Excel 内容为空');
        }

        $operator      = $this->currentUser();
        $supplierNames = [];

        foreach (Supplier::column('name') as $name) {
            $supplierNames[mb_strtolower((string) $name, 'UTF-8')] = (string) $name;
        }

        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($rows as $row) {
            $lineNo   = $row['line'];
            $username = $row['username'];
            $realName = $row['real_name'];
            $role     = $this->parseRole($row['role']);
            $dept     = trim($row['dept']);
            $phone    = trim($row['phone']);
            $password = trim($row['password']);

            if ($username === '') {
                $skipped++;
                $errors[] = "第 {$lineNo} 行：登录账号为空";
                continue;
            }

            if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
                $skipped++;
                $errors[] = "第 {$lineNo} 行：账号「{$username}」只能由字母、数字、下划线组成";
                continue;
            }

            if (mb_strlen($username) > 50) {
                $skipped++;
                $errors[] = "第 {$lineNo} 行：账号长度不能超过 50 位";
                continue;
            }

            if ($realName === '') {
                $skipped++;
                $errors[] = "第 {$lineNo} 行：姓名为空";
                continue;
            }

            if (mb_strlen($realName) > 50) {
                $skipped++;
                $errors[] = "第 {$lineNo} 行：姓名长度不能超过 50 位";
                continue;
            }

            if ($role === null) {
                $skipped++;
                $errors[] = "第 {$lineNo} 行：角色不合法（应填 1供应商 / 2评审员 / 3管理员）";
                continue;
            }

            if ($password !== '' && (mb_strlen($password) < 6 || mb_strlen($password) > 32)) {
                $skipped++;
                $errors[] = "第 {$lineNo} 行：密码长度须为 6–32 位";
                continue;
            }

            if ($role === UserModel::ROLE_SUBMITTER) {
                if ($dept === '') {
                    $skipped++;
                    $errors[] = "第 {$lineNo} 行：供应商必须填写所属供应商";
                    continue;
                }

                $supplierKey = mb_strtolower($dept, 'UTF-8');

                if (!isset($supplierNames[$supplierKey])) {
                    $skipped++;
                    $errors[] = "第 {$lineNo} 行：供应商「{$dept}」不存在，请先维护供应商档案";
                    continue;
                }

                $dept = $supplierNames[$supplierKey];
            }

            if (mb_strlen($phone) > 30) {
                $skipped++;
                $errors[] = "第 {$lineNo} 行：联系电话长度不能超过 30 位";
                continue;
            }

            $existing = UserModel::where('username', $username)->find();

            if ($existing) {
                $before = $existing->toArray();

                $existing->save([
                    'real_name' => $realName,
                    'role'      => $role,
                    'dept'      => $dept,
                    'phone'     => $phone,
                ]);

                if ($password !== '') {
                    $existing->save(['password' => $password]);
                }

                $changes = MasterLogService::changes(MasterLog::BIZ_USER, $before, $existing->toArray());

                if ($changes !== []) {
                    MasterLogService::record(
                        MasterLog::BIZ_USER,
                        (int) $existing->id,
                        (string) $existing->username,
                        (int) $operator->id,
                        'user_update',
                        implode('；', $changes)
                    );
                }

                if ($password !== '') {
                    MasterLogService::record(
                        MasterLog::BIZ_USER,
                        (int) $existing->id,
                        (string) $existing->username,
                        (int) $operator->id,
                        'user_reset',
                        '批量导入重置登录密码'
                    );
                }

                $updated++;
            } else {
                if ($password === '') {
                    $skipped++;
                    $errors[] = "第 {$lineNo} 行：新账号必须填写初始密码";
                    continue;
                }

                $user = UserModel::create([
                    'username'  => $username,
                    'password'  => $password,
                    'real_name' => $realName,
                    'role'      => $role,
                    'dept'      => $dept,
                    'phone'     => $phone,
                    'status'    => UserModel::STATUS_ENABLED,
                ]);

                MasterLogService::record(
                    MasterLog::BIZ_USER,
                    (int) $user->id,
                    (string) $user->username,
                    (int) $operator->id,
                    'user_create',
                    MasterLogService::createContent(MasterLog::BIZ_USER, $user->toArray())
                );

                $inserted++;
            }
        }

        AuditService::record((int) $operator->id, 'user_import', 0, [
            'content' => "批量导入用户：新增 {$inserted} 条，更新 {$updated} 条，跳过 {$skipped} 条",
        ]);

        return $this->success([
            'inserted' => $inserted,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'errors'   => array_slice($errors, 0, 20),
        ], '导入完成');
    }

    /**
     * 下载用户导入模板
     * @return \think\Response
     * @throws \Throwable
     */
    public function template(): \think\Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('用户');

        foreach (self::HEADERS as $index => $title) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($column . '1', $title);
            $sheet->getColumnDimension($column)->setWidth(20);
        }

        $sheet->setCellValue('A2', 'submitter01');
        $sheet->setCellValue('B2', '张三');
        $sheet->setCellValue('C2', '1');
        $sheet->setCellValue('D2', '示例供应商');
        $sheet->setCellValue('E2', '13800000000');
        $sheet->setCellValue('F2', '123456');

        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A1:F1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFEFF4FF');

        // 填写说明
        $help = $spreadsheet->createSheet();
        $help->setTitle('填写说明');
        $helpRows = [
            ['说明', '内容'],
            ['角色', '1=供应商，2=评审员，3=管理员（也可填写中文：供应商 / 评审员 / 管理员）'],
            ['部门/供应商', '仅角色为“供应商”时必填，且必须与供应商档案中的名称一致'],
            ['初始密码', '新增账号必填，长度 6–32 位；已有账号可留空（留空不修改密码），填写则重置密码'],
            ['已有账号', '账号已存在时会更新姓名 / 角色 / 部门 / 电话，并可选重置密码'],
        ];

        foreach ($helpRows as $rowIndex => $cells) {
            foreach ($cells as $colIndex => $value) {
                $help->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 1) . ($rowIndex + 1), $value);
            }
        }

        $help->getColumnDimension('A')->setWidth(16);
        $help->getColumnDimension('B')->setWidth(70);
        $help->getStyle('A1:B1')->getFont()->setBold(true);

        $spreadsheet->setActiveSheetIndex(0);

        $file = tempnam(sys_get_temp_dir(), 'user_template_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($file);
        $spreadsheet->disconnectWorksheets();

        $content = (string) @file_get_contents($file);
        @unlink($file);

        return response($content)
            ->contentType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header([
                'Content-Disposition' => "attachment; filename*=UTF-8''" . rawurlencode('用户导入模板.xlsx'),
                'Content-Length'      => (string) strlen($content),
            ]);
    }

    /**
     * 读取用户导入 Excel
     * @param mixed $file
     * @return array<int, array{line:int,username:string,real_name:string,role:string,dept:string,phone:string,password:string}>
     * @throws BizException
     */
    protected function readUserRows($file): array
    {
        if (!$file) {
            throw new BizException('请上传 Excel 文件');
        }

        $ext = strtolower((string) $file->getOriginalExtension());

        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            throw new BizException('仅支持 .xlsx / .xls 格式');
        }

        if ((int) $file->getSize() > 10 * 1024 * 1024) {
            throw new BizException('文件大小不能超过 10MB');
        }

        try {
            $reader = IOFactory::createReader($ext === 'xls' ? 'Xls' : 'Xlsx');
            $reader->setReadDataOnly(true);
            $rows = $reader->load($file->getPathname())->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            throw new BizException('Excel 解析失败：' . $e->getMessage());
        }

        if (!$rows) {
            throw new BizException('Excel 内容为空');
        }

        $start    = 0;
        $firstRow = array_map(static fn ($cell) => trim((string) $cell), $rows[0] ?? []);

        if (mb_strpos((string) ($firstRow[0] ?? ''), '账号') !== false) {
            $start = 1;
        }

        $list = [];

        foreach (array_slice($rows, $start) as $index => $row) {
            $cells = array_map(
                static fn ($cell) => trim((string) $cell),
                array_pad(array_slice($row, 0, 6), 6, '')
            );

            [$username, $realName, $role, $dept, $phone, $password] = $cells;

            if ($username === '' && $realName === '' && $role === '' && $dept === '' && $phone === '' && $password === '') {
                continue;
            }

            $list[] = [
                'line'      => $start + $index + 1,
                'username'  => $username,
                'real_name' => $realName,
                'role'      => $role,
                'dept'      => $dept,
                'phone'     => $phone,
                'password'  => $password,
            ];
        }

        return $list;
    }

    /**
     * 角色文字 / 数字 → 角色值
     * @param string $value
     * @return int|null
     */
    protected function parseRole(string $value): ?int
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (in_array($value, ['1', '2', '3'], true)) {
            return (int) $value;
        }

        $map = [
            '供应商' => UserModel::ROLE_SUBMITTER,
            '评审员' => UserModel::ROLE_REVIEWER,
            '管理员' => UserModel::ROLE_ADMIN,
            'submitter' => UserModel::ROLE_SUBMITTER,
            'reviewer'  => UserModel::ROLE_REVIEWER,
            'admin'     => UserModel::ROLE_ADMIN,
        ];

        return $map[$value] ?? null;
    }

    /**
     * 供应商/供应商账号的供应商必须来自供应商档案
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
            throw new BizException('请选择供应商所属供应商');
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
