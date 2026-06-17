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
        Schema::create('package_rawdah_tasreehs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->date('date')->nullable();
            $table->unsignedInteger('women_passengers')->nullable();
            $table->string('women_time')->nullable();
            $table->unsignedInteger('men_passengers')->nullable();
            $table->string('men_time')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_rawdah_tasreehs');
    }
};
