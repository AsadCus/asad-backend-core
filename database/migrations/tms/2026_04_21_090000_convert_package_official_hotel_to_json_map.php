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
        Schema::table('package_officials', function (Blueprint $table): void {
            $table->json('hotel_json')->nullable()->after('name');
        });

        $accommodationIdsByPackage = DB::table('package_accommodations')
            ->select(['package_id', 'id'])
            ->orderBy('id')
            ->get()
            ->groupBy('package_id')
            ->map(function ($rows) {
                return collect($rows)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
            });

        DB::table('package_officials')
            ->select(['id', 'package_id', 'hotel'])
            ->orderBy('id')
            ->chunkById(100, function ($officials) use ($accommodationIdsByPackage): void {
                foreach ($officials as $official) {
                    $legacyHotel = trim((string) ($official->hotel ?? ''));

                    if ($legacyHotel === '') {
                        DB::table('package_officials')
                            ->where('id', $official->id)
                            ->update(['hotel_json' => null]);

                        continue;
                    }

                    $packageAccommodationIds = $accommodationIdsByPackage->get((int) $official->package_id, []);

                    $hotelMap = [];
                    if (is_array($packageAccommodationIds) && $packageAccommodationIds !== []) {
                        foreach ($packageAccommodationIds as $accommodationId) {
                            $hotelMap[(string) (int) $accommodationId] = $legacyHotel;
                        }
                    } else {
                        $hotelMap['0'] = $legacyHotel;
                    }

                    DB::table('package_officials')
                        ->where('id', $official->id)
                        ->update(['hotel_json' => json_encode($hotelMap, JSON_UNESCAPED_UNICODE)]);
                }
            });

        Schema::table('package_officials', function (Blueprint $table): void {
            $table->dropColumn('hotel');
        });

        Schema::table('package_officials', function (Blueprint $table): void {
            $table->renameColumn('hotel_json', 'hotel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('package_officials', function (Blueprint $table): void {
            $table->string('hotel_string')->nullable()->after('name');
        });

        DB::table('package_officials')
            ->select(['id', 'hotel'])
            ->orderBy('id')
            ->chunkById(100, function ($officials): void {
                foreach ($officials as $official) {
                    $hotelMap = json_decode((string) ($official->hotel ?? ''), true);
                    $legacyHotel = null;

                    if (is_array($hotelMap)) {
                        foreach ($hotelMap as $value) {
                            $candidate = trim((string) ($value ?? ''));
                            if ($candidate !== '') {
                                $legacyHotel = $candidate;
                                break;
                            }
                        }
                    }

                    DB::table('package_officials')
                        ->where('id', $official->id)
                        ->update(['hotel_string' => $legacyHotel]);
                }
            });

        Schema::table('package_officials', function (Blueprint $table): void {
            $table->dropColumn('hotel');
        });

        Schema::table('package_officials', function (Blueprint $table): void {
            $table->renameColumn('hotel_string', 'hotel');
        });
    }
};
