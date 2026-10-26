<?php
// 全局中间件定义文件
return [
    // 跨域处理（前后端分离）
    \app\middleware\Cors::class,
    // 安全响应头
    \app\middleware\SecurityHeaders::class,
];
