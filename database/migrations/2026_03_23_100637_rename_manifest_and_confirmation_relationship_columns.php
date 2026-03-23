<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('manifest_members', function (Blueprint $table) {
            $table->renameColumn('role', 'relationship');
        });

        Schema::table('customer_confirmation_members', function (Blueprint $table) {
            $table->renameColumn('role', 'relationship');
        });

        Schema::table('manifest_rooms', function (Blueprint $table) {
            $table->renameColumn('relationship', 'group_relationship');
        });

        Schema::table('manifest_sharing_groups', function (Blueprint $table) {
            $table->renameColumn('relation', 'group_relationship');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manifest_members', function (Blueprint $table) {
            $table->renameColumn('relationship', 'role');
        });

        Schema::table('customer_confirmation_members', function (Blueprint $table) {
            $table->renameColumn('relationship', 'role');
        });

        Schema::table('manifest_rooms', function (Blueprint $table) {
            $table->renameColumn('group_relationship', 'relationship');
        });

        Schema::table('manifest_sharing_groups', function (Blueprint $table) {
            $table->renameColumn('group_relationship', 'relation');
        });
    }
};
