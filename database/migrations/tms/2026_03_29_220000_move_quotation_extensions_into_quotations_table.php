<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'extensions')) {
                $table->json('extensions')->nullable()->after('description');
            }
        });

        if (Schema::hasTable('quotation_extensions')) {
            $rows = DB::table('quotation_extensions')
                ->orderBy('quotation_id')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->groupBy('quotation_id');

            foreach ($rows as $quotationId => $extensions) {
                $payload = collect($extensions)
                    ->map(function ($extension, int $index): array {
                        return [
                            'id' => null,
                            'quotation_extension_master_id' => $extension->quotation_extension_master_id ?? null,
                            'name' => $extension->name,
                            'type' => $extension->type,
                            'calculation_mode' => $extension->calculation_mode ?? 'fixed',
                            'calculation_value' => $extension->calculation_value ?? 0,
                            'amount' => $extension->amount ?? 0,
                            'sort_order' => $extension->sort_order ?? ($index + 1),
                        ];
                    })
                    ->values()
                    ->all();

                DB::table('quotations')
                    ->where('id', (int) $quotationId)
                    ->update(['extensions' => json_encode($payload)]);
            }

            Schema::dropIfExists('quotation_extensions');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('quotation_extensions')) {
            Schema::create('quotation_extensions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
                $table->foreignId('quotation_extension_master_id')
                    ->nullable()
                    ->constrained('quotation_extension_masters')
                    ->nullOnDelete();
                $table->string('name');
                $table->string('type')->default('discount');
                $table->string('calculation_mode')->default('fixed');
                $table->decimal('calculation_value', 12, 4)->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->unsignedInteger('sort_order')->default(1);
                $table->timestamps();
            });
        }

        if (Schema::hasColumn('quotations', 'extensions')) {
            $quotations = DB::table('quotations')
                ->select('id', 'extensions')
                ->whereNotNull('extensions')
                ->get();

            foreach ($quotations as $quotation) {
                $extensions = json_decode((string) $quotation->extensions, true);

                if (! is_array($extensions)) {
                    continue;
                }

                foreach (array_values($extensions) as $index => $extension) {
                    if (! is_array($extension)) {
                        continue;
                    }

                    DB::table('quotation_extensions')->insert([
                        'quotation_id' => (int) $quotation->id,
                        'quotation_extension_master_id' => $extension['quotation_extension_master_id'] ?? null,
                        'name' => $extension['name'] ?? 'Extension',
                        'type' => $extension['type'] ?? 'discount',
                        'calculation_mode' => $extension['calculation_mode'] ?? 'fixed',
                        'calculation_value' => $extension['calculation_value'] ?? 0,
                        'amount' => $extension['amount'] ?? 0,
                        'sort_order' => $extension['sort_order'] ?? ($index + 1),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            Schema::table('quotations', function (Blueprint $table) {
                $table->dropColumn('extensions');
            });
        }
    }
};
