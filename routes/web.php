<?php

use App\Http\Controllers\AgreementController;
use App\Http\Controllers\CustomerConfirmationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\EnquiryRemarkController;
use App\Http\Controllers\GeneralEnquiryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\Master\AdminController as MasterAdminController;
use App\Http\Controllers\Master\BranchController as MasterBranchController;
use App\Http\Controllers\Master\CustomerController as MasterCustomerController;
use App\Http\Controllers\Master\FinancialYearController as MasterFinancialYearController;
use App\Http\Controllers\Master\MasterController;
use App\Http\Controllers\Master\SalesController as MasterSalesController;
use App\Http\Controllers\Master\SupplierController as MasterSupplierController;
use App\Http\Controllers\Master\UserController as MasterUserController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OpsMovementController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PrivateEnquiryController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\QuotationItemController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserLogsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // return Inertia::render('welcome');
    return redirect()->route('login');
})->name('home');

// Public General Enquiry Routes (No Authentication Required)
Route::get('general-enquiries/public/create', [GeneralEnquiryController::class, 'publicForm'])->name('general-enquiries.public.create');
Route::post('general-enquiries/public/store', [GeneralEnquiryController::class, 'storePublic'])->name('general-enquiries.public.store');

// Public Private Enquiry Routes (No Authentication Required)
Route::get('private-enquiries/public/create', [PrivateEnquiryController::class, 'publicForm'])->name('private-enquiries.public.create');
Route::post('private-enquiries/public/store', [PrivateEnquiryController::class, 'storePublic'])->name('private-enquiries.public.store');

// Public Customer Confirmation Create
Route::get('customer-confirmation/public/create', [CustomerConfirmationController::class, 'publicCreateForm'])->name('customer-confirmation.public.create');
Route::post('customer-confirmation/public/create', [CustomerConfirmationController::class, 'publicCreateStore'])->name('customer-confirmation.public.store');

// Public Customer Confirmation Edit (Encrypted ID)
Route::get('customer-confirmation/public/edit/{encryptedId}', [CustomerConfirmationController::class, 'publicEditForm'])->name('customer-confirmation.public.edit');
Route::post('customer-confirmation/public/update/{encryptedId}', [CustomerConfirmationController::class, 'publicEditStore'])->name('customer-confirmation.public.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/sales-period-options', [DashboardController::class, 'getSalesPeriodOptions'])->name('dashboard.sales-period-options');
    Route::get('dashboard/quotation-converted-by-salesperson', [DashboardController::class, 'getQuotationConvertedBySalesperson'])->name('dashboard.quotation-converted-by-salesperson');
    Route::get('dashboard/sales-dashboard-data', [DashboardController::class, 'getSalesDashboardData'])->name('dashboard.sales-dashboard-data');
    Route::get('dashboard/fiscal-year-total-sales', [DashboardController::class, 'getFiscalYearTotalSales'])->name('dashboard.fiscal-year-total-sales');
    Route::get('dashboard/revenue-by-month', [DashboardController::class, 'getRevenueByMonth'])->name('dashboard.revenue-by-month');
    Route::get('dashboard/income-by-month', [DashboardController::class, 'getIncomeByMonth'])->name('dashboard.income-by-month');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications', [NotificationController::class, 'store'])->name('notifications.store');
    Route::post('notifications/{notification}/action', [NotificationController::class, 'handleAction'])->name('notifications.action');
    Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Masters
    Route::prefix('master')->name('master.')->middleware(['role:admin'])->group(function () {
        Route::get('/', [MasterController::class, 'index'])->name('index');

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
    Route::get('sales/{sale}/preview', [SalesController::class, 'preview'])->name('sales.preview');
    Route::get('sales/{sale}/generate-pdf', [SalesController::class, 'generatePdf'])->name('sales.generate.pdf');

    // Supplier
    Route::resource('supplier', SupplierController::class);

    // Customer
    Route::resource('customer', CustomerController::class);
    Route::get('customer-get-for-show/{id}', [CustomerController::class, 'getForShow'])->name('customer.get-for-show');
    Route::put('customer/{id}/handle', [CustomerController::class, 'handleCustomer'])->name('customer.handle');
    Route::put('customer/{id}/enable', [CustomerController::class, 'enableCustomer'])->name('customer.enable');
    Route::put('customer/{id}/disable', [CustomerController::class, 'disableCustomer'])->name('customer.disable');

    // Quotation
    Route::resource('quotation', QuotationController::class);
    Route::get('quotation-get-for-show/{id}', [QuotationController::class, 'getForShow'])->name('quotation.get-for-show');
    Route::get('quotation/{id}/preview', [QuotationController::class, 'preview'])->name('quotation.preview');
    Route::get('quotation/{id}/generate-pdf', [QuotationController::class, 'generatePdf'])->name('quotation.generate.pdf');
    Route::put('quotation/{id}/ready', [QuotationController::class, 'readyQuotation'])->name('quotation.ready');
    Route::put('quotation/{id}/accept', [QuotationController::class, 'acceptQuotation'])->name('quotation.accept');
    Route::put('quotation/{id}/reject', [QuotationController::class, 'rejectQuotation'])->name('quotation.reject');
    Route::put('quotation/{id}/expire', [QuotationController::class, 'expireQuotation'])->name('quotation.expire');
    Route::put('quotation/{id}/cancel', [QuotationController::class, 'cancelQuotation'])->name('quotation.cancel');

    Route::resource('quotation-items', QuotationItemController::class);
    Route::get('quotation-items-list', [QuotationItemController::class, 'getQuotationItemMastersForOptions'])->name('quotation-items-list');

    // Order
    Route::resource('order', OrderController::class);

    // Invoice
    Route::resource('invoice', InvoiceController::class);
    Route::get('invoice/{id}/preview', [InvoiceController::class, 'preview'])->name('invoice.preview');
    Route::get('invoice/{id}/generate-pdf', [InvoiceController::class, 'generatePdf'])->name('invoice.generate.pdf');
    Route::get('invoice-get-for-show/{id}', [InvoiceController::class, 'getForShow'])->name('invoice.get-for-show');

    // Receipt
    Route::resource('receipt', ReceiptController::class);
    Route::get('receipt/{id}/preview', [ReceiptController::class, 'preview'])->name('receipt.preview');
    Route::get('receipt/{id}/generate-pdf', [ReceiptController::class, 'generatePdf'])->name('receipt.generate.pdf');
    Route::get('receipt-get-for-show/{id}', [ReceiptController::class, 'getForShow'])->name('receipt.get-for-show');

    // Schedule - Generated from Quotation (no database table)
    Route::get('schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::get('schedule/{quotation}/export-pdf', [ScheduleController::class, 'exportPdf'])->name('schedule.export-pdf');

    // Agreement - Generated from Quotation (no database table)
    Route::get('agreement', [AgreementController::class, 'index'])->name('agreement.index');
    Route::get('agreement/{quotation}/preview', [AgreementController::class, 'preview'])->name('agreement.preview');
    Route::get('agreement/{quotation}/export-pdf', [AgreementController::class, 'exportPdf'])->name('agreement.export-pdf');

    // User Logs
    Route::get('user-logs', [UserLogsController::class, 'index'])->name('user-logs.index');

    // General Enquiries
    Route::resource('general-enquiries', GeneralEnquiryController::class);
    Route::get('general-enquiries-get-for-show/{id}', [GeneralEnquiryController::class, 'getForShow'])->name('general-enquiries.get-for-show');

    // Private Enquiries
    Route::resource('private-enquiries', PrivateEnquiryController::class);
    Route::get('private-enquiries-get-for-show/{id}', [PrivateEnquiryController::class, 'getForShow'])->name('private-enquiries.get-for-show');

    // Enquiry Dashboard (read-only view combining general + private)
    Route::get('enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
    Route::get('enquiries-get-for-show/{id}', [EnquiryController::class, 'getForShow'])->name('enquiries.get-for-show');
    Route::put('enquiries/{id}/status', [EnquiryController::class, 'transitionStatus'])->name('enquiries.transition-status');
    Route::post('enquiries/{id}/confirm', [EnquiryController::class, 'confirm'])->name('enquiries.confirm');
    Route::get('enquiries/{id}/package-prefill', [EnquiryController::class, 'packagePrefill'])->name('enquiries.package-prefill');
    Route::post('enquiries/customer-confirmation', [EnquiryController::class, 'createCustomerConfirmation'])->name('enquiries.create-customer-confirmation');
    Route::get('enquiries/search-customers', [EnquiryController::class, 'searchCustomers'])->name('enquiries.search-customers');
    Route::get('enquiries/available-enquiries', [EnquiryController::class, 'availableEnquiries'])->name('enquiries.available-enquiries');
    Route::get('enquiries/list-customers', [EnquiryController::class, 'listCustomers'])->name('enquiries.list-customers');
    Route::put('enquiries/{id}/package', [EnquiryController::class, 'updatePackage'])->name('enquiries.update-package');

    // Customer Confirmations
    Route::get('customer-confirmations/{id}', [CustomerConfirmationController::class, 'show'])->name('customer-confirmations.show');
    Route::put('customer-confirmations/{id}', [CustomerConfirmationController::class, 'update'])->name('customer-confirmations.update');
    Route::delete('customer-confirmations/{id}', [CustomerConfirmationController::class, 'destroy'])->name('customer-confirmations.destroy');
    Route::put('customer-confirmations/members/{memberId}', [CustomerConfirmationController::class, 'updateMember'])->name('customer-confirmations.members.update');
    Route::post('customer-confirmations/members/{memberId}/cancel', [CustomerConfirmationController::class, 'cancelMember'])->name('customer-confirmations.members.cancel');
    Route::post('customer-confirmations/{id}/move-members', [CustomerConfirmationController::class, 'moveMembers'])->name('customer-confirmations.move-members');
    Route::post('customer-confirmations/{id}/generate-quotations', [CustomerConfirmationController::class, 'generateQuotations'])->name('customer-confirmations.generate-quotations');
    Route::get('customer-confirmations/{enquiryId}/generate-link', [CustomerConfirmationController::class, 'generatePublicLink'])->name('customer-confirmations.generate-link');
    Route::get('customer-confirmations/{groupId}/generate-edit-link', [CustomerConfirmationController::class, 'generatePublicEditLink'])->name('customer-confirmations.generate-edit-link');

    // Confirmed Customer (Customer Confirmations)
    Route::get('confirmed-customer', [CustomerConfirmationController::class, 'index'])->middleware('permission:customer view')->name('confirmed-customer.index');
    Route::get('customer-holding', [CustomerConfirmationController::class, 'holdingIndex'])->middleware('permission:customer view')->name('customer-holding.index');
    Route::delete('confirmed-customer/{id}', [CustomerConfirmationController::class, 'destroy'])->middleware('permission:customer edit')->name('confirmed-customer.destroy');

    // Enquiry Remarks
    Route::get('enquiries/{enquiryId}/remarks', [EnquiryRemarkController::class, 'index'])->name('enquiry-remarks.index');
    Route::post('enquiries/{enquiryId}/remarks', [EnquiryRemarkController::class, 'store'])->name('enquiry-remarks.store');
    Route::put('enquiries/{enquiryId}/remarks/{remarkId}', [EnquiryRemarkController::class, 'update'])->name('enquiry-remarks.update');
    Route::delete('enquiries/{enquiryId}/remarks/{remarkId}', [EnquiryRemarkController::class, 'destroy'])->name('enquiry-remarks.destroy');

    // Packages
    Route::resource('packages', PackageController::class);
    Route::get('packages/{id}/download', [PackageController::class, 'generatePdf'])->name('packages.download');
    Route::get('packages-get-for-show/{id}', [PackageController::class, 'getForShow'])->name('packages.get-for-show');

    // Manifests
    Route::resource('manifests', ManifestController::class);
    Route::get('manifests-get-for-show/{id}', [ManifestController::class, 'getForShow'])->name('manifests.get-for-show');

    // Manifest Rooms
    Route::post('manifests/{manifestId}/rooms', [ManifestController::class, 'addRoom'])->name('manifests.rooms.store');
    Route::put('manifests/rooms/{roomId}', [ManifestController::class, 'updateRoom'])->name('manifests.rooms.update');
    Route::delete('manifests/rooms/{roomId}', [ManifestController::class, 'deleteRoom'])->name('manifests.rooms.destroy');

    // Manifest Payments
    Route::post('manifests/{manifestId}/payments', [ManifestController::class, 'addPayment'])->name('manifests.payments.store');
    Route::put('manifests/payments/{paymentId}', [ManifestController::class, 'updatePayment'])->name('manifests.payments.update');
    Route::delete('manifests/payments/{paymentId}', [ManifestController::class, 'deletePayment'])->name('manifests.payments.destroy');
    Route::post('manifests/{manifestId}/travelers/{travelerId}/move-holding', [ManifestController::class, 'moveTravelerToHolding'])->name('manifests.travelers.move-holding');

    // Manifest Sharing Groups
    Route::post('manifests/{manifestId}/sharing-groups', [ManifestController::class, 'attachSharingGroup'])->name('manifests.sharing-groups.attach');
    Route::delete('manifests/{manifestId}/sharing-groups/{sharingGroupId}', [ManifestController::class, 'detachSharingGroup'])->name('manifests.sharing-groups.detach');

    // Ops Movements (read-only view from packages + manifests)
    Route::resource('ops-movements', OpsMovementController::class)->only(['index', 'show']);
});

Route::fallback(function () {
    return Inertia::render('error/notfound');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
