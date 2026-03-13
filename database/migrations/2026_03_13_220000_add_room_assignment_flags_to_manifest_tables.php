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
        Schema::table('manifest_rooms', function (Blueprint $table) {
            $table->boolean('number_of_beds_checked')->default(false)->after('meal');
        });

        Schema::table('manifest_room_members', function (Blueprint $table) {
            $table->boolean('is_assigned')->default(true)->after('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manifest_room_members', function (Blueprint $table) {
            $table->dropColumn('is_assigned');
        });

        Schema::table('manifest_rooms', function (Blueprint $table) {
            $table->dropColumn('number_of_beds_checked');
        });
    }
};
