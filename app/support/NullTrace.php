<?php
declare (strict_types = 1);

namespace app\support;

use think\App;
use think\Response;

/**
 * 空调试面板输出
 * 通过 config/trace.php 指定，避免调试模式下向 HTML 响应注入框架调试面板
 * （面板会暴露服务器绝对路径、加载文件列表等信息）
 */
class NullTrace
{
    /**
     * @param App      $app
     * @param Response $response
     * @param array    $log
     * @return bool
     */
    public function output(App $app, Response $response, array $log = []): bool
    {
        return false;
    }
}
