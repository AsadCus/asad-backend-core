<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'country_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'country_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('country_id')
                ->nullable()
                ->after('contact')
                ->constrained('countries')
                ->nullOnDelete();
        });
    }
};
