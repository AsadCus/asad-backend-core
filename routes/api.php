<?php

use App\Http\Controllers\Api\Account\PersonalController as AccountPersonalController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceCorrectionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DataScopeController;
use App\Http\Controllers\Api\Master\ApprovalMatrixController as MasterApprovalMatrixController;
use App\Http\Controllers\Api\Master\AttendanceEligibilityController as MasterAttendanceEligibilityController;
use App\Http\Controllers\Api\Master\BranchController as MasterBranchController;
use App\Http\Controllers\Api\Master\BusinessUnitController as MasterBusinessUnitController;
use App\Http\Controllers\Api\Master\CountryController as MasterCountryController;
use App\Http\Controllers\Api\Master\DepartmentController as MasterDepartmentController;
use App\Http\Controllers\Api\Master\EmployeeController as MasterEmployeeController;
use App\Http\Controllers\Api\Master\EmployeeScheduleController as MasterEmployeeScheduleController;
use App\Http\Controllers\Api\Master\FinancialYearController as MasterFinancialYearController;
use App\Http\Controllers\Api\Master\HoldingController as MasterHoldingController;
use App\Http\Controllers\Api\Master\HolidayController as MasterHolidayController;
use App\Http\Controllers\Api\Master\LeaveBalanceController as MasterLeaveBalanceController;
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
    Route::post('/notifications/{id}/action', [NotificationController::class, 'action']);

    Route::post('/data-scope/countries', [DataScopeController::class, 'updateCountries']);

    Route::get('/master/stats', MasterStatsController::class);

    Route::apiResource('/master/country', MasterCountryController::class);
    Route::get('/master/branch/options', [MasterBranchController::class, 'options']);
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

    Route::get('/master/work-schedules/options', [MasterWorkScheduleController::class, 'options']);
    Route::apiResource('/master/work-schedules', MasterWorkScheduleController::class);

    Route::apiResource('/master/holidays', MasterHolidayController::class);

    Route::get('/master/leave-types/options', [MasterLeaveTypeController::class, 'options']);
    Route::apiResource('/master/leave-types', MasterLeaveTypeController::class);

    // HRIS employee + attendance foundation
    Route::get('/master/employees/options', [MasterEmployeeController::class, 'options']);
    Route::apiResource('/master/employees', MasterEmployeeController::class);

    // HRIS attendance eligibility — admin governs who may check in/out (per-employee toggle).
    Route::get('/master/attendance-eligibility', [MasterAttendanceEligibilityController::class, 'index']);
    Route::post('/master/attendance-eligibility/bulk', [MasterAttendanceEligibilityController::class, 'bulk']);
    Route::put('/master/attendance-eligibility/{employee}', [MasterAttendanceEligibilityController::class, 'update']);

    Route::apiResource('/master/employee-schedules', MasterEmployeeScheduleController::class);
    Route::apiResource('/master/approval-matrices', MasterApprovalMatrixController::class);
    Route::apiResource('/master/leave-balances', MasterLeaveBalanceController::class);

    // HRIS attendance — online check-in/out, index/detail, bulk import, user-lock.
    Route::get('/attendances', [AttendanceController::class, 'index']);
    Route::get('/attendances/today', [AttendanceController::class, 'today']);
    Route::post('/attendances/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('/attendances/check-out', [AttendanceController::class, 'checkOut']);
    Route::post('/attendances/import', [AttendanceController::class, 'import']);
    Route::get('/attendance-locks/candidates', [AttendanceController::class, 'lockCandidates']);
    Route::get('/attendance-locks', [AttendanceController::class, 'lockedList']);
    Route::post('/attendance-locks/{employee}', [AttendanceController::class, 'lock']);
    Route::delete('/attendance-locks/{employee}', [AttendanceController::class, 'unlock']);
    Route::get('/attendances/{id}', [AttendanceController::class, 'show'])->whereNumber('id');

    // HRIS attendance correction — submit → supervisor → HR approval workflow.
    Route::get('/attendance-corrections', [AttendanceCorrectionController::class, 'index']);
    Route::post('/attendance-corrections', [AttendanceCorrectionController::class, 'store']);
    Route::get('/attendance-corrections/{id}', [AttendanceCorrectionController::class, 'show'])->whereNumber('id');
    Route::post('/attendance-corrections/{id}/approve', [AttendanceCorrectionController::class, 'approve']);
    Route::post('/attendance-corrections/{id}/verify', [AttendanceCorrectionController::class, 'verify']);
    Route::post('/attendance-corrections/{id}/reject', [AttendanceCorrectionController::class, 'reject']);
    Route::post('/attendance-corrections/{id}/cancel', [AttendanceCorrectionController::class, 'cancel']);

    Route::get('/quotation-items', [QuotationItemController::class, 'index']);
    Route::post('/quotation-items/quick-create', [QuotationItemController::class, 'quickCreate']);

    Route::get('/settings/profile', [SettingsProfileController::class, 'show']);
    Route::put('/settings/profile', [SettingsProfileController::class, 'update']);
    Route::delete('/settings/profile', [SettingsProfileController::class, 'destroy']);
    Route::post('/settings/profile/avatar', [SettingsProfileController::class, 'updateAvatar']);
    Route::delete('/settings/profile/avatar', [SettingsProfileController::class, 'destroyAvatar']);
    Route::put('/settings/password', [SettingsPasswordController::class, 'update']);

    Route::get('/account/personal', [AccountPersonalController::class, 'show']);
    Route::put('/account/personal', [AccountPersonalController::class, 'update']);

    Route::get('/user-logs', [ApiUserLogsController::class, 'index']);
});
