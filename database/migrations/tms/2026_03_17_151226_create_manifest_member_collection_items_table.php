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
        Schema::create('manifest_member_collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_member_id')
                ->constrained('manifest_members')
                ->cascadeOnDelete()
                ->unique();
            $table->boolean('course_1')->default(false);
            $table->boolean('course_2')->default(false);
            $table->boolean('lanyard')->default(false);
            $table->boolean('luggage_tag')->default(false);
            $table->boolean('cabin_tag')->default(false);
            $table->boolean('passport_cover')->default(false);
            $table->boolean('umrah_guidebook')->default(false);
            $table->boolean('sling_bag')->default(false);
            $table->boolean('cabin_size_luggage')->default(false);
            $table->boolean('umrah_essentials')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifest_member_collection_items');
    }
};
