<?php

namespace App\Http\Requests\CustomerConfirmation;

use App\Models\CustomerConfirmation;
use App\Rules\PayerMemberMappingBelongsToConfirmation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GenerateQuotationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payer_to_members' => [
                'required',
                'array',
                'min:1',
                new PayerMemberMappingBelongsToConfirmation((int) $this->route('id')),
            ],
            'payer_to_members.*' => ['required', 'array', 'min:1'],
            'payer_to_members.*.*' => ['integer', 'exists:customer_confirmation_members,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $confirmation = CustomerConfirmation::query()
                ->with(['members.customer.user'])
                ->find((int) $this->route('id'));

            if (! $confirmation) {
                return;
            }

            if ((int) ($confirmation->package_id ?? 0) <= 0) {
                $validator->errors()->add(
                    'payer_to_members',
                    'Cannot generate quotation: please select package in customer confirmation first.',
                );

                return;
            }

            $requestedMemberIds = collect((array) $this->input('payer_to_members', []))
                ->flatten()
                ->map(fn ($memberId): int => (int) $memberId)
                ->filter(fn (int $memberId): bool => $memberId > 0)
                ->unique()
                ->values();

            if ($requestedMemberIds->isEmpty()) {
                return;
            }

            $missingSharingPlanMembers = $confirmation->members
                ->whereIn('id', $requestedMemberIds)
                ->filter(function ($member): bool {
                    return trim((string) ($member->sharing_plan ?? '')) === '';
                })
                ->map(function ($member): string {
                    return (string) ($member->customer?->user?->name ?? ('Member #'.$member->id));
                })
                ->values();

            if ($missingSharingPlanMembers->isNotEmpty()) {
                $validator->errors()->add(
                    'payer_to_members',
                    'Cannot generate quotation: pricing plan is missing for '.$missingSharingPlanMembers->join(', ').'.',
                );
            }
        });
    }
}
