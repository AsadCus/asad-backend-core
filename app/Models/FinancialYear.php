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
        return self::query()
            ->where('default', true)
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first();
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
        $existingYear = self::resolveForTransactionDate($date);

        if ($existingYear) {
            return $existingYear;
        }

        $defaultYear = self::getCurrentYear();

        if ($defaultYear) {
            return $defaultYear;
        }

        $startDate = Carbon::create($date->year, 1, 1)->startOfDay();
        $endDate = Carbon::create($date->year, 12, 31)->endOfDay();
        $yearLabel = self::calculateDominantYear($startDate->copy(), $endDate->copy());

        self::query()->where('default', true)->update(['default' => false]);

        return self::create([
            'year' => (string) $yearLabel,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'default' => true,
            'is_active' => true,
        ]);
    }

    public static function resolveForTransactionDate(Carbon $date): ?self
    {
        $defaultYear = self::getCurrentYear();

        $matchingYears = self::query()
            ->where('is_active', true)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->orderByDesc('default')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        if ($matchingYears->isNotEmpty()) {
            if ($defaultYear && $matchingYears->contains('id', $defaultYear->id)) {
                return $defaultYear;
            }

            return $matchingYears->first();
        }

        return $defaultYear;
    }

    /**
     * Progress financial year to the current period
     * Creates the year if it doesn't exist, then activates it
     * Sets previous years to inactive and non-default
     */
    public static function progressFinancialYear(?Carbon $today = null): ?self
    {
        $todayDate = ($today ?? Carbon::today())->copy()->startOfDay();

        $defaultYear = self::getCurrentYear();

        if (! $defaultYear) {
            $initialStart = Carbon::create($todayDate->year, 1, 1)->startOfDay();
            $initialEnd = Carbon::create($todayDate->year, 12, 31)->endOfDay();

            $created = self::create([
                'year' => (string) self::calculateDominantYear($initialStart->copy(), $initialEnd->copy()),
                'start_date' => $initialStart,
                'end_date' => $initialEnd,
                'default' => true,
                'is_active' => true,
            ]);

            return $created;
        }

        if ($defaultYear->start_date && $defaultYear->end_date && $defaultYear->containsDate($todayDate)) {
            if (! $defaultYear->default) {
                self::query()->where('id', '!=', $defaultYear->id)->update(['default' => false]);
                $defaultYear->update(['default' => true, 'is_active' => true]);
            }

            return $defaultYear;
        }

        $coveringYear = self::query()
            ->where('is_active', true)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('start_date', '<=', $todayDate->toDateString())
            ->where('end_date', '>=', $todayDate->toDateString())
            ->orderByDesc('default')
            ->orderByDesc('start_date')
            ->first();

        if ($coveringYear) {
            self::query()->where('id', '!=', $coveringYear->id)->update(['default' => false]);
            $coveringYear->update(['default' => true, 'is_active' => true]);

            return $coveringYear;
        }

        $currentYear = $defaultYear;

        while ($currentYear->end_date && $todayDate->gt(Carbon::parse($currentYear->end_date)->endOfDay())) {
            $currentStart = Carbon::parse($currentYear->start_date)->startOfDay();
            $currentEnd = Carbon::parse($currentYear->end_date)->endOfDay();
            $periodDays = $currentStart->diffInDays($currentEnd) + 1;

            $nextStart = $currentEnd->copy()->addDay()->startOfDay();
            $nextEnd = $nextStart->copy()->addDays($periodDays - 1)->endOfDay();
            $nextYearLabel = self::calculateDominantYear($nextStart->copy(), $nextEnd->copy());

            $existingNext = self::query()
                ->whereDate('start_date', $nextStart->toDateString())
                ->whereDate('end_date', $nextEnd->toDateString())
                ->first();

            if (! $existingNext) {
                $existingNext = self::create([
                    'year' => (string) $nextYearLabel,
                    'start_date' => $nextStart,
                    'end_date' => $nextEnd,
                    'default' => false,
                    'is_active' => true,
                ]);
            }

            self::query()->where('id', '!=', $existingNext->id)->update(['default' => false]);
            $existingNext->update(['default' => true, 'is_active' => true]);

            $currentYear = $existingNext;
        }

        return $currentYear;
    }
}
