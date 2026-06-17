<?php

namespace Database\Seeders;

use App\Models\GhostUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class GhostAdministratorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates the initial ghost administrator account: asad@example.com / password,
     * assigned the `administrator` role with a ghost_users row. Idempotent.
     */
    public function run(): void
    {
        $email = 'asad@example.com';

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Asad',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $administrator = Role::findByName('administrator');

        if ($administrator && ! $user->hasRole($administrator)) {
            $user->assignRole($administrator);
        }

        GhostUser::firstOrCreate(['user_id' => $user->id]);

        $this->command->info("Ghost administrator seeded: {$email} / password");
    }
}
