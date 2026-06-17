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
        if (! Schema::hasTable('private_enquiries') || ! Schema::hasColumn('private_enquiries', 'need_wheelchair')) {
            return;
        }

        Schema::table('private_enquiries', function (Blueprint $table) {
            $table->boolean('need_wheelchair_bool')->default(false);
        });

        DB::table('private_enquiries')
            ->select(['id', 'need_wheelchair'])
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $normalized = strtolower(trim((string) ($row->need_wheelchair ?? '')));
                    $booleanValue = in_array($normalized, ['yes', 'true', '1', 'y'], true);

                    DB::table('private_enquiries')
                        ->where('id', $row->id)
                        ->update(['need_wheelchair_bool' => $booleanValue]);
                }
            });

        Schema::table('private_enquiries', function (Blueprint $table) {
            $table->dropColumn('need_wheelchair');
        });

        Schema::table('private_enquiries', function (Blueprint $table) {
            $table->renameColumn('need_wheelchair_bool', 'need_wheelchair');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('private_enquiries') || ! Schema::hasColumn('private_enquiries', 'need_wheelchair')) {
            return;
        }

        Schema::table('private_enquiries', function (Blueprint $table) {
            $table->string('need_wheelchair_text')->nullable();
        });

        DB::table('private_enquiries')
            ->select(['id', 'need_wheelchair'])
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $value = (bool) ($row->need_wheelchair ?? false) ? 'Yes' : 'No';

                    DB::table('private_enquiries')
                        ->where('id', $row->id)
                        ->update(['need_wheelchair_text' => $value]);
                }
            });

        Schema::table('private_enquiries', function (Blueprint $table) {
            $table->dropColumn('need_wheelchair');
        });

        Schema::table('private_enquiries', function (Blueprint $table) {
            $table->renameColumn('need_wheelchair_text', 'need_wheelchair');
        });
    }
};
