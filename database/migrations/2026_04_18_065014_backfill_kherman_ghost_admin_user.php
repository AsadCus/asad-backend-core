<?php

use App\Models\Admin;
use App\Models\Country;
use App\Models\GhostUser;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $adminRole = Role::findOrCreate('admin', 'web');

        $kherman = User::firstOrCreate(
            ['email' => 'kherman@example.com'],
            [
                'name' => 'Kherman',
                'contact' => '+6400000000000',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if (! $kherman->hasRole($adminRole->name)) {
            $kherman->assignRole($adminRole);
        }

        $selectedCountryIds = Country::query()
            ->whereIn('name', ['Singapore', 'Malaysia'])
            ->pluck('id')
            ->map(fn (int|string $id): int => (int) $id)
            ->values()
            ->all();

        Admin::updateOrCreate(
            ['user_id' => (int) $kherman->id],
            [
                'branch_id' => null,
                'country_id' => $selectedCountryIds[0] ?? null,
                'branch_ids' => [],
                'country_ids' => $selectedCountryIds,
            ],
        );

        GhostUser::firstOrCreate([
            'user_id' => (int) $kherman->id,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $kherman = User::query()
            ->where('email', 'kherman@example.com')
            ->first();

        if ($kherman === null) {
            return;
        }

        GhostUser::query()
            ->where('user_id', (int) $kherman->id)
            ->delete();

        Admin::query()
            ->where('user_id', (int) $kherman->id)
            ->delete();

        $kherman->forceDelete();
    }
};
