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
        Schema::create('private_enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->nullable()->constrained('enquiries')->cascadeOnDelete();
            $table->date('passport_expiry_date');
            $table->date('departure_date');
            $table->date('return_date');
            $table->unsignedInteger('no_of_pax');
            $table->unsignedInteger('no_of_children');
            $table->string('airline');
            $table->string('class');
            $table->boolean('require_mutawif');
            $table->boolean('require_umrah_course');
            $table->boolean('require_umrah_official');
            $table->string('makkah_or_madinah_first');
            $table->string('no_of_nights_makkah');
            $table->string('hotel_makkah');
            $table->string('meals_makkah');
            $table->string('no_of_nights_madinah');
            $table->string('hotel_madinah');
            $table->string('meals_madinah');
            $table->string('land_transfer');
            $table->boolean('add_on_speed_train');
            $table->boolean('require_meet_greet');
            $table->boolean('require_mutawiffah_ustazah_rawdah');
            $table->boolean('madinah_tour_with_mutawif');
            $table->boolean('makkah_tour_with_mutawif');
            $table->boolean('has_chronic_disease');
            $table->text('chronic_disease_details')->nullable();
            $table->string('need_wheelchair');
            $table->text('other_remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('private_enquiries');
    }
};
