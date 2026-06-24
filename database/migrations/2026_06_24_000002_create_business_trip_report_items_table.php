<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_trip_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_trip_id')->constrained()->cascadeOnDelete();
            $table->string('category'); // income | expense | settlement | ticket
            $table->date('date');
            $table->string('description');
            $table->string('kategori')->nullable(); // sub-category — expense rows only
            $table->unsignedBigInteger('amount');
            $table->string('attachment_path')->nullable();
            $table->timestamps();

            $table->index(['business_trip_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_trip_report_items');
    }
};
