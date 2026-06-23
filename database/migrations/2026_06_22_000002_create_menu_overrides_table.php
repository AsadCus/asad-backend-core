<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sparse global overrides on top of the frontend NAV_ZONES registry. One row per
        // menu the administrator has changed; menus with no row use their registry defaults.
        Schema::create('menu_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('menu_key')->unique();   // = the nav node's i18n titleKey, e.g. "nav.dashboard"
            $table->string('label')->nullable();     // rename override (null = use i18n default)
            $table->string('icon')->nullable();      // Lucide icon name override (null = registry icon)
            $table->string('zone')->nullable();      // recategorise into another zone (null = registry zone)
            $table->integer('sort_order')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->string('permission')->nullable(); // override the gating permission (null = registry permission)
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_overrides');
    }
};
