<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Enquiry;
use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerConfirmationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip if already seeded
        if (CustomerConfirmation::count() > 0) {
            $this->command->info('Customer confirmations already seeded, skipping...');

            return;
        }

        $confirmedEnquiries = Enquiry::query()
            ->where('status', 'confirmed')
            ->whereDoesntHave('customerConfirmation')
            ->get();

        if ($confirmedEnquiries->isEmpty()) {
            $this->command->warn('No confirmed enquiries without customer confirmations found.');

            return;
        }

        $adminUser = User::role('admin')->first();

        foreach ($confirmedEnquiries as $enquiry) {
            $this->createConfirmationForEnquiry($enquiry, $adminUser);
        }

        $this->command->info('Created '.count($confirmedEnquiries).' customer confirmations.');
    }

    private function createConfirmationForEnquiry(Enquiry $enquiry, ?User $adminUser): void
    {
        // Get or create customer for lead person
        $customer = $enquiry->customer ?? $this->createCustomerFromEnquiry($enquiry);

        if (! $customer) {
            return;
        }

        // Get package or use first open package
        $package = $enquiry->package ?? Package::query()->where('status', 'open')->first();

        if (! $package) {
            return;
        }

        // Create customer confirmation
        $confirmation = CustomerConfirmation::create([
            'enquiry_id' => $enquiry->id,
            'created_by' => $adminUser?->id ?? User::role('admin')->first()?->id,
            'package_id' => $package->id,
            'package_room_type' => 'double',
            'package_category' => $package->category ?? 'standard',
            'date_of_application' => now()->subDays(rand(1, 14)),
        ]);

        // Create leader member
        CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
            'status' => 'confirmed',
            'sharing_plan' => 'double',
        ]);

        // Add 1-3 additional members
        $memberCount = rand(1, 3);
        for ($i = 0; $i < $memberCount; $i++) {
            $additionalCustomer = $this->createAdditionalMember($enquiry);
            if ($additionalCustomer) {
                CustomerConfirmationMember::create([
                    'customer_confirmation_id' => $confirmation->id,
                    'customer_id' => $additionalCustomer->id,
                    'is_leader' => false,
                    'status' => 'confirmed',
                    'sharing_plan' => 'double',
                ]);
            }
        }
    }

    private function createCustomerFromEnquiry(Enquiry $enquiry): ?Customer
    {
        $user = User::create([
            'name' => $enquiry->name,
            'email' => $enquiry->email ?? fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'phone_number' => $enquiry->contact_number,
        ]);

        return Customer::create([
            'user_id' => $user->id,
            'branch_id' => 1,
            'handled_by' => User::role('sales')->first()?->id ?? User::role('admin')->first()?->id,
            'is_active' => true,
        ]);
    }

    private function createAdditionalMember(Enquiry $enquiry): ?Customer
    {
        $user = User::create([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'phone_number' => fake()->phoneNumber(),
        ]);

        return Customer::create([
            'user_id' => $user->id,
            'branch_id' => 1,
            'handled_by' => User::role('sales')->first()?->id ?? User::role('admin')->first()?->id,
            'is_active' => true,
        ]);
    }
}
