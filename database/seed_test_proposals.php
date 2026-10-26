<?php
/**
 * 生成测试方案（含方案图片附件）
 *
 * 用法：php database/seed_test_proposals.php
 * 说明：
 *   - 数据库连接取自项目根目录 .env
 *   - 方案编号已存在时自动跳过，可重复执行
 *   - 图片写入 public/storage/attachments/202610/，并登记到 pf_attachment
 */

declare (strict_types = 1);

const FONT_FILE = '/System/Library/Fonts/Supplemental/Arial Unicode.ttf';

if (!is_file(FONT_FILE)) {
    exit("缺少字体文件：" . FONT_FILE . "\n");
}

$root = dirname(__DIR__);
$env  = parse_ini_file($root . '/.env') ?: [];

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $env['DB_HOST'] ?? '127.0.0.1',
    $env['DB_PORT'] ?? '3306',
    $env['DB_NAME'] ?? 'pack_submit',
    $env['DB_CHARSET'] ?? 'utf8mb4'
);

$pdo = new PDO($dsn, (string) ($env['DB_USER'] ?? 'root'), (string) ($env['DB_PASS'] ?? ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$prefix = (string) ($env['DB_PREFIX'] ?? 'pf_');
$imageDir = $root . '/public/storage/attachments/202610';

if (!is_dir($imageDir) && !mkdir($imageDir, 0755, true) && !is_dir($imageDir)) {
    exit("无法创建图片目录：{$imageDir}\n");
}

/** 客户 / 供应商档案 */
function master(PDO $pdo, string $prefix, string $table, string $name): array
{
    $stmt = $pdo->prepare("SELECT * FROM {$prefix}{$table} WHERE name = ? LIMIT 1");
    $stmt->execute([$name]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

/* ------------------------------ 绘图工具 ------------------------------ */

function color($im, string $hex)
{
    return imagecolorallocate(
        $im,
        (int) hexdec(substr($hex, 1, 2)),
        (int) hexdec(substr($hex, 3, 2)),
        (int) hexdec(substr($hex, 5, 2))
    );
}

function text($im, int $size, int $x, int $y, $color, string $str, string $align = 'left'): void
{
    $box = imagettfbbox($size, 0, FONT_FILE, $str);
    $width = $box[2] - $box[0];

    if ($align === 'center') {
        $x -= (int) round($width / 2);
    } elseif ($align === 'right') {
        $x -= $width;
    }

    imagettftext($im, $size, 0, $x, $y, $color, FONT_FILE, $str);
}

function rect($im, int $x1, int $y1, int $x2, int $y2, $color, int $thick = 2): void
{
    for ($i = 0; $i < $thick; $i++) {
        imagerectangle($im, $x1 + $i, $y1 + $i, $x2 - $i, $y2 - $i, $color);
    }
}

function fillRect($im, int $x1, int $y1, int $x2, int $y2, $color): void
{
    imagefilledrectangle($im, $x1, $y1, $x2, $y2, $color);
}

/**
 * 画一个伪 3D 纸箱
 */
function box3d($im, int $x, int $y, int $w, int $h, int $d, $face, $top, $side, $line): void
{
    // 正面
    fillRect($im, $x, $y, $x + $w, $y + $h, $face);
    rect($im, $x, $y, $x + $w, $y + $h, $line, 2);
    // 顶面
    imagefilledpolygon($im, [$x, $y, $x + $d, $y - $d, $x + $w + $d, $y - $d, $x + $w, $y], $top);
    imagepolygon($im, [$x, $y, $x + $d, $y - $d, $x + $w + $d, $y - $d, $x + $w, $y], $line);
    // 侧面
    imagefilledpolygon($im, [$x + $w, $y, $x + $w + $d, $y - $d, $x + $w + $d, $y + $h - $d, $x + $w, $y + $h], $side);
    imagepolygon($im, [$x + $w, $y, $x + $w + $d, $y - $d, $x + $w + $d, $y + $h - $d, $x + $w, $y + $h], $line);
}

/**
 * 生成方案图片
 * @return array{path:string,size:int,name:string}
 */
function makeImage(string $dir, string $type, array $p): array
{
    $w = 900;
    $h = 650;
    $im = imagecreatetruecolor($w, $h);

    $bg     = color($im, '#F5F8FC');
    $white  = color($im, '#FFFFFF');
    $text   = color($im, '#1F2D3D');
    $muted  = color($im, '#6B7A90');
    $line   = color($im, '#D6DEE9');

    $meta = [
        'part'      => ['零件图片', 'PART IMAGE', '#3B82F6'],
        'inner'     => ['内部排列', 'INNER LAYOUT', '#10B981'],
        'final'     => ['整体包装', 'FINAL PACKING', '#F59E0B'],
        'packaging' => ['包装图片', 'PACKAGING', '#8B5CF6'],
    ][$type];

    [$label, $en, $hex] = $meta;
    $accent = color($im, $hex);

    imagefill($im, 0, 0, $bg);
    fillRect($im, 0, 0, $w, 92, $accent);
    fillRect($im, 0, 92, $w, 96, $accent);

    text($im, 28, 40, 58, $white, "{$label}  {$en}");
    text($im, 16, $w - 40, 58, $white, $p['proposal_no'], 'right');

    // 内容卡片
    fillRect($im, 40, 130, $w - 40, $h - 130, $white);
    rect($im, 40, 130, $w - 40, $h - 130, $line, 2);

    $face = color($im, '#E8F0FB');
    $top  = color($im, '#D3E3F8');
    $side = color($im, '#BFD5F0');
    $dark = color($im, '#9FB6D4');

    if ($type === 'part') {
        // 零件 + 尺寸标注
        box3d($im, 330, 330, 240, 150, 60, $face, $top, $side, $accent);
        $dim = color($im, '#3B82F6');
        // 宽
        imageline($im, 330, 500, 570, 500, $dim);
        imageline($im, 330, 490, 330, 510, $dim);
        imageline($im, 570, 490, 570, 510, $dim);
        text($im, 20, 585, 507, $dim, 'L');
        // 高
        imageline($im, 280, 330, 280, 480, $dim);
        imageline($im, 270, 330, 290, 330, $dim);
        imageline($im, 270, 480, 290, 480, $dim);
        text($im, 20, 250, 415, $dim, 'H', 'center');
        text($im, 22, 450, 175, $text, '零件三维示意', 'center');
    } elseif ($type === 'inner') {
        // 外箱 + 内部排列
        rect($im, 170, 210, 730, 500, $accent, 3);
        $cols = 3;
        $rows = 2;
        $gap  = 14;
        $cellW = (int) ((730 - 170 - $gap * ($cols + 1)) / $cols);
        $cellH = (int) ((500 - 210 - $gap * ($rows + 1)) / $rows);

        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $x = 170 + $gap + $c * ($cellW + $gap);
                $y = 210 + $gap + $r * ($cellH + $gap);
                fillRect($im, $x, $y, $x + $cellW, $y + $cellH, $face);
                rect($im, $x, $y, $x + $cellW, $y + $cellH, $accent, 2);
                text($im, 16, $x + $cellW / 2, $y + $cellH / 2 + 6, $muted, 'PART', 'center');
            }
        }
        text($im, 22, 450, 175, $text, '箱内排列示意', 'center');
    } elseif ($type === 'final') {
        // 托盘 + 两层纸箱
        fillRect($im, 220, 470, 680, 500, color($im, '#C89B6A'));
        rect($im, 220, 470, 680, 500, color($im, '#9C7346'), 2);
        for ($i = 0; $i < 3; $i++) {
            fillRect($im, 250 + $i * 20, 505, 650 - $i * 20, 512, color($im, '#9C7346'));
        }

        box3d($im, 300, 320, 140, 150, 34, $face, $top, $side, $accent);
        box3d($im, 440, 320, 140, 150, 34, $face, $top, $side, $accent);
        box3d($im, 370, 230, 140, 80, 34, $face, $top, $side, $accent);

        text($im, 22, 450, 175, $text, '托盘堆码示意', 'center');
    } else {
        // 缠绕膜托盘
        fillRect($im, 230, 470, 670, 500, color($im, '#C89B6A'));
        rect($im, 230, 470, 670, 500, color($im, '#9C7346'), 2);
        box3d($im, 320, 260, 260, 210, 44, color($im, '#EFEFF4'), color($im, '#E1E1EA'), color($im, '#D3D3DF'), $accent);

        $film = color($im, '#B9C4D4');
        for ($i = 0; $i < 7; $i++) {
            $x = 330 + $i * 36;
            imageline($im, $x, 265, $x + 20, 465, $film);
        }
        // 打包带
        $strap = color($im, '#F59E0B');
        imageline($im, 380, 260, 380, 470, $strap);
        imageline($im, 520, 260, 520, 470, $strap);

        text($im, 22, 450, 175, $text, '缠绕膜 + 打包带', 'center');
    }

    // 底部信息
    text($im, 24, 60, $h - 82, $text, $p['part_name']);
    text($im, 17, 60, $h - 52, $muted, "{$p['customer']} · {$p['supplier']} · 箱号 {$p['pack_box_no']}");
    text($im, 17, $w - 60, $h - 52, $muted, '测试数据', 'right');

    $fileName = sprintf('seed_%s_%s.png', $p['proposal_no'], $type);
    $path     = $dir . '/' . $fileName;
    imagepng($im, $path);
    imagedestroy($im);

    return [
        'name' => sprintf('%s_%s.png', $label, $p['part_name']),
        'file' => $fileName,
        'size' => (int) filesize($path),
        'path' => 'storage/attachments/202610/' . $fileName,
    ];
}

/* ------------------------------ 测试方案 ------------------------------ */

$plans = [
    [
        'proposal_no' => 'PS202610250002',
        'customer' => '客户A', 'supplier' => '供应商A', 'user_id' => 3,
        'status' => 0, 'write_date' => '2026-10-20',
        'project_model' => '新能源重卡 X1', 'part_name' => '精密减速机壳体',
        'part_no' => 'PN-2026-0001', 'part_material' => 'ADC12 压铸铝',
        'pack_box_no' => 'BX-2026-001', 'pack_material' => 'carton',
        'part_weight' => 12.5,
        'part_size' => [420, 320, 280],
        'box_size' => [600, 400, 300],
        'gross_weight' => 15.2,
        'packing_qty' => 4, 'returnable' => 0, 'dunnage_returnable' => 0,
        'with_pallet' => 1,
        'pallet_size' => [1200, 1000, 1350],
        'pallet_layer_qty' => 8, 'pallet_layer_count' => 4, 'pallet_parts_qty' => 128,
        'description' => '精密减速机壳体出口包装，五层瓦楞纸箱 + EPE 内衬。',
        'instruction' => "1. 每箱内衬 EPE 10mm，零件间用隔板分隔；\n2. 每层 8 箱，堆码 4 层，单托 128 件；\n3. 托盘缠绕膜 3 层，加两道打包带固定；\n4. 箱外贴唛头，注明零件号与数量。",
        'create_time' => '2026-10-20 09:12:00',
    ],
    [
        'proposal_no' => 'PS202610250003',
        'customer' => '客户B', 'supplier' => '供应商A', 'user_id' => 3,
        'status' => 1, 'write_date' => '2026-10-21',
        'project_model' => '电驱系统 E2', 'part_name' => '电机定子总成',
        'part_no' => 'PN-2026-0002', 'part_material' => '硅钢片 + 铜绕组',
        'pack_box_no' => 'BX-2026-002', 'pack_material' => 'plastic',
        'part_weight' => 18.6,
        'part_size' => [380, 380, 260],
        'box_size' => [500, 500, 300],
        'gross_weight' => 22.4,
        'packing_qty' => 2, 'returnable' => 1, 'dunnage_returnable' => 1,
        'with_pallet' => 1,
        'pallet_size' => [1200, 1000, 1200],
        'pallet_layer_qty' => 8, 'pallet_layer_count' => 3, 'pallet_parts_qty' => 48,
        'description' => '电机定子总成周转包装，可回收塑料箱 + 定制内衬。',
        'instruction' => "1. 使用可回收塑料周转箱，内衬 EVA 定位；\n2. 每层 8 箱、堆码 3 层，单托 48 件；\n3. 空箱回收时叠放，不得压伤内衬；\n4. 运输过程禁止雨淋、倒置。",
        'create_time' => '2026-10-21 10:35:00',
    ],
    [
        'proposal_no' => 'PS202610250004',
        'customer' => '客户C', 'supplier' => '供应商A', 'user_id' => 3,
        'status' => 2, 'write_date' => '2026-10-21',
        'project_model' => '智能座舱 C3', 'part_name' => '电控箱体',
        'part_no' => 'PN-2026-0003', 'part_material' => '铝合金 6061',
        'pack_box_no' => 'BX-2026-003', 'pack_material' => 'carton',
        'part_weight' => 6.8,
        'part_size' => [460, 320, 280],
        'box_size' => [580, 420, 360],
        'gross_weight' => 10.1,
        'packing_qty' => 3, 'returnable' => 0, 'dunnage_returnable' => 0,
        'with_pallet' => 1,
        'pallet_size' => [1200, 800, 1400],
        'pallet_layer_qty' => 6, 'pallet_layer_count' => 4, 'pallet_parts_qty' => 72,
        'description' => '电控箱体纸箱包装，防静电袋 + EPE 缓冲。',
        'instruction' => "1. 零件先套防静电袋，再放入 EPE 内衬；\n2. 每箱 3 件，箱内间隙用泡沫填充；\n3. 每层 6 箱、堆码 4 层，单托 72 件；\n4. 托盘四角加护角，防止运输磕碰。",
        'create_time' => '2026-10-21 15:20:00',
        'review_time' => '2026-10-22 09:30:00',
        'review_remark' => '包装方案符合要求，同意通过。',
    ],
    [
        'proposal_no' => 'PS202610250005',
        'customer' => '客户D', 'supplier' => '供应商A', 'user_id' => 3,
        'status' => 3, 'write_date' => '2026-10-22',
        'project_model' => '商用车 D4', 'part_name' => '传动轴组件',
        'part_no' => 'PN-2026-0004', 'part_material' => '40Cr 锻钢',
        'pack_box_no' => 'BX-2026-004', 'pack_material' => 'metal',
        'part_weight' => 24.3,
        'part_size' => [900, 160, 160],
        'box_size' => [1000, 220, 220],
        'gross_weight' => 28.7,
        'packing_qty' => 2, 'returnable' => 0, 'dunnage_returnable' => 0,
        'with_pallet' => 0,
        'pallet_size' => [0, 0, 0],
        'pallet_layer_qty' => 0, 'pallet_layer_count' => 0, 'pallet_parts_qty' => 0,
        'description' => '传动轴组件铁箱包装，不带托盘交付，直接装车。',
        'instruction' => "1. 铁箱内衬橡胶垫，轴端加保护套；\n2. 每箱 2 件，轴向固定不得窜动；\n3. 不带托盘，装车时箱体之间加隔板；\n4. 吊装时使用箱体专用吊耳。",
        'create_time' => '2026-10-22 11:05:00',
        'review_time' => '2026-10-23 10:10:00',
        'review_remark' => '铁箱内缓冲不足，且缺少防锈说明，请补充后重新提交。',
    ],
    [
        'proposal_no' => 'PS202610250006',
        'customer' => '客户E', 'supplier' => '供应商A', 'user_id' => 3,
        'status' => 4, 'write_date' => '2026-10-19',
        'project_model' => '轻卡 K5', 'part_name' => '后桥壳体总成',
        'part_no' => 'PN-2026-0005', 'part_material' => 'QT450 球墨铸铁',
        'pack_box_no' => 'BX-2026-005', 'pack_material' => 'metal',
        'part_weight' => 32.4,
        'part_size' => [620, 480, 420],
        'box_size' => [700, 560, 480],
        'gross_weight' => 36.8,
        'packing_qty' => 1, 'returnable' => 0, 'dunnage_returnable' => 0,
        'with_pallet' => 1,
        'pallet_size' => [1200, 1000, 1500],
        'pallet_layer_qty' => 4, 'pallet_layer_count' => 3, 'pallet_parts_qty' => 12,
        'description' => '后桥壳体铁箱包装，出口订单，已评审通过并归档。',
        'instruction' => "1. 铁箱底部铺 20mm 橡胶垫，壳体内腔加支撑；\n2. 每托 4 箱、堆码 3 层，单托 12 件；\n3. 表面涂防锈油，箱内放置干燥剂；\n4. 出口唛头按客户模板打印。",
        'create_time' => '2026-10-19 08:40:00',
        'review_time' => '2026-10-20 14:15:00',
        'review_remark' => '方案完整，同意通过。',
        'archive_time' => '2026-10-21 09:00:00',
    ],
];

$attachmentTypes = ['part', 'inner', 'final', 'packaging'];

/**
 * 生成方案图片并登记附件（已存在则刷新图片与文件大小）
 */
$saveImages = function (int $proposalId, array $plan) use ($pdo, $prefix, $imageDir, $attachmentTypes): void {
    $find = $pdo->prepare("SELECT id FROM {$prefix}attachment WHERE proposal_id = ? AND attach_type = ? LIMIT 1");

    foreach ($attachmentTypes as $type) {
        $image = makeImage($imageDir, $type, [
            'proposal_no' => $plan['proposal_no'],
            'part_name'   => $plan['part_name'],
            'customer'    => $plan['customer'],
            'supplier'    => $plan['supplier'],
            'pack_box_no' => $plan['pack_box_no'],
        ]);

        $find->execute([$proposalId, $type]);
        $attachmentId = (int) ($find->fetchColumn() ?: 0);

        if ($attachmentId > 0) {
            $pdo->prepare("UPDATE {$prefix}attachment
                SET file_name = ?, file_path = ?, file_size = ?, mime_type = 'image/png'
                WHERE id = ?")
                ->execute([$image['name'], $image['path'], $image['size'], $attachmentId]);
            continue;
        }

        $pdo->prepare("INSERT INTO {$prefix}attachment
            (proposal_id, attach_type, user_id, file_name, file_path, file_ext, file_size, mime_type, create_time)
            VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([
                $proposalId, $type, $plan['user_id'], $image['name'], $image['path'],
                'png', $image['size'], 'image/png', $plan['create_time'],
            ]);
    }
};

$pdo->beginTransaction();

try {
    foreach ($plans as $plan) {
        $stmt = $pdo->prepare("SELECT id FROM {$prefix}pack_proposal WHERE proposal_no = ? LIMIT 1");
        $stmt->execute([$plan['proposal_no']]);
        $existingId = (int) ($stmt->fetchColumn() ?: 0);

        if ($existingId > 0) {
            $saveImages($existingId, $plan);
            echo "刷新图片：{$plan['proposal_no']}\n";
            continue;
        }

        $customer = master($pdo, $prefix, 'customer', $plan['customer']);
        $supplier = master($pdo, $prefix, 'supplier', $plan['supplier']);

        [$bl, $bw, $bh] = $plan['box_size'];
        [$pl, $pw, $ph] = $plan['part_size'];
        [$tl, $tw, $th] = $plan['pallet_size'];

        $approved = in_array($plan['status'], [2, 4], true);
        $rejected = $plan['status'] === 3;
        $submitted = $plan['status'] >= 1;

        $sql = "INSERT INTO {$prefix}pack_proposal
            (proposal_no, customer, spec, quantity, supplier, contact, phone, description,
             write_date, project_model, part_name, part_no, part_material,
             customer_address, customer_contact, customer_phone, customer_email, customer_fax,
             supplier_code, supplier_address, supplier_email, supplier_fax,
             pack_box_no, pack_material, part_weight, part_size_l, part_size_w, part_size_h,
             box_size_l, box_size_w, box_size_h, gross_weight, returnable, dunnage_returnable,
             with_pallet, packing_qty, pallet_size_l, pallet_size_w, pallet_size_h,
             pallet_layer_qty, pallet_layer_count, pallet_parts_qty, packaging_instruction,
             version, status, user_id, reviewer_id, review_remark,
             submit_time, review_time, archive_time, create_time, update_time)
            VALUES " . '(' . implode(',', array_fill(0, 53, '?')) . ')';

        $pdo->prepare($sql)->execute([
            $plan['proposal_no'], $plan['customer'], "{$bl}*{$bw}*{$bh}mm", $plan['packing_qty'],
            $plan['supplier'], $supplier['contact'] ?? '', $supplier['phone'] ?? '', $plan['description'],
            $plan['write_date'], $plan['project_model'], $plan['part_name'], $plan['part_no'], $plan['part_material'],
            $customer['address'] ?? '', $customer['contact'] ?? '', $customer['phone'] ?? '',
            $customer['email'] ?? '', $customer['fax'] ?? '',
            $supplier['code'] ?? '', $supplier['address'] ?? '', $supplier['email'] ?? '', $supplier['fax'] ?? '',
            $plan['pack_box_no'], $plan['pack_material'], $plan['part_weight'], $pl, $pw, $ph,
            $bl, $bw, $bh, $plan['gross_weight'], $plan['returnable'], $plan['dunnage_returnable'],
            $plan['with_pallet'], $plan['packing_qty'], $tl, $tw, $th,
            $plan['pallet_layer_qty'], $plan['pallet_layer_count'], $plan['pallet_parts_qty'],
            $plan['instruction'],
            $approved || $rejected ? 2 : 1,
            $plan['status'],
            $plan['user_id'],
            $approved || $rejected ? 2 : null,
            $approved ? $plan['review_remark'] : ($rejected ? $plan['review_remark'] : ''),
            $submitted ? $plan['create_time'] : null,
            $plan['review_time'] ?? null,
            $plan['archive_time'] ?? null,
            $plan['create_time'], $plan['create_time'],
        ]);

        $proposalId = (int) $pdo->lastInsertId();

        // 方案图片
        $saveImages($proposalId, $plan);

        // 审计日志
        $logs = [
            ['create', '新增方案', null, 0, 1, '创建方案：' . $plan['part_name'], $plan['user_id'], $plan['create_time']],
        ];

        if ($submitted) {
            $logs[] = ['submit', '提交评审', 0, 1, 1, '第 1 版提交评审', $plan['user_id'], $plan['create_time']];
        }
        if ($approved || $rejected) {
            $logs[] = [
                $approved ? 'review_pass' : 'review_reject',
                $approved ? '评审通过' : '评审驳回',
                1, $approved ? 2 : 3, 1,
                $plan['review_remark'], 2, $plan['review_time'],
            ];
        }
        if ($plan['status'] === 4) {
            $logs[] = ['archive', '方案归档', 2, 4, 2, '方案归档：' . $plan['part_name'], 1, $plan['archive_time']];
        }

        foreach ($logs as $log) {
            $pdo->prepare("INSERT INTO {$prefix}review_log
                (proposal_id, user_id, action, action_name, from_status, to_status, version, content, ip, create_time)
                VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$proposalId, $log[6], $log[0], $log[1], $log[2], $log[3], $log[4], $log[5], '127.0.0.1', $log[7]]);
        }

        echo "已生成：{$plan['proposal_no']}   {$plan['part_name']}   4 张图片\n";
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    exit('生成失败：' . $e->getMessage() . "\n");
}

echo "完成。\n";
