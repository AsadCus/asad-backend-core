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
        Schema::create('maid_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maid_id')->constrained('maids')->onDelete('cascade')->comment('Links to the main FDW profile');
            $table->enum('attribute_category', [
                'ILLNESS',
                'ILLNESS_OTHERS',
                'ALLERGY',
                'PHYSICAL_DISABILITY',
                'DIET_RESTRICTION',
                'FOOD_PREFERENCE',
                'FOOD_PREFERENCE_OTHERS'
            ])->comment('Classification of the attribute type');
            $table->string('attribute_name', 200)->comment('e.g., Asthma, Peanuts, No Pork, Severe Back Pain');

            // index
            $table->index('maid_id');
            $table->index('attribute_category');
            $table->index('attribute_name');
            // $table->index(['maid_id', 'attribute_category', 'attribute_name']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maid_attributes');
    }
};
