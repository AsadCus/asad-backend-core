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
        Schema::table('quotation_extensions', function (Blueprint $table) {
            $table->foreignId('quotation_extension_master_id')
                ->nullable()
                ->after('quotation_id')
                ->constrained('quotation_extension_masters')
                ->nullOnDelete();
            $table->string('calculation_mode')
                ->default('fixed')
                ->after('type');
            $table->decimal('calculation_value', 12, 4)
                ->nullable()
                ->after('calculation_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_extensions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quotation_extension_master_id');
            $table->dropColumn(['calculation_mode', 'calculation_value']);
        });
    }
};
