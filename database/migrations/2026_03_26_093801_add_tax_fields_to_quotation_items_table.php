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
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->foreignId('tax_extension_master_id')
                ->nullable()
                ->after('rate')
                ->constrained('quotation_extension_masters')
                ->nullOnDelete();
            $table->string('tax_name')->nullable()->after('tax_extension_master_id');
            $table->string('tax_calculation_mode', 20)->nullable()->after('tax_name');
            $table->decimal('tax_calculation_value', 12, 4)->nullable()->after('tax_calculation_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_extension_master_id');
            $table->dropColumn([
                'tax_name',
                'tax_calculation_mode',
                'tax_calculation_value',
            ]);
        });
    }
};
