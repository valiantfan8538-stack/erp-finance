<?php
use think\facade\Route;

// Public routes (no authentication required)
Route::group('api', function () {
    Route::post('auth/login', 'auth.Login/login');
})->allowCrossDomain();

// Protected routes (JWT authentication required)
Route::group('api', function () {

    // Auth
    Route::get('auth/me', 'auth.Login/me');
    Route::post('auth/logout', 'auth.Login/logout');

    // System management
    Route::group('system', function () {
        Route::resource('account-books', 'system.AccountBook');
        Route::post('periods/:id/close', 'system.Period/close');
        Route::post('periods/:id/open', 'system.Period/open');
        Route::resource('periods', 'system.Period');
        Route::resource('voucher-types', 'system.VoucherType');
        Route::resource('users', 'system.User');
        Route::resource('roles', 'system.Role');
        Route::resource('partners', 'system.Partner');
    });

    // Finance - General Ledger
    Route::group('finance', function () {
        Route::get('subjects/tree', 'finance.AccountSubject/tree');
        Route::post('subjects/import', 'finance.AccountSubject/import');
        Route::resource('subjects', 'finance.AccountSubject');

        Route::post('vouchers/:id/audit', 'finance.Voucher/audit');
        Route::post('vouchers/:id/unaudit', 'finance.Voucher/unaudit');
        Route::post('vouchers/:id/post', 'finance.Voucher/post');
        Route::post('vouchers/:id/unpost', 'finance.Voucher/unpost');
        Route::resource('vouchers', 'finance.Voucher');

        Route::get('ledger/general', 'finance.Ledger/general');
        Route::get('ledger/detail', 'finance.Ledger/detail');
        Route::get('ledger/cash', 'finance.Ledger/cash');
        Route::get('ledger/bank', 'finance.Ledger/bank');
        Route::get('ledger/subject-balance', 'finance.Ledger/subjectBalance');
    });

    // Finance - AR/AP
    Route::group('finance', function () {
        Route::resource('receivables', 'finance.ARAP');
        Route::resource('payables', 'finance.ARAP');
        Route::post('receivables/:id/verify', 'finance.ARAP/verifyReceivable');
        Route::post('payables/:id/verify', 'finance.ARAP/verifyPayable');
        Route::get('arap/aging', 'finance.ARAP/agingAnalysis');
    });

    // Finance - Fixed Assets
    Route::group('finance', function () {
        Route::post('asset-cards/:id/depreciate', 'finance.FixedAsset/depreciate');
        Route::post('asset-cards/:id/dispose', 'finance.FixedAsset/dispose');
        Route::post('asset-batch-depreciate', 'finance.FixedAsset/batchDepreciate');
        Route::get('asset-depreciation-summary', 'finance.FixedAsset/depreciationSummary');
        Route::resource('asset-cards', 'finance.FixedAsset');
    });

    // Reports
    Route::group('finance', function () {
        Route::get('reports/balance-sheet', 'finance.Report/balanceSheet');
        Route::get('reports/income-statement', 'finance.Report/incomeStatement');
        Route::get('reports/cash-flow', 'finance.Report/cashFlow');
        Route::get('reports/export', 'finance.Report/export');
    });

    // CRM Integration
    Route::group('crm', function () {
        Route::post('sync/order', 'finance.CRM/syncOrder');
        Route::post('sync/payment', 'finance.CRM/syncPayment');
    });

})->middleware(\app\middleware\AuthMiddleware::class)->allowCrossDomain();
