<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Sales;
use App\Models\Branch;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Notification;
use App\Models\UserNotification;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ages = ['18-25', '26-35', '36-45', '45+'];
        $experiences = ['0-1', '2-3', '4-5', '5+'];
        $availableRoles = ['admin', 'sales', 'supplier', 'customer'];

        $fixedUsersData = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'contact' => '+6500000000',
            ],
            [
                'name' => 'Sales User',
                'email' => 'sales@example.com',
                'password' => Hash::make('password'),
                'role' => 'sales',
                'contact' => '+6500000000',
                'branch_id' => 'Yishun',
            ],
            [
                'name' => 'Supplier User',
                'email' => 'supplier@example.com',
                'password' => Hash::make('password'),
                'role' => 'supplier',
                'contact' => '+6400000000000',
                'supplier_name' => 'Supplier',
                'address' => 'Address',
            ],
            [
                'name' => 'Customer User',
                'email' => 'customer@example.com',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'contact' => '+6500000000',
                'address' => 'Address',
                'age_preferences' => ['18-25', '26-35'],
                'country_preferences' => ['Indonesia', 'Singapore'],
                'experience_preferences' => ['0-1', '2-3'],
                'branch_id' => 'Yishun',
                'handled_by' => 'sales@example.com',
                'last_login' => now()->subDays(rand(0, 7)),
            ],
            [
                'name' => 'Asad',
                'email' => 'asad@example.com',
                'password' => Hash::make('NumberLock(90)'),
                'role' => 'admin',
                'contact' => '+6400000000000',
            ],
        ];

        foreach ($fixedUsersData as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'contact' => $userData['contact'],
                    'password' => $userData['password'],
                ]
            );

            $user->assignRole(Role::findByName($userData['role']));

            $branchId = null;
            $handledBy = null;

            if (!empty($userData['branch_id'])) {
                $branchId = Branch::where('name', $userData['branch_id'])->value('id');
            }

            if (!empty($userData['handled_by'])) {
                $handler = User::where('email', $userData['handled_by'])->first();
                $handledBy = $handler?->id;
            }

            match ($userData['role']) {
                'sales' => Sales::firstOrCreate([
                    'user_id' => $user->id,
                ], [
                    'branch_id' => $branchId,
                ]),
                'supplier' => Supplier::firstOrCreate([
                    'user_id' => $user->id,
                ], [
                    'name' => $userData['supplier_name'] ?? null,
                    'address' => $userData['address'] ?? null,
                ]),
                'customer' => Customer::firstOrCreate([
                    'user_id' => $user->id,
                ], [
                    'nric_number' => strtoupper(fake()->bothify('??######')),
                    'address' => $userData['address'] ?? null,
                    'age_preferences' => json_encode($userData['age_preferences'] ?? []),
                    'country_preferences' => json_encode(
                        collect($userData['country_preferences'] ?? [])
                            ->map(fn($c) => Country::where('name', $c)->value('name'))
                            ->filter()
                            ->values()
                            ->toArray()
                    ),
                    'experience_preferences' => json_encode($userData['experience_preferences'] ?? []),
                    'branch_id' => $branchId,
                    'handled_by' => $handledBy,
                    'last_login' => $userData['last_login'] ?? now(),
                ]),
                default => null,
            };
        }

        $targetCount = 20;
        $existingCount = User::count();
        $remainingToCreate = $targetCount - $existingCount;

        if ($remainingToCreate > 0) {
            echo "Creating {$remainingToCreate} additional random users...\n";

            $branches = Branch::all();
            $countries = Country::all();

            $users = User::factory($remainingToCreate)->create();

            $users->each(function ($user) use ($availableRoles) {
                $roleName = $availableRoles[array_rand($availableRoles)];
                $user->assignRole(Role::findByName($roleName));
                $user->role_name = $roleName;
            });

            $users->each(function ($user) use ($branches) {
                $roleName = $user->role_name ?? $user->getRoleNames()->first();
                $branch = $branches->random();

                match ($roleName) {
                    'sales' => Sales::create([
                        'user_id' => $user->id,
                        'branch_id' => $branch->id,
                    ]),
                    'supplier' => Supplier::create([
                        'user_id' => $user->id,
                        'name' => fake()->company(),
                        'address' => fake()->address(),
                    ]),
                    default => null,
                };
            });

            $userThatCanHandleCustomer = User::role(['sales', 'admin'])->get();

            $users->each(function ($user) use (
                $branches,
                $countries,
                $ages,
                $experiences,
                $userThatCanHandleCustomer,
            ) {
                $roleName = $user->role_name ?? $user->getRoleNames()->first();

                if ($roleName !== 'customer') {
                    return;
                }

                $branch = $branches->random();
                $handledBy = null;

                if (rand(0, 1) === 1 && $userThatCanHandleCustomer->isNotEmpty()) {
                    $handler = $userThatCanHandleCustomer->random();
                    $handledBy = $handler->id;
                }

                Customer::create([
                    'user_id' => $user->id,
                    'nric_number' => strtoupper(fake()->bothify('??######')),
                    'address' => fake()->address(),
                    'age_preferences' => json_encode(fake()->randomElements($ages, rand(1, 2))),
                    'country_preferences' => json_encode(
                        $countries->random(rand(1, 2))->pluck('name')->toArray()
                    ),
                    'experience_preferences' => json_encode(fake()->randomElements($experiences, rand(1, 2))),
                    'branch_id' => $branch->id,
                    'handled_by' => $handledBy,
                    'last_login' => now()->subDays(rand(0, 7)),
                ]);
            });

            $this->command->info('Random users and related models created successfully!');
        }


        // notifications
        $notifications = collect([
            [
                'title' => 'Welcome to the System',
                'message' => 'We’re excited to have you onboard. Explore your dashboard for updates.',
                'link' => '/dashboard',
                'type' => 'info',
            ],
            [
                'title' => 'New Feature Released',
                'message' => 'Check out the latest improvements and new features added to your workspace.',
                'link' => '/maid',
                'type' => 'info',
            ],
            [
                'title' => 'Monthly Report Available',
                'message' => 'Your latest performance report is now ready. Visit your account to view details.',
                'link' => '/settings/profile',
                'type' => 'warning',
            ],
        ])->map(fn($n) => Notification::firstOrCreate(['title' => $n['title']], $n));

        User::all()->each(function ($user) use ($notifications) {
            $selected = $notifications->random(2);

            foreach ($selected as $notification) {
                UserNotification::firstOrCreate([
                    'user_id' => $user->id,
                    'notification_id' => $notification->id,
                    'is_read' => false,
                ]);
            }
        });

        $this->command->info('Shared notifications created and assigned to users!');
    }
}
