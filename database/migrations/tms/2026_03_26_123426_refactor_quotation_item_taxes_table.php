<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_item_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_item_id')
                ->constrained('quotation_items')
                ->cascadeOnDelete();
            $table->foreignId('quotation_extension_master_id')
                ->nullable()
                ->constrained('quotation_extension_masters')
                ->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('calculation_mode', 20)->nullable();
            $table->decimal('calculation_value', 12, 4)->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });

        if (Schema::hasColumn('quotation_items', 'tax_extension_master_id')) {
            Schema::table('quotation_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tax_extension_master_id');
                $table->dropColumn([
                    'tax_name',
                    'tax_calculation_mode',
                    'tax_calculation_value',
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_item_taxes');

        if (! Schema::hasColumn('quotation_items', 'tax_extension_master_id')) {
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
    }
};
