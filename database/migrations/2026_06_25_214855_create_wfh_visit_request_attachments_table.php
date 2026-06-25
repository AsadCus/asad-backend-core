<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wfh_visit_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wfh_visit_request_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime_type')->nullable();
            $table->timestamps();

            $table->index('wfh_visit_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wfh_visit_request_attachments');
    }
};
