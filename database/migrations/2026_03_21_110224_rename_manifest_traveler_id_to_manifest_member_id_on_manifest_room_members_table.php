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
        if (! Schema::hasTable('manifest_room_members')) {
            return;
        }

        if (! Schema::hasColumn('manifest_room_members', 'manifest_traveler_id')) {
            return;
        }

        if (Schema::hasColumn('manifest_room_members', 'manifest_member_id')) {
            return;
        }

        Schema::table('manifest_room_members', function (Blueprint $table) {
            $table->dropForeign(['manifest_traveler_id']);
        });

        Schema::table('manifest_room_members', function (Blueprint $table) {
            $table->renameColumn('manifest_traveler_id', 'manifest_member_id');
        });

        Schema::table('manifest_room_members', function (Blueprint $table) {
            $table->foreign('manifest_member_id')->references('id')->on('manifest_members')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('manifest_room_members')) {
            return;
        }

        if (! Schema::hasColumn('manifest_room_members', 'manifest_member_id')) {
            return;
        }

        if (Schema::hasColumn('manifest_room_members', 'manifest_traveler_id')) {
            return;
        }

        Schema::table('manifest_room_members', function (Blueprint $table) {
            $table->dropForeign(['manifest_member_id']);
        });

        Schema::table('manifest_room_members', function (Blueprint $table) {
            $table->renameColumn('manifest_member_id', 'manifest_traveler_id');
        });

        Schema::table('manifest_room_members', function (Blueprint $table) {
            $table->foreign('manifest_traveler_id')->references('id')->on('manifest_members')->cascadeOnDelete();
        });
    }
};
