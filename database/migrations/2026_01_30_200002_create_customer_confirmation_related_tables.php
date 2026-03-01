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
        Schema::create('sharing_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_confirmation_id')->constrained('customer_confirmations')->cascadeOnDelete();
            $table->string('sharing_plan');
            $table->unsignedTinyInteger('expected_capacity')->default(1);
            $table->string('status')->default('draft');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('sharing_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sharing_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_confirmation_member_id')->constrained('customer_confirmation_members')->cascadeOnDelete();
            $table->string('role_in_group')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('manifest_room_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manifest_traveler_id')->constrained()->cascadeOnDelete();
            $table->string('role_in_room')->nullable();
            $table->timestamps();
        });

        Schema::create('manifest_sharing_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sharing_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manifest_room_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['manifest_id', 'sharing_group_id']);
        });

        Schema::create('receipt_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->cascadeOnDelete();
            $table->foreignId('customer_confirmation_member_id')->constrained('customer_confirmation_members')->cascadeOnDelete();
            $table->decimal('allocated_amount', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_allocations');
        Schema::dropIfExists('manifest_sharing_groups');
        Schema::dropIfExists('manifest_room_members');
        Schema::dropIfExists('sharing_group_members');
        Schema::dropIfExists('sharing_groups');
    }
};
