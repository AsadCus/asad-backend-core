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
        Schema::create('package_flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('from')->nullable();
            $table->string('to')->nullable();
            $table->string('description')->nullable();
            $table->string('airline')->nullable();
            $table->string('pnr')->nullable();
            $table->dateTime('departure_datetime')->nullable();
            $table->dateTime('arrival_datetime')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_flights');
    }
};
