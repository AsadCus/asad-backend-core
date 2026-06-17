<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DataScopeController;
use App\Http\Controllers\Api\Master\BranchController as MasterBranchController;
use App\Http\Controllers\Api\Master\BusinessUnitController as MasterBusinessUnitController;
use App\Http\Controllers\Api\Master\CountryController as MasterCountryController;
use App\Http\Controllers\Api\Master\DepartmentController as MasterDepartmentController;
use App\Http\Controllers\Api\Master\FinancialYearController as MasterFinancialYearController;
use App\Http\Controllers\Api\Master\HoldingController as MasterHoldingController;
use App\Http\Controllers\Api\Master\HolidayController as MasterHolidayController;
use App\Http\Controllers\Api\Master\LeaveTypeController as MasterLeaveTypeController;
use App\Http\Controllers\Api\Master\MasterStatsController;
use App\Http\Controllers\Api\Master\PositionController as MasterPositionController;
use App\Http\Controllers\Api\Master\ShiftController as MasterShiftController;
use App\Http\Controllers\Api\Master\UserController as MasterUserController;
use App\Http\Controllers\Api\Master\WorkScheduleController as MasterWorkScheduleController;
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

    Route::get('/master/stats', MasterStatsController::class);

    Route::apiResource('/master/country', MasterCountryController::class);
    Route::apiResource('/master/branch', MasterBranchController::class);
    Route::apiResource('/master/financial-year', MasterFinancialYearController::class);
    Route::put('/master/financial-year/{id}/default', [MasterFinancialYearController::class, 'setDefault']);

    Route::get('/master/users/options', [MasterUserController::class, 'options']);
    Route::get('/master/users/stats', [MasterUserController::class, 'stats']);
    Route::apiResource('/master/users', MasterUserController::class);

    // HRIS organisation masters
    Route::get('/master/holdings/options', [MasterHoldingController::class, 'options']);
    Route::apiResource('/master/holdings', MasterHoldingController::class);

    Route::get('/master/business-units/options', [MasterBusinessUnitController::class, 'options']);
    Route::apiResource('/master/business-units', MasterBusinessUnitController::class);

    Route::get('/master/departments/options', [MasterDepartmentController::class, 'options']);
    Route::apiResource('/master/departments', MasterDepartmentController::class);

    Route::get('/master/positions/options', [MasterPositionController::class, 'options']);
    Route::apiResource('/master/positions', MasterPositionController::class);

    // HRIS schedule + leave masters
    Route::apiResource('/master/shifts', MasterShiftController::class);
    Route::apiResource('/master/work-schedules', MasterWorkScheduleController::class);
    Route::apiResource('/master/holidays', MasterHolidayController::class);
    Route::apiResource('/master/leave-types', MasterLeaveTypeController::class);

    Route::get('/quotation-items', [QuotationItemController::class, 'index']);
    Route::post('/quotation-items/quick-create', [QuotationItemController::class, 'quickCreate']);

    Route::get('/settings/profile', [SettingsProfileController::class, 'show']);
    Route::put('/settings/profile', [SettingsProfileController::class, 'update']);
    Route::delete('/settings/profile', [SettingsProfileController::class, 'destroy']);
    Route::put('/settings/password', [SettingsPasswordController::class, 'update']);

    Route::get('/user-logs', [ApiUserLogsController::class, 'index']);
});
