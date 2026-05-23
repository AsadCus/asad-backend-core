<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $symbols = [
            'singapore' => 'S$',
            'malaysia' => 'RM',
        ];

        foreach ($symbols as $countryName => $symbol) {
            DB::table('countries')
                ->whereRaw('lower(name) = ?', [$countryName])
                ->where(function ($query): void {
                    $query->whereNull('currency_symbol')
                        ->orWhere('currency_symbol', '');
                })
                ->update(['currency_symbol' => $symbol]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $symbols = [
            'singapore' => 'S$',
            'malaysia' => 'RM',
        ];

        foreach ($symbols as $countryName => $symbol) {
            DB::table('countries')
                ->whereRaw('lower(name) = ?', [$countryName])
                ->where('currency_symbol', $symbol)
                ->update(['currency_symbol' => null]);
        }
    }
};
