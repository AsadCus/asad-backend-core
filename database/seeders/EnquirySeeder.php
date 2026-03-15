<?php

namespace Database\Seeders;

use App\Enums\EnquiryStatus;
use App\Helpers\NumberGenerator;
use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Enquiry;
use App\Models\EnquiryRemark;
use App\Models\GeneralEnquiry;
use App\Models\Notification;
use App\Models\Package;
use App\Models\PrivateEnquiry;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EnquirySeeder extends Seeder
{
    private const GENERAL_ENQUIRY_COUNT = 24;

    private const PRIVATE_ENQUIRY_COUNT = 24;

    /** @var array<int, EnquiryStatus> */
    private const NON_CONFIRMED_STATUSES = [
        EnquiryStatus::NewLead,
        EnquiryStatus::Contacted,
        EnquiryStatus::Negotiating,
    ];

    /** @var string[] */
    private const PRIVATE_AIRLINES = [
        'Saudia Airlines',
        'Emirates',
        'Qatar Airways',
    ];

    /** @var string[] */
    private const PRIVATE_FLIGHT_CLASSES = [
        'Business',
        'Economy',
    ];

    /** @var string[] */
    private const PRIVATE_HOTELS_MAKKAH = [
        'Makkah Hotel & Towers',
        'Swissotel Makkah',
        'Hilton Suites Makkah',
        'Jumeirah Jabal Omar Makkah',
        'Fairmont Makkah Clock Royal Tower Hotel',
        'Address Jabal Omar Makkah',
        'Swissotel Maqam',
        'Hyatt Jabal Omar',
        'InterContinental Dar Al Tawhid Makkah',
        'Hilton Convention Makkah',
        'Conrad Jabal Omar',
        'Al Ghufran Safwa Hotel',
    ];

    /** @var string[] */
    private const PRIVATE_HOTELS_MADINAH = [
        'The Oberoi',
        'Intercontinental Dar Al Iman',
        'Sofitel Shahd Al Madinah',
        'Madinah Hilton Hotel',
        'Dar Al Eiman Al Haram Madinah',
        'Dar Al Taqwa Madinah',
    ];

    /** @var string[] */
    private const PRIVATE_MEAL_OPTIONS = [
        'Breakfast Only',
        'Half Board',
        'Full Board (3 Meals)',
    ];

    /** @var string[] */
    private const PRIVATE_NIGHTS_MAKKAH = ['4', '5'];

    /** @var string[] */
    private const PRIVATE_NIGHTS_MADINAH = ['3', '4', '5'];

    /** @var string[] */
    private const PRIVATE_LAND_TRANSFERS = [
        'Sedan (2 Pax)',
        'Starex (4 Pax)',
        'GMC (4 Pax)',
        'Hi-Ace (8 Pax)',
        'Coaster (12 Pax)',
    ];

    private int $confirmedCustomerGroupCounter = 0;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedGeneralEnquiries();
        $this->seedPrivateEnquiries();
        $this->ensureMinimumCustomerConfirmations(10);
        $this->createRandomRemarks();
        $this->createEnquiryNotifications();
    }

    private function ensureMinimumCustomerConfirmations(int $minimum): void
    {
        $currentCount = CustomerConfirmation::query()->count();

        if ($currentCount >= $minimum) {
            return;
        }

        $remaining = $minimum - $currentCount;

        $candidateEnquiries = Enquiry::query()
            ->where('status', EnquiryStatus::Confirmed->value)
            ->whereDoesntHave('customerConfirmation')
            ->orderBy('id')
            ->limit($remaining)
            ->get();

        foreach ($candidateEnquiries as $enquiry) {
            $this->createCustomerForConfirmedEnquiry($enquiry);
        }
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

        $openPackages = Package::query()->where('status', 'open')->inRandomOrder()->get();

        if ($openPackages->isEmpty()) {
            $this->command->warn('No open packages found for general enquiry seeding.');

            return;
        }

        $generalEnquiries = [];

        for ($index = 0; $index < self::GENERAL_ENQUIRY_COUNT; $index++) {
            $status = $this->resolveEnquiryStatus($index, self::GENERAL_ENQUIRY_COUNT);

            $selectedPackage = $openPackages->random();
            $travelDate = now()->addDays(fake()->numberBetween(10, 120));

            $generalEnquiries[] = [
                'name' => fake()->name(),
                'contact_number' => fake()->phoneNumber(),
                'email' => fake()->unique()->safeEmail(),
                'package_name' => $selectedPackage->name,
                'package_room_type' => fake()->randomElement(['single', 'double', 'triple', 'quad']),
                'preferred_destinations' => collect(fake()->randomElements([
                    'Makkah',
                    'Madinah',
                    'Taif',
                    'Jeddah',
                ], 2))->implode(', '),
                'preferred_travelling_date' => $travelDate->toDateString(),
                'no_of_adults' => fake()->numberBetween(1, 4),
                'no_of_children' => fake()->numberBetween(0, 2),
                'requires_mobility_assistance' => fake()->boolean(20)
                    ? fake()->randomElement([
                        'Wheelchair support required',
                        'Elderly assistance needed',
                        'Prefer lift-accessible rooms',
                    ])
                    : null,
                'status' => $status,
            ];
        }

        foreach ($generalEnquiries as $data) {
            $status = $data['status'];
            unset($data['status']);

            $selectedPackage = $this->resolveSeededPackageSelection(
                $data['package_name'] ?? null,
                $data['package_room_type'] ?? null,
            );

            unset($data['package_name'], $data['package_room_type']);

            $parentEnquiry = Enquiry::create([
                'type' => 'general',
                'status' => $status->value,
                'name' => $data['name'],
                'contact_number' => $data['contact_number'],
                'email' => $data['email'],
                'package_id' => $selectedPackage['package_id'],
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

        $privateEnquiries = [];

        for ($index = 0; $index < self::PRIVATE_ENQUIRY_COUNT; $index++) {
            $status = $this->resolveEnquiryStatus($index, self::PRIVATE_ENQUIRY_COUNT);

            $departureDate = now()->addDays(fake()->numberBetween(20, 140));
            $returnDate = (clone $departureDate)->addDays(fake()->numberBetween(8, 14));

            $privateEnquiries[] = [
                'name' => fake()->name(),
                'contact_number' => fake()->phoneNumber(),
                'email' => fake()->unique()->safeEmail(),
                'passport_expiry_date' => now()->addYears(fake()->numberBetween(2, 8))->toDateString(),
                'departure_date' => $departureDate->toDateString(),
                'return_date' => $returnDate->toDateString(),
                'no_of_pax' => fake()->numberBetween(2, 6),
                'no_of_children' => fake()->numberBetween(0, 2),
                'airline' => fake()->randomElement(self::PRIVATE_AIRLINES),
                'class' => fake()->randomElement(self::PRIVATE_FLIGHT_CLASSES),
                'require_mutawif' => fake()->boolean(),
                'require_umrah_course' => fake()->boolean(),
                'require_umrah_official' => fake()->boolean(),
                'makkah_or_madinah_first' => fake()->randomElement(['Makkah', 'Madinah']),
                'no_of_nights_makkah' => fake()->randomElement(self::PRIVATE_NIGHTS_MAKKAH),
                'hotel_makkah' => fake()->randomElement(self::PRIVATE_HOTELS_MAKKAH),
                'meals_makkah' => fake()->randomElement(self::PRIVATE_MEAL_OPTIONS),
                'no_of_nights_madinah' => fake()->randomElement(self::PRIVATE_NIGHTS_MADINAH),
                'hotel_madinah' => fake()->randomElement(self::PRIVATE_HOTELS_MADINAH),
                'meals_madinah' => fake()->randomElement(self::PRIVATE_MEAL_OPTIONS),
                'land_transfer' => fake()->randomElement(self::PRIVATE_LAND_TRANSFERS),
                'add_on_speed_train' => fake()->boolean(),
                'require_meet_greet' => fake()->boolean(),
                'require_mutawiffah_ustazah_rawdah' => fake()->boolean(),
                'madinah_tour_with_mutawif' => fake()->boolean(),
                'makkah_tour_with_mutawif' => fake()->boolean(),
                'has_chronic_disease' => fake()->boolean(20),
                'chronic_disease_details' => fake()->boolean(20) ? fake()->sentence(4) : null,
                'need_wheelchair' => fake()->randomElement(['Yes', 'No']),
                'other_remarks' => fake()->boolean(35) ? fake()->sentence() : null,
                'status' => $status,
            ];
        }

        foreach ($privateEnquiries as $data) {
            $status = $data['status'];
            unset($data['status']);

            $parentEnquiry = Enquiry::create([
                'type' => 'private',
                'status' => $status->value,
                'name' => $data['name'],
                'contact_number' => $data['contact_number'],
                'email' => $data['email'],
                'package_id' => null,
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

        $biodata = $this->buildBiodata();

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
        $existingGroup = CustomerConfirmation::where('enquiry_id', $enquiry->id)->first();

        if (! $existingGroup) {
            $selectedPackage = $this->getPackageSelectionForConfirmedEnquiry($enquiry);
            $this->confirmedCustomerGroupCounter++;

            $withoutQuotation = $this->confirmedCustomerGroupCounter % 2 === 0;

            $statusScenario = ((int) $enquiry->id) % 3;

            $resolveLeaderStatus = $withoutQuotation
                ? 'draft'
                : match ($statusScenario) {
                    0 => 'confirmed',
                    1 => 'partially_paid',
                    default => 'pending_payment',
                };

            $resolveMemberStatus = $withoutQuotation
                ? 'draft'
                : match ($statusScenario) {
                    0 => 'confirmed',
                    default => 'pending_payment',
                };

            // Create customer confirmation with the customer as leader
            $group = CustomerConfirmation::create([
                'enquiry_id' => $enquiry->id,
                'created_by' => User::role('admin')->first()?->id,
                'package_id' => $selectedPackage['package_id'],
                'package_room_type' => $selectedPackage['package_room_type'],
                'package_category' => $selectedPackage['package_category'],
                'date_of_application' => now()->subDays(rand(1, 14)),
            ]);

            CustomerConfirmationMember::create([
                'customer_confirmation_id' => $group->id,
                'customer_id' => $customer->id,
                'is_leader' => true,
                'status' => $resolveLeaderStatus,
                'sharing_plan' => $selectedPackage['package_room_type'] ?? 'double',
            ]);

            $additionalMembers = $this->buildAdditionalMembers(fake()->numberBetween(1, 3));
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
                    $memberBiodata = $this->buildBiodata();
                    $memberCustomer = Customer::create(array_merge([
                        'user_id' => $memberUser->id,
                        'branch_id' => 1,
                        'handled_by' => User::role(['admin', 'sales'])->first()?->id,
                        'is_active' => true,
                    ], $memberBiodata));
                }

                CustomerConfirmationMember::create([
                    'customer_confirmation_id' => $group->id,
                    'customer_id' => $memberCustomer->id,
                    'is_leader' => false,
                    'status' => $resolveMemberStatus,
                    'sharing_plan' => $selectedPackage['package_room_type'] ?? 'double',
                ]);
            }
        }
    }

    /**
     * Determine package selection for a confirmed enquiry based on workflow.
     * General enquiries carry selected package before confirmation.
     * Private enquiries select package after confirmation.
     *
     * @return array{package_id: int|null, package_room_type: string, package_category: string}
     */
    private function getPackageSelectionForConfirmedEnquiry(Enquiry $enquiry): array
    {
        if ($enquiry->type === 'private') {
            $exclusivePackage = $enquiry->package_id
                ? Package::find($enquiry->package_id)
                : $this->createExclusivePackageForPrivateEnquiry($enquiry);

            if ($exclusivePackage && ! $enquiry->package_id) {
                $enquiry->update([
                    'package_id' => $exclusivePackage->id,
                ]);
            }

            return [
                'package_id' => $exclusivePackage?->id,
                'package_room_type' => 'double',
                'package_category' => 'deluxe_umrah',
            ];
        }

        if ($enquiry->package_id) {
            $package = Package::find($enquiry->package_id);

            return [
                'package_id' => $package?->id,
                'package_room_type' => $this->inferRoomTypeFromPackage($package?->name),
                'package_category' => $this->inferCategoryFromPackage($package?->name),
            ];
        }

        $fallbackPackage = Package::query()->where('status', 'open')->orderBy('id')->first();

        if ($fallbackPackage) {
            $enquiry->update([
                'package_id' => $fallbackPackage->id,
            ]);
        }

        return [
            'package_id' => $fallbackPackage?->id,
            'package_room_type' => $this->inferRoomTypeFromPackage($fallbackPackage?->name),
            'package_category' => $this->inferCategoryFromPackage($fallbackPackage?->name),
        ];
    }

    private function createExclusivePackageForPrivateEnquiry(Enquiry $enquiry): Package
    {
        $basePrice = fake()->randomFloat(2, 3000, 7000);
        $departureDate = now()->addDays(fake()->numberBetween(20, 180));
        $returnDate = (clone $departureDate)->addDays(fake()->numberBetween(8, 14));
        $airline = fake()->randomElement(self::PRIVATE_AIRLINES);
        $pnr = strtoupper(fake()->bothify('??###'));

        $package = Package::create([
            'package_number' => NumberGenerator::generate('package'),
            'name' => 'Exclusive Private '.$enquiry->name.' '.strtoupper(fake()->lexify('??')),
            'status' => 'open',
            'price_single' => $basePrice,
            'price_double' => max($basePrice - 700, 1000),
            'price_triple' => max($basePrice - 1200, 900),
            'price_quad' => max($basePrice - 1600, 800),
            'child_with_bed_price' => max($basePrice - 1800, 700),
            'child_no_bed_price' => max($basePrice - 2200, 600),
            'infant_price' => 450,
            'departure_date' => $departureDate->toDateString(),
            'return_date' => $returnDate->toDateString(),
            'total_seats' => fake()->numberBetween(8, 20),
            'seats_left' => fake()->numberBetween(2, 8),
            'visa_type' => 'Umrah Visa',
            'vehicle_type' => fake()->randomElement(self::PRIVATE_LAND_TRANSFERS),
            'ticket_type' => fake()->boolean() ? 'speed_train' : null,
            'included' => "Flight Tickets\nHotel Accommodation\nGround Transport",
            'not_included' => "Personal Expenses\nTips & Gratuities",
            'offer' => null,
            'remarks' => 'Exclusive package generated for private enquiry workflow',
        ]);

        $package->accommodations()->createMany([
            [
                'location' => 'Makkah',
                'hotel_name' => fake()->randomElement(self::PRIVATE_HOTELS_MAKKAH),
                'type_of_meal' => fake()->randomElement(self::PRIVATE_MEAL_OPTIONS),
                'check_in' => $departureDate->copy()->addDay()->toDateString(),
                'check_out' => $departureDate->copy()->addDays(6)->toDateString(),
            ],
            [
                'location' => 'Madinah',
                'hotel_name' => fake()->randomElement(self::PRIVATE_HOTELS_MADINAH),
                'type_of_meal' => fake()->randomElement(self::PRIVATE_MEAL_OPTIONS),
                'check_in' => $departureDate->copy()->addDays(6)->toDateString(),
                'check_out' => $returnDate->copy()->subDay()->toDateString(),
            ],
        ]);

        $package->flights()->createMany([
            [
                'from' => 'KUL',
                'to' => 'JED',
                'description' => 'Outbound',
                'airline' => $airline,
                'pnr' => $pnr,
                'departure_datetime' => $departureDate->copy()->setTime(9, 0)->toDateTimeString(),
                'arrival_datetime' => $departureDate->copy()->setTime(15, 0)->toDateTimeString(),
                'sort_order' => 1,
            ],
            [
                'from' => 'MED',
                'to' => 'KUL',
                'description' => 'Return',
                'airline' => $airline,
                'pnr' => $pnr,
                'departure_datetime' => $returnDate->copy()->setTime(10, 30)->toDateTimeString(),
                'arrival_datetime' => $returnDate->copy()->setTime(22, 30)->toDateTimeString(),
                'sort_order' => 2,
            ],
        ]);

        return $package;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBiodata(): array
    {
        $gender = fake()->randomElement(['male', 'female']);
        $dob = fake()->dateTimeBetween('-65 years', '-12 years');

        return [
            'nric_number' => strtoupper(fake()->bothify('S#######?')),
            'address' => fake()->address(),
            'nationality' => fake()->country(),
            'passport_number' => strtoupper(fake()->bothify('??#######')),
            'passport_issue_date' => fake()->dateTimeBetween('-8 years', '-2 years')->format('Y-m-d'),
            'passport_expiry_date' => fake()->dateTimeBetween('+2 years', '+10 years')->format('Y-m-d'),
            'passport_place_of_issue' => fake()->city(),
            'gender' => $gender,
            'marital_status' => fake()->randomElement(['single', 'married', 'divorced', 'widowed']),
            'date_of_birth' => $dob->format('Y-m-d'),
            'place_of_birth' => fake()->city(),
            'first_time_umrah' => fake()->boolean(),
            'has_chronic_disease' => fake()->boolean(18),
            'chronic_disease_details' => fake()->boolean(18) ? fake()->sentence() : null,
        ];
    }

    /**
     * @return array<int, array{name: string, email: string, contact: string}>
     */
    private function buildAdditionalMembers(int $count): array
    {
        $members = [];

        for ($index = 0; $index < $count; $index++) {
            $members[] = [
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'contact' => fake()->phoneNumber(),
            ];
        }

        return $members;
    }

    /**
     * Resolve a package selection by seed data package name.
     *
     * @return array{package_id: int|null, package_room_type: string, package_category: string}
     */
    private function resolveSeededPackageSelection(?string $packageName, ?string $packageRoomType = null): array
    {
        $package = Package::query()
            ->when($packageName, fn ($query) => $query->where('name', $packageName))
            ->orderBy('id')
            ->first();

        return [
            'package_id' => $package?->id,
            'package_room_type' => $packageRoomType ?? $this->inferRoomTypeFromPackage($package?->name),
            'package_category' => $this->inferCategoryFromPackage($package?->name),
        ];
    }

    private function inferRoomTypeFromPackage(?string $packageName): string
    {
        if (! is_string($packageName)) {
            return 'double';
        }

        $normalized = strtolower($packageName);

        return str_contains($normalized, 'family') ? 'quad' : 'double';
    }

    private function inferCategoryFromPackage(?string $packageName): string
    {
        if (! is_string($packageName)) {
            return 'classic_umrah';
        }

        $normalized = strtolower($packageName);

        if (str_contains($normalized, 'premium') || str_contains($normalized, 'ramadan')) {
            return 'deluxe_umrah';
        }

        return 'classic_umrah';
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

    private function resolveEnquiryStatus(int $index, int $total): EnquiryStatus
    {
        $confirmedCount = (int) floor($total / 2);

        if ($index < $confirmedCount) {
            return EnquiryStatus::Confirmed;
        }

        $offset = ($index - $confirmedCount) % count(self::NON_CONFIRMED_STATUSES);

        return self::NON_CONFIRMED_STATUSES[$offset];
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
            ->with('customerConfirmation.members.customer.user')
            ->get();

        foreach ($confirmedEnquiries as $enquiry) {
            $customerName = $enquiry->customerConfirmation?->members?->first()?->customer?->user?->name ?? $enquiry->name;

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
