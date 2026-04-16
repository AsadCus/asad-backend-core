<?php

namespace App\Services;

use App\Models\FinancialTransaction;
use App\Models\FinancialYear;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialYearService
{
    protected $financialTransactionService;

    public function __construct(FinancialTransactionService $financialTransactionService)
    {
        $this->financialTransactionService = $financialTransactionService;
    }

    public function get()
    {
        $data = FinancialYear::get();

        return $data;
    }

    public function getForDataTable()
    {
        $data = FinancialYear::query()
            ->where('default', true)
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->get()
            ->map(function ($q) {
                return [
                    'id' => $q->id,
                    'year' => $q->year,
                    'start_date' => $q->start_date?->toDateString(),
                    'end_date' => $q->end_date?->toDateString(),
                    'start_date_formatted' => $q->start_date?->translatedFormat('d F Y'),
                    'end_date_formatted' => $q->end_date?->translatedFormat('d F Y'),
                    'start_date_day_month' => $q->start_date?->translatedFormat('j F'),
                    'end_date_day_month' => $q->end_date?->translatedFormat('j F'),
                    'default' => $q->default,
                ];
            });

        return $data;
    }

    public function hasActiveFinancialYear(): bool
    {
        return FinancialYear::query()->where('is_active', true)->exists();
    }

    public function getForFilter()
    {
        $data = FinancialYear::query()
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->get()
            ->map(function ($q) {
                return [
                    'value' => $q->id,
                    'label' => $q->year,
                ];
            });

        return $data;
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $hasActiveYear = FinancialYear::query()->where('is_active', true)->exists();

            if ($hasActiveYear) {
                throw ValidationException::withMessages([
                    'start_date' => 'Fiscal year already exists. Please edit the current fiscal year instead.',
                ]);
            }

            $resolvedPeriod = $this->resolvePeriodData($data, Carbon::now()->year);

            FinancialYear::query()->where('default', true)->update(['default' => false]);

            $financialYear = FinancialYear::create([
                'year' => (string) $resolvedPeriod['year'],
                'start_date' => $resolvedPeriod['start_date']->toDateString(),
                'end_date' => $resolvedPeriod['end_date']->toDateString(),
                'default' => true,
                'is_active' => true,
            ]);

            activity()
                ->performedOn($financialYear)
                ->withProperties(['subject_type' => 'FinancialYear', 'subject_id' => $financialYear->id ?? null])
                ->log('FinancialYear created successfully #'.($financialYear->id ?? null));

            return $financialYear;
        });
    }

    public function getForEditShow($id)
    {
        $financialYear = FinancialYear::findOrFail($id);

        $startDate = $financialYear->start_date
            ? Carbon::parse($financialYear->start_date)
            : null;
        $endDate = $financialYear->end_date
            ? Carbon::parse($financialYear->end_date)
            : null;

        $data = [
            'id' => $financialYear->id,
            'year' => $financialYear->year,
            'start_date' => $financialYear->start_date?->toDateString(),
            'end_date' => $financialYear->end_date?->toDateString(),
            'start_date_formatted' => $financialYear->start_date?->translatedFormat('d F Y'),
            'end_date_formatted' => $financialYear->end_date?->translatedFormat('d F Y'),
            'start_date_day_month' => $financialYear->start_date?->translatedFormat('j F'),
            'end_date_day_month' => $financialYear->end_date?->translatedFormat('j F'),
            'start_day' => $startDate ? (string) $startDate->day : '',
            'start_month' => $startDate ? (string) $startDate->month : '',
            'end_day' => $endDate ? (string) $endDate->day : '',
            'end_month' => $endDate ? (string) $endDate->month : '',
            'default' => $financialYear->default,
        ];

        return $data;
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $financialYear = FinancialYear::findOrFail($id);

            $baseYear = $this->resolveBaseYear($financialYear);
            $resolvedPeriod = $this->resolvePeriodData($data, $baseYear);

            FinancialYear::query()->where('id', '!=', $id)->where('default', true)->update(['default' => false]);

            $financialYear->update([
                'year' => (string) $resolvedPeriod['year'],
                'start_date' => $resolvedPeriod['start_date']->toDateString(),
                'end_date' => $resolvedPeriod['end_date']->toDateString(),
                'default' => true,
                'is_active' => true,
            ]);

            activity()
                ->performedOn($financialYear)
                ->withProperties(['subject_type' => 'FinancialYear', 'subject_id' => $financialYear->id ?? null])
                ->log('FinancialYear updated successfully #'.($financialYear->id ?? null));

            return $financialYear;
        });
    }

    /**
     * Reassign all financial transactions to their correct financial years
     * based on transaction_date and the current financial year date ranges
     */
    public function reassignFinancialTransactions(): void
    {
        $transactions = FinancialTransaction::all();

        foreach ($transactions as $transaction) {
            $transactionDate = $transaction->transaction_date;

            $financialYear = FinancialYear::where('start_date', '<=', $transactionDate)->where('end_date', '>=', $transactionDate)->first();

            if ($financialYear && $transaction->financial_year_id !== $financialYear->id) {
                $transaction->update(['financial_year_id' => $financialYear->id]);
            }
        }
    }

    public function setDefault($id)
    {
        return DB::transaction(function () use ($id) {
            $financialYear = FinancialYear::findOrFail($id);

            FinancialYear::where('id', '!=', $id)->where('default', true)->update(['default' => false]);

            $financialYear->update([
                'default' => true,
            ]);

            return $financialYear;
        });
    }

    public function delete($id)
    {
        $financialYear = FinancialYear::find($id);

        if (! $financialYear) {
            return false;
        }

        return $financialYear->update(['is_active' => false]);
    }

    public function getCurrentYearSummary(): array
    {
        return $this->financialTransactionService->getCurrentYearSummary();
    }

    public function getCurrentYearMonthlyBreakdown(): array
    {
        $currentYear = FinancialYear::getCurrentYear();

        if (! $currentYear) {
            return [];
        }

        return $this->financialTransactionService->getMonthlyBreakdown($currentYear->id);
    }

    public function getCurrentYearQuarterlyBreakdown(): array
    {
        $currentYear = FinancialYear::getCurrentYear();

        if (! $currentYear) {
            return [];
        }

        return $this->financialTransactionService->getQuarterlyBreakdown($currentYear->id);
    }

    public function getCurrentMonthRevenue($financialYearId = null): float
    {
        $year = $financialYearId ? FinancialYear::find($financialYearId) : FinancialYear::getCurrentYear();

        if (! $year) {
            return 0;
        }

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        return FinancialTransaction::forYear($year->id)->revenue()->dateRange($startOfMonth, $endOfMonth)->sum('amount');
    }

    public function getCurrentYearOrdersCount($financialYearId = null): int
    {
        $year = $financialYearId ? FinancialYear::find($financialYearId) : FinancialYear::getCurrentYear();

        if (! $year) {
            return 0;
        }

        return Order::whereBetween('created_at', [$year->start_date, $year->end_date])->count();
    }

    /**
     * Get available financial years for filter
     */
    public function getAvailableYears(): array
    {
        return FinancialYear::where('is_active', true)->orderBy('start_date', 'desc')->get()->map(function ($year) {
            return ['value' => $year->id, 'label' => $year->year];
        })->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{year:int,start_date:Carbon,end_date:Carbon}
     */
    private function resolvePeriodData(array $data, int $baseYear): array
    {
        $startDateValue = isset($data['start_date']) ? trim((string) $data['start_date']) : '';
        $endDateValue = isset($data['end_date']) ? trim((string) $data['end_date']) : '';

        if ($startDateValue !== '' && $endDateValue !== '') {
            try {
                $startDate = Carbon::parse($startDateValue)->startOfDay();
            } catch (\Throwable $exception) {
                throw ValidationException::withMessages([
                    'start_date' => 'Start date is not valid.',
                ]);
            }

            try {
                $endDate = Carbon::parse($endDateValue)->endOfDay();
            } catch (\Throwable $exception) {
                throw ValidationException::withMessages([
                    'end_date' => 'End date is not valid.',
                ]);
            }

            if ($endDate->lte($startDate)) {
                throw ValidationException::withMessages([
                    'end_date' => 'End date must be after start date.',
                ]);
            }

            $yearLabel = FinancialYear::calculateDominantYear($startDate->copy(), $endDate->copy());

            return [
                'year' => $yearLabel,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        }

        $startDay = (int) ($data['start_day'] ?? 0);
        $startMonth = (int) ($data['start_month'] ?? 0);
        $endDay = (int) ($data['end_day'] ?? 0);
        $endMonth = (int) ($data['end_month'] ?? 0);

        if (! checkdate($startMonth, $startDay, $baseYear)) {
            throw ValidationException::withMessages([
                'start_day' => 'Start date is not valid for the selected day and month.',
            ]);
        }

        $endYear = ($endMonth < $startMonth || ($endMonth === $startMonth && $endDay < $startDay))
            ? $baseYear + 1
            : $baseYear;

        if (! checkdate($endMonth, $endDay, $endYear)) {
            throw ValidationException::withMessages([
                'end_day' => 'End date is not valid for the selected day and month.',
            ]);
        }

        $startDate = Carbon::create($baseYear, $startMonth, $startDay)->startOfDay();
        $endDate = Carbon::create($endYear, $endMonth, $endDay)->endOfDay();

        $yearLabel = FinancialYear::calculateDominantYear($startDate->copy(), $endDate->copy());

        return [
            'year' => $yearLabel,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    private function resolveBaseYear(FinancialYear $financialYear): int
    {
        $numericYear = (int) ($financialYear->year ?? 0);

        if ($numericYear > 0) {
            return $numericYear;
        }

        if ($financialYear->start_date) {
            return Carbon::parse($financialYear->start_date)->year;
        }

        return Carbon::now()->year;
    }
}
