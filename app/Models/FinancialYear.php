<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class FinancialYear extends Model
{
    protected $fillable = [
        'year',
        'start_date',
        'end_date',
        'default',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'default' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Formatting Helpers
    public function getStartDateFormattedAttribute(): ?string
    {
        return $this->start_date
            ? Carbon::parse($this->start_date)->translatedFormat('d F Y')
            : null;
    }

    public function getEndDateFormattedAttribute(): ?string
    {
        return $this->end_date
            ? Carbon::parse($this->end_date)->translatedFormat('d F Y')
            : null;
    }

    public function containsDate(Carbon $date): bool
    {
        return $date->between($this->start_date, $this->end_date);
    }

    public static function getCurrentYear(): ?self
    {
        return self::where('default', true)->where('is_active', true)->first();
    }

    public static function getNextYearPeriod(): array
    {
        $currentYear = self::getCurrentYear();

        if (! $currentYear) {
            // Default: Jan 1 to Dec 31 of current year
            $now = Carbon::now();
            $startDate = Carbon::create($now->year, 1, 1);
            $endDate = Carbon::create($now->year, 12, 31);
            $yearLabel = $now->year;
        } else {
            $startDate = Carbon::parse($currentYear->end_date)->addDay();
            $endDate = $startDate->copy()->addYear()->subDay();
            $yearLabel = self::calculateDominantYear($startDate, $endDate);
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'year' => $yearLabel,
        ];
    }

    /**
     * Calculate which year has the most months between start and end date
     */
    public static function calculateDominantYear(Carbon $startDate, Carbon $endDate): int
    {
        $startYear = $startDate->year;
        $endYear = $endDate->year;

        if ($startYear === $endYear) {
            return $startYear;
        }

        // Count months in each year
        $monthsInStartYear = 12 - $startDate->month + 1; // months remaining in start year
        $monthsInEndYear = $endDate->month; // months in end year

        return $monthsInStartYear >= $monthsInEndYear ? $startYear : $endYear;
    }

    /**
     * Get or create a financial year for a specific date
     * Creates year with default=false, is_active=false if it doesn't exist
     * Uses dynamic date ranges based on existing financial years or defaults to Jan 1 - Dec 31
     */
    public static function getOrCreateForDate(Carbon $date): ?self
    {
        // First, try to find an existing financial year that contains this date
        $existingYear = self::where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

        if ($existingYear) {
            return $existingYear;
        }

        // Default to calendar year (Jan 1 - Dec 31)
        $startDate = Carbon::create($date->year, 1, 1);
        $endDate = Carbon::create($date->year, 12, 31);
        $yearLabel = $date->year;

        // Check if a year with this range already exists
        $existingByRange = self::where('start_date', $startDate->format('Y-m-d'))->where('end_date', $endDate->format('Y-m-d'))->first();

        if ($existingByRange) {
            return $existingByRange;
        }

        return self::create([
            'year' => (string) $yearLabel,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'default' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Progress financial year to the current period
     * Creates the year if it doesn't exist, then activates it
     * Sets previous years to inactive and non-default
     */
    public static function progressFinancialYear(): void
    {
        $today = Carbon::today();

        $currentPeriodYear = self::getOrCreateForDate($today);

        if ($currentPeriodYear) {
            self::where('id', '!=', $currentPeriodYear->id)->update(['default' => false]);

            $currentPeriodYear->update(['default' => true, 'is_active' => true]);
        }
    }
}
