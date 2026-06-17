<?php

namespace Database\Seeders\Tms;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Receipt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class EmailDispatchTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create or get the User with the specified email
        $user = User::firstOrCreate(
            ['email' => 'badar.maulana@student.president.ac.id'],
            [
                'name' => 'Badar Maulana',
                'password' => Hash::make('password123'),
            ]
        );

        // Assign 'customer' role if not assigned (optional, but good practice if roles exist)
        if (method_exists($user, 'assignRole') && ! Role::where('name', 'customer')->exists() === false) {
            $user->assignRole('customer');
        }

        // 2. Create Customer Profile
        $customer = Customer::firstOrCreate(
            ['user_id' => $user->id],
            [
                'customer_number' => 'CUST-TEST-001',
                'address' => 'Jl. President University, Cikarang',
            ]
        );

        // Fetch a random admin user to be the handler
        $adminId = User::whereHas('roles', function ($q) {
            $q->where('name', 'superadmin')->orWhere('name', 'admin');
        })->first()->id ?? $user->id;
        $branchId = Branch::first()->id ?? null;
        $countryId = Country::first()->id ?? null;

        // 3. Create a Quotation
        $quotation = Quotation::firstOrCreate(
            ['quotation_number' => 'QUO-TEST-001'],
            [
                'customer_id' => $customer->id,
                'quotation_date' => Carbon::now(),
                'expiry_date' => Carbon::now()->addDays(7),
                'handled_by' => $adminId,
                'branch_id' => $branchId,
                'country_id' => $countryId,
                'payment_plan' => 'full',
                'status' => 'draft',
                'description' => 'Test quotation for email dispatch',
            ]
        );

        // 4. Create an Order
        $order = Order::firstOrCreate(
            ['order_number' => 'ORD-TEST-001'],
            [
                'quotation_id' => $quotation->id,
                'payment_plan' => 'full',
            ]
        );

        // 5. Create an Invoice
        $invoice = Invoice::firstOrCreate(
            ['invoice_number' => 'INV-TEST-001'],
            [
                'order_id' => $order->id,
                'amount' => 5000000,
                'invoice_date' => Carbon::now(),
                'due_date' => Carbon::now()->addDays(7),
                'status' => 'paid',
                'description' => 'Invoice for Bali Trip Package',
                'payment_method' => 'bank_transfer',
            ]
        );

        // 6. Create a Receipt
        $receipt = Receipt::firstOrCreate(
            ['receipt_number' => 'REC-TEST-001'],
            [
                'invoice_id' => $invoice->id,
                'amount' => 5000000,
                'receipt_date' => Carbon::now(),
                'payment_method' => 'bank_transfer',
                'description' => 'Receipt for Bali Trip Package Invoice',
            ]
        );

        $this->command->info('Test data for Email Dispatch has been successfully seeded.');
        $this->command->info("Email mapped to: {$user->email}");
        $this->command->info("Invoice ID: {$invoice->id} | Receipt ID: {$receipt->id}");
    }
}
