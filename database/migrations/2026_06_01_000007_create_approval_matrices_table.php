<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_matrices', function (Blueprint $table) {
            $table->id();
            $table->string('submitter_level')->unique();
            $table->string('approver_1_level');
            $table->string('approver_2_level')->nullable();
            $table->string('final_verifier_role')->default('hr');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_matrices');
    }
};
