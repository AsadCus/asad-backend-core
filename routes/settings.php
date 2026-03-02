<?php

use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\Settings\NotificationController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ReportTemplateController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    Route::get('settings/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');
    Route::post('settings/appearance', [AppearanceController::class, 'update'])->name('appearance.update');

    Route::get('settings/report-template', [ReportTemplateController::class, 'index'])->name('report-template.edit');
    Route::put('settings/report-template', [ReportTemplateController::class, 'update'])->name('report-template.update');
    Route::post('settings/report-template/modules', [ReportTemplateController::class, 'storeModule'])->name('report-template.modules.store');
    Route::delete('settings/report-template/modules/{key}', [ReportTemplateController::class, 'destroyModule'])->name('report-template.modules.destroy');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});
