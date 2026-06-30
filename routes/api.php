<?php

use App\Http\Controllers\Api\Account\PersonalController as AccountPersonalController;
use App\Http\Controllers\Api\Account\SignatureController as AccountSignatureController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceCorrectionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessTripController;
use App\Http\Controllers\Api\Company\OrgInfoController;
use App\Http\Controllers\Api\DataScopeController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\Master\ApprovalMatrixController as MasterApprovalMatrixController;
use App\Http\Controllers\Api\Master\AttendanceEligibilityController as MasterAttendanceEligibilityController;
use App\Http\Controllers\Api\Master\BranchController as MasterBranchController;
use App\Http\Controllers\Api\Master\CountryController as MasterCountryController;
use App\Http\Controllers\Api\Master\EmployeeController as MasterEmployeeController;
use App\Http\Controllers\Api\Master\EmployeeScheduleController as MasterEmployeeScheduleController;
use App\Http\Controllers\Api\Master\FinancialYearController as MasterFinancialYearController;
use App\Http\Controllers\Api\Master\HolidayController as MasterHolidayController;
use App\Http\Controllers\Api\Master\LeaveBalanceController as MasterLeaveBalanceController;
use App\Http\Controllers\Api\Master\LeaveTypeController as MasterLeaveTypeController;
use App\Http\Controllers\Api\Master\ManagementLevelController as MasterManagementLevelController;
use App\Http\Controllers\Api\Master\MasterStatsController;
use App\Http\Controllers\Api\Master\OrgUnitController as MasterOrgUnitController;
use App\Http\Controllers\Api\Master\RoleController as MasterRoleController;
use App\Http\Controllers\Api\Master\RoleGroupController as MasterRoleGroupController;
use App\Http\Controllers\Api\Master\ShiftController as MasterShiftController;
use App\Http\Controllers\Api\Master\UserController as MasterUserController;
// Holding/BusinessUnit/Department controllers removed — collapsed into the org_units tree.
use App\Http\Controllers\Api\Master\WorkLocationController as MasterWorkLocationController;
use App\Http\Controllers\Api\Master\WorkScheduleController as MasterWorkScheduleController;
use App\Http\Controllers\Api\MenuConfigController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\QuotationItemController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\ScopeController;
use App\Http\Controllers\Api\Settings\PasswordController as SettingsPasswordController;
use App\Http\Controllers\Api\Settings\ProfileController as SettingsProfileController;
use App\Http\Controllers\Api\TeamOverviewController;
use App\Http\Controllers\Api\TwoFactorChallengeController;
use App\Http\Controllers\Api\UserLogsController as ApiUserLogsController;
use App\Http\Controllers\Api\WfhVisitRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/quote', [QuoteController::class, 'random']);
Route::get('/auth/me', [AuthController::class, 'me']);
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

    // HRIS org switcher — active scope selection (narrowing within the user's allowed subtree)
    Route::get('/scope/org-units', [ScopeController::class, 'orgUnits']);
    Route::put('/scope/org-unit', [ScopeController::class, 'setOrgUnit']);

    // Informasi Perusahaan — per-org-unit company info (hierarchical read; admin/HR manage)
    Route::get('/company/org-infos', [OrgInfoController::class, 'index']);
    Route::post('/company/org-infos', [OrgInfoController::class, 'store']);
    Route::put('/company/org-infos/{id}', [OrgInfoController::class, 'update']);
    Route::delete('/company/org-infos/{id}', [OrgInfoController::class, 'destroy']);

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
    // Recursive org tree (holding → BU → branch → department → division)
    Route::get('/master/org-units/options', [MasterOrgUnitController::class, 'options']);
    Route::get('/master/org-units/tree', [MasterOrgUnitController::class, 'tree']);
    Route::apiResource('/master/org-units', MasterOrgUnitController::class);

    // Role = Jabatan management (editable roles + permission matrix + classification)
    Route::get('/master/roles/options', [MasterRoleController::class, 'options']);
    Route::get('/master/roles/permissions', [MasterRoleController::class, 'permissions']);
    Route::get('/master/roles/permission-sets', [MasterRoleController::class, 'permissionSets']);
    Route::apiResource('/master/roles', MasterRoleController::class);

    Route::get('/master/role-groups/options', [MasterRoleGroupController::class, 'options']);
    Route::apiResource('/master/role-groups', MasterRoleGroupController::class);

    Route::get('/master/management-levels/options', [MasterManagementLevelController::class, 'options']);
    Route::apiResource('/master/management-levels', MasterManagementLevelController::class);

    // HRIS schedule + leave masters
    Route::get('/master/shifts/options', [MasterShiftController::class, 'options']);
    Route::apiResource('/master/shifts', MasterShiftController::class);

    Route::get('/master/work-schedules/options', [MasterWorkScheduleController::class, 'options']);
    Route::post('/master/work-schedules/{id}/generate-down', [MasterWorkScheduleController::class, 'generateDown']);
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

    // HRIS work-location governance — admin assigns per-employee geofence anchor.
    Route::get('/master/work-locations', [MasterWorkLocationController::class, 'index']);
    Route::get('/master/work-locations/options', [MasterWorkLocationController::class, 'locationOptions']);
    Route::post('/master/work-locations/bulk', [MasterWorkLocationController::class, 'bulk']);
    Route::put('/master/work-locations/{employee}', [MasterWorkLocationController::class, 'update']);

    Route::post('/master/employee-schedules/import', [MasterEmployeeScheduleController::class, 'import']);
    Route::apiResource('/master/employee-schedules', MasterEmployeeScheduleController::class);
    Route::apiResource('/master/approval-matrices', MasterApprovalMatrixController::class);
    Route::get('/master/leave-balances/template', [MasterLeaveBalanceController::class, 'template']);
    Route::post('/master/leave-balances/import', [MasterLeaveBalanceController::class, 'import']);
    Route::apiResource('/master/leave-balances', MasterLeaveBalanceController::class);

    // HRIS team — supervisor's subordinate overview with today's attendance status.
    Route::get('/team/overview', TeamOverviewController::class);

    // HRIS attendance — online check-in/out, index/detail, bulk import, user-lock.
    Route::get('/attendances', [AttendanceController::class, 'index']);
    Route::get('/attendances/today', [AttendanceController::class, 'today']);
    Route::get('/my-schedule', [AttendanceController::class, 'mySchedule']);
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
    Route::get('/attendance-corrections/my', [AttendanceCorrectionController::class, 'my']);
    Route::post('/attendance-corrections', [AttendanceCorrectionController::class, 'store']);
    Route::get('/attendance-corrections/{id}', [AttendanceCorrectionController::class, 'show'])->whereNumber('id');
    Route::post('/attendance-corrections/{id}/approve', [AttendanceCorrectionController::class, 'approve']);
    Route::post('/attendance-corrections/{id}/verify', [AttendanceCorrectionController::class, 'verify']);
    Route::post('/attendance-corrections/{id}/reject', [AttendanceCorrectionController::class, 'reject']);
    Route::post('/attendance-corrections/{id}/cancel', [AttendanceCorrectionController::class, 'cancel']);

    // HRIS leave request — submit → supervisor → HR approval workflow.
    Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
    Route::get('/leave-requests/my', [LeaveRequestController::class, 'my']);
    Route::get('/leave-requests/requester-info', [LeaveRequestController::class, 'requesterInfo']);
    Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
    Route::get('/leave-requests/{id}', [LeaveRequestController::class, 'show'])->whereNumber('id');
    Route::post('/leave-requests/{id}/approve', [LeaveRequestController::class, 'approve']);
    Route::post('/leave-requests/{id}/verify', [LeaveRequestController::class, 'verify']);
    Route::post('/leave-requests/{id}/reject', [LeaveRequestController::class, 'reject']);
    Route::post('/leave-requests/{id}/cancel', [LeaveRequestController::class, 'cancel']);

    // HRIS WFH / Visit request — submit → supervisor → HR approval workflow.
    Route::get('/wfh-visit-requests', [WfhVisitRequestController::class, 'index']);
    Route::get('/wfh-visit-requests/my', [WfhVisitRequestController::class, 'my']);
    Route::post('/wfh-visit-requests', [WfhVisitRequestController::class, 'store']);
    Route::get('/wfh-visit-requests/{id}', [WfhVisitRequestController::class, 'show'])->whereNumber('id');
    Route::post('/wfh-visit-requests/{id}/approve', [WfhVisitRequestController::class, 'approve']);
    Route::post('/wfh-visit-requests/{id}/verify', [WfhVisitRequestController::class, 'verify']);
    Route::post('/wfh-visit-requests/{id}/reject', [WfhVisitRequestController::class, 'reject']);
    Route::post('/wfh-visit-requests/{id}/cancel', [WfhVisitRequestController::class, 'cancel']);

    // HRIS business trip — submit → leader → HC → finance approval, then disbursement + report.
    Route::get('/business-trips', [BusinessTripController::class, 'index']);
    Route::get('/business-trips/my', [BusinessTripController::class, 'my']);
    Route::post('/business-trips', [BusinessTripController::class, 'store']);
    Route::get('/business-trips/{id}', [BusinessTripController::class, 'show'])->whereNumber('id');
    Route::post('/business-trips/{id}/approve-leader', [BusinessTripController::class, 'approveLeader']);
    Route::post('/business-trips/{id}/approve-hc', [BusinessTripController::class, 'approveHc']);
    Route::post('/business-trips/{id}/approve-finance', [BusinessTripController::class, 'approveFinance']);
    Route::post('/business-trips/{id}/reject', [BusinessTripController::class, 'reject']);
    Route::post('/business-trips/{id}/cancel', [BusinessTripController::class, 'cancel']);
    Route::post('/business-trips/{id}/pay', [BusinessTripController::class, 'pay']);
    Route::get('/business-trips/{id}/report', [BusinessTripController::class, 'showReport'])->whereNumber('id');
    Route::post('/business-trips/{id}/report', [BusinessTripController::class, 'report']);
    Route::post('/business-trips/{id}/report/approve-leader', [BusinessTripController::class, 'reportApproveLeader']);
    Route::post('/business-trips/{id}/report/approve-finance', [BusinessTripController::class, 'reportApproveFinance']);
    Route::post('/business-trips/{id}/report/reject', [BusinessTripController::class, 'reportReject']);
    Route::post('/business-trips/{id}/settle', [BusinessTripController::class, 'settle']);

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
    Route::put('/account/signature', [AccountSignatureController::class, 'store']);
    Route::delete('/account/signature', [AccountSignatureController::class, 'destroy']);

    Route::get('/user-logs', [ApiUserLogsController::class, 'index']);

    // Menu management — sparse overrides on the frontend NAV_ZONES registry.
    Route::get('/menu/config', [MenuConfigController::class, 'show']);
    Route::put('/menu/overrides', [MenuConfigController::class, 'updateOverrides']);   // admin only
    Route::put('/menu/preferences', [MenuConfigController::class, 'updatePreferences']); // self-service
});
