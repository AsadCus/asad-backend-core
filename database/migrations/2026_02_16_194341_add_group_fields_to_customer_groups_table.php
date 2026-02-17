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
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('enquiry_id')->constrained('packages')->nullOnDelete();
            $table->string('package_room_type')->nullable()->after('package_id');
            $table->string('package_category')->nullable()->after('package_room_type');
            $table->date('date_of_application')->nullable()->after('package_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropColumn(['package_id', 'package_room_type', 'package_category', 'date_of_application']);
        });
    }
};
