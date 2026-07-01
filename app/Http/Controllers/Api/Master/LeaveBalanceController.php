<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\LeaveBalanceRule;
use App\Services\LeaveBalanceImportService;
use App\Services\LeaveBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveBalanceController extends Controller
{
    public function __construct(
        private LeaveBalanceService $leaveBalanceService,
        private LeaveBalanceRule $leaveBalanceRule,
        private LeaveBalanceImportService $leaveBalanceImportService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->leaveBalanceService->getForDataTable());
    }

    public function my(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('hris.leave-balance view-own'), 403);

        return response()->json($this->leaveBalanceService->myBalances($request->user()));
    }

    public function forEmployee(Request $request, int $employee): JsonResponse
    {
        abort_unless($request->user()->can('hris.leave-balance view'), 403);

        return response()->json($this->leaveBalanceService->forEmployee($employee));
    }

    public function assignableTypes(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('hris.leave-balance view'), 403);

        return response()->json($this->leaveBalanceService->assignableTypes(
            $request->integer('employee_id') ?: null,
            $request->integer('year') ?: null,
            $request->string('gender')->value() ?: null,
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->leaveBalanceRule->rules());

        return response()->json($this->leaveBalanceService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->leaveBalanceService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->leaveBalanceRule->rules($id));

        return response()->json($this->leaveBalanceService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        return $this->deleteOneOrMany($request, $id, fn (string $itemId) => $this->leaveBalanceService->delete($itemId));
    }

    public function import(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('hris.leave-balance manage'), 403);

        $request->validate($this->leaveBalanceRule->importRules());

        return response()->json($this->leaveBalanceImportService->import($request->file('file')));
    }

    public function template(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('hris.leave-balance manage'), 403);

        return $this->leaveBalanceImportService->template();
    }
}
