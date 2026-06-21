<?php

namespace App\Rules;

class AttendanceRule
{
    /**
     * Per the Prosedur Absensi SOP, selfie + GPS are mandatory for every punch.
     *
     * @return array<string, array<int, string>>
     */
    public function punchRules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:500'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function lockRules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
            'dates' => ['nullable', 'array'],
            'dates.*' => ['string'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function importRules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }
}
