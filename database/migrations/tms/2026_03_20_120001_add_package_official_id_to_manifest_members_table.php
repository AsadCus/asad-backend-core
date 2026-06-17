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
            if (! Schema::hasColumn('manifest_members', 'package_official_id')) {
                $table->foreignId('package_official_id')
                    ->nullable()
                    ->after('customer_confirmation_member_id')
                    ->constrained('package_officials')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manifest_members', function (Blueprint $table) {
            if (Schema::hasColumn('manifest_members', 'package_official_id')) {
                $table->dropConstrainedForeignId('package_official_id');
            }
        });
    }
};
