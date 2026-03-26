<?php

namespace App\Models;

use App\Helpers\NumberGenerator;
use App\Services\FinancialTransactionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Maid extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'maid_number',
        'passport_number',

        // profile (Section A1)
        'name',
        'date_of_birth',
        'place_of_birth',
        'height',
        'weight',
        'country_id',
        'address',
        'repatriation_port_airport',
        'contact_number_home_country',
        'religion_id',
        'education_level_id',
        'marital_status',
        'number_of_siblings',
        'number_of_children',
        'children_ages',
        'photo_url',

        // medical history (Section A2)
        'allergies',
        'physical_disabilities',
        'dietary_restrictions',
        'food_preferences',
        'mental_illness',
        'tuberculosis',
        'epilepsy',
        'malaria',
        'asthma',
        'operations',
        'diabetes',
        'hypertension',
        'heart_disease',
        'other_illnesses',

        // a3 others
        'bio_code',
        'rest_days_per_month',
        'other_remarks',

        // others
        'status',
        'remaining_loan',
        'monthly_salary',
        'commission',
        'cost_of_maid',

        // status management
        'interview_date',
        'interview_end_date',
        'pending_until',
        'pending_reason',
        'status_job_id',
        'assigned_date',

        // skills assessment (Section D)
        'skills_assessment_singapore',
        'skills_assessment_overseas',

        // employment history (Section C)
        'employment_history',
        'singapore_experience',
        'experience_years',
        'employment_feedback',
        'employer_feedback',

        // Section D: AVAILABILITY OF FDW TO BE INTERVIEWED BY PROSPECTIVE EMPLOYER
        'interview_not_available',
        'interview_by_phone',
        'interview_by_video',
        'interview_in_person',

        // Section E: availability remarks
        'availability_remarks',

        // Section B: evaluation methods (methods of evaluation)
        'eval_declaration_no_eval',
        'eval_sg_interview',
        'eval_sg_phone',
        'eval_sg_video',
        'eval_sg_in_person',
        'eval_sg_in_person_observed',
        'eval_overseas_interview',
        'eval_overseas_name',
        'eval_overseas_cert',
        'eval_overseas_phone',
        'eval_overseas_video',
        'eval_overseas_in_person',
        'eval_overseas_in_person_observed',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'interview_date' => 'datetime',
        'interview_end_date' => 'datetime',
        'pending_until' => 'datetime',
        'assigned_date' => 'datetime',
        'singapore_experience' => 'boolean',
        'remaining_loan' => 'decimal:2',
        'monthly_salary' => 'decimal:2',
        'commission' => 'decimal:2',
        'cost_of_maid' => 'decimal:2',
        'skills_assessment_singapore' => 'array',
        'skills_assessment_overseas' => 'array',
        'employment_history' => 'array',
        'employment_feedback' => 'array',
        'employer_feedback' => 'array',
        'eval_declaration_no_eval' => 'boolean',
        'eval_sg_interview' => 'boolean',
        'eval_sg_phone' => 'boolean',
        'eval_sg_video' => 'boolean',
        'eval_sg_in_person' => 'boolean',
        'eval_sg_in_person_observed' => 'boolean',
        'eval_overseas_interview' => 'boolean',
        'eval_overseas_phone' => 'boolean',
        'eval_overseas_video' => 'boolean',
        'eval_overseas_in_person' => 'boolean',
        'eval_overseas_in_person_observed' => 'boolean',
        'interview_not_available' => 'boolean',
        'interview_by_phone' => 'boolean',
        'interview_by_video' => 'boolean',
        'interview_in_person' => 'boolean',
        'mental_illness' => 'boolean',
        'tuberculosis' => 'boolean',
        'epilepsy' => 'boolean',
        'malaria' => 'boolean',
        'asthma' => 'boolean',
        'operations' => 'boolean',
        'diabetes' => 'boolean',
        'hypertension' => 'boolean',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function religion(): BelongsTo
    {
        return $this->belongsTo(Religion::class, 'religion_id');
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class, 'education_level_id');
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(MaidAttribute::class, 'maid_id');
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Maid::class, 'customer_maid_recommendations');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'maid_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'maid_id');
    }

    // Formatting Helpers
    public function getDateOfBirthFormattedAttribute(): ?string
    {
        return $this->date_of_birth
            ? Carbon::parse($this->date_of_birth)->translatedFormat('d F Y')
            : null;
    }

    public function getInterviewDateFormattedAttribute(): ?string
    {
        if (! $this->interview_date) {
            return null;
        }

        $start = Carbon::parse($this->interview_date);
        $formatted = $start->format('M d, Y h:i A');

        if ($this->interview_end_date) {
            $end = Carbon::parse($this->interview_end_date);
            if ($start->isSameDay($end)) {
                $formatted .= ' to '.$end->format('h:i A');
            } else {
                $formatted .= ' to '.$end->format('M d, Y h:i A');
            }
        }

        return $formatted;
    }

    public function getHeightFormattedAttribute(): ?string
    {
        return $this->height ? (string) intval($this->height) : null;
    }

    public function getWeightFormattedAttribute(): ?string
    {
        return $this->weight ? (string) intval($this->weight) : null;
    }

    public function getAssignedDateFormattedAttribute(): ?string
    {
        return $this->assigned_date
            ? Carbon::parse($this->assigned_date)->translatedFormat('d F Y')
            : null;
    }

    public function getTotalCostOfMaid(): ?float
    {
        $cost = (float) ($this->cost_of_maid ?? 0);
        $commission = (float) ($this->commission ?? 0);

        return $cost - $commission;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($maid) {
            if (empty($maid->maid_number)) {
                $maid->maid_number = NumberGenerator::generate('maid');
            }
        });

        static::created(function ($maid) {
            $cost = $maid->getTotalCostOfMaid();

            if ($cost > 0) {
                $financialTransactionService = app(FinancialTransactionService::class);
                $financialTransactionService->recordExpense(
                    amount: $cost,
                    description: "Maid creation - {$maid->name} ({$maid->maid_number})",
                    date: Carbon::now(),
                    referenceType: 'App\Models\Maid',
                    referenceId: $maid->id,
                    metadata: [
                        'maid_number' => $maid->maid_number,
                        'name' => $maid->name,
                        'cost_of_maid' => $maid->cost_of_maid,
                        'commission' => $maid->commission,
                    ]
                );
            }
        });

        static::updated(function ($maid) {
            if ($maid->isDirty(['cost_of_maid', 'commission'])) {
                $financialTransactionService = app(FinancialTransactionService::class);
                $financialTransactionService->updateMaidExpense($maid);
            }
        });

        static::deleting(function ($maid) {
            FinancialTransaction::where('reference_type', 'App\Models\Maid')->where('reference_id', $maid->id)->delete();

            $maid->attributes()->delete();
        });

        static::restored(function ($maid) {
            FinancialTransaction::withTrashed()->where('reference_type', 'App\Models\Maid')->where('reference_id', $maid->id)->restore();
        });
    }
}
