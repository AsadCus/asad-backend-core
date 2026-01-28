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
        Schema::create('general_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('mobile');
            $table->string('email');
            $table->text('preferred_destinations');
            $table->date('preferred_travelling_date');
            $table->unsignedInteger('no_of_adults')->default(0);
            $table->unsignedInteger('no_of_children')->default(0);
            $table->text('requires_mobility_assistance')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_enquiries');
    }
};
