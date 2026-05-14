<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DataScopeController;
use App\Http\Controllers\Api\Master\BranchController as MasterBranchController;
use App\Http\Controllers\Api\Master\CountryController as MasterCountryController;
use App\Http\Controllers\Api\Master\FinancialYearController as MasterFinancialYearController;
use App\Http\Controllers\Api\Master\UserController as MasterUserController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\QuotationItemController;
use App\Http\Controllers\Api\Settings\PasswordController as SettingsPasswordController;
use App\Http\Controllers\Api\Settings\ProfileController as SettingsProfileController;
use App\Http\Controllers\Api\TwoFactorChallengeController;
use App\Http\Controllers\Api\UserLogsController as ApiUserLogsController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [PasswordController::class, 'sendResetLink']);
Route::post('/reset-password', [PasswordController::class, 'resetPassword']);
Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'challenge']);
Route::post('/two-factor-cancel', [TwoFactorChallengeController::class, 'cancel']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::post('/data-scope/countries', [DataScopeController::class, 'updateCountries']);

    Route::apiResource('/master/country', MasterCountryController::class);
    Route::apiResource('/master/branch', MasterBranchController::class);
    Route::apiResource('/master/financial-year', MasterFinancialYearController::class);
    Route::put('/master/financial-year/{id}/default', [MasterFinancialYearController::class, 'setDefault']);

    Route::get('/master/users/options', [MasterUserController::class, 'options']);
    Route::get('/master/users/stats', [MasterUserController::class, 'stats']);
    Route::apiResource('/master/users', MasterUserController::class);

    Route::get('/quotation-items', [QuotationItemController::class, 'index']);
    Route::post('/quotation-items/quick-create', [QuotationItemController::class, 'quickCreate']);

    Route::get('/settings/profile', [SettingsProfileController::class, 'show']);
    Route::put('/settings/profile', [SettingsProfileController::class, 'update']);
    Route::delete('/settings/profile', [SettingsProfileController::class, 'destroy']);
    Route::put('/settings/password', [SettingsPasswordController::class, 'update']);

    Route::get('/user-logs', [ApiUserLogsController::class, 'index']);
});
