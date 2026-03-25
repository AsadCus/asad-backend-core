<?php

namespace Tests\Unit;

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

    public function test_sales_role_still_requires_branch_id(): void
    {
        $rule = new UserRule;

        $payload = [
            'name' => 'Sales One',
            'email' => 'sales.one@example.com',
            'role' => 'sales',
        ];

        $validator = Validator::make($payload, $rule->rules('sales'));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('branch_id', $validator->errors()->toArray());
    }

    public function test_admin_role_requires_branch_id(): void
    {
        $rule = new UserRule;

        $payload = [
            'name' => 'Admin One',
            'email' => 'admin.one@example.com',
            'role' => 'admin',
        ];

        $validator = Validator::make($payload, $rule->rules('admin'));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('branch_id', $validator->errors()->toArray());
    }
}
