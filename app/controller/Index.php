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
            $methods = $this->allowedMethods($path);

            // 路径存在但请求方法不对（如用 GET 调用仅支持 POST 的接口）：明确提示，避免与「路径不存在」混淆
            if ($methods !== []) {
                return json([
                    'code' => 0,
                    'msg'  => '请求方法不支持，该接口仅支持 ' . implode(' / ', $methods),
                    'data' => [],
                ], 405);
            }

            return json([
                'code' => 0,
                'msg'  => '接口不存在（当前请求方法：' . strtoupper($this->request->method()) . '）',
                'data' => [],
            ], 404);
        }

        // 带扩展名的地址按静态资源处理，不做回退
        if (str_contains(basename($path), '.')) {
            return response('页面不存在', 404);
        }

        return $this->renderSpa();
    }

    /**
     * 路径能匹配到的路由所允许的请求方法（用于区分「方法不对」与「接口不存在」）
     * @param string $path
     * @return array<int, string>
     */
    protected function allowedMethods(string $path): array
    {
        try {
            $rules = app('route')->getRuleList();
        } catch (\Throwable $e) {
            return [];
        }

        $methods = [];

        foreach ($rules as $item) {
            $rule   = trim((string) ($item['rule'] ?? ''), '/');
            $method = strtoupper((string) ($item['method'] ?? ''));

            if ($rule === '' || $method === '' || str_contains($rule, '<MISS>')) {
                continue;
            }

            if (preg_match('#^' . $this->ruleRegex($rule) . '$#', $path)) {
                $methods[$method] = $method;
            }
        }

        sort($methods);

        return array_values($methods);
    }

    /**
     * 路由规则转正则：<id> 等变量占位按单段任意字符匹配
     * @param string $rule
     * @return string
     */
    protected function ruleRegex(string $rule): string
    {
        // 先把占位符替换为不会出现在规则中的控制字符，再做正则转义
        $token  = str_replace(['<', '>'], ["\x01", "\x02"], $rule);
        $quoted = preg_quote($token, '#');

        return preg_replace('/\x01[^\x02]*\x02/', '[^/]+', $quoted);
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
