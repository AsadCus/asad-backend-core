<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MaidController;
use App\Http\Controllers\Master\FinancialYearController as MasterFinancialYearController;
use App\Http\Controllers\Master\AdminController as MasterAdminController;
use App\Http\Controllers\Master\BranchController as MasterBranchController;
use App\Http\Controllers\Master\CustomerController as MasterCustomerController;
use App\Http\Controllers\Master\SalesController as MasterSalesController;
use App\Http\Controllers\Master\SupplierController as MasterSupplierController;
use App\Http\Controllers\Master\UserController as MasterUserController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\QuotationItemController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\AgreementController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // return Inertia::render('welcome');
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications', [NotificationController::class, 'store'])->name('notifications.store');
    Route::post('notifications/{notification}/action', [NotificationController::class, 'handleAction'])->name('notifications.action');
    Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Masters
    Route::prefix('master')->name('master.')->middleware(['role:admin'])->group(function () {
        Route::get('/', function () {
            return Inertia::render('masters/index');
        })->name('index');

        // Master - Users
        Route::prefix('user')->name('user.')->group(function () {
            Route::resource('admin', MasterAdminController::class);
            Route::resource('sales', MasterSalesController::class);
            Route::resource('supplier', MasterSupplierController::class);
            Route::resource('customer', MasterCustomerController::class);
            Route::post('customer/{id}/create-quotation', [MasterCustomerController::class, 'createQuotation'])->name('customer.create-quotation');
        });

        Route::resource('user', MasterUserController::class);

        Route::resource('branch', MasterBranchController::class);

        Route::resource('financial-year', MasterFinancialYearController::class);
        Route::put('financial-year/{id}/set-default', [MasterFinancialYearController::class, 'updateDefault'])->name('financial-year.set-default');

        // notes
        Route::resource('note', NoteController::class);
    });

    // Sales
    Route::resource('sales', SalesController::class);

    // Supplier
    Route::resource('supplier', SupplierController::class);

    // Customer
    Route::resource('customer', CustomerController::class);
    Route::post('customer/{id}/recommend-maid', [CustomerController::class, 'submitRecommendMaid'])->name('customer.recommend-maid-submit');
    Route::get('customer-get-for-show/{id}', [CustomerController::class, 'getForShow'])->name('customer.get-for-show');
    Route::get('customer/{id}/recommend-maid', [CustomerController::class, 'editRecommendMaid'])->name('customer.recommend-maid-edit');
    Route::put('customer/{id}/handle', [CustomerController::class, 'handleCustomer'])->name('customer.handle');
    Route::post('customer/{customerId}/assign-maid/{maidId}', [CustomerController::class, 'assignMaidToCustomer'])->name('customer.assign-maid');

    // Maid
    Route::get('maid-get-for-show/{id}', [MaidController::class, 'getForShow'])->name('maid.get-for-show');
    Route::post('maid/upload-document', [MaidController::class, 'uploadDocument'])->name('maid.upload.document');
    Route::post('maid/save-scan-result', [MaidController::class, 'saveScanResult'])->name('maid.save.scan');

    // Maid Document Generation
    Route::get('maid/{id}/generate-pdf', [MaidController::class, 'generatePdf'])->name('maid.generate.pdf');
    Route::get('maid/{id}/preview-biodata', [MaidController::class, 'previewBiodata'])->name('maid.preview.biodata');

    // Maid Status Management
    Route::post('maid/{id}/schedule-interview', [MaidController::class, 'scheduleInterview'])->name('maid.schedule.interview');
    Route::post('maid/{id}/complete-interview', [MaidController::class, 'completeInterview'])->name('maid.complete.interview');
    Route::post('maid/{id}/finalize-documents', [MaidController::class, 'finalizeDocuments'])->name('maid.finalize.documents');
    Route::put('maid/{id}/update-status', [MaidController::class, 'updateStatus'])->name('maid.update.status');
    Route::put('maid/{id}/update-maid-status', [MaidController::class, 'updateMaidStatus'])->name('maid.update.maid.status');
    Route::delete('maid/{id}/cancel-interview', [MaidController::class, 'cancelInterview'])->name('maid.cancel.interview');

    Route::resource('maid', MaidController::class);

    // Quotation
    Route::resource('quotation', QuotationController::class);
    Route::get('quotation-get-for-show/{id}', [QuotationController::class, 'getForShow'])->name('quotation.get-for-show');
    Route::get('quotation/{id}/generate-pdf', [QuotationController::class, 'generatePdf'])->name('quotation.generate.pdf');
    Route::put('quotation/{id}/ready', [QuotationController::class, 'readyQuotation'])->name('quotation.ready');
    Route::put('quotation/{id}/accept', [QuotationController::class, 'acceptQuotation'])->name('quotation.accept');
    Route::put('quotation/{id}/reject', [QuotationController::class, 'rejectQuotation'])->name('quotation.reject');
    Route::put('quotation/{id}/expire', [QuotationController::class, 'expireQuotation'])->name('quotation.expire');

    Route::resource('quotation-items', QuotationItemController::class);
    Route::get('quotation-items-list', [QuotationItemController::class, 'getQuotationItemMastersForOptions'])->name('quotation-items-list');

    // Order
    Route::resource('order', OrderController::class);

    // Invoice
    Route::resource('invoice', InvoiceController::class);
    Route::get('invoice/{id}/generate-pdf', [InvoiceController::class, 'generatePdf'])->name('invoice.generate.pdf');
    Route::get('invoice-get-for-show/{id}', [InvoiceController::class, 'getForShow'])->name('invoice.get-for-show');

    // Receipt
    Route::resource('receipt', ReceiptController::class);
    Route::get('receipt/{id}/generate-pdf', [ReceiptController::class, 'generatePdf'])->name('receipt.generate.pdf');
    Route::get('receipt-get-for-show/{id}', [ReceiptController::class, 'getForShow'])->name('receipt.get-for-show');

    // Schedule - Generated from Quotation (no database table)
    Route::get('schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::get('schedule/{quotation}/export-pdf', [ScheduleController::class, 'exportPdf'])->name('schedule.export-pdf');

    // Agreement - Generated from Quotation (no database table)
    Route::get('agreement', [AgreementController::class, 'index'])->name('agreement.index');
    Route::get('agreement/{quotation}/export-pdf', [AgreementController::class, 'exportPdf'])->name('agreement.export-pdf');
});

Route::fallback(function () {
    return Inertia::render('error/notfound');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
