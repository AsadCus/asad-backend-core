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
        Schema::create('manifests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number')->unique();
            $table->string('company_address')->nullable();
            $table->string('company_phone')->nullable();
            $table->date('departure_date');
            $table->date('return_date');
            $table->string('duration')->nullable();
            $table->string('makkah_hotel')->nullable();
            $table->date('makkah_check_in')->nullable();
            $table->date('makkah_check_out')->nullable();
            $table->string('madinah_hotel')->nullable();
            $table->date('madinah_check_in')->nullable();
            $table->date('madinah_check_out')->nullable();
            $table->json('flight_details')->nullable();
            $table->text('notes')->nullable();
            $table->string('first_meal')->nullable();
            $table->string('last_meal')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('manifest_travelers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sn');
            $table->string('name_as_per_passport');
            $table->string('relationship')->nullable();
            $table->string('passport_no')->nullable();
            $table->string('room_no')->nullable();
            $table->string('room_type')->nullable();
            $table->string('bed_type')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->unsignedInteger('no_of_beds_checked')->nullable();
            $table->string('meal')->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->decimal('total_paid', 10, 2)->default(0);
            $table->decimal('outstanding_amount', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('manifest_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained()->cascadeOnDelete();
            $table->string('location');
            $table->string('room_number');
            $table->string('room_type')->nullable();
            $table->string('bed_type')->nullable();
            $table->unsignedInteger('capacity')->default(1);
            $table->timestamps();
        });

        Schema::create('manifest_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained()->cascadeOnDelete();
            $table->string('traveler_name');
            $table->string('description');
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('outstanding_amount', 10, 2)->default(0);
            $table->date('payment_date')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifest_payments');
        Schema::dropIfExists('manifest_rooms');
        Schema::dropIfExists('manifest_travelers');
        Schema::dropIfExists('manifests');
    }
};
