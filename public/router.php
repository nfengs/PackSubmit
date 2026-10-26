<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
// $Id$

$path   = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$index  = __DIR__ . '/index.html';

// 前端路由回退（Vue history 模式）：刷新 /admin、/proposal/form 等地址时返回 index.html
// 注意：内置服务器对 /admin 会把 SCRIPT_NAME 置为 /index.php，因此必须在下面的静态判断之前处理
if (
    in_array($method, ['GET', 'HEAD'], true)
    && !str_starts_with($path, '/api')
    && !str_contains(basename($path), '.')
    && is_file($index)
    && !is_file($_SERVER['DOCUMENT_ROOT'] . $path)
) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($index);

    return true;
}

// 静态文件直接交给内置服务器处理
if (is_file($_SERVER["DOCUMENT_ROOT"] . $_SERVER["SCRIPT_NAME"])) {
    return false;
}

$_SERVER["SCRIPT_FILENAME"] = __DIR__ . '/index.php';

require __DIR__ . "/index.php";
