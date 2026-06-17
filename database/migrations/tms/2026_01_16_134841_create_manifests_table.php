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
            $table->string('manifest_number')->unique();
            $table->text('notes')->nullable();
            $table->json('ops_movement_extension')->nullable();
            $table->timestamps();
        });

        Schema::create('manifest_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('manifest_sharing_group_id')->nullable();
            $table->foreignId('customer_confirmation_member_id')->nullable()->constrained('customer_confirmation_members')->nullOnDelete();
            $table->string('relationship')->nullable();
            $table->string('sharing_plan')->nullable();
            $table->string('name')->nullable();
            $table->string('arabic_name')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('nationality')->nullable();
            $table->string('passport_number')->nullable();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('passport_issue_date')->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->string('passport_place_of_issue')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->boolean('first_time_umrah')->nullable();
            $table->boolean('has_chronic_disease')->nullable();
            $table->boolean('is_using_wheelchair')->nullable();
            $table->text('chronic_disease_details')->nullable();
            $table->string('passport_path')->nullable();
            $table->string('photo_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('manifest_sharing_group_id');
        });

        Schema::create('manifest_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('location')->nullable();
            $table->string('group_relationship')->nullable();
            $table->string('room_label')->nullable();
            $table->string('room_number')->nullable();
            $table->string('room_type')->nullable();
            $table->string('bed_type')->nullable();
            $table->unsignedInteger('capacity')->nullable()->default(1);
            $table->string('sharing_plan')->nullable();
            $table->string('status')->default('pending');
            $table->string('meal')->nullable();
            $table->boolean('number_of_beds_checked')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifest_rooms');
        Schema::dropIfExists('manifest_members');
        Schema::dropIfExists('manifests');
    }
};
