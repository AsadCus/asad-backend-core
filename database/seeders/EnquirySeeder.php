<?php

namespace Database\Seeders;

use App\Enums\EnquiryStatus;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerGroupMember;
use App\Models\Enquiry;
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
        $this->createEnquiryNotifications();
    }

    /**
     * Seed general enquiries with parent enquiry records.
     */
    private function seedGeneralEnquiries(): void
    {
        $generalEnquiries = [
            [
                'full_name' => 'John Smith',
                'mobile' => '+1234567890',
                'email' => 'john.smith@example.com',
                'preferred_destinations' => 'Paris, London, Amsterdam',
                'preferred_travelling_date' => '2026-05-15',
                'no_of_adults' => 2,
                'no_of_children' => 1,
                'requires_mobility_assistance' => null,
                'status' => EnquiryStatus::NewLead,
            ],
            [
                'full_name' => 'Sarah Johnson',
                'mobile' => '+9876543210',
                'email' => 'sarah.johnson@example.com',
                'preferred_destinations' => 'Tokyo, Bangkok, Singapore',
                'preferred_travelling_date' => '2026-06-20',
                'no_of_adults' => 3,
                'no_of_children' => 2,
                'requires_mobility_assistance' => null,
                'status' => EnquiryStatus::Contacted,
            ],
            [
                'full_name' => 'Michael Brown',
                'mobile' => '+1122334455',
                'email' => 'michael.brown@example.com',
                'preferred_destinations' => 'Sydney, Melbourne, Brisbane',
                'preferred_travelling_date' => '2026-07-10',
                'no_of_adults' => 2,
                'no_of_children' => 0,
                'requires_mobility_assistance' => 'Yes, wheelchair accessibility required',
                'status' => EnquiryStatus::Negotiating,
            ],
            [
                'full_name' => 'Emily White',
                'mobile' => '+5566778899',
                'email' => 'emily.white@example.com',
                'preferred_destinations' => 'Barcelona, Madrid, Lisbon',
                'preferred_travelling_date' => '2026-08-05',
                'no_of_adults' => 1,
                'no_of_children' => 0,
                'requires_mobility_assistance' => null,
                'status' => EnquiryStatus::Confirmed,
            ],
            [
                'full_name' => 'David Martinez',
                'mobile' => '+4433221100',
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
                'full_name' => $data['full_name'],
                'contact_number' => $data['mobile'],
                'email' => $data['email'],
            ]);

            GeneralEnquiry::create(array_merge($data, [
                'enquiry_id' => $parentEnquiry->id,
            ]));

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
        $privateEnquiries = [
            [
                'full_name' => 'Ahmad Bin Ali',
                'contact_number' => '0123456789',
                'email' => 'ahmad.ali@example.com',
                'passport_expiry_date' => '2027-12-31',
                'departure_date' => '2026-03-01',
                'return_date' => '2026-03-15',
                'no_of_pax' => 4,
                'no_of_children' => 2,
                'airline' => 'Saudi Airlines',
                'class' => 'Economy',
                'require_mutawif' => true,
                'require_umrah_course' => false,
                'require_umrah_official' => true,
                'makkah_or_madinah_first' => 'Makkah',
                'no_of_nights_makkah' => '5',
                'hotel_makkah' => 'Hilton Suites',
                'meals_makkah' => 'Breakfast',
                'no_of_nights_madinah' => '4',
                'hotel_madinah' => 'Anwar Al Madinah',
                'meals_madinah' => 'Half Board',
                'land_transfer' => 'Bus',
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
                'full_name' => 'Siti Aminah',
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
                'no_of_nights_makkah' => '3',
                'hotel_makkah' => 'Swissotel',
                'meals_makkah' => 'Full Board',
                'no_of_nights_madinah' => '5',
                'hotel_madinah' => 'Pullman Zamzam',
                'meals_madinah' => 'Breakfast',
                'land_transfer' => 'Private Car',
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
        ];

        foreach ($privateEnquiries as $data) {
            $status = $data['status'];
            unset($data['status']);

            $parentEnquiry = Enquiry::create([
                'type' => 'private',
                'status' => $status->value,
                'full_name' => $data['full_name'],
                'contact_number' => $data['contact_number'],
                'email' => $data['email'],
            ]);

            PrivateEnquiry::create(array_merge($data, [
                'enquiry_id' => $parentEnquiry->id,
            ]));

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
        // Create the customer user
        $user = User::create([
            'name' => $enquiry->full_name,
            'email' => $enquiry->email,
            'contact' => $enquiry->contact_number,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('customer');

        $customer = Customer::create([
            'user_id' => $user->id,
            'branch_id' => 1,
            'handled_by' => User::role(['admin', 'sales'])->first()?->id,
        ]);

        // Create customer group with the customer as leader
        $group = CustomerGroup::create([
            'enquiry_id' => $enquiry->id,
            'created_by' => User::role('admin')->first()?->id,
        ]);

        CustomerGroupMember::create([
            'customer_group_id' => $group->id,
            'customer_id' => $customer->id,
            'is_leader' => true,
        ]);
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
                'message' => "A new {$typeLabel} enquiry has been created by {$enquiry->full_name}.",
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
            $customerName = $enquiry->customerGroup?->members?->first()?->customer?->user?->name ?? $enquiry->full_name;

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
