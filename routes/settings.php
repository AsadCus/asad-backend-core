<?php

use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\Settings\ModelNumberFormatController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ReportTemplateController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    Route::get('settings/appearance', [AppearanceController::class, 'edit'])
        ->middleware('role:admin')
        ->name('appearance.edit');
    Route::post('settings/appearance', [AppearanceController::class, 'update'])
        ->middleware('role:admin')
        ->name('appearance.update');

    Route::get('settings/report-template', [ReportTemplateController::class, 'index'])
        ->middleware('role:admin')
        ->name('report-template.edit');
    Route::put('settings/report-template', [ReportTemplateController::class, 'update'])
        ->middleware('role:admin')
        ->name('report-template.update');
    Route::post('settings/report-template/modules', [ReportTemplateController::class, 'storeModule'])
        ->middleware('role:admin')
        ->name('report-template.modules.store');
    Route::delete('settings/report-template/modules/{key}', [ReportTemplateController::class, 'destroyModule'])
        ->middleware('role:admin')
        ->name('report-template.modules.destroy');
    Route::get('api/report-template/branding', [ReportTemplateController::class, 'getBrandingData'])
        ->middleware('role:admin')
        ->name('report-template.branding.get');
    Route::post('api/report-template/preview', [ReportTemplateController::class, 'preview'])
        ->middleware('role:admin')
        ->name('report-template.preview');

    Route::get('settings/model-number-formats', [ModelNumberFormatController::class, 'edit'])
        ->middleware('role:admin')
        ->name('model-number-formats.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});
