<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\exception\BizException;
use app\model\PartLog;
use app\model\Supplier;
use app\model\SupplierPart as SupplierPartModel;
use app\model\User;
use app\service\AuditService;
use app\service\ProposalService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Db;
use think\response\Json;

/**
 * 管理员 - 供应商指定零件号维护（Excel 导入）
 */
class SupplierPart extends BaseController
{
    /** 导入模板表头 */
    protected const HEADERS = ['供应商名称', '项目名称', '零件号', '零件名称'];

    /**
     * 指定零件号列表
     * @return Json
     */
    public function index(): Json
    {
        $page     = max(1, (int) $this->request->param('page', 1));
        $pageSize = min(200, max(1, (int) $this->request->param('page_size', 10)));

        $query = SupplierPartModel::field('id,supplier_name,project_name,part_no,part_name,create_time,update_time');

        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $like = '%' . $keyword . '%';
                $q->whereLike('part_no', $like)
                    ->whereOr('part_name', 'like', $like)
                    ->whereOr('project_name', 'like', $like);
            });
        }

        $supplier = trim((string) $this->request->param('supplier', ''));
        if ($supplier !== '') {
            $query->where('supplier_name', $supplier);
        }

        $total = (clone $query)->count();
        $list  = $query->order('supplier_name')->order('part_no')->page($page, $pageSize)->select();

        return $this->success([
            'list'      => $list->toArray(),
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * Excel 导入
     * 第一列供应商名称、第二列项目名称、第三列零件号、第四列零件名称（首行表头自动识别）
     * @return Json
     * @throws BizException
     * @throws \Throwable
     */
    public function import(): Json
    {
        $rows = $this->readPartRows($this->request->file('file'));

        if ($rows === []) {
            throw new BizException('Excel 内容为空');
        }

        // 供应商名称按不区分大小写匹配，并统一为档案中的标准名称
        $suppliers = $this->supplierNameMap();

        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;
        $drafts   = 0;
        $errors   = [];
        $warnings = [];
        $operator = $this->currentUser();

        // 供应商提交人缓存：方案草稿归属该供应商的提交人账号
        $ownerCache = [];
        $ownerOf = static function (string $supplierName) use (&$ownerCache): ?User {
            if (!array_key_exists($supplierName, $ownerCache)) {
                $ownerCache[$supplierName] = User::where('role', User::ROLE_SUBMITTER)
                    ->where('dept', $supplierName)
                    ->where('status', User::STATUS_ENABLED)
                    ->order('id')
                    ->find();
            }

            return $ownerCache[$supplierName];
        };

        foreach ($rows as $row) {
            $lineNo       = $row['line'];
            $supplierName = $row['supplier_name'];
            $projectName  = $row['project_name'];
            $partNo       = $row['part_no'];
            $partName     = $row['part_name'];

            if ($supplierName === '' || $partNo === '') {
                $skipped++;
                $errors[] = "第 {$lineNo} 行：供应商名称或零件号为空";
                continue;
            }

            if (mb_strlen($supplierName) > 100 || mb_strlen($projectName) > 150
                || mb_strlen($partNo) > 100 || mb_strlen($partName) > 200) {
                $skipped++;
                $errors[] = "第 {$lineNo} 行：内容超过字段长度限制";
                continue;
            }

            $supplierKey = mb_strtolower($supplierName, 'UTF-8');

            if (!isset($suppliers[$supplierKey])) {
                $skipped++;
                $errors[] = "第 {$lineNo} 行：供应商「{$supplierName}」不存在";
                continue;
            }

            // 统一使用档案中的标准名称
            $supplierName = $suppliers[$supplierKey];

            $part = SupplierPartModel::where('supplier_name', $supplierName)
                ->where('part_no', $partNo)
                ->find();

            if ($part) {
                $changes = $this->partChanges($part, $projectName, $partName);

                $part->save([
                    'project_name' => $projectName,
                    'part_name'    => $partName,
                ]);
                $updated++;

                // 导入时项目/零件名称发生变化也要留痕，并同步已生成的方案
                if ($changes !== []) {
                    ProposalService::syncPartInfo($supplierName, $partNo, $projectName, $partName);

                    PartLog::create([
                        'supplier_name' => $supplierName,
                        'part_no'       => $partNo,
                        'user_id'       => (int) $operator->id,
                        'action'        => 'part_update',
                        'action_name'   => '导入修改零件号信息',
                        'content'       => implode('；', $changes),
                    ]);
                }
            } else {
                $part = SupplierPartModel::create([
                    'supplier_name' => $supplierName,
                    'project_name'  => $projectName,
                    'part_no'       => $partNo,
                    'part_name'     => $partName,
                ]);
                $inserted++;

                // 首次导入的零件号写入修改日志
                PartLog::create([
                    'supplier_name' => $supplierName,
                    'part_no'       => $partNo,
                    'user_id'       => (int) $operator->id,
                    'action'        => 'part_create',
                    'action_name'   => '导入新增零件号',
                    'content'       => '新增零件号：项目名称 ' . self::showValue($projectName)
                        . '；零件名称 ' . self::showValue($partName),
                ]);
            }

            // 导入即生成方案草稿：已有有效方案（含已归档）的零件不重复生成
            if (ProposalService::hasPartProposal($supplierName, $partNo)) {
                continue;
            }

            $owner = $ownerOf($supplierName);

            if (!$owner) {
                $warnings[] = "第 {$lineNo} 行：供应商「{$supplierName}」未绑定提交人账号，未生成方案草稿";
                continue;
            }

            if (ProposalService::createDraftFromPart($part, $owner, $operator)) {
                $drafts++;
            }
        }

        AuditService::record($this->userId(), 'part_import', 0, [
            'content' => "导入指定零件号：新增 {$inserted} 条，更新 {$updated} 条，跳过 {$skipped} 条，生成方案草稿 {$drafts} 条",
        ]);

        return $this->success([
            'inserted' => $inserted,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'drafts'   => $drafts,
            'errors'   => array_slice($errors, 0, 20),
            'warnings' => array_slice($warnings, 0, 20),
        ], '导入完成');
    }

    /**
     * 批量修改 - 上传 Excel 校验预览（不写库）
     * Excel 格式与导入一致：供应商名称、项目名称、零件号、零件名称
     * @return Json
     * @throws BizException
     */
    public function batchPreview(): Json
    {
        $rows = $this->readPartRows($this->request->file('file'));

        if ($rows === []) {
            throw new BizException('Excel 内容为空');
        }

        $suppliers = $this->supplierNameMap();
        $preview   = [];
        $seen      = [];
        $summary   = ['update' => 0, 'unchanged' => 0, 'not_found' => 0, 'error' => 0];

        foreach ($rows as $row) {
            $supplierName = $row['supplier_name'];
            $partNo       = $row['part_no'];
            $projectName  = $row['project_name'];
            $partName     = $row['part_name'];

            $item = [
                'line'                 => $row['line'],
                'supplier_name'        => $supplierName,
                'part_no'              => $partNo,
                'project_name'         => $projectName,
                'part_name'            => $partName,
                'current_project_name' => '',
                'current_part_name'    => '',
                'changes'              => '',
                'status'               => 'error',
                'message'              => '',
            ];

            if ($supplierName === '' || $partNo === '') {
                $item['message'] = '供应商名称或零件号为空';
            } elseif (mb_strlen($supplierName) > 100 || mb_strlen($projectName) > 150
                || mb_strlen($partNo) > 100 || mb_strlen($partName) > 200) {
                $item['message'] = '内容超过字段长度限制';
            } else {
                $supplierKey = mb_strtolower($supplierName, 'UTF-8');

                if (!isset($suppliers[$supplierKey])) {
                    $item['message'] = "供应商「{$supplierName}」不存在";
                } else {
                    // 统一使用档案中的标准名称
                    $supplierName = $suppliers[$supplierKey];
                    $item['supplier_name'] = $supplierName;

                    $key = $supplierName . '|' . $partNo;

                    if (isset($seen[$key])) {
                        $item['message'] = '与第 ' . $seen[$key] . ' 行重复（同一供应商零件号唯一）';
                    } else {
                        $seen[$key] = $row['line'];

                        $part = SupplierPartModel::where('supplier_name', $supplierName)
                            ->where('part_no', $partNo)
                            ->find();

                        if (!$part) {
                            $item['status']  = 'not_found';
                            $item['message'] = '该供应商下不存在此零件号，需通过导入新增';
                        } else {
                            $item['current_project_name'] = (string) $part->project_name;
                            $item['current_part_name']    = (string) $part->part_name;
                            $changes = $this->partChanges($part, $projectName, $partName);

                            if ($changes === []) {
                                $item['status']  = 'unchanged';
                                $item['message'] = '无变化';
                            } else {
                                $item['status']  = 'update';
                                $item['changes'] = implode('；', $changes);
                            }
                        }
                    }
                }
            }

            $summary[$item['status']]++;
            $preview[] = $item;
        }

        return $this->success([
            'rows'    => $preview,
            'summary' => $summary,
        ], '校验完成，请确认批量修改内容');
    }

    /**
     * 批量修改项目名称 / 零件名称（仅更新已存在的供应商 + 零件号）
     * @return Json
     * @throws BizException
     * @throws \Throwable
     */
    public function batchUpdate(): Json
    {
        $rows = $this->request->param('rows', []);

        if (!is_array($rows) || $rows === []) {
            throw new BizException('请先上传 Excel 并校验');
        }

        $suppliers = $this->supplierNameMap();
        $userId    = $this->userId();

        $updated   = 0;
        $unchanged = 0;
        $notFound  = 0;
        $invalid   = 0;
        $seen      = [];

        Db::transaction(function () use (
            $rows,
            $suppliers,
            $userId,
            &$updated,
            &$unchanged,
            &$notFound,
            &$invalid,
            &$seen
        ) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    $invalid++;
                    continue;
                }

                $supplierInput = trim((string) ($row['supplier_name'] ?? ''));
                $partNo        = trim((string) ($row['part_no'] ?? ''));
                $projectName   = trim((string) ($row['project_name'] ?? ''));
                $partName      = trim((string) ($row['part_name'] ?? ''));

                $supplierKey = mb_strtolower($supplierInput, 'UTF-8');

                if ($supplierInput === '' || $partNo === '' || !isset($suppliers[$supplierKey])
                    || mb_strlen($projectName) > 150 || mb_strlen($partName) > 200) {
                    $invalid++;
                    continue;
                }

                $supplierName = $suppliers[$supplierKey];
                $key          = $supplierName . '|' . $partNo;

                // 同一供应商 + 零件号只处理一次，保证唯一
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;

                $part = SupplierPartModel::where('supplier_name', $supplierName)
                    ->where('part_no', $partNo)
                    ->find();

                if (!$part) {
                    $notFound++;
                    continue;
                }

                $changes = $this->partChanges($part, $projectName, $partName);

                if ($changes === []) {
                    $unchanged++;
                    continue;
                }

                $part->save([
                    'project_name' => $projectName,
                    'part_name'    => $partName,
                ]);

                // 同步该零件号下所有方案（已归档 / 未归档）
                ProposalService::syncPartInfo($supplierName, $partNo, $projectName, $partName);

                PartLog::create([
                    'supplier_name' => (string) $part->supplier_name,
                    'part_no'       => (string) $part->part_no,
                    'user_id'       => $userId,
                    'action'        => 'part_update',
                    'action_name'   => '批量修改零件号信息',
                    'content'       => implode('；', $changes),
                ]);

                $updated++;
            }

            if ($updated === 0) {
                throw new BizException('没有需要修改的内容');
            }

            AuditService::record($userId, 'part_update', 0, [
                'content' => "批量修改指定零件号：更新 {$updated} 条，无变化 {$unchanged} 条，"
                    . "未匹配 {$notFound} 条，格式错误 {$invalid} 条",
            ]);
        });

        return $this->success([
            'updated'   => $updated,
            'unchanged' => $unchanged,
            'not_found' => $notFound,
            'invalid'   => $invalid,
        ], "批量修改完成：更新 {$updated} 条");
    }

    /**
     * 计算项目名称/零件名称的变化
     * @param SupplierPartModel $part
     * @param string            $projectName
     * @param string            $partName
     * @return array<int, string>
     */
    protected function partChanges(SupplierPartModel $part, string $projectName, string $partName): array
    {
        $changes = [];

        if ($projectName !== (string) $part->project_name) {
            $changes[] = '项目名称：' . self::showValue((string) $part->project_name)
                . ' → ' . self::showValue($projectName);
        }

        if ($partName !== (string) $part->part_name) {
            $changes[] = '零件名称：' . self::showValue((string) $part->part_name)
                . ' → ' . self::showValue($partName);
        }

        return $changes;
    }

    /**
     * 读取上传的零件号 Excel
     * 列顺序：供应商名称、项目名称、零件号、零件名称（首行表头自动跳过）
     * @param mixed $file 上传文件
     * @return array<int, array{line:int,supplier_name:string,project_name:string,part_no:string,part_name:string}>
     * @throws BizException
     */
    protected function readPartRows($file): array
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
            // 上传后的临时文件没有扩展名，按原始扩展名选择读取器
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

        // 首行是表头则跳过
        if (mb_strpos((string) ($firstRow[0] ?? ''), '供应商') !== false) {
            $start = 1;
        }

        $list = [];

        foreach (array_slice($rows, $start) as $index => $row) {
            $cells = array_map(
                static fn ($cell) => trim((string) $cell),
                array_pad(array_slice($row, 0, 4), 4, '')
            );

            [$supplierName, $projectName, $partNo, $partName] = $cells;

            if ($supplierName === '' && $projectName === '' && $partNo === '' && $partName === '') {
                continue;
            }

            $list[] = [
                'line'          => $start + $index + 1,
                'supplier_name' => $supplierName,
                'project_name'  => $projectName,
                'part_no'       => $partNo,
                'part_name'     => $partName,
            ];
        }

        return $list;
    }

    /**
     * 供应商名称小写 => 档案标准名称
     * @return array<string, string>
     */
    protected function supplierNameMap(): array
    {
        $map = [];

        foreach (Supplier::column('name') as $name) {
            $map[mb_strtolower((string) $name, 'UTF-8')] = (string) $name;
        }

        return $map;
    }

    /**
     * 修改指定零件号的项目名称 / 零件名称（记录修改日志）
     * @param int $id
     * @return Json
     * @throws BizException
     * @throws \Throwable
     */
    public function update(int $id): Json
    {
        $part = SupplierPartModel::find($id);

        if (!$part) {
            throw new BizException('记录不存在', 0, 404);
        }

        $data        = $this->request->only(['project_name', 'part_name']);
        $projectName = trim((string) ($data['project_name'] ?? $part->project_name));
        $partName    = trim((string) ($data['part_name'] ?? $part->part_name));

        if (mb_strlen($projectName) > 150) {
            throw new BizException('项目名称不能超过 150 个字符');
        }

        if (mb_strlen($partName) > 200) {
            throw new BizException('零件名称不能超过 200 个字符');
        }

        $changes = $this->partChanges($part, $projectName, $partName);

        if ($changes === []) {
            throw new BizException('未检测到修改内容');
        }

        $userId = $this->userId();

        Db::transaction(function () use ($part, $projectName, $partName, $changes, $userId) {
            $part->save([
                'project_name' => $projectName,
                'part_name'    => $partName,
            ]);

            // 同步该零件号下所有方案（已归档 / 未归档）
            ProposalService::syncPartInfo(
                (string) $part->supplier_name,
                (string) $part->part_no,
                $projectName,
                $partName
            );

            // 页面内可查询的修改日志
            PartLog::create([
                'supplier_name' => (string) $part->supplier_name,
                'part_no'       => (string) $part->part_no,
                'user_id'       => $userId,
                'action'        => 'part_update',
                'action_name'   => '修改零件号信息',
                'content'       => implode('；', $changes),
            ]);

            // 同步写入全局审计日志
            AuditService::record($userId, 'part_update', 0, [
                'content' => '修改指定零件号：' . $part->supplier_name . ' / ' . $part->part_no
                    . '（' . implode('；', $changes) . '）',
            ]);
        });

        return $this->success($part->toArray(), '修改成功');
    }

    /**
     * 指定零件号修改日志
     * @param int $id
     * @return Json
     * @throws BizException
     */
    public function logs(int $id): Json
    {
        $part = SupplierPartModel::find($id);

        if (!$part) {
            throw new BizException('记录不存在', 0, 404);
        }

        $logs = PartLog::with(['operator'])
            ->where('supplier_name', (string) $part->supplier_name)
            ->where('part_no', (string) $part->part_no)
            ->order('id', 'desc')
            ->limit(100)
            ->select();

        return $this->success($logs->toArray());
    }

    /**
     * 日志中空值的展示
     * @param string $value
     * @return string
     */
    protected static function showValue(string $value): string
    {
        return $value === '' ? '（空）' : $value;
    }

    /**
     * 下载导入模板
     * @return \think\Response
     * @throws \Throwable
     */
    public function template(): \think\Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('指定零件号');

        foreach (self::HEADERS as $index => $title) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($column . '1', $title);
            $sheet->getColumnDimension($column)->setWidth(22);
        }

        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getStyle('A1:D1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFEFF4FF');

        $file = tempnam(sys_get_temp_dir(), 'part_template_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($file);
        $spreadsheet->disconnectWorksheets();

        $content = (string) @file_get_contents($file);
        @unlink($file);

        return response($content)
            ->contentType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header([
                'Content-Disposition' => "attachment; filename*=UTF-8''" . rawurlencode('指定零件号导入模板.xlsx'),
                'Content-Length'      => (string) strlen($content),
            ]);
    }

    /**
     * 删除指定零件号
     * @param int $id
     * @return Json
     * @throws BizException
     */
    public function delete(int $id): Json
    {
        $part = SupplierPartModel::find($id);

        if (!$part) {
            throw new BizException('记录不存在', 0, 404);
        }

        $content = '删除指定零件号：' . $part->supplier_name . ' / ' . $part->part_no;
        $part->delete();

        AuditService::record($this->userId(), 'part_delete', 0, ['content' => $content]);

        return $this->success([], '已删除');
    }
}
