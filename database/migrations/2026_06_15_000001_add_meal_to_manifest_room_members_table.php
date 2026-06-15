<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('manifest_room_members', function (Blueprint $table) {
            $table->string('meal')->nullable()->after('remarks');
        });

        // Backfill each room member with its parent room's meal so existing
        // manifests keep the current behaviour (all members share one meal).
        DB::table('manifest_room_members')
            ->whereNull('meal')
            ->update([
                'meal' => DB::raw('(select meal from manifest_rooms where manifest_rooms.id = manifest_room_members.manifest_room_id)'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manifest_room_members', function (Blueprint $table) {
            $table->dropColumn('meal');
        });
    }
};
