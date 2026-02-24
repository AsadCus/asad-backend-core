<?php

namespace Database\Seeders;

use App\Enums\EnquiryStatus;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerGroupMember;
use App\Models\Enquiry;
use App\Models\EnquiryRemark;
use App\Models\GeneralEnquiry;
use App\Models\Notification;
use App\Models\PrivateEnquiry;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EnquirySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedGeneralEnquiries();
        $this->seedPrivateEnquiries();
        $this->createRandomRemarks();
        $this->createEnquiryNotifications();
    }

    /**
     * Seed general enquiries with parent enquiry records.
     */
    private function seedGeneralEnquiries(): void
    {
        // Skip if general enquiries already exist
        if (GeneralEnquiry::count() > 0) {
            $this->command->info('General enquiries already seeded, skipping...');

            return;
        }

        $adminAndSalesUsers = User::role(['admin', 'sales'])->get();
        $defaultCreator = $adminAndSalesUsers->first()?->id;

        $generalEnquiries = [
            [
                'name' => 'John Smith',
                'contact_number' => '+1234567890',
                'email' => 'john.smith@example.com',
                'preferred_destinations' => 'Paris, London, Amsterdam',
                'preferred_travelling_date' => '2026-05-15',
                'no_of_adults' => 2,
                'no_of_children' => 1,
                'requires_mobility_assistance' => null,
                'status' => EnquiryStatus::NewLead,
            ],
            [
                'name' => 'Sarah Johnson',
                'contact_number' => '+9876543210',
                'email' => 'sarah.johnson@example.com',
                'preferred_destinations' => 'Tokyo, Bangkok, Singapore',
                'preferred_travelling_date' => '2026-06-20',
                'no_of_adults' => 3,
                'no_of_children' => 2,
                'requires_mobility_assistance' => null,
                'status' => EnquiryStatus::Contacted,
            ],
            [
                'name' => 'Michael Brown',
                'contact_number' => '+1122334455',
                'email' => 'michael.brown@example.com',
                'preferred_destinations' => 'Sydney, Melbourne, Brisbane',
                'preferred_travelling_date' => '2026-07-10',
                'no_of_adults' => 2,
                'no_of_children' => 0,
                'requires_mobility_assistance' => 'Yes, wheelchair accessibility required',
                'status' => EnquiryStatus::Negotiating,
            ],
            [
                'name' => 'Emily White',
                'contact_number' => '+5566778899',
                'email' => 'emily.white@example.com',
                'preferred_destinations' => 'Barcelona, Madrid, Lisbon',
                'preferred_travelling_date' => '2026-08-05',
                'no_of_adults' => 1,
                'no_of_children' => 0,
                'requires_mobility_assistance' => null,
                'status' => EnquiryStatus::Confirmed,
            ],
            [
                'name' => 'David Martinez',
                'contact_number' => '+4433221100',
                'email' => 'david.martinez@example.com',
                'preferred_destinations' => 'New York, Los Angeles, Miami',
                'preferred_travelling_date' => '2026-09-12',
                'no_of_adults' => 2,
                'no_of_children' => 2,
                'requires_mobility_assistance' => null,
                'status' => EnquiryStatus::NewLead,
            ],
        ];

        foreach ($generalEnquiries as $data) {
            $status = $data['status'];
            unset($data['status']);

            $parentEnquiry = Enquiry::create([
                'type' => 'general',
                'status' => $status->value,
                'name' => $data['name'],
                'contact_number' => $data['contact_number'],
                'email' => $data['email'],
                'created_by' => $adminAndSalesUsers->random()->id ?? $defaultCreator,
            ]);

            GeneralEnquiry::create([
                'enquiry_id' => $parentEnquiry->id,
                'preferred_destinations' => $data['preferred_destinations'],
                'preferred_travelling_date' => $data['preferred_travelling_date'],
                'no_of_adults' => $data['no_of_adults'],
                'no_of_children' => $data['no_of_children'],
                'requires_mobility_assistance' => $data['requires_mobility_assistance'],
            ]);

            // Create customer group for confirmed enquiries
            if ($status === EnquiryStatus::Confirmed) {
                $this->createCustomerForConfirmedEnquiry($parentEnquiry);
            }
        }
    }

    /**
     * Seed private enquiries with parent enquiry records.
     */
    private function seedPrivateEnquiries(): void
    {
        // Skip if private enquiries already exist
        if (PrivateEnquiry::count() > 0) {
            $this->command->info('Private enquiries already seeded, skipping...');

            return;
        }

        $adminAndSalesUsers = User::role(['admin', 'sales'])->get();
        $defaultCreator = $adminAndSalesUsers->first()?->id;

        $privateEnquiries = [
            [
                'name' => 'Ahmad Bin Ali',
                'contact_number' => '0123456789',
                'email' => 'ahmad.ali@example.com',
                'passport_expiry_date' => '2027-12-31',
                'departure_date' => '2026-03-01',
                'return_date' => '2026-03-15',
                'no_of_pax' => 4,
                'no_of_children' => 2,
                'airline' => 'Saudia Airlines',
                'class' => 'Economy',
                'require_mutawif' => true,
                'require_umrah_course' => false,
                'require_umrah_official' => true,
                'makkah_or_madinah_first' => 'Makkah',
                'no_of_nights_makkah' => '5',
                'hotel_makkah' => 'Hilton Suites Makkah',
                'meals_makkah' => 'Breakfast Only',
                'no_of_nights_madinah' => '4',
                'hotel_madinah' => 'The Oberoi',
                'meals_madinah' => 'Half Board',
                'land_transfer' => 'Hi-Ace (8 Pax)',
                'add_on_speed_train' => true,
                'require_meet_greet' => false,
                'require_mutawiffah_ustazah_rawdah' => false,
                'madinah_tour_with_mutawif' => true,
                'makkah_tour_with_mutawif' => false,
                'has_chronic_disease' => false,
                'chronic_disease_details' => null,
                'need_wheelchair' => 'No',
                'other_remarks' => 'Vegetarian meal preferred',
                'status' => EnquiryStatus::NewLead,
            ],
            [
                'name' => 'Siti Aminah',
                'contact_number' => '0198765432',
                'email' => 'siti.aminah@example.com',
                'passport_expiry_date' => '2028-05-20',
                'departure_date' => '2026-04-10',
                'return_date' => '2026-04-25',
                'no_of_pax' => 2,
                'no_of_children' => 0,
                'airline' => 'Emirates',
                'class' => 'Business',
                'require_mutawif' => false,
                'require_umrah_course' => true,
                'require_umrah_official' => false,
                'makkah_or_madinah_first' => 'Madinah',
                'no_of_nights_makkah' => '4',
                'hotel_makkah' => 'Swissotel Makkah',
                'meals_makkah' => 'Full Board',
                'no_of_nights_madinah' => '5',
                'hotel_madinah' => 'Intercontinental Dar Al Iman',
                'meals_madinah' => 'Breakfast Only',
                'land_transfer' => 'Sedan (2 Pax)',
                'add_on_speed_train' => false,
                'require_meet_greet' => true,
                'require_mutawiffah_ustazah_rawdah' => true,
                'madinah_tour_with_mutawif' => false,
                'makkah_tour_with_mutawif' => true,
                'has_chronic_disease' => true,
                'chronic_disease_details' => 'Diabetes',
                'need_wheelchair' => 'Yes',
                'other_remarks' => null,
                'status' => EnquiryStatus::Contacted,
            ],
            [
                'name' => 'Fatimah Binti Hassan',
                'contact_number' => '0171234567',
                'email' => 'fatimah.hassan@example.com',
                'passport_expiry_date' => '2029-08-15',
                'departure_date' => '2026-05-01',
                'return_date' => '2026-05-14',
                'no_of_pax' => 3,
                'no_of_children' => 1,
                'airline' => 'Qatar Airways',
                'class' => 'Economy',
                'require_mutawif' => true,
                'require_umrah_course' => true,
                'require_umrah_official' => true,
                'makkah_or_madinah_first' => 'Makkah',
                'no_of_nights_makkah' => '5',
                'hotel_makkah' => 'Fairmont Makkah Clock Royal Tower Hotel',
                'meals_makkah' => 'Half Board',
                'no_of_nights_madinah' => '4',
                'hotel_madinah' => 'Sofitel Shahd Al Madinah',
                'meals_madinah' => 'Full Board',
                'land_transfer' => 'GMC (4 Pax)',
                'add_on_speed_train' => true,
                'require_meet_greet' => true,
                'require_mutawiffah_ustazah_rawdah' => false,
                'madinah_tour_with_mutawif' => true,
                'makkah_tour_with_mutawif' => true,
                'has_chronic_disease' => false,
                'chronic_disease_details' => null,
                'need_wheelchair' => 'No',
                'other_remarks' => 'Family trip, need adjoining rooms.',
                'status' => EnquiryStatus::Confirmed,
            ],
        ];

        foreach ($privateEnquiries as $data) {
            $status = $data['status'];
            unset($data['status']);

            $parentEnquiry = Enquiry::create([
                'type' => 'private',
                'status' => $status->value,
                'name' => $data['name'],
                'contact_number' => $data['contact_number'],
                'email' => $data['email'],
                'created_by' => $adminAndSalesUsers->random()->id ?? $defaultCreator,
            ]);

            PrivateEnquiry::create([
                'enquiry_id' => $parentEnquiry->id,
                'passport_expiry_date' => $data['passport_expiry_date'],
                'departure_date' => $data['departure_date'],
                'return_date' => $data['return_date'],
                'no_of_pax' => $data['no_of_pax'],
                'no_of_children' => $data['no_of_children'],
                'airline' => $data['airline'],
                'class' => $data['class'],
                'require_mutawif' => $data['require_mutawif'],
                'require_umrah_course' => $data['require_umrah_course'],
                'require_umrah_official' => $data['require_umrah_official'],
                'makkah_or_madinah_first' => $data['makkah_or_madinah_first'],
                'no_of_nights_makkah' => $data['no_of_nights_makkah'],
                'hotel_makkah' => $data['hotel_makkah'],
                'meals_makkah' => $data['meals_makkah'],
                'no_of_nights_madinah' => $data['no_of_nights_madinah'],
                'hotel_madinah' => $data['hotel_madinah'],
                'meals_madinah' => $data['meals_madinah'],
                'land_transfer' => $data['land_transfer'],
                'add_on_speed_train' => $data['add_on_speed_train'],
                'require_meet_greet' => $data['require_meet_greet'],
                'require_mutawiffah_ustazah_rawdah' => $data['require_mutawiffah_ustazah_rawdah'],
                'madinah_tour_with_mutawif' => $data['madinah_tour_with_mutawif'],
                'makkah_tour_with_mutawif' => $data['makkah_tour_with_mutawif'],
                'has_chronic_disease' => $data['has_chronic_disease'],
                'chronic_disease_details' => $data['chronic_disease_details'],
                'need_wheelchair' => $data['need_wheelchair'],
                'other_remarks' => $data['other_remarks'],
            ]);

            // Create customer group for confirmed enquiries
            if ($status === EnquiryStatus::Confirmed) {
                $this->createCustomerForConfirmedEnquiry($parentEnquiry);
            }
        }
    }

    /**
     * Create a customer user and customer group for a confirmed enquiry.
     */
    private function createCustomerForConfirmedEnquiry(Enquiry $enquiry): void
    {
        // Check if user already exists
        $user = User::where('email', $enquiry->email)->first();

        if (! $user) {
            // Create the customer user
            $user = User::create([
                'name' => $enquiry->name,
                'email' => $enquiry->email,
                'contact' => $enquiry->contact_number,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
            $user->assignRole('customer');
        }

        // Get biodata from pre-defined customer profiles
        $biodata = $this->getCustomerBiodata($enquiry->email);

        // Check if customer already exists
        $customer = Customer::where('user_id', $user->id)->first();

        if (! $customer) {
            $customer = Customer::create(array_merge([
                'user_id' => $user->id,
                'branch_id' => 1,
                'handled_by' => User::role(['admin', 'sales'])->first()?->id,
                'is_active' => true,
            ], $biodata));
        }

        // Check if customer group already exists for this enquiry
        $existingGroup = CustomerGroup::where('enquiry_id', $enquiry->id)->first();

        if (! $existingGroup) {
            // Create customer group with the customer as leader
            $group = CustomerGroup::create([
                'enquiry_id' => $enquiry->id,
                'created_by' => User::role('admin')->first()?->id,
                'package_id' => 1,
                'package_room_type' => 'double',
                'package_category' => 'classic_umrah',
                'date_of_application' => now()->subDays(rand(1, 14)),
            ]);

            CustomerGroupMember::create([
                'customer_group_id' => $group->id,
                'customer_id' => $customer->id,
                'is_leader' => true,
            ]);

            // Add additional members for multi-member groups
            $additionalMembers = $this->getAdditionalGroupMembers($enquiry->email);
            foreach ($additionalMembers as $memberData) {
                $memberUser = User::where('email', $memberData['email'])->first();
                if (! $memberUser) {
                    $memberUser = User::create([
                        'name' => $memberData['name'],
                        'email' => $memberData['email'],
                        'contact' => $memberData['contact'],
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]);
                    $memberUser->assignRole('customer');
                }

                $memberCustomer = Customer::where('user_id', $memberUser->id)->first();
                if (! $memberCustomer) {
                    $memberBiodata = $this->getCustomerBiodata($memberData['email']);
                    $memberCustomer = Customer::create(array_merge([
                        'user_id' => $memberUser->id,
                        'branch_id' => 1,
                        'handled_by' => User::role(['admin', 'sales'])->first()?->id,
                        'is_active' => true,
                    ], $memberBiodata));
                }

                CustomerGroupMember::create([
                    'customer_group_id' => $group->id,
                    'customer_id' => $memberCustomer->id,
                    'is_leader' => false,
                ]);
            }
        }
    }

    /**
     * Get pre-defined customer biodata by email.
     *
     * @return array<string, mixed>
     */
    private function getCustomerBiodata(string $email): array
    {
        $profiles = [
            'emily.white@example.com' => [
                'nric_number' => 'S9012345A',
                'address' => '12 Orchard Road, Singapore 238828',
                'nationality' => 'Singaporean',
                'passport_number' => 'E1234567A',
                'passport_issue_date' => '2023-01-15',
                'passport_expiry_date' => '2033-01-14',
                'passport_place_of_issue' => 'Singapore',
                'gender' => 'female',
                'marital_status' => 'single',
                'date_of_birth' => '1990-03-22',
                'place_of_birth' => 'Singapore',
                'first_time_umrah' => true,
                'has_chronic_disease' => false,
                'chronic_disease_details' => null,
            ],
            'fatimah.hassan@example.com' => [
                'nric_number' => 'S8501234B',
                'address' => '88 Jalan Sultan, Singapore 199489',
                'nationality' => 'Malaysian',
                'passport_number' => 'A12345678',
                'passport_issue_date' => '2022-06-10',
                'passport_expiry_date' => '2032-06-09',
                'passport_place_of_issue' => 'Kuala Lumpur',
                'gender' => 'female',
                'marital_status' => 'married',
                'date_of_birth' => '1985-07-14',
                'place_of_birth' => 'Kuala Lumpur',
                'first_time_umrah' => false,
                'has_chronic_disease' => false,
                'chronic_disease_details' => null,
            ],
            'ibrahim.hassan@example.com' => [
                'nric_number' => 'S8401567C',
                'address' => '88 Jalan Sultan, Singapore 199489',
                'nationality' => 'Malaysian',
                'passport_number' => 'A98765432',
                'passport_issue_date' => '2021-11-20',
                'passport_expiry_date' => '2031-11-19',
                'passport_place_of_issue' => 'Kuala Lumpur',
                'gender' => 'male',
                'marital_status' => 'married',
                'date_of_birth' => '1984-02-28',
                'place_of_birth' => 'Kuala Lumpur',
                'first_time_umrah' => false,
                'has_chronic_disease' => true,
                'chronic_disease_details' => 'Asthma',
            ],
            'nur.hassan@example.com' => [
                'nric_number' => 'S1201234D',
                'address' => '88 Jalan Sultan, Singapore 199489',
                'nationality' => 'Malaysian',
                'passport_number' => 'A55566677',
                'passport_issue_date' => '2023-03-05',
                'passport_expiry_date' => '2033-03-04',
                'passport_place_of_issue' => 'Kuala Lumpur',
                'gender' => 'female',
                'marital_status' => 'single',
                'date_of_birth' => '2012-09-18',
                'place_of_birth' => 'Singapore',
                'first_time_umrah' => true,
                'has_chronic_disease' => false,
                'chronic_disease_details' => null,
            ],
            'james.white@example.com' => [
                'nric_number' => 'S8812345E',
                'address' => '12 Orchard Road, Singapore 238828',
                'nationality' => 'Singaporean',
                'passport_number' => 'E7654321B',
                'passport_issue_date' => '2022-08-01',
                'passport_expiry_date' => '2032-07-31',
                'passport_place_of_issue' => 'Singapore',
                'gender' => 'male',
                'marital_status' => 'married',
                'date_of_birth' => '1988-11-05',
                'place_of_birth' => 'Singapore',
                'first_time_umrah' => true,
                'has_chronic_disease' => false,
                'chronic_disease_details' => null,
            ],
        ];

        return $profiles[$email] ?? [];
    }

    /**
     * Get additional group members for a confirmed enquiry leader.
     *
     * @return array<int, array{name: string, email: string, contact: string}>
     */
    private function getAdditionalGroupMembers(string $leaderEmail): array
    {
        $members = [
            // Emily White's group - add her husband
            'emily.white@example.com' => [
                ['name' => 'James White', 'email' => 'james.white@example.com', 'contact' => '+5566778800'],
            ],
            // Fatimah's group - add her husband and daughter
            'fatimah.hassan@example.com' => [
                ['name' => 'Ibrahim Hassan', 'email' => 'ibrahim.hassan@example.com', 'contact' => '0171234568'],
                ['name' => 'Nur Hassan', 'email' => 'nur.hassan@example.com', 'contact' => '0171234569'],
            ],
        ];

        return $members[$leaderEmail] ?? [];
    }

    /**
     * Create random remarks for enquiries.
     */
    private function createRandomRemarks(): void
    {
        // Skip if remarks already exist
        if (EnquiryRemark::count() > 0) {
            $this->command->info('Enquiry remarks already exist, skipping...');

            return;
        }

        $adminAndSalesUsers = User::role(['admin', 'sales'])->get();

        if ($adminAndSalesUsers->isEmpty()) {
            return;
        }

        $remarkTemplates = [
            'Called customer to discuss travel requirements.',
            'Customer requested additional information about package details.',
            'Sent quotation via email.',
            'Customer is considering the offer and will get back to us.',
            'Follow-up call scheduled for next week.',
            'Customer wants to modify the travel dates.',
            'Discussed payment terms and options.',
            'Customer has some budget concerns, negotiating.',
            'Confirmed customer interest, preparing documentation.',
            'Customer agreed to all terms and conditions.',
            'Waiting for customer response on revised quotation.',
            'Customer requested discount for group booking.',
            'Discussed special dietary requirements.',
            'Customer needs time to consult with family members.',
            'Clarified visa and passport requirements.',
            'Customer satisfied with the package offered.',
            'Resolved customer questions about cancellation policy.',
            'Customer requested upgrade options.',
            'Sent brochure and additional destination information.',
            'Customer comparing with other travel agencies.',
        ];

        $enquiries = Enquiry::all();

        foreach ($enquiries as $enquiry) {
            $currentTimestamp = $enquiry->created_at->copy();

            $statusTransitions = $this->getStatusTransitionsForSeeder($enquiry->status);
            foreach ($statusTransitions as [$fromStatus, $toStatus]) {
                $createdBy = $adminAndSalesUsers->random();
                $currentTimestamp = $currentTimestamp->copy()->addHours(rand(1, 12));

                EnquiryRemark::create([
                    'enquiry_id' => $enquiry->id,
                    'created_by' => $createdBy->id,
                    'status_at_time' => $toStatus->value,
                    'remark' => "Status updated from {$fromStatus->label()} to {$toStatus->label()}.",
                    'created_at' => $currentTimestamp,
                    'updated_at' => $currentTimestamp,
                ]);

                if ($toStatus !== EnquiryStatus::Confirmed) {
                    $enquiry->update([
                        'handled_by' => $createdBy->id,
                    ]);
                }
            }

            // Create 1-4 random remarks per enquiry
            $remarkCount = rand(1, 4);

            for ($i = 0; $i < $remarkCount; $i++) {
                $createdBy = $adminAndSalesUsers->random();
                $remark = $remarkTemplates[array_rand($remarkTemplates)];

                // Create remarks with dates after enquiry creation
                $currentTimestamp = $currentTimestamp->copy()->addHours(rand(1, 12));
                $createdAt = $currentTimestamp;

                EnquiryRemark::create([
                    'enquiry_id' => $enquiry->id,
                    'created_by' => $createdBy->id,
                    'status_at_time' => $enquiry->status->value,
                    'remark' => $remark,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                if ($enquiry->status !== EnquiryStatus::Confirmed) {
                    $enquiry->update([
                        'handled_by' => $createdBy->id,
                    ]);
                }
            }
        }

        $this->command->info('Random remarks created for enquiries!');
    }

    /**
     * Build status transition pairs up to the enquiry's current status.
     *
     * @return array<int, array{0: EnquiryStatus, 1: EnquiryStatus}>
     */
    private function getStatusTransitionsForSeeder(EnquiryStatus $targetStatus): array
    {
        $workflow = [
            EnquiryStatus::NewLead,
            EnquiryStatus::Contacted,
            EnquiryStatus::Negotiating,
            EnquiryStatus::Confirmed,
        ];

        $targetIndex = array_search($targetStatus, $workflow, true);
        if (! is_int($targetIndex) || $targetIndex === 0) {
            return [];
        }

        $transitions = [];

        for ($index = 1; $index <= $targetIndex; $index++) {
            $transitions[] = [$workflow[$index - 1], $workflow[$index]];
        }

        return $transitions;
    }

    /**
     * Create notifications for admin/sales about enquiries and customers.
     */
    private function createEnquiryNotifications(): void
    {
        $adminAndSalesUsers = User::role(['admin', 'sales'])->get();
        $enquiries = Enquiry::all();

        // Notify about each created enquiry
        foreach ($enquiries as $enquiry) {
            $typeLabel = $enquiry->type === 'general' ? 'General' : 'Private';
            $link = $enquiry->type === 'general'
                ? '/general-enquiries'
                : '/private-enquiries';

            $notification = Notification::create([
                'title' => "New {$typeLabel} Enquiry",
                'message' => "A new {$typeLabel} enquiry has been created by {$enquiry->name}.",
                'link' => $link,
                'type' => 'info',
            ]);

            foreach ($adminAndSalesUsers as $user) {
                UserNotification::create([
                    'user_id' => $user->id,
                    'notification_id' => $notification->id,
                    'is_read' => false,
                ]);
            }
        }

        // Notify about customers created from confirmed enquiries
        $confirmedEnquiries = Enquiry::where('status', EnquiryStatus::Confirmed->value)
            ->with('customerGroup.members.customer.user')
            ->get();

        foreach ($confirmedEnquiries as $enquiry) {
            $customerName = $enquiry->customerGroup?->members?->first()?->customer?->user?->name ?? $enquiry->name;

            $notification = Notification::create([
                'title' => 'New Customer Created',
                'message' => "Customer {$customerName} has been created from a confirmed enquiry.",
                'link' => '/general-enquiries',
                'type' => 'success',
            ]);

            foreach ($adminAndSalesUsers as $user) {
                UserNotification::create([
                    'user_id' => $user->id,
                    'notification_id' => $notification->id,
                    'is_read' => false,
                ]);
            }
        }

        $this->command->info('Enquiry notifications created and assigned to admin/sales users!');
    }
}
