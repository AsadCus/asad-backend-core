<?php

namespace App\Http\Controllers\Api\Account;

use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\PersonalUpdateRequest;
use App\Models\EducationLevel;
use App\Models\Employee;
use App\Models\Religion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        return response()->json([
            'personal' => $this->personalPayload($employee),
            'options' => [
                'genders' => Gender::options(),
                'religions' => Religion::orderBy('name')->get(['id', 'name'])
                    ->map(fn (Religion $r) => ['value' => $r->id, 'label' => $r->name]),
                'educationLevels' => EducationLevel::orderBy('name')->get(['id', 'name'])
                    ->map(fn (EducationLevel $e) => ['value' => $e->id, 'label' => $e->name]),
            ],
        ]);
    }

    public function update(PersonalUpdateRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            abort(404, 'No employee record is linked to this account.');
        }

        $employee->fill($request->validated())->save();

        return response()->json([
            'status' => 'ok',
            'personal' => $this->personalPayload($employee->refresh()),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function personalPayload(?Employee $employee): ?array
    {
        if (! $employee) {
            return null;
        }

        return [
            'nik' => $employee->nik,
            'gender' => $employee->gender?->value,
            'birth_date' => $employee->birth_date?->toDateString(),
            'religion_id' => $employee->religion_id,
            'education_level_id' => $employee->education_level_id,
            'phone' => $employee->phone,
            'address' => $employee->address,
            'emergency_contact_name' => $employee->emergency_contact_name,
            'emergency_contact_phone' => $employee->emergency_contact_phone,
        ];
    }
}
