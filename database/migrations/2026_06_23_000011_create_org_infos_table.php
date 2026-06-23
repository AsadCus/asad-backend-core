<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-org-unit company information — free title + content entries, many per unit.
 * Displayed hierarchically (holding → … → active unit) on the Informasi Perusahaan page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_infos');
    }
};
