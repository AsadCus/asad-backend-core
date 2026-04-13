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
        Schema::create('manifest_room_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manifest_member_id')->constrained('manifest_members')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_assigned')->default(true);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('manifest_sharing_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_confirmation_id')->nullable()->constrained('customer_confirmations')->nullOnDelete();
            $table->foreignId('source_quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('group_relationship')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifest_sharing_groups');
        Schema::dropIfExists('manifest_room_members');
    }
};
