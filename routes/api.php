<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DataScopeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\TwoFactorChallengeController;
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
});
