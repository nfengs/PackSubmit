<?php
// 应用公共文件

if (!function_exists('like_escape')) {
    /**
     * 转义 LIKE 查询中的通配符（% _ \），避免用户输入被当作通配符
     * 用法：$like = '%' . like_escape($keyword) . '%';
     * @param string $value
     * @return string
     */
    function like_escape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
