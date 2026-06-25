<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\QuotationItemController;
use App\Http\Controllers\Api\ScopeController;
use App\Http\Controllers\Api\Settings\ProfileController as SettingsProfileController;
use App\Http\Controllers\Api\TwoFactorChallengeController;
use App\Http\Controllers\Api\UserLogsController as ApiUserLogsController;
use Illuminate\Support\Facades\Route;

Route::get('/quote', [QuoteController::class, 'random']);
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
    Route::post('/notifications/{id}/action', [NotificationController::class, 'action']);

    Route::get('/user-logs', [ApiUserLogsController::class, 'index']);
    Route::get('/user-logs/{id}', [ApiUserLogsController::class, 'show']);

    Route::put('/settings/profile', [SettingsProfileController::class, 'update']);
    Route::put('/settings/password', [SettingsProfileController::class, 'password']);

    Route::get('/scopes', [ScopeController::class, 'index']);
    Route::get('/quotation-items', [QuotationItemController::class, 'index']);
    Route::post('/quotation-items/quick-create', [QuotationItemController::class, 'quickCreate']);
});
