<?php

namespace App\Services;

use App\Models\FinancialYear;
use Illuminate\Support\Facades\DB;

class FinancialYearService
{
    public function get()
    {
        $data = FinancialYear::get();

        return $data;
    }

    public function getForDataTable()
    {
        $data = FinancialYear::orderBy('year', 'desc')->get()->map(function ($q) {
            return [
                'id' => $q->id,
                'year' => $q->year,
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
            if (!empty($data['default']) && $data['default'] === true) {
                FinancialYear::where('default', true)->update(['default' => false]);
            }

            $financialYear = FinancialYear::create([
                'year' => $data['year'],
                'default' => $data['default'],
            ]);

            return $financialYear;
        });
    }

    public function getForEditShow($id)
    {
        $financialYear = FinancialYear::findOrFail($id);

        $data = [
            'id' => $financialYear->id,
            'year' => $financialYear->year,
            'default' => $financialYear->default
        ];

        return $data;
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $financialYear = FinancialYear::findOrFail($id);

            if (!empty($data['default']) && $data['default'] === true) {
                FinancialYear::where('id', '!=', $id)->where('default', true)->update(['default' => false]);
            }

            $financialYear->update([
                'year' => $data['year'],
                'default' => $data['default'],
            ]);

            return $financialYear;
        });
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
        $data = FinancialYear::find($id);
        if (!$data) {
            return false;
        }

        return $data->delete();
    }
}
