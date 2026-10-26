<?php
declare (strict_types = 1);

namespace app\service;

use app\model\Attachment;
use app\model\Dict;
use app\model\PackProposal;
use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 包装确认单导出服务
 * Excel：以既有 A4 横向模板为底版填充数据，保留模板的合并单元格与样式
 * PDF ：按模板版式重新排版，使用 Dompdf 输出 A4 横向
 */
class PackExportService
{
    /** 模板路径（相对项目根目录） */
    private const TEMPLATE = 'public/包装确认单-模板 20260730.xlsx';

    /** 模板中需要先清空的数据单元格 */
    private const CLEAR_CELLS = [
        // 填写日期
        'BH4', 'BF13',
        // 客户资料
        'Q5', 'Q7', 'Q9', 'BB9', 'Q11', 'BB11',
        // 供应商资料
        'Q14', 'BH14', 'Q16', 'Q18', 'BB18', 'Q20', 'BB20',
        // 产品信息
        'CL5', 'CL7', 'CL9', 'CL11', 'CL17', 'CL20', 'CS20', 'CZ20',
        // 包装信息
        'CL14', 'DL16', 'DV16', 'DL18', 'DV18', 'CL23', 'DL23', 'DV23',
        'CL26', 'CS26', 'CZ26', 'CL29', 'DL29', 'DV29',
        // 托盘信息
        'BB44', 'BJ44', 'BR44', 'BB48', 'BN48', 'BB51',
        // 包装操作说明
        'Q54',
    ];

    /** 数值单元格（以数字写入） */
    private const NUMBER_CELLS = [
        'CL17', 'CL20', 'CS20', 'CZ20', 'CL23', 'CL26', 'CS26', 'CZ26', 'CL29',
        'BB44', 'BJ44', 'BR44', 'BB48', 'BN48', 'BB51',
    ];

    /** 图片占位：模板锚点 => [附件类型, 最大宽(px), 最大高(px)] */
    private const IMAGE_SLOTS = [
        'DK6'  => [Attachment::TYPE_PART, 520, 138],
        'E26'  => [Attachment::TYPE_INNER, 520, 230],
        'AR26' => [Attachment::TYPE_FINAL, 470, 300],
        'H40'  => [Attachment::TYPE_PACKAGING, 520, 230],
    ];

    /** 模板中固定的协议条款 */
    private const NOTES = [
        '1.本协议书以双方会签之日起生效，包装协议书附在物流协议书之后。',
        '2.在使用过程中各方有义务协商改进包装方式。',
        '3.包装协议有任何改动请重新提交和签署，并且以最新签订的包装协议书为依据。改动后以签字之日起生效。',
        '4.本协议包含路试结果的确认（若工厂对路试有特殊要求则以实际需求为准，可参考 YFPO-MS16-00-06）。',
        '5.本协议一式两份，协议由供应商和延锋彼欧代表（工厂）各保留一份；协议双方代表签字并盖章确认生效。',
        '6.除了标准塑料箱、围板箱、纸箱包装以外的料架类，需延锋彼欧安全部门、制造系统部会签。',
    ];

    /**
     * 生成 Excel 文件
     * @param PackProposal $proposal
     * @return string 临时文件绝对路径
     */
    public static function excel(PackProposal $proposal): string
    {
        $book = IOFactory::load(root_path() . self::TEMPLATE);
        $book->setActiveSheetIndex(0);
        $sheet = $book->getSheet(0);

        // 仅保留「料架类」主表
        for ($i = $book->getSheetCount() - 1; $i > 0; $i--) {
            $book->removeSheetByIndex($i);
        }

        foreach (self::CLEAR_CELLS as $cell) {
            $sheet->setCellValue($cell, '');
        }

        self::removeTemplateImages($sheet);
        self::fillCells($sheet, $proposal);
        self::fillImages($sheet, (int) $proposal->id);

        $sheet->getStyle('Q54')->getAlignment()->setWrapText(true);

        $path = self::tempPath('xlsx');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    /**
     * 生成 PDF 文件
     * @param PackProposal $proposal
     * @return string 临时文件绝对路径
     */
    public static function pdf(PackProposal $proposal): string
    {
        $cacheDir = runtime_path() . 'dompdf';

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        [$fontFace, $family] = self::fontFace();

        $dompdf = new Dompdf([
            // 字体可能位于系统目录，放开根目录以便读取
            'chroot'               => '/',
            'tempDir'              => $cacheDir,
            'fontDir'              => $cacheDir,
            'fontCache'            => $cacheDir,
            'defaultFont'          => $family,
            'isRemoteEnabled'      => false,
            'isHtml5ParserEnabled' => true,
            'dpi'                  => 96,
        ]);

        $dompdf->loadHtml(self::pdfHtml($proposal, $fontFace, $family), 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $path = self::tempPath('pdf');
        file_put_contents($path, (string) $dompdf->output());

        return $path;
    }

    /**
     * 导出文件名：包装确认单-供应商名称-零件号-零件名称.扩展名
     * @param PackProposal $proposal
     * @param string       $ext
     * @return string
     */
    public static function fileName(PackProposal $proposal, string $ext): string
    {
        $segments = [
            (string) $proposal->supplier,
            (string) $proposal->part_no,
            (string) $proposal->part_name,
        ];

        $segments = array_map(
            static fn (string $value): string => self::fileNameSegment($value),
            $segments
        );

        return sprintf('包装确认单-%s.%s', implode('-', $segments), $ext);
    }

    /**
     * 文件名片段：去掉路径分隔符等非法字符，空值用「未填写」占位
     * @param string $value
     * @return string
     */
    protected static function fileNameSegment(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '未填写';
        }

        $value = preg_replace('/[\\\\\\/:*?"<>|\r\n\t]+/u', '', $value);

        return $value === '' || $value === null ? '未填写' : $value;
    }

    // ------------------------------------------------------------------
    // Excel
    // ------------------------------------------------------------------

    /**
     * 填充文本与数值单元格
     * @param Worksheet    $sheet
     * @param PackProposal $proposal
     * @return void
     */
    private static function fillCells(Worksheet $sheet, PackProposal $proposal): void
    {
        $writeDate = $proposal->write_date ? (string) $proposal->write_date : '';

        $values = [
            'BH4'  => $writeDate,
            'BF13' => $writeDate,

            'Q5'   => self::dictLabel('customer', (string) $proposal->customer),
            'Q7'   => (string) $proposal->customer_address,
            'Q9'   => (string) $proposal->customer_contact,
            'BB9'  => (string) $proposal->customer_email,
            'Q11'  => (string) $proposal->customer_phone,
            'BB11' => (string) $proposal->customer_fax,

            'Q14'  => (string) $proposal->supplier,
            'BH14' => (string) $proposal->supplier_code,
            'Q16'  => (string) $proposal->supplier_address,
            'Q18'  => (string) $proposal->contact,
            'BB18' => (string) $proposal->supplier_email,
            'Q20'  => (string) $proposal->phone,
            'BB20' => (string) $proposal->supplier_fax,

            'CL5'  => (string) $proposal->project_model,
            'CL7'  => (string) $proposal->part_name,
            'CL9'  => (string) $proposal->part_no,
            'CL11' => (string) $proposal->part_material,
            'CL17' => self::number($proposal->part_weight, 3),
            'CL20' => self::number($proposal->part_size_l),
            'CS20' => self::number($proposal->part_size_w),
            'CZ20' => self::number($proposal->part_size_h),

            'CL14' => (string) $proposal->pack_box_no,
            'CL23' => self::number($proposal->packing_qty, 0),
            'CL26' => self::number($proposal->box_size_l),
            'CS26' => self::number($proposal->box_size_w),
            'CZ26' => self::number($proposal->box_size_h),
            'CL29' => self::number($proposal->gross_weight, 3),

            'BB44' => self::number($proposal->pallet_size_l),
            'BJ44' => self::number($proposal->pallet_size_w),
            'BR44' => self::number($proposal->pallet_size_h),
            'BB48' => self::number($proposal->pallet_layer_qty, 0),
            'BN48' => self::number($proposal->pallet_layer_count, 0),
            'BB51' => self::number($proposal->pallet_parts_qty, 0),

            'Q54'  => (string) $proposal->packaging_instruction,
        ];

        foreach ($values as $cell => $value) {
            if (in_array($cell, self::NUMBER_CELLS, true)) {
                if (is_numeric($value)) {
                    $sheet->setCellValue($cell, (float) $value);
                }
                continue;
            }

            $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
        }

        // 包装箱材料勾选：塑料 / 金属 / 纸箱 / 其他
        $materialChecks = [
            'plastic' => 'DL16',
            'metal'   => 'DV16',
            'carton'  => 'DL18',
            'other'   => 'DV18',
        ];
        $material = (string) $proposal->pack_material;

        if (isset($materialChecks[$material])) {
            $sheet->setCellValue($materialChecks[$material], '√');
        }

        // 包装箱是否返回 / 内衬是否返回
        $sheet->setCellValue((int) $proposal->returnable === 1 ? 'DL23' : 'DV23', '√');
        $sheet->setCellValue((int) $proposal->dunnage_returnable === 1 ? 'DL29' : 'DV29', '√');
    }

    /**
     * 移除模板中的示例图片（保留 C2 的 logo）
     * @param Worksheet $sheet
     * @return void
     */
    private static function removeTemplateImages(Worksheet $sheet): void
    {
        $collection = $sheet->getDrawingCollection();
        $removeKeys = [];

        foreach ($collection as $key => $drawing) {
            if ($drawing->getCoordinates() === 'C2') {
                continue;
            }
            $removeKeys[] = $key;
        }

        foreach ($removeKeys as $key) {
            $collection->offsetUnset($key);
        }
    }

    /**
     * 按附件类型插入图片
     * @param Worksheet $sheet
     * @param int       $proposalId
     * @return void
     */
    private static function fillImages(Worksheet $sheet, int $proposalId): void
    {
        foreach (self::IMAGE_SLOTS as $anchor => [$type, $maxWidth, $maxHeight]) {
            // 每个图片位置只嵌入 1 张（取最早上传的一张），不兼容历史多图数据
            $path = Attachment::imagePathsByType($proposalId, $type)[0] ?? '';

            if ($path === '') {
                continue;
            }

            $size = @getimagesize($path);

            if ($size === false) {
                continue;
            }

            [$width, $height] = self::fitPixels($size[0], $size[1], $maxWidth - 8, $maxHeight);

            $drawing = new Drawing();
            $drawing->setPath($path);
            $drawing->setCoordinates($anchor);
            $drawing->setOffsetX(4);
            $drawing->setOffsetY(6);
            $drawing->setWidth($width);
            $drawing->setHeight($height);
            $drawing->setWorksheet($sheet);
        }
    }

    // ------------------------------------------------------------------
    // PDF
    // ------------------------------------------------------------------

    /**
     * 生成 PDF 版式 HTML
     * @param PackProposal $proposal
     * @param string       $fontFace
     * @param string       $family
     * @return string
     */
    private static function pdfHtml(PackProposal $proposal, string $fontFace, string $family): string
    {
        $id = (int) $proposal->id;

        // 标题区
        $logo         = self::logoDataUri();
        $logoHtml     = $logo !== '' ? '<img src="' . $logo . '" style="height:9mm;">' : '';
        $proposalNo   = self::e($proposal->proposal_no);
        $version      = (int) $proposal->version;
        $writeDate    = self::e($proposal->write_date ?: '-');

        // 客户资料
        $customerName    = self::e(self::dictLabel('customer', (string) $proposal->customer));
        $customerAddress = self::e($proposal->customer_address);
        $customerContact = self::e($proposal->customer_contact);
        $customerEmail   = self::e($proposal->customer_email);
        $customerPhone   = self::e($proposal->customer_phone);
        $customerFax     = self::e($proposal->customer_fax);

        // 供应商资料
        $supplierName    = self::e($proposal->supplier);
        $supplierCode    = self::e($proposal->supplier_code);
        $supplierAddress = self::e($proposal->supplier_address);
        $supplierContact = self::e($proposal->contact);
        $supplierEmail   = self::e($proposal->supplier_email);
        $supplierPhone   = self::e($proposal->phone);
        $supplierFax     = self::e($proposal->supplier_fax);

        // 产品信息
        $projectModel   = self::e($proposal->project_model);
        $partName       = self::e($proposal->part_name);
        $partNo         = self::e($proposal->part_no);
        $partMaterial   = self::e($proposal->part_material);
        $partWeight     = self::numText($proposal->part_weight, 3);
        $partSize       = self::sizeText($proposal->part_size_l, $proposal->part_size_w, $proposal->part_size_h);

        // 包装信息
        $packBoxNo     = self::e($proposal->pack_box_no);
        $packingQty    = self::numText($proposal->packing_qty, 0);
        $boxSize       = self::sizeText($proposal->box_size_l, $proposal->box_size_w, $proposal->box_size_h);
        $grossWeight   = self::numText($proposal->gross_weight, 3);
        $returnable    = self::yesNoText((int) $proposal->returnable === 1);
        $dunnageReturn = self::yesNoText((int) $proposal->dunnage_returnable === 1);

        $material = (string) $proposal->pack_material;
        $materialChecks = '';
        foreach (PackProposal::PACK_MATERIAL_TEXT as $value => $label) {
            $materialChecks .= self::checkbox($material === $value, $label) . '&nbsp;&nbsp;';
        }

        // 托盘信息
        $palletSize  = self::sizeText($proposal->pallet_size_l, $proposal->pallet_size_w, $proposal->pallet_size_h);
        $palletLayer = self::numText($proposal->pallet_layer_qty, 0) . ' 箱/层 × '
            . self::numText($proposal->pallet_layer_count, 0) . ' 层/托';
        $palletParts = self::numText($proposal->pallet_parts_qty, 0);

        // 说明与图片
        $instruction = self::e($proposal->packaging_instruction);
        $innerBox    = self::pdfImageBlock($id, Attachment::TYPE_INNER, 82, 30);
        $finalBox    = self::pdfImageBlock($id, Attachment::TYPE_FINAL, 82, 30);
        $packBox     = self::pdfImageBlock($id, Attachment::TYPE_PACKAGING, 82, 26);
        $partBox     = self::pdfImageBlock($id, Attachment::TYPE_PART, 82, 26);

        $notes = '';
        foreach (self::NOTES as $note) {
            $notes .= '<div class="note">' . self::e($note) . '</div>';
        }

        $passBox = self::checkbox(false, '通过 Yes');
        $failBox = self::checkbox(false, '未通过 No');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>包装确认单</title>
<style>
{$fontFace}
@page { margin: 5mm 6mm; }
* { box-sizing: border-box; }
body { font-family: {$family}; font-size: 6.2pt; line-height: 1.22; color: #000; margin: 0; }
.title-wrap { width: 100%; border-bottom: 1pt solid #000; padding-bottom: 0.8mm; margin-bottom: 0.8mm; }
.title-wrap td { vertical-align: middle; border: none; padding: 0; }
.brand { width: 24%; }
.title { text-align: center; }
.title h1 { font-size: 13pt; margin: 0; letter-spacing: 3pt; }
.title .en { font-size: 6.6pt; letter-spacing: 1pt; }
.meta { width: 26%; text-align: right; font-size: 5.8pt; line-height: 1.4; }
table { border-collapse: collapse; width: 100%; }
table.grid td { border: 0.6pt solid #000; padding: 0.42mm 1mm; vertical-align: middle; }
.sec { background: #e6e6e6; font-weight: bold; font-size: 6.6pt; }
.lb { background: #f7f7f7; font-weight: bold; width: 21%; }
.val { word-wrap: break-word; }
.en-inline { font-size: 4.9pt; color: #333; }
.chk { font-size: 7.6pt; }
.imgcell { text-align: center; }
.imgcell .cap { font-size: 5.4pt; color: #444; }
.imgcell .ph { color: #999; font-size: 5.8pt; padding: 4mm 0; }
.col-left { float: left; width: 62.5%; }
.col-right { float: right; width: 36.5%; }
.clear { clear: both; height: 0; }
.gap { height: 0.8mm; }
.note { font-size: 4.8pt; line-height: 1.25; }
.sign td { height: 5mm; }
</style>
</head>
<body>

<table class="title-wrap">
  <tr>
    <td class="brand">{$logoHtml}</td>
    <td class="title">
      <h1>包装确认单</h1>
      <div class="en">Packaging Agreement</div>
    </td>
    <td class="meta">
      编号 No.：YFPO-MS15-17-11<br>
      方案编号：{$proposalNo}<br>
      填写日期：{$writeDate}　版本 V{$version}
    </td>
  </tr>
</table>

<div class="col-left">

  <table class="grid">
    <tr><td class="sec" colspan="4">客户资料 Customer Details</td></tr>
    <tr><td class="lb">客户名称 <span class="en-inline">Customer name</span></td><td colspan="3" class="val">{$customerName}</td></tr>
    <tr><td class="lb">供货地址 <span class="en-inline">Address</span></td><td colspan="3" class="val">{$customerAddress}</td></tr>
    <tr><td class="lb">联系人 <span class="en-inline">Contact Person</span></td><td class="val">{$customerContact}</td><td class="lb">电子邮箱 <span class="en-inline">E-mail</span></td><td class="val">{$customerEmail}</td></tr>
    <tr><td class="lb">联系电话 <span class="en-inline">Phone Number</span></td><td class="val">{$customerPhone}</td><td class="lb">传真 <span class="en-inline">Fax Number</span></td><td class="val">{$customerFax}</td></tr>
  </table>

  <div class="gap"></div>

  <table class="grid">
    <tr><td class="sec" colspan="4">供应商资料 Supplier Details</td></tr>
    <tr><td class="lb">供应商名称 <span class="en-inline">Supplier name</span></td><td class="val">{$supplierName}</td><td class="lb">供应商代码 <span class="en-inline">Supplier Code</span></td><td class="val">{$supplierCode}</td></tr>
    <tr><td class="lb">供应商地址 <span class="en-inline">Supplier Address</span></td><td colspan="3" class="val">{$supplierAddress}</td></tr>
    <tr><td class="lb">联系人 <span class="en-inline">Contact Person</span></td><td class="val">{$supplierContact}</td><td class="lb">电子邮箱 <span class="en-inline">E-mail</span></td><td class="val">{$supplierEmail}</td></tr>
    <tr><td class="lb">联系电话 <span class="en-inline">Phone Number</span></td><td class="val">{$supplierPhone}</td><td class="lb">传真 <span class="en-inline">Fax Number</span></td><td class="val">{$supplierFax}</td></tr>
  </table>

  <div class="gap"></div>

  <table class="grid">
    <tr><td class="sec" colspan="2">包装示意图（附操作说明）Photo of Packaging</td></tr>
    <tr>
      <td class="imgcell" style="width:50%"><div class="cap">内部排列 Internal Arrangement</div>{$innerBox}</td>
      <td class="imgcell" style="width:50%"><div class="cap">整体包装 Final Packaging</div>{$finalBox}</td>
    </tr>
    <tr>
      <td class="imgcell"><div class="cap">包装图片 Packaging Only</div>{$packBox}</td>
      <td class="imgcell"><div class="cap">零件图片 Part Picture</div>{$partBox}</td>
    </tr>
    <tr>
      <td colspan="2" class="val"><b>包装操作说明 Packaging Instruction：</b><br>{$instruction}</td>
    </tr>
  </table>

  <div class="gap"></div>

  <table class="grid">
    <tr><td class="sec">备注 Note</td></tr>
    <tr><td style="padding:1.2mm;">{$notes}</td></tr>
  </table>

</div>
<div class="col-right">

  <table class="grid">
    <tr><td class="sec" colspan="2">产品信息 Parts Details</td></tr>
    <tr><td class="lb">项目车型 <span class="en-inline">Vehicle model</span></td><td class="val">{$projectModel}</td></tr>
    <tr><td class="lb">零件名称 <span class="en-inline">Parts Name</span></td><td class="val">{$partName}</td></tr>
    <tr><td class="lb">零件号 <span class="en-inline">Part Number</span></td><td class="val">{$partNo}</td></tr>
    <tr><td class="lb">材料 <span class="en-inline">Material</span></td><td class="val">{$partMaterial}</td></tr>
    <tr><td class="lb">产品净重 <span class="en-inline">Part Weight</span></td><td class="val">{$partWeight} KG</td></tr>
    <tr><td class="lb">产品外形尺寸 <span class="en-inline">Part size L*W*H</span></td><td class="val">{$partSize} mm</td></tr>
  </table>

  <div class="gap"></div>

  <table class="grid">
    <tr><td class="sec" colspan="2">包装信息 Packaging Details</td></tr>
    <tr><td class="lb">包装箱编号 <span class="en-inline">Packaging Type</span></td><td class="val">{$packBoxNo}</td></tr>
    <tr><td class="lb">包装箱材料 <span class="en-inline">Packaging material</span></td><td class="val">{$materialChecks}</td></tr>
    <tr><td class="lb">存放数量 <span class="en-inline">Packing Quantity</span></td><td class="val">{$packingQty} 件/箱</td></tr>
    <tr><td class="lb">包装箱外尺寸 <span class="en-inline">Outside Size L*W*H</span></td><td class="val">{$boxSize} mm</td></tr>
    <tr><td class="lb">毛重 <span class="en-inline">Gross weight</span></td><td class="val">{$grossWeight} KG</td></tr>
    <tr><td class="lb">是否返回 <span class="en-inline">Returnable</span></td><td class="val">包装箱：{$returnable}　内衬：{$dunnageReturn}</td></tr>
  </table>

  <div class="gap"></div>

  <table class="grid">
    <tr><td class="sec" colspan="2">托盘信息 Pallet info（非托盘打包不填写）</td></tr>
    <tr><td class="lb">满托盘外尺寸 <span class="en-inline">Full pallet size L*W*H</span></td><td class="val">{$palletSize} mm</td></tr>
    <tr><td class="lb">包装数量 <span class="en-inline">PCK QTY per pallet</span></td><td class="val">{$palletLayer}</td></tr>
    <tr><td class="lb">零件数量 <span class="en-inline">Parts QTY per pallet</span></td><td class="val">{$palletParts} 件/托</td></tr>
  </table>

  <div class="gap"></div>

  <table class="grid sign">
    <tr><td class="sec" colspan="2">会签 Sign</td></tr>
    <tr><td class="lb">供应商代表 <span class="en-inline">Supplier Representative</span></td><td class="val"></td></tr>
    <tr><td class="lb">延锋彼欧代表(工厂) <span class="en-inline">YFPO Plant</span></td><td class="val"></td></tr>
    <tr><td class="lb">延锋彼欧安全工程师 <span class="en-inline">YFPO Safety</span></td><td class="val"></td></tr>
    <tr><td class="lb">延锋彼欧制造系统部 <span class="en-inline">YFPO MS</span></td><td class="val"></td></tr>
    <tr><td class="lb">路试结果 <span class="en-inline">Road test result</span></td><td class="val">{$passBox}　{$failBox}</td></tr>
  </table>

</div>
<div class="clear"></div>

</body>
</html>
HTML;

        return $html;
    }

    /**
     * PDF 图片块
     * @param int    $proposalId
     * @param string $type
     * @param float  $boxWidth  可用宽（mm）
     * @param float  $boxHeight 可用高（mm）
     * @return string
     */
    private static function pdfImageBlock(int $proposalId, string $type, float $boxWidth, float $boxHeight): string
    {
        // 每个图片位置只嵌入 1 张（取最早上传的一张），不兼容历史多图数据
        $path = Attachment::imagePathsByType($proposalId, $type)[0] ?? '';

        if ($path === '') {
            return '<div class="ph">（无图片）</div>';
        }

        $image = self::pdfImageSource($path);

        if ($image === null) {
            return '<div class="ph">（无图片）</div>';
        }

        [$width, $height] = self::fitMillimeters(
            $image['width'],
            $image['height'],
            $boxWidth - 2,
            $boxHeight
        );

        return sprintf(
            '<img src="data:%s;base64,%s" style="width:%.1fmm;height:%.1fmm;">',
            $image['mime'],
            base64_encode($image['data']),
            $width,
            $height
        );
    }

    /**
     * 读取图片并转为可嵌入 PDF 的数据（超大图先等比缩小，避免 PDF 体积膨胀）
     * @param string $path
     * @return array{mime:string,data:string,width:int,height:int}|null
     */
    private static function pdfImageSource(string $path): ?array
    {
        $info = @getimagesize($path);

        if ($info === false) {
            return null;
        }

        [$width, $height] = $info;
        $mime             = (string) $info['mime'];
        $binary           = null;
        $maxEdge          = 1600;

        if (max($width, $height) > $maxEdge && function_exists('imagecreatetruecolor')) {
            $binary = self::shrinkImage($path, $mime, $width, $height, $maxEdge);

            if ($binary !== null) {
                $shrunk = @getimagesizefromstring($binary);

                if ($shrunk !== false) {
                    [$width, $height] = $shrunk;
                    $mime             = 'image/jpeg';
                }
            }
        }

        if ($binary === null) {
            $binary = (string) file_get_contents($path);
        }

        if ($binary === '') {
            return null;
        }

        return ['mime' => $mime, 'data' => $binary, 'width' => $width, 'height' => $height];
    }

    /**
     * 使用 GD 缩小图片（输出 jpeg）
     * @param string $path
     * @param string $mime
     * @param int    $width
     * @param int    $height
     * @param int    $maxEdge
     * @return string|null
     */
    private static function shrinkImage(string $path, string $mime, int $width, int $height, int $maxEdge): ?string
    {
        $source = match ($mime) {
            'image/png'  => @imagecreatefrompng($path),
            'image/jpeg' => @imagecreatefromjpeg($path),
            default      => null,
        };

        if (!$source) {
            return null;
        }

        $scale  = $maxEdge / max($width, $height);
        $target = imagecreatetruecolor(
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale))
        );

        // png 透明区域填充白色，保证 jpeg 输出不发黑
        imagefilledrectangle(
            $target,
            0,
            0,
            imagesx($target),
            imagesy($target),
            imagecolorallocate($target, 255, 255, 255)
        );
        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            imagesx($target),
            imagesy($target),
            $width,
            $height
        );

        ob_start();
        imagejpeg($target, null, 82);
        $binary = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($target);

        return $binary !== '' ? $binary : null;
    }

    /**
     * 中文字体：优先项目 public/fonts，其次常见系统字体
     * @return array{0:string,1:string} [@font-face 规则, 字体族名]
     */
    private static function fontFace(): array
    {
        $candidates = [
            root_path() . 'public/fonts/pack-cn.ttf',
            '/Library/Fonts/Arial Unicode.ttf',
            '/System/Library/Fonts/Supplemental/Arial Unicode.ttf',
            '/usr/share/fonts/truetype/wqy/wqy-microhei.ttf',
            '/usr/share/fonts/truetype/arphic/uming.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ];

        foreach ($candidates as $path) {
            if (!is_file($path)) {
                continue;
            }

            // DejaVu 不含中文字形，仅作为最后的兜底
            $family = str_contains($path, 'DejaVu') ? 'DejaVu Sans' : 'packcn';
            $url    = 'file://' . $path;
            $rule   = "@font-face { font-family: {$family}; font-style: normal; font-weight: normal; "
                . "src: url(\"{$url}\") format(\"truetype\"); }";

            return [$rule, $family];
        }

        return ['', 'DejaVu Sans'];
    }

    /**
     * 提取模板中的 logo
     * @return string data uri 或空串
     */
    private static function logoDataUri(): string
    {
        $file = root_path() . self::TEMPLATE;

        if (!is_file($file) || !class_exists(\ZipArchive::class)) {
            return '';
        }

        $zip = new \ZipArchive();

        if ($zip->open($file) !== true) {
            return '';
        }

        $content = $zip->getFromName('xl/media/image1.png');
        $zip->close();

        if ($content === false || $content === '') {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode($content);
    }

    // ------------------------------------------------------------------
    // 通用工具
    // ------------------------------------------------------------------

    /**
     * HTML 转义
     * @param mixed $value
     * @return string
     */
    private static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * 数值文字：0 或空输出空串（Excel 用）
     * @param mixed $value
     * @param int   $decimals
     * @return string
     */
    private static function number(mixed $value, int $decimals = 2): string
    {
        $number = (float) $value;

        if ($number <= 0) {
            return '';
        }

        if ($decimals <= 0) {
            return number_format($number, 0, '.', '');
        }

        // 去掉小数部分末尾无效的 0，整数位不受影响
        return rtrim(rtrim(number_format($number, $decimals, '.', ''), '0'), '.');
    }

    /**
     * 数值文字：空值输出短横线（PDF 用）
     * @param mixed $value
     * @param int   $decimals
     * @return string
     */
    private static function numText(mixed $value, int $decimals = 2): string
    {
        $text = self::number($value, $decimals);

        return $text === '' ? '-' : $text;
    }

    /**
     * 尺寸文字（长 × 宽 × 高）
     * @param mixed $length
     * @param mixed $width
     * @param mixed $height
     * @return string
     */
    private static function sizeText(mixed $length, mixed $width, mixed $height): string
    {
        if ((float) $length <= 0 && (float) $width <= 0 && (float) $height <= 0) {
            return '-';
        }

        return self::number($length) . ' × ' . self::number($width) . ' × ' . self::number($height);
    }

    /**
     * 复选框
     * @param bool   $checked
     * @param string $label
     * @return string
     */
    private static function checkbox(bool $checked, string $label): string
    {
        return '<span class="chk">' . ($checked ? '☑' : '☐') . '</span>' . self::e($label);
    }

    /**
     * 是 / 否
     * @param bool $yes
     * @return string
     */
    private static function yesNoText(bool $yes): string
    {
        return $yes ? '是 Yes' : '否 No';
    }

    /**
     * 字典标签
     * @param string $type
     * @param string $value
     * @return string
     */
    private static function dictLabel(string $type, string $value): string
    {
        if ($value === '') {
            return '';
        }

        $label = Dict::where('dict_type', $type)->where('dict_value', $value)->value('dict_label');

        return $label !== null ? (string) $label : $value;
    }

    /**
     * 等比缩放到像素框内
     * @param int   $width
     * @param int   $height
     * @param float $maxWidth
     * @param float $maxHeight
     * @return array{0:int,1:int}
     */
    private static function fitPixels(int $width, int $height, float $maxWidth, float $maxHeight): array
    {
        $maxWidth  = max(40, $maxWidth);
        $maxHeight = max(40, $maxHeight);

        $scale = min($maxWidth / $width, $maxHeight / $height, 1.6);

        return [(int) round($width * $scale), (int) round($height * $scale)];
    }

    /**
     * 等比缩放到毫米框内
     * @param int   $width
     * @param int   $height
     * @param float $maxWidth
     * @param float $maxHeight
     * @return array{0:float,1:float}
     */
    private static function fitMillimeters(int $width, int $height, float $maxWidth, float $maxHeight): array
    {
        $scale = min($maxWidth / $width, $maxHeight / $height);

        return [round($width * $scale, 1), round($height * $scale, 1)];
    }

    /**
     * 生成临时文件路径
     * @param string $ext
     * @return string
     */
    private static function tempPath(string $ext): string
    {
        $dir = runtime_path() . 'export';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir . DIRECTORY_SEPARATOR . uniqid('pack_', true) . '.' . $ext;
    }
}
