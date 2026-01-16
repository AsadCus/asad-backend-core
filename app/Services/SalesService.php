<?php

namespace App\Services;

use App\Models\Sales;
use App\Models\User;

class SalesService
{
    public function getForDataTable()
    {
        $data = User::role('sales')->with('sales')->get()->map(function ($q) {
            return [
                'id' => $q->id,
                'name' => $q->name,
                'email' => $q->email,
                'contact' => $q->contact,
                'branch_id' => $q->sales->branch_id,
                'branch_name' => $q->sales->branch->name,
            ];
        });

        return $data;
    }

    public function getForFilter()
    {
        $data = User::role(['admin', 'sales'])->where('deleted_at', null)->get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->name,
                'branch_id' => $q->sales->branch_id ?? null,
            ];
        });

        return $data;
    }

    public function show($id)
    {
        $data = User::role('sales')->with('sales')->find($id);

        return $data;
    }
}
