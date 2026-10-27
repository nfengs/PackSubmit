<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\exception\BizException;
use app\model\MasterLog;
use app\model\PackProposal;
use app\model\Supplier as SupplierModel;
use app\model\SupplierContact;
use app\model\SupplierPart;
use app\model\User;
use app\service\AuditService;
use app\service\MasterLogService;
use app\validate\SupplierValidate;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Db;
use think\response\Json;

/**
 * 管理员 - 供应商档案维护
 */
class Supplier extends BaseController
{
    /** 导入模板表头 */
    protected const HEADERS = ['供应商名称', '供应商代码', '供应商地址'];
    /**
     * 供应商档案列表
     * @return Json
     */
    public function index(): Json
    {
        $page     = max(1, (int) $this->request->param('page', 1));
        $pageSize = min(100, max(1, (int) $this->request->param('page_size', 10)));

        $query = SupplierModel::field('id,name,code,address,status,create_time,update_time');

        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $like = '%' . like_escape($keyword) . '%';
                $q->whereLike('name', $like)
                    ->whereOr('code', 'like', $like);
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
        $data = $this->request->only(['name', 'code', 'address', 'status']);
        $this->validate($data, SupplierValidate::class);

        $name = $this->normalizeName((string) $data['name']);
        $this->assertNameUnique($name);

        try {
            $supplier = SupplierModel::create([
                'name'    => $name,
                'code'    => (string) ($data['code'] ?? ''),
                'address' => (string) ($data['address'] ?? ''),
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
     * 名称变更时同步供应商账号的「部门/供应商」，保证供应商归属不断链
     * @param int $id
     * @return Json
     * @throws BizException
     * @throws \think\exception\ValidateException
     * @throws \Throwable
     */
    public function update(int $id): Json
    {
        $supplier = $this->findSupplier($id);
        $data     = $this->request->only(['name', 'code', 'address', 'status']);
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
                    'status'  => (int) ($data['status'] ?? $supplier->status),
                ]);

                if ($oldName !== $name) {
                    // 账号、指定零件号、方案都以供应商名称关联，改名需全部同步，避免方案丢失归属
                    User::where('dept', $oldName)->update(['dept' => $name]);
                    SupplierPart::where('supplier_name', $oldName)->update(['supplier_name' => $name]);
                    PackProposal::where('supplier', $oldName)->update(['supplier' => $name]);
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
     * Excel 批量导入供应商（名称重复按不区分大小写匹配，更新代码与地址）
     * 列顺序：供应商名称、供应商代码、供应商地址（首行表头自动识别）
     * @return Json
     * @throws BizException
     * @throws \Throwable
     */
    public function import(): Json
    {
        $rows = $this->readSupplierRows($this->request->file('file'));

        if ($rows === []) {
            throw new BizException('Excel 内容为空');
        }

        $operator = $this->currentUser();
        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($rows as $row) {
            $lineNo  = $row['line'];
            $name    = $row['name'];
            $code    = $row['code'];
            $address = $row['address'];

            if ($name === '') {
                $skipped++;
                $errors[] = "第 {$lineNo} 行：供应商名称为空";
                continue;
            }

            if (mb_strlen($name) > 100 || mb_strlen($code) > 50 || mb_strlen($address) > 255) {
                $skipped++;
                $errors[] = "第 {$lineNo} 行：内容超过字段长度限制";
                continue;
            }

            $name     = $this->normalizeName($name);
            $lower    = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
            $existing = SupplierModel::whereRaw('LOWER(`name`) = ?', [$lower])->find();

            if ($existing) {
                $before = $existing->toArray();

                $existing->save([
                    'code'    => $code,
                    'address' => $address,
                ]);

                $changes = MasterLogService::changes(MasterLog::BIZ_SUPPLIER, $before, $existing->toArray());

                if ($changes !== []) {
                    MasterLogService::record(
                        MasterLog::BIZ_SUPPLIER,
                        (int) $existing->id,
                        (string) $existing->name,
                        (int) $operator->id,
                        'supplier_update',
                        implode('；', $changes)
                    );
                }

                $updated++;
            } else {
                $supplier = SupplierModel::create([
                    'name'    => $name,
                    'code'    => $code,
                    'address' => $address,
                    'status'  => SupplierModel::STATUS_ENABLED,
                ]);

                MasterLogService::record(
                    MasterLog::BIZ_SUPPLIER,
                    (int) $supplier->id,
                    (string) $supplier->name,
                    (int) $operator->id,
                    'supplier_create',
                    MasterLogService::createContent(MasterLog::BIZ_SUPPLIER, $supplier->toArray())
                );

                $inserted++;
            }
        }

        AuditService::record((int) $operator->id, 'supplier_import', 0, [
            'content' => "批量导入供应商：新增 {$inserted} 条，更新 {$updated} 条，跳过 {$skipped} 条",
        ]);

        return $this->success([
            'inserted' => $inserted,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'errors'   => array_slice($errors, 0, 20),
        ], '导入完成');
    }

    /**
     * 下载供应商导入模板
     * @return \think\Response
     * @throws \Throwable
     */
    public function template(): \think\Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('供应商');

        foreach (self::HEADERS as $index => $title) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($column . '1', $title);
            $sheet->getColumnDimension($column)->setWidth(24);
        }

        $sheet->setCellValue('A2', '示例供应商');
        $sheet->setCellValue('B2', 'SUP001');
        $sheet->setCellValue('C2', '江苏省苏州市工业园区示例路 1 号');

        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getStyle('A1:C1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFEFF4FF');

        $file = tempnam(sys_get_temp_dir(), 'supplier_template_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($file);
        $spreadsheet->disconnectWorksheets();

        $content = (string) @file_get_contents($file);
        @unlink($file);

        return response($content)
            ->contentType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header([
                'Content-Disposition' => "attachment; filename*=UTF-8''" . rawurlencode('供应商导入模板.xlsx'),
                'Content-Length'      => (string) strlen($content),
            ]);
    }

    /**
     * 读取供应商导入 Excel
     * @param mixed $file
     * @return array<int, array{line:int,name:string,code:string,address:string}>
     * @throws BizException
     */
    protected function readSupplierRows($file): array
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

        if (mb_strpos((string) ($firstRow[0] ?? ''), '供应商') !== false) {
            $start = 1;
        }

        $list = [];

        foreach (array_slice($rows, $start) as $index => $row) {
            $cells = array_map(
                static fn ($cell) => trim((string) $cell),
                array_pad(array_slice($row, 0, 3), 3, '')
            );

            [$name, $code, $address] = $cells;

            if ($name === '' && $code === '' && $address === '') {
                continue;
            }

            $list[] = [
                'line'    => $start + $index + 1,
                'name'    => $name,
                'code'    => $code,
                'address' => $address,
            ];
        }

        return $list;
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
            throw new BizException('该供应商仍有供应商账号在用，不能删除');
        }

        $name = (string) $supplier->name;

        // 从属数据统计（删除前）
        $contactCount = SupplierContact::where('supplier_id', $id)->count();
        $partCount    = SupplierPart::where('supplier_name', $name)->count();

        Db::transaction(function () use ($supplier, $name, $id) {
            // 清理从属数据：避免已删除供应商的联系人、零件号继续被拉取，
            // 也避免同名供应商重新创建后继承旧数据
            SupplierContact::where('supplier_id', $id)->delete();
            SupplierPart::where('supplier_name', $name)->delete();

            // 软删除并改写名称，释放唯一索引，便于重新创建同名供应商
            $supplier->save(['name' => $this->deletedName($name, $id)]);
            $supplier->delete();
        });

        MasterLogService::record(
            MasterLog::BIZ_SUPPLIER,
            $id,
            $name,
            $this->userId(),
            'supplier_delete',
            sprintf('删除供应商；同时删除联系人 %d 条、指定零件号 %d 条', $contactCount, $partCount)
        );

        AuditService::record($this->userId(), 'supplier_delete', 0, [
            'content' => sprintf(
                '删除供应商：%s（联系人 %d 条、零件号 %d 条）',
                $name,
                $contactCount,
                $partCount
            ),
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
