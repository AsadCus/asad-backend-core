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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('group_number')->unique();
            $table->string('name');
            $table->string('status')->default('open'); // open / closed

            // Pricing
            $table->decimal('price_single', 10, 2)->default(0);
            $table->decimal('price_double', 10, 2)->default(0);
            $table->decimal('price_triple', 10, 2)->default(0);
            $table->decimal('price_quad', 10, 2)->default(0);
            $table->decimal('child_with_bed_price', 10, 2)->default(0);
            $table->decimal('child_no_bed_price', 10, 2)->default(0);
            $table->decimal('infant_price', 10, 2)->default(0);

            // Flight Details
            $table->string('airline')->nullable();
            $table->string('pnr')->nullable();
            $table->date('departure_date')->nullable();
            $table->date('arrival_date')->nullable();
            $table->unsignedInteger('total_seats')->default(0);
            $table->unsignedInteger('seats_left')->default(0);

            // Visa
            $table->string('visa_type')->nullable();

            // Vehicle
            $table->string('vehicle_type')->nullable();

            // Train Ticket
            $table->string('ticket_type')->nullable();

            // Package Inclusions
            $table->text('included')->nullable();
            $table->text('not_included')->nullable();

            // Remarks
            $table->text('remarks')->nullable();

            $table->timestamps();
        });

        Schema::create('package_accommodations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('location'); // e.g. Mekkah, Madinah, Taif
            $table->string('hotel_name');
            $table->string('type_of_meal')->nullable();
            $table->date('check_in')->nullable();
            $table->date('check_out')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_accommodations');
        Schema::dropIfExists('packages');
    }
};
