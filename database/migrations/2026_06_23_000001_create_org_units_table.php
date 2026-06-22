<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            // Recursive org tree: holding -> business_unit -> branch -> department -> division.
            // Depth is data, not schema. parent_id null = root (holding).
            // No ON DELETE CASCADE: self-referencing cascade is unreliable in MySQL and we
            // want subtree deletes handled (soft-delete) in the service layer.
            $table->foreignId('parent_id')->nullable()->constrained('org_units');
            $table->string('type'); // App\Enums\OrgUnitType
            $table->string('name');
            $table->string('code')->unique();
            $table->unsignedInteger('sort_order')->default(0);

            // Type-specific attributes (nullable; set only on the relevant type).
            // ponytail: nullable columns until per-type attribute sets actually grow.
            $table->string('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();   // branch geofence
            $table->decimal('longitude', 11, 8)->nullable();  // branch geofence
            $table->unsignedInteger('geofence_radius_meters')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_units');
    }
};
