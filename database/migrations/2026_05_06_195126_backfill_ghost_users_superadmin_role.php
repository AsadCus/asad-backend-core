<?php

use App\Models\GhostUser;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $superadminRole = Role::findOrCreate('superadmin', 'web');

        $ghostEmails = ['asad@example.com', 'kherman@example.com'];

        User::query()
            ->whereIn('email', $ghostEmails)
            ->get()
            ->each(function (User $user) use ($superadminRole): void {
                if (! $user->hasRole($superadminRole->name)) {
                    $user->syncRoles([$superadminRole]);
                }

                GhostUser::firstOrCreate(['user_id' => (int) $user->id]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
