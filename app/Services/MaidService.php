<?php

namespace App\Services;

use App\Helpers\FormatService;
use Carbon\Carbon;
use App\Models\Maid;
use App\Models\MaidAttribute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class MaidService
{
    protected $formatService;

    public function __construct(FormatService $formatService)
    {
        $this->formatService = $formatService;
    }

    public function getActiveCount()
    {
        return Maid::whereIn('status', ['available', 'interviewing'])->count();
    }

    public function getDailyStats($days = 30)
    {
        $stats = collect();
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = Maid::whereDate('created_at', $date->format('Y-m-d'))
                ->count();

            $stats->push([
                'date' => $date->format('Y-m-d'),
                'count' => $count,
                'label' => $date->format('M d')
            ]);
        }
        return $stats;
    }

    public function getMonthlyStats()
    {
        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $count = Maid::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();

            $months->push([
                'date' => $date->format('Y-m-d'),
                'count' => $count,
                'label' => $date->format('M Y')
            ]);
        }
        return $months;
    }

    public function get()
    {
        return Maid::get();
    }

    public function getForDataTable(array $maidIds = [])
    {
        $query = Maid::with('attributes', 'country')
            ->orderBy('created_at', 'desc');

        if (!empty($maidIds)) {
            $query->whereIn('id', $maidIds);
        }

        $maids = $query->get();

        return $maids->map(function ($maid) {
            $grouped = $maid->attributes->groupBy('attribute_category');

            $join = fn($keys) => collect($keys)
                ->flatMap(fn($key) => $grouped[$key] ?? [])
                ->pluck('attribute_name')
                ->join(', ') ?: '-';

            return [
                'id' => $maid->id,
                'maid_number' => $maid->maid_number,
                'passport_number' => $maid->passport_number,
                'name' => $maid->name,
                'date_of_birth' => $maid->date_of_birth_formatted,
                'place_of_birth' => $maid->place_of_birth,
                'height' => $maid->height ? $this->formatService->cleanDecimal($maid->height) : null,
                'weight' => $maid->weight ? $this->formatService->cleanDecimal($maid->weight) : null,
                'nationality' => $maid->country->adjective,
                'address' => $maid->address,
                'repatriation_port_airport' => $maid->repatriation_port_airport,
                'contact_number_home_country' => $maid->contact_number_home_country,
                'religion' => $maid->religion->name,
                'education_level' => $maid->educationLevel->name,
                'marital_status' => $maid->marital_status,
                'number_of_siblings' => $maid->number_of_siblings,
                'number_of_children' => $maid->number_of_children,
                'children_ages' => $maid->children_ages,
                'photo_url' => $maid->photo_url,

                // Others
                'rest_days_per_month' => $maid->rest_days_per_month,
                'other_remarks' => $maid->other_remarks,
                'status' => $maid->status,
                'interview_date' => $maid->interview_date?->toIso8601String(),
                'interview_end_date' => $maid->interview_end_date?->toIso8601String(),
                'interview_date_formatted' => $maid->interview_date_formatted,
                'pending_until' => $maid->pending_until?->toIso8601String(),
                'pending_reason' => $maid->pending_reason,
                'supplier_id' => $maid->supplier_id,
                'supplier_user_id' => $maid->supplier->user->id ?? null,
                'supplier' => $maid->supplier->name ?? '-',
                'remaining_loan' => $this->formatService->cleanDecimal($maid->remaining_loan),
                'cost_of_maid' => $this->formatService->cleanDecimal($maid->cost_of_maid),

                // Employment
                'singapore_experience' => $maid->singapore_experience,
                'experience_years' => $maid->experience_years,
                'employment_feedback' => $maid->employment_feedback,

                // Availability
                'availability_remarks' => $maid->availability_remarks,
                'age' => $maid->date_of_birth
                    ? (int) Carbon::parse($maid->date_of_birth)->diffInYears(now())
                    : '-',

                // Grouped attributes
                'illness_attributes' => $join(['ILLNESS', 'ILLNESS_OTHERS']),
                'allergy_attributes' => $join(['ALLERGY']),
                'physical_disability_attributes' => $join(['PHYSICAL_DISABILITY']),
                'diet_restriction_attributes' => $join(['DIET_RESTRICTION']),
                'food_preference_attributes' => $join(['FOOD_PREFERENCE', 'FOOD_PREFERENCE_OTHERS']),
            ];
        });
    }

    public function getForRecommendation()
    {
        $query = Maid::with('attributes', 'country')
            ->where('status', '!=', 'assigned')
            ->orderBy('created_at', 'desc');

        $maids = $query->get();

        return $maids->map(function ($maid) {
            $grouped = $maid->attributes->groupBy('attribute_category');

            $join = fn($keys) => collect($keys)
                ->flatMap(fn($key) => $grouped[$key] ?? [])
                ->pluck('attribute_name')
                ->join(', ') ?: '-';

            return [
                'id' => $maid->id,
                'maid_number' => $maid->maid_number,
                'passport_number' => $maid->passport_number,
                'name' => $maid->name,
                'date_of_birth' => $maid->date_of_birth_formatted,
                'place_of_birth' => $maid->place_of_birth,
                'height' => $maid->height ? $this->formatService->cleanDecimal($maid->height) : null,
                'weight' => $maid->weight ? $this->formatService->cleanDecimal($maid->weight) : null,
                'nationality' => $maid->country->adjective,
                'address' => $maid->address,
                'repatriation_port_airport' => $maid->repatriation_port_airport,
                'contact_number_home_country' => $maid->contact_number_home_country,
                'religion' => $maid->religion->name,
                'education_level' => $maid->educationLevel->name,
                'marital_status' => $maid->marital_status,
                'number_of_siblings' => $maid->number_of_siblings,
                'number_of_children' => $maid->number_of_children,
                'children_ages' => $maid->children_ages,
                'photo_url' => $maid->photo_url,

                // Others
                'rest_days_per_month' => $maid->rest_days_per_month,
                'other_remarks' => $maid->other_remarks,
                'status' => $maid->status,
                'interview_date' => $maid->interview_date?->toIso8601String(),
                'interview_end_date' => $maid->interview_end_date?->toIso8601String(),
                'interview_date_formatted' => $maid->interview_date_formatted,
                'pending_until' => $maid->pending_until?->toIso8601String(),
                'pending_reason' => $maid->pending_reason,
                'supplier_id' => $maid->supplier_id,
                'supplier_user_id' => $maid->supplier->user->id ?? null,
                'supplier' => $maid->supplier->name ?? '-',
                'remaining_loan' => $this->formatService->cleanDecimal($maid->remaining_loan),
                'cost_of_maid' => $this->formatService->cleanDecimal($maid->cost_of_maid),

                // Employment
                'singapore_experience' => $maid->singapore_experience,
                'experience_years' => $maid->experience_years,
                'employment_feedback' => $maid->employment_feedback,

                // Availability
                'availability_remarks' => $maid->availability_remarks,
                'age' => $maid->date_of_birth
                    ? (int) Carbon::parse($maid->date_of_birth)->diffInYears(now())
                    : '-',

                // Grouped attributes
                'illness_attributes' => $join(['ILLNESS', 'ILLNESS_OTHERS']),
                'allergy_attributes' => $join(['ALLERGY']),
                'physical_disability_attributes' => $join(['PHYSICAL_DISABILITY']),
                'diet_restriction_attributes' => $join(['DIET_RESTRICTION']),
                'food_preference_attributes' => $join(['FOOD_PREFERENCE', 'FOOD_PREFERENCE_OTHERS']),
            ];
        });
    }

    public function getForFilter()
    {
        $data = Maid::get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->name,
            ];
        });

        return $data;
    }

    public function getForFilterWithCode()
    {
        $data = Maid::get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => "{$q->maid_number} - {$q->name}",
            ];
        });

        return $data;
    }

    public function store(array $data, $request)
    {
        return DB::transaction(function () use ($data, $request) {
            // Log skills data for debugging
            if (isset($data['skills_assessment_singapore'])) {
                Log::info('Storing maid with Singapore skills', [
                    'type' => gettype($data['skills_assessment_singapore']),
                    'data' => $data['skills_assessment_singapore']
                ]);
            }
            if (isset($data['skills_assessment_overseas'])) {
                Log::info('Storing maid with Overseas skills', [
                    'type' => gettype($data['skills_assessment_overseas']),
                    'data' => $data['skills_assessment_overseas']
                ]);
            }
            if (isset($data['attributes'])) {
                Log::info('Storing maid with attributes', ['attributes' => $data['attributes']]);
            }


            if (!empty($data['date_of_birth'])) {
                $data['date_of_birth'] = Carbon::parse($data['date_of_birth'])->format('Y-m-d');
            }

            // Handle photo_url: could be new file upload or URL string (from document scan)
            $photoUrl = null;

            if ($request->hasFile('photo_url')) {
                // New file uploaded manually
                $path = $request->file('photo_url')->store('maids/photos', 'public');
                $photoUrl = Storage::url($path);
            } elseif ($request->filled('photo_url') && is_string($request->input('photo_url'))) {
                // Photo URL from document scan or existing URL
                $photoUrl = $request->input('photo_url');
            } elseif (isset($data['photo_url']) && is_string($data['photo_url'])) {
                // Fallback: check in validated data
                $photoUrl = $data['photo_url'];
            }

            $maid = Maid::create([
                'passport_number' => $data['passport_number'] ?? null,
                'name' => $data['name'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'place_of_birth' => $data['place_of_birth'] ?? null,
                'height' => $data['height'] ?? null,
                'weight' => $data['weight'] ?? null,
                'country_id' => $data['country_id'] ?? null,
                'address' => $data['address'] ?? null,
                'repatriation_port_airport' => $data['repatriation_port_airport'] ?? null,
                'contact_number_home_country' => $data['contact_number_home_country'] ?? null,
                'religion_id' => $data['religion_id'] ?? null,
                'education_level_id' => $data['education_level_id'] ?? null,
                'marital_status' => $data['marital_status'] ?? null,
                'number_of_siblings' => $data['number_of_siblings'] ?? null,
                'number_of_children' => $data['number_of_children'] ?? null,
                'children_ages' => $data['children_ages'] ?? null,
                'photo_url' => $photoUrl,
                // Section A3 - Others
                'rest_days_per_month' => $data['rest_days_per_month'] ?? 4,
                'other_remarks' => $data['other_remarks'] ?? null,
                'status' => $data['status'] ?? 'available',
                'supplier_id' => $data['supplier_id'] ?? null,
                'remaining_loan' => $data['remaining_loan'] ?? null,
                'monthly_salary' => $data['monthly_salary'] ?? null,
                'cost_of_maid' => $data['cost_of_maid'] ?? null,
                // Section B - Skills and Evaluation
                'skills_assessment_singapore' => !empty($data['skills_assessment_singapore']) ? $data['skills_assessment_singapore'] : null,
                'skills_assessment_overseas' => !empty($data['skills_assessment_overseas']) ? $data['skills_assessment_overseas'] : null,
                'eval_declaration_no_eval' => $data['eval_declaration_no_eval'] ?? false,
                'eval_sg_interview' => $data['eval_sg_interview'] ?? false,
                'eval_sg_phone' => $data['eval_sg_phone'] ?? false,
                'eval_sg_video' => $data['eval_sg_video'] ?? false,
                'eval_sg_in_person' => $data['eval_sg_in_person'] ?? false,
                'eval_sg_in_person_observed' => $data['eval_sg_in_person_observed'] ?? false,
                'eval_overseas_interview' => $data['eval_overseas_interview'] ?? false,
                'eval_overseas_name' => $data['eval_overseas_name'] ?? null,
                'eval_overseas_cert' => $data['eval_overseas_cert'] ?? null,
                'eval_overseas_phone' => $data['eval_overseas_phone'] ?? false,
                'eval_overseas_video' => $data['eval_overseas_video'] ?? false,
                'eval_overseas_in_person' => $data['eval_overseas_in_person'] ?? false,
                'eval_overseas_in_person_observed' => $data['eval_overseas_in_person_observed'] ?? false,

                // Section C - Employment
                'employment_history' => !empty($data['employment_history']) ? $data['employment_history'] : null,
                'singapore_experience' => $data['singapore_experience'] ?? false,
                'experience_years' => $data['experience_years'] ?? null,
                'employment_feedback' => $data['employment_feedback'] ?? null,
                'employer_feedback' => !empty($data['employer_feedback']) ? $data['employer_feedback'] : null,

                // Section D - Interview Availability
                'interview_not_available' => $data['interview_not_available'] ?? false,
                'interview_by_phone' => $data['interview_by_phone'] ?? false,
                'interview_by_video' => $data['interview_by_video'] ?? false,
                'interview_in_person' => $data['interview_in_person'] ?? false,

                // Section E - Availability
                'availability_remarks' => $data['availability_remarks'] ?? null,
            ]);

            if (!empty($data['attributes']) && is_array($data['attributes'])) {
                foreach ($data['attributes'] as $attr) {
                    MaidAttribute::create([
                        'maid_id' => $maid->id,
                        'attribute_category' => $attr['attribute_category'],
                        'attribute_name' => $attr['attribute_name'],
                    ]);
                }
            }

            return $maid->load('attributes');
        });
    }

    public function getForEditShow($id)
    {
        $maid = Maid::with('attributes')->findOrFail($id);

        return [
            'id' => $maid->id,
            'maid_number' => $maid->maid_number,
            'passport_number' => $maid->passport_number,
            'name' => $maid->name,
            'date_of_birth' => $maid->date_of_birth_formatted,
            'place_of_birth' => $maid->place_of_birth,
            'height' => $this->formatService->cleanDecimal($maid->height),
            'weight' => $this->formatService->cleanDecimal($maid->weight),
            'country_id' => $maid->country_id,
            'address' => $maid->address,
            'repatriation_port_airport' => $maid->repatriation_port_airport,
            'contact_number_home_country' => $maid->contact_number_home_country,
            'religion_id' => $maid->religion_id,
            'education_level_id' => $maid->education_level_id,
            'marital_status' => $maid->marital_status,
            'number_of_siblings' => $maid->number_of_siblings,
            'number_of_children' => $maid->number_of_children,
            'children_ages' => $maid->children_ages,
            'photo_url' => $maid->photo_url,

            // Section A3 - Others
            'rest_days_per_month' => $maid->rest_days_per_month,
            'other_remarks' => $maid->other_remarks,
            'status' => $maid->status,
            'supplier_id' => $maid->supplier_id,
            'remaining_loan' => $this->formatService->cleanDecimal($maid->remaining_loan),
            'monthly_salary' => $this->formatService->cleanDecimal($maid->monthly_salary),
            'cost_of_maid' => $this->formatService->cleanDecimal($maid->cost_of_maid),

            // Section B - Skills and Evaluation
            'skills_assessment_singapore' => $maid->skills_assessment_singapore ?? [],
            'skills_assessment_overseas' => $maid->skills_assessment_overseas ?? [],
            'eval_declaration_no_eval' => $maid->eval_declaration_no_eval,
            'eval_sg_interview' => $maid->eval_sg_interview,
            'eval_sg_phone' => $maid->eval_sg_phone,
            'eval_sg_video' => $maid->eval_sg_video,
            'eval_sg_in_person' => $maid->eval_sg_in_person,
            'eval_sg_in_person_observed' => $maid->eval_sg_in_person_observed,
            'eval_overseas_interview' => $maid->eval_overseas_interview,
            'eval_overseas_name' => $maid->eval_overseas_name,
            'eval_overseas_cert' => $maid->eval_overseas_cert,
            'eval_overseas_phone' => $maid->eval_overseas_phone,
            'eval_overseas_video' => $maid->eval_overseas_video,
            'eval_overseas_in_person' => $maid->eval_overseas_in_person,
            'eval_overseas_in_person_observed' => $maid->eval_overseas_in_person_observed,

            // Section C - Employment History
            'employment_history' => $maid->employment_history ?? [],
            'singapore_experience' => $maid->singapore_experience,
            'experience_years' => $maid->experience_years,
            'employment_feedback' => $maid->employment_feedback,
            'employer_feedback' => $maid->employer_feedback ?? [],

            // Section D - Interview Availability
            'interview_not_available' => $maid->interview_not_available,
            'interview_by_phone' => $maid->interview_by_phone,
            'interview_by_video' => $maid->interview_by_video,
            'interview_in_person' => $maid->interview_in_person,

            // Section E - Availability
            'availability_remarks' => $maid->availability_remarks,

            'attributes' => $maid->attributes->map(function ($attr) {
                return [
                    'id' => $attr->id,
                    'attribute_category' => $attr->attribute_category,
                    'attribute_name' => $attr->attribute_name,
                ];
            })->toArray(),
        ];
    }

    public function update(array $data, $id, $request)
    {
        return DB::transaction(function () use ($data, $id, $request) {
            // Log attributes data for debugging
            if (isset($data['attributes'])) {
                Log::info('Updating maid with attributes', ['maid_id' => $id, 'attributes' => $data['attributes']]);
            }

            if (!empty($data['date_of_birth'])) {
                $data['date_of_birth'] = Carbon::parse($data['date_of_birth'])->format('Y-m-d');
            }


            $maid = Maid::findOrFail($id);

            // Handle photo_url: could be new file, existing URL string, or empty
            $photoUrl = $maid->photo_url; // Default: keep existing photo

            if ($request->hasFile('photo_url')) {
                // Check if it's actually a new file upload (not an existing URL converted to file)
                $file = $request->file('photo_url');
                $originalName = $file->getClientOriginalName();

                // If file has a real original name (not from URL), it's a new upload
                if ($originalName && !filter_var($originalName, FILTER_VALIDATE_URL)) {
                    // Delete old photo if exists
                    if ($maid->photo_url && \Illuminate\Support\Str::startsWith($maid->photo_url, '/storage/')) {
                        $oldPath = str_replace('/storage/', '', $maid->photo_url);
                        Storage::disk('public')->delete($oldPath);
                    }

                    // Store new photo
                    $path = $file->store('maids/photos', 'public');
                    $photoUrl = Storage::url($path);
                } else {
                    // It's an existing photo being re-submitted, keep the existing URL
                }
            } elseif ($request->filled('photo_url') && is_string($request->input('photo_url'))) {
                // String URL provided (could be new or existing)
                $inputUrl = $request->input('photo_url');
                if ($inputUrl !== $maid->photo_url) {
                    $photoUrl = $inputUrl;
                }
            } elseif ($request->has('photo_url') && !$request->filled('photo_url')) {
                // Explicitly clearing photo - delete the old photo file
                if ($maid->photo_url && \Illuminate\Support\Str::startsWith($maid->photo_url, '/storage/')) {
                    $oldPath = str_replace('/storage/', '', $maid->photo_url);
                    try {
                        Storage::disk('public')->delete($oldPath);
                    } catch (\Exception $e) {
                        Log::warning('Failed to delete old maid photo during update', [
                            'maid_id' => $id,
                            'photo_url' => $maid->photo_url,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                $photoUrl = null;
            }

            $maid->update([
                'passport_number' => $data['passport_number'] ?? null,
                'name' => $data['name'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'place_of_birth' => $data['place_of_birth'] ?? null,
                'height' => $data['height'] ?? null,
                'weight' => $data['weight'] ?? null,
                'country_id' => $data['country_id'] ?? null,
                'address' => $data['address'] ?? null,
                'repatriation_port_airport' => $data['repatriation_port_airport'] ?? null,
                'contact_number_home_country' => $data['contact_number_home_country'] ?? null,
                'religion_id' => $data['religion_id'] ?? null,
                'education_level_id' => $data['education_level_id'] ?? null,
                'marital_status' => $data['marital_status'] ?? null,
                'number_of_siblings' => $data['number_of_siblings'] ?? null,
                'number_of_children' => $data['number_of_children'] ?? null,
                'children_ages' => $data['children_ages'] ?? null,
                'photo_url' => $photoUrl,
                // Section A3 - Others
                'rest_days_per_month' => $data['rest_days_per_month'] ?? 4,
                'other_remarks' => $data['other_remarks'] ?? null,
                'status' => $data['status'] ?? 'available',
                'supplier_id' => $data['supplier_id'] ?? null,
                'remaining_loan' => $data['remaining_loan'] ?? null,
                'monthly_salary' => $data['monthly_salary'] ?? null,
                'cost_of_maid' => $data['cost_of_maid'] ?? null,
                // Section B - Skills and Evaluation
                'skills_assessment_singapore' => !empty($data['skills_assessment_singapore']) ? $data['skills_assessment_singapore'] : null,
                'skills_assessment_overseas' => !empty($data['skills_assessment_overseas']) ? $data['skills_assessment_overseas'] : null,
                'eval_declaration_no_eval' => $data['eval_declaration_no_eval'] ?? false,
                'eval_sg_interview' => $data['eval_sg_interview'] ?? false,
                'eval_sg_phone' => $data['eval_sg_phone'] ?? false,
                'eval_sg_video' => $data['eval_sg_video'] ?? false,
                'eval_sg_in_person' => $data['eval_sg_in_person'] ?? false,
                'eval_sg_in_person_observed' => $data['eval_sg_in_person_observed'] ?? false,
                'eval_overseas_interview' => $data['eval_overseas_interview'] ?? false,
                'eval_overseas_name' => $data['eval_overseas_name'] ?? null,
                'eval_overseas_cert' => $data['eval_overseas_cert'] ?? null,
                'eval_overseas_phone' => $data['eval_overseas_phone'] ?? false,
                'eval_overseas_video' => $data['eval_overseas_video'] ?? false,
                'eval_overseas_in_person' => $data['eval_overseas_in_person'] ?? false,
                'eval_overseas_in_person_observed' => $data['eval_overseas_in_person_observed'] ?? false,

                // Section C - Employment
                'employment_history' => !empty($data['employment_history']) ? $data['employment_history'] : null,
                'singapore_experience' => $data['singapore_experience'] ?? false,
                'experience_years' => $data['experience_years'] ?? null,
                'employment_feedback' => $data['employment_feedback'] ?? null,
                'employer_feedback' => !empty($data['employer_feedback']) ? $data['employer_feedback'] : null,

                // Section D - Interview Availability
                'interview_not_available' => $data['interview_not_available'] ?? false,
                'interview_by_phone' => $data['interview_by_phone'] ?? false,
                'interview_by_video' => $data['interview_by_video'] ?? false,
                'interview_in_person' => $data['interview_in_person'] ?? false,

                // Section E - Availability
                'availability_remarks' => $data['availability_remarks'] ?? null,
            ]);

            if (isset($data['attributes']) && is_array($data['attributes'])) {
                $maid->attributes()->delete();

                foreach ($data['attributes'] as $attr) {
                    MaidAttribute::create([
                        'maid_id' => $maid->id,
                        'attribute_category' => $attr['attribute_category'],
                        'attribute_name' => $attr['attribute_name'],
                    ]);
                }
            }

            return $maid->load('attributes');
        });
    }

    public function updateStatus($id, $status, $reason = null)
    {
        return DB::transaction(function () use ($id, $status, $reason) {
            $maid = Maid::findOrFail($id);

            Log::info('MaidService updateStatus called', [
                'maid_id' => $id,
                'current_status' => $maid->status,
                'new_status' => $status,
                'reason' => $reason
            ]);

            // Validate status transition using enum
            $currentStatus = \App\Enums\MaidStatus::from($maid->status);
            $newStatus = \App\Enums\MaidStatus::from($status);

            if (!$currentStatus->canTransitionTo($newStatus)) {
                Log::warning('Invalid status transition', [
                    'maid_id' => $id,
                    'from' => $currentStatus->value,
                    'to' => $newStatus->value
                ]);
                throw new \Exception("Cannot transition from {$currentStatus->label()} to {$newStatus->label()}");
            }

            $updateData = ['status' => $status];

            // Handle pending status with reason and expiry
            if ($status === 'pending') {
                $updateData['pending_reason'] = $reason;
                $updateData['pending_until'] = now()->addDays(3); // 3 days grace period
            } else {
                $updateData['pending_reason'] = null;
                $updateData['pending_until'] = null;
            }

            $maid->update($updateData);

            Log::info('Maid status updated successfully', [
                'maid_id' => $id,
                'new_status' => $status
            ]);

            return $maid;
        });
    }

    public function delete($id)
    {
        $maid = Maid::find($id);
        if (!$maid) {
            return false;
        }

        return $maid->delete();
    }

    /**
     * Parse uploaded document and extract maid data
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param array $parsers ['pdf' => PdfParser, 'docx' => DocxParser]
     * @param array $extractors ['section', 'personal', 'medical', 'skills', 'employment']
     * @return array Extracted and formatted data ready for form
     */
    public function parseDocument($file, array $parsers, array $extractors): array
    {
        $uploadService = \App\Services\MaidManagement\MaidDocumentUploadService::create(
            $parsers,
            $extractors
        );

        return $uploadService->process($file);
    }

    public function revertMaidToAvailable($maidId)
    {
        $maid = Maid::find($maidId);

        if ($maid->status !== 'available') {
            $maid->status = 'available';
            $maid->pending_until = null;
            $maid->pending_reason = null;
            $maid->status_job_id = null;
            $maid->save();
        }
    }
}
