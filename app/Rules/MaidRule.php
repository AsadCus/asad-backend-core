<?php

namespace App\Rules;

class MaidRule
{
    public function rules()
    {
        $rules = [
            'passport_number' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before:today',
            'place_of_birth' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'address' => 'nullable|string|max:500',
            'repatriation_port_airport' => 'nullable|string|max:255',
            'contact_number_home_country' => 'nullable|string|max:30',
            'religion_id' => 'required|exists:religions,id',
            'education_level_id' => 'required|exists:education_levels,id',
            'marital_status' => 'required|string|in:Single,Married,Widowed,Divorced,Divorce',

            'number_of_siblings' => 'nullable|integer|min:0',
            'number_of_children' => 'nullable|integer|min:0',
            'children_ages' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    $ages = collect(explode(',', $value))
                        ->map(fn ($age) => trim($age))
                        ->filter(fn ($age) => $age !== '');

                    request()->merge([
                        'children_ages_count' => $ages->count(),
                    ]);

                    if (request('number_of_children') != $ages->count()) {
                        $fail('The number of children ages must match the number of children.');
                    }

                    foreach ($ages as $age) {
                        if (! is_numeric($age) || $age < 0) {
                            $fail('Each child age must be a positive number.');
                        }
                    }
                },
            ],

            'height' => 'nullable|numeric|min:0|max:250',
            'weight' => 'nullable|numeric|min:0|max:200',
            'photo_url' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    // Skip validation if value is empty
                    if ($value === null || $value === '') {
                        return;
                    }

                    // Accept string URL (already uploaded photo)
                    if (is_string($value)) {
                        // Valid string URL or path, allow it
                        return;
                    }

                    // If it's a file upload, validate as image
                    if ($value instanceof \Illuminate\Http\UploadedFile) {
                        $validator = \Illuminate\Support\Facades\Validator::make(
                            [$attribute => $value],
                            [$attribute => 'image|mimes:jpg,jpeg,png,webp|max:2048']
                        );
                        if ($validator->fails()) {
                            $fail($validator->errors()->first($attribute));
                        }

                        return;
                    }

                    // If it's neither string nor file, fail
                    $fail('The photo url field must be an image file or a valid URL.');
                },
            ],

            'status' => 'required|string|in:unavailable,available,interviewing,pending,assigned',
            'rest_days_per_month' => 'nullable|integer|min:0|max:8',
            'other_remarks' => 'nullable|string|max:500',
            'remaining_loan' => 'nullable|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
            'monthly_salary' => 'nullable|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
            'commission' => 'nullable|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
            'cost_of_maid' => 'nullable|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',

            'attributes' => 'array',

            // Medical History (Section A2)
            'allergies' => 'nullable|string|max:1000',
            'physical_disabilities' => 'nullable|string|max:1000',
            'dietary_restrictions' => 'nullable|string|max:1000',
            'food_preferences' => 'nullable|string|max:1000',

            // Skills Assessment (Section B) - JSON format
            'skills_assessment_singapore' => 'nullable|array',
            'skills_assessment_singapore.*.area' => 'nullable|string',
            'skills_assessment_singapore.*.willingness' => 'nullable|string',
            'skills_assessment_singapore.*.experience' => 'nullable|string',
            'skills_assessment_singapore.*.assessment' => 'nullable|string',
            'skills_assessment_singapore.*.observation' => 'nullable|string',
            'skills_assessment_singapore.*.assessment_observation' => 'nullable|string',
            'skills_assessment_overseas' => 'nullable|array',
            'skills_assessment_overseas.*.area' => 'nullable|string',
            'skills_assessment_overseas.*.willingness' => 'nullable|string',
            'skills_assessment_overseas.*.experience' => 'nullable|string',
            'skills_assessment_overseas.*.experience_years' => 'nullable|string',
            'skills_assessment_overseas.*.assessment' => 'nullable|string',
            'skills_assessment_overseas.*.observation' => 'nullable|string',
            'skills_assessment_overseas.*.assessment_observation' => 'nullable|string',

            // Employment History (Section C) - JSON format
            'employment_history' => 'nullable|array',
            'employment_history.*.country' => 'nullable|string',
            'employment_history.*.employer' => 'nullable|string',
            'employment_history.*.period' => 'nullable|string',
            'employment_history.*.duties' => 'nullable|string',
            'employment_history.*.remarks' => 'nullable|string',
            'singapore_experience' => 'nullable|boolean',
            'experience_years' => 'nullable|numeric|min:0',
            'employment_feedback' => 'nullable|string|max:2000',

            // Employer Feedback (Section C3) - JSON format
            'employer_feedback' => 'nullable|array',
            'employer_feedback.*.employer' => 'nullable|string|max:500',
            'employer_feedback.*.feedback' => 'nullable|string|max:2000',

            // Interview Availability (Section D)
            'interview_not_available' => 'nullable|boolean',
            'interview_by_phone' => 'nullable|boolean',
            'interview_by_video' => 'nullable|boolean',
            'interview_in_person' => 'nullable|boolean',

            // Availability (Section E)
            'availability_remarks' => 'nullable|string|max:1000',

            // Evaluation methods (Section B - methods of evaluation)
            'eval_declaration_no_eval' => 'nullable|boolean',
            'eval_sg_interview' => 'nullable|boolean',
            'eval_sg_phone' => 'nullable|boolean',
            'eval_sg_video' => 'nullable|boolean',
            'eval_sg_in_person' => 'nullable|boolean',
            'eval_sg_in_person_observed' => 'nullable|boolean',
            'eval_overseas_interview' => 'nullable|boolean',
            'eval_overseas_name' => 'nullable|string|max:255',
            'eval_overseas_cert' => 'nullable|string|max:255',
            'eval_overseas_phone' => 'nullable|boolean',
            'eval_overseas_video' => 'nullable|boolean',
            'eval_overseas_in_person' => 'nullable|boolean',
            'eval_overseas_in_person_observed' => 'nullable|boolean',
        ];

        return $rules;
    }

    public function uploadDocumentRules()
    {
        return [
            'document' => 'required|file|mimes:pdf,docx|max:10240', // Max 10MB
        ];
    }
}
