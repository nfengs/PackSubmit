<?php
// +----------------------------------------------------------------------
// | 包装方案提交评审系统 API 路由
// | 统一前缀 /api
// +----------------------------------------------------------------------

use app\middleware\JwtAuth;
use app\middleware\RoleAuth;
use think\facade\Route;

Route::group('api', function () {

    // -------------------- 公共接口（无需登录） --------------------
    Route::post('auth/login', 'Auth/login');

    // -------------------- 需登录接口 --------------------
    Route::group(function () {

        // 认证
        Route::post('auth/logout', 'Auth/logout');
        Route::get('auth/profile', 'Auth/profile');
        Route::put('auth/password', 'Auth/password');

        // 字典（下拉数据源）
        Route::get('dict/options', 'Dict/options');
        Route::get('dict/types', 'Dict/types');

        // 主数据（客户 / 供应商档案，选择后自动带出资料）
        Route::get('master-data/customers', 'MasterData/customers');
        Route::get('master-data/suppliers', 'MasterData/suppliers');
        Route::get('master-data/my-supplier', 'MasterData/mySupplier');
        Route::get('master-data/parts', 'MasterData/parts');

        // 供应商联系人（供应商自行维护：新增 / 编辑 / 删除；方案表单按供应商读取）
        Route::get('supplier-contacts', 'SupplierContact/index');
        Route::post('supplier-contacts', 'SupplierContact/save');
        Route::put('supplier-contacts/:id', 'SupplierContact/update')->pattern(['id' => '\d+']);
        Route::delete('supplier-contacts/:id', 'SupplierContact/delete')->pattern(['id' => '\d+']);

        // 包装方案
        Route::get('proposals/statistics', 'Proposal/statistics');
        Route::get('proposals', 'Proposal/index');
        Route::get('proposals/projects', 'Proposal/projects');
        Route::post('proposals', 'Proposal/save');
        Route::post('proposals/:id/copy', 'Proposal/copy')->pattern(['id' => '\d+']);
        Route::post('proposals/:id/change', 'Proposal/change')->pattern(['id' => '\d+']);
        Route::get('proposals/:id', 'Proposal/read')->pattern(['id' => '\d+']);
        Route::put('proposals/:id', 'Proposal/update')->pattern(['id' => '\d+']);
        Route::delete('proposals/:id', 'Proposal/delete')->pattern(['id' => '\d+']);
        Route::post('proposals/:id/submit', 'Proposal/submit')->pattern(['id' => '\d+']);
        Route::post('proposals/:id/withdraw', 'Proposal/withdraw')->pattern(['id' => '\d+']);
        Route::post('proposals/:id/review', 'Proposal/review')->pattern(['id' => '\d+']);

        // 包装确认单导出
        Route::get('proposals/:id/export/excel', 'Proposal/exportExcel')->pattern(['id' => '\d+']);

        // PDF 导出功能暂停（实现保留在 Proposal::exportPdf / PackExportService::pdf，恢复时取消注释）
        // Route::get('proposals/:id/export/pdf', 'Proposal/exportPdf')->pattern(['id' => '\d+']);

        // 附件
        Route::post('attachments/upload', 'Attachment/upload');
        Route::post('attachments/bind', 'Attachment/bind');
        Route::get('attachments/:id/download', 'Attachment/download')->pattern(['id' => '\d+']);
        Route::get('attachments/:id/preview', 'Attachment/preview')->pattern(['id' => '\d+']);
        Route::delete('attachments/:id', 'Attachment/delete')->pattern(['id' => '\d+']);

        // -------------------- 管理员接口（强制 role=3） --------------------
        Route::group('admin', function () {

            // 用户管理
            Route::get('users', 'admin.User/index');
            Route::get('users/:id/logs', 'admin.User/logs')->pattern(['id' => '\d+']);
            Route::post('users', 'admin.User/save');
            Route::put('users/:id', 'admin.User/update')->pattern(['id' => '\d+']);
            Route::patch('users/:id/status', 'admin.User/status')->pattern(['id' => '\d+']);
            Route::post('users/:id/reset-password', 'admin.User/resetPassword')->pattern(['id' => '\d+']);

            // 字典管理
            Route::get('dicts', 'admin.Dict/index');
            Route::post('dicts', 'admin.Dict/save');
            Route::put('dicts/:id', 'admin.Dict/update')->pattern(['id' => '\d+']);
            Route::delete('dicts/:id', 'admin.Dict/delete')->pattern(['id' => '\d+']);

            // 客户档案维护
            Route::get('customers', 'admin.Customer/index');
            Route::get('customers/check-name', 'admin.Customer/checkName');
            Route::get('customers/:id/logs', 'admin.Customer/logs')->pattern(['id' => '\d+']);
            Route::post('customers', 'admin.Customer/save');
            Route::put('customers/:id', 'admin.Customer/update')->pattern(['id' => '\d+']);
            Route::delete('customers/:id', 'admin.Customer/delete')->pattern(['id' => '\d+']);

            // 指定零件号维护（Excel 导入）
            Route::get('parts', 'admin.SupplierPart/index');
            Route::get('parts/template', 'admin.SupplierPart/template');
            Route::post('parts/import', 'admin.SupplierPart/import');
            Route::post('parts/batch-preview', 'admin.SupplierPart/batchPreview');
            Route::post('parts/batch-update', 'admin.SupplierPart/batchUpdate');
            Route::get('parts/:id/logs', 'admin.SupplierPart/logs')->pattern(['id' => '\d+']);
            Route::put('parts/:id', 'admin.SupplierPart/update')->pattern(['id' => '\d+']);
            Route::delete('parts/:id', 'admin.SupplierPart/delete')->pattern(['id' => '\d+']);

            // 供应商档案维护
            Route::get('suppliers', 'admin.Supplier/index');
            Route::get('suppliers/check-name', 'admin.Supplier/checkName');
            Route::get('suppliers/:id/logs', 'admin.Supplier/logs')->pattern(['id' => '\d+']);
            Route::post('suppliers', 'admin.Supplier/save');
            Route::put('suppliers/:id', 'admin.Supplier/update')->pattern(['id' => '\d+']);
            Route::delete('suppliers/:id', 'admin.Supplier/delete')->pattern(['id' => '\d+']);

            // 全量方案 / 全量详细方案 / 批量归档
            Route::get('proposals', 'admin.Proposal/index');
            Route::get('proposals/detail', 'admin.Proposal/detail');
            Route::post('proposals/archive', 'admin.Proposal/archive');

            // 全局审计日志
            Route::get('logs', 'admin.Log/index');

        })->middleware(RoleAuth::class, '3');

    })->middleware(JwtAuth::class);

});
