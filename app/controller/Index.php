<?php

namespace app\controller;

use app\BaseController;
use think\Response;

class Index extends BaseController
{
    public function index()
    {
        return $this->renderSpa();
    }

    public function hello($name = 'ThinkPHP8')
    {
        return 'hello,' . $name;
    }

    /**
     * 前端路由回退（Vue history 模式）
     * 直接访问 / 刷新 /admin、/proposal/form 等页面时返回 index.html；
     * /api 下未匹配的地址仍按接口不存在返回 JSON
     */
    public function spa(): Response
    {
        $path = trim((string) $this->request->pathinfo(), '/');

        if (str_starts_with($path, 'api')) {
            return json(['code' => 0, 'msg' => '接口不存在', 'data' => []], 404);
        }

        // 带扩展名的地址按静态资源处理，不做回退
        if (str_contains(basename($path), '.')) {
            return response('页面不存在', 404);
        }

        return $this->renderSpa();
    }

    /**
     * 输出前端入口文件
     */
    protected function renderSpa(): Response
    {
        $file = public_path() . 'index.html';

        if (!is_file($file)) {
            return response('前端资源未构建，请先执行 npm run build', 404);
        }

        return Response::create((string) file_get_contents($file), 'html', 200);
    }
}
