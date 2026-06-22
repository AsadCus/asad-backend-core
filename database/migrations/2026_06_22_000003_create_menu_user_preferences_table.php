<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sparse per-user personalisation: favourites, personal hide, personal order within
        // the customisable "Me" zone. Nullable booleans so an explicit `false` can override a
        // role-default favourite (null = fall back to the role default).
        Schema::create('menu_user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('menu_key');
            $table->boolean('is_favorite')->nullable();
            $table->boolean('is_hidden')->nullable();
            $table->integer('sort_order')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'menu_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_user_preferences');
    }
};
