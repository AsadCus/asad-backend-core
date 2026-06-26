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
        Schema::table('wfh_visit_request_attachments', function (Blueprint $table) {
            $table->string('stage')->default('submission')->after('mime_type');
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete()->after('stage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wfh_visit_request_attachments', function (Blueprint $table) {
            $table->dropForeign(['uploader_id']);
            $table->dropColumn(['stage', 'uploader_id']);
        });
    }
};
