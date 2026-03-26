<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\Sales;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createUsers();
        $this->createNotifications();
    }

    /**
     * Create admin and sales users.
     */
    private function createUsers(): void
    {
        // Admin Users
        $adminUsers = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'contact' => '+6500000000',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Asad',
                'email' => 'asad@example.com',
                'contact' => '+6400000000000',
                'password' => Hash::make('password'),
            ],
        ];

        foreach ($adminUsers as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'contact' => $userData['contact'],
                'password' => $userData['password'],
                'email_verified_at' => now(),
            ]);
            $user->assignRole(Role::findByName('admin'));
        }

        // Sales Users
        $salesUsers = [
            [
                'name' => 'Sales User',
                'email' => 'sales@example.com',
                'contact' => '+6400000000000',
            ],
            [
                'name' => 'Sales TMS',
                'email' => 'sales@tms.com',
                'contact' => '+6512345678',
            ],
        ];

        foreach ($salesUsers as $salesData) {
            $salesUser = User::create([
                'name' => $salesData['name'],
                'email' => $salesData['email'],
                'contact' => $salesData['contact'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
            $salesUser->assignRole(Role::findByName('sales'));

            Sales::create([
                'user_id' => $salesUser->id,
                'branch_id' => 1,
            ]);
        }

        $this->command->info('Users created successfully!');
    }

    /**
     * Create notifications and assign to admin/sales users.
     */
    private function createNotifications(): void
    {
        $notifications = collect([
            [
                'title' => 'Welcome to the System',
                'message' => 'We are excited to have you onboard. Explore your dashboard for updates.',
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
        ])->map(fn (array $n) => Notification::create($n));

        $adminAndSalesUsers = User::role(['admin', 'sales'])->get();

        $adminAndSalesUsers->each(function (User $user) use ($notifications) {
            foreach ($notifications as $notification) {
                UserNotification::create([
                    'user_id' => $user->id,
                    'notification_id' => $notification->id,
                    'is_read' => false,
                ]);
            }
        });

        $this->command->info('Notifications created and assigned to admin/sales users!');
    }
}
