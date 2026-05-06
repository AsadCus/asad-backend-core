<?php

namespace Tests\Unit;

use App\Models\User;
use App\Rules\UserRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UserRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_role_accepts_minimal_required_fields(): void
    {
        $rule = new UserRule;

        $payload = [
            'name' => 'Customer One',
            'email' => 'customer.one@example.com',
            'role' => 'customer',
        ];

        $validator = Validator::make($payload, $rule->rules('customer'));

        $this->assertTrue($validator->passes(), 'Customer payload with minimal required fields should be valid.');
    }

    public function test_sales_role_requires_scope_ids_in_country_mode(): void
    {
        config(['data_scope.mode' => 'country']);

        $rule = new UserRule;

        $payload = [
            'name' => 'Sales One',
            'email' => 'sales.one@example.com',
            'role' => 'sales',
        ];

        $validator = Validator::make($payload, $rule->rules('sales'));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('scope_ids', $validator->errors()->toArray());
    }

    public function test_superadmin_and_admin_roles_require_scope_ids_in_branch_mode(): void
    {
        config(['data_scope.mode' => 'branch']);

        $rule = new UserRule;

        foreach (['superadmin', 'admin'] as $role) {
            $payload = [
                'name' => ucfirst($role).' One',
                'email' => $role.'.one@example.com',
                'role' => $role,
            ];

            $validator = Validator::make($payload, $rule->rules($role));

            $this->assertTrue($validator->fails());
            $this->assertArrayHasKey('scope_ids', $validator->errors()->toArray());
        }
    }

    public function test_create_rules_allow_email_when_existing_user_is_soft_deleted(): void
    {
        User::factory()->create([
            'email' => 'softdeleted.user@example.com',
        ])->delete();

        $rule = new UserRule;

        $payload = [
            'name' => 'Replacement User',
            'email' => 'softdeleted.user@example.com',
            'role' => 'customer',
        ];

        $validator = Validator::make($payload, $rule->rules('customer'));

        $this->assertTrue($validator->passes(), 'Soft-deleted user emails should be reusable.');
    }
}
