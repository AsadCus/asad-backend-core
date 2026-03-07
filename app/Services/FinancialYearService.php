<?php

namespace App\Services;

use App\Models\FinancialTransaction;
use App\Models\FinancialYear;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
        $data = FinancialYear::where('default', true)->orderBy('year', 'desc')->get()->map(function ($q) {
            return [
                'id' => $q->id,
                'year' => $q->year,
                'start_date' => $q->start_date_formatted,
                'end_date' => $q->end_date_formatted,
                'default' => $q->default,
            ];
        });

        return $data;
    }

    public function getForFilter()
    {
        $data = FinancialYear::get()->map(function ($q) {
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
            $financialYear = FinancialYear::create([
                'year' => $data['year'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'default' => $data['default'],
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

        $data = [
            'id' => $financialYear->id,
            'year' => $financialYear->year,
            'start_date' => $financialYear->start_date_formatted,
            'end_date' => $financialYear->end_date_formatted,
            'default' => $financialYear->default
        ];

        return $data;
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $financialYear = FinancialYear::findOrFail($id);

            $oldStartDate = $financialYear->start_date;
            $oldEndDate = $financialYear->end_date;

            if (!empty($data['default']) && $data['default'] === true) {
                FinancialYear::where('id', '!=', $id)->where('default', true)->update(['default' => false]);
            }

            $financialYear->update([
                'year' => $data['year'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'default' => $data['default'],
            ]);

            $newStartDate = $financialYear->start_date;
            $newEndDate = $financialYear->end_date;

            if ($oldStartDate != $newStartDate || $oldEndDate != $newEndDate) {
                $this->reassignFinancialTransactions();
            }

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

        if (!$financialYear) {
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

        if (!$currentYear) {
            return [];
        }

        return $this->financialTransactionService->getMonthlyBreakdown($currentYear->id);
    }

    public function getCurrentYearQuarterlyBreakdown(): array
    {
        $currentYear = FinancialYear::getCurrentYear();

        if (!$currentYear) {
            return [];
        }

        return $this->financialTransactionService->getQuarterlyBreakdown($currentYear->id);
    }

    public function getCurrentMonthRevenue($financialYearId = null): float
    {
        $year = $financialYearId ? FinancialYear::find($financialYearId) : FinancialYear::getCurrentYear();

        if (!$year) {
            return 0;
        }

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        return FinancialTransaction::forYear($year->id)->revenue()->dateRange($startOfMonth, $endOfMonth)->sum('amount');
    }

    public function getCurrentYearOrdersCount($financialYearId = null): int
    {
        $year = $financialYearId ? FinancialYear::find($financialYearId) : FinancialYear::getCurrentYear();

        if (!$year) {
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
}
