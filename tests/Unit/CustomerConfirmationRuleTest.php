<?php

namespace Tests\Unit;

use App\Rules\CustomerConfirmationRule;
use PHPUnit\Framework\TestCase;

class CustomerConfirmationRuleTest extends TestCase
{
    public function test_only_name_email_and_contact_are_required_for_member_customer_info(): void
    {
        $rule = new CustomerConfirmationRule;
        $rules = $rule->rules();

        $this->assertContains('required', $rules['members.*.name']);
        $this->assertContains('required', $rules['members.*.email']);
        $this->assertContains('required', $rules['members.*.contact_number']);
    }

    public function test_other_member_customer_info_fields_are_nullable(): void
    {
        $rule = new CustomerConfirmationRule;
        $rules = $rule->rules();

        $nullableFields = [
            'members.*.nric_number',
            'members.*.address',
            'members.*.nationality',
            'members.*.passport_number',
            'members.*.passport_issue_date',
            'members.*.passport_expiry_date',
            'members.*.passport_place_of_issue',
            'members.*.gender',
            'members.*.marital_status',
            'members.*.date_of_birth',
            'members.*.place_of_birth',
        ];

        foreach ($nullableFields as $field) {
            $this->assertContains('nullable', $rules[$field]);
            $this->assertNotContains('required', $rules[$field]);
        }
    }

    public function test_member_sharing_plan_rule_allows_child_and_infant_values(): void
    {
        $rule = new CustomerConfirmationRule;
        $rules = $rule->rules();

        $sharingPlanRule = collect($rules['members.*.sharing_plan'])
            ->first(fn ($item) => is_string($item) && str_starts_with($item, 'in:'));

        $this->assertIsString($sharingPlanRule);
        $this->assertStringContainsString('child_with_bed', $sharingPlanRule);
        $this->assertStringContainsString('child_no_bed', $sharingPlanRule);
        $this->assertStringContainsString('infant', $sharingPlanRule);
    }
}
