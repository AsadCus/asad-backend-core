<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $asadAdminUserId = DB::table('users')
            ->join('model_has_roles', function ($join) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('users.email', 'asad@example.com')
            ->where('roles.name', 'admin')
            ->value('users.id');

        if ($asadAdminUserId) {
            DB::table('ghost_users')->updateOrInsert([
                'user_id' => (int) $asadAdminUserId,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $asadAdminUserId = DB::table('users')
            ->where('email', 'asad@example.com')
            ->value('id');

        if ($asadAdminUserId) {
            DB::table('ghost_users')
                ->where('user_id', (int) $asadAdminUserId)
                ->delete();
        }
    }
};
