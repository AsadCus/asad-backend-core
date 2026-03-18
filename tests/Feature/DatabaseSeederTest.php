<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\CustomerConfirmation;
use App\Models\Enquiry;
use App\Models\FinancialYear;
use App\Models\Invoice;
use App\Models\Manifest;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\Receipt;
use App\Models\Sales;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_seeds_only_master_data_and_admin_sales_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, Country::count());
        $this->assertGreaterThan(0, FinancialYear::count());

        $this->assertSame(2, User::role('admin')->count());
        $this->assertSame(2, User::role('sales')->count());
        $this->assertSame(0, User::role('supplier')->count());
        $this->assertSame(2, Sales::count());

        $this->assertSame(0, Quotation::count());
        $this->assertSame(0, Order::count());
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Receipt::count());

        $this->assertSame(0, Enquiry::count());
        $this->assertSame(0, CustomerConfirmation::count());
        $this->assertSame(0, Package::count());
        $this->assertSame(0, Manifest::count());
    }
}
