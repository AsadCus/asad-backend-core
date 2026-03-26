<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\UserRoles\CustomerUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerUserServiceNumberingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('sales', 'web');
        Role::findOrCreate('customer', 'web');
    }

    public function test_store_generates_customer_number_when_missing(): void
    {
        $user = app(CustomerUserService::class)->store([
            'name' => 'Generated Customer',
            'email' => 'generated.customer@example.com',
            'contact' => '0123456789',
        ]);

        $customer = Customer::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertMatchesRegularExpression(
            '/^CUST-\d{4}-\d{4}$/',
            (string) $customer->customer_number,
        );
    }

    public function test_update_accepts_manual_customer_number_matching_format(): void
    {
        $service = app(CustomerUserService::class);

        $user = $service->store([
            'name' => 'Manual Customer',
            'email' => 'manual.customer@example.com',
            'contact' => '0198765432',
        ]);

        $manualNumber = 'CUST-'.now()->format('Y').'-9999';

        $service->update([
            'name' => 'Manual Customer Updated',
            'email' => 'manual.customer@example.com',
            'contact' => '0198765432',
            'customer_number' => $manualNumber,
        ], $user->id);

        $customer = Customer::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame($manualNumber, (string) $customer->customer_number);
    }
}
