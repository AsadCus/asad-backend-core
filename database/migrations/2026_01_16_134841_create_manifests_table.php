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
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('manifest_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('manifest_sharing_group_id')->nullable();
            $table->foreignId('customer_confirmation_member_id')->nullable()->constrained('customer_confirmation_members')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('manifest_sharing_group_id');
        });

        Schema::create('manifest_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('location')->nullable();
            $table->string('relationship')->nullable();
            $table->string('room_label')->nullable();
            $table->string('room_number')->nullable();
            $table->string('room_type')->nullable();
            $table->string('bed_type')->nullable();
            $table->unsignedInteger('capacity')->nullable()->default(1);
            $table->string('sharing_plan')->nullable();
            $table->string('status')->default('pending');
            $table->string('meal')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('manifest_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manifest_traveler_id')->nullable()->constrained('manifest_members')->nullOnDelete();
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
        Schema::dropIfExists('manifest_members');
        Schema::dropIfExists('manifests');
    }
};
