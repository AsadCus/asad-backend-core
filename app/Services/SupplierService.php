<?php

namespace App\Services;

use App\Models\User;

class SupplierService
{
    public function getForDataTable()
    {
        $data = User::role('supplier')->with('supplier', 'supplier.maids')->get()->map(function ($q) {
            $address = $q->supplier->address ?? '';
            $addressFormatted = nl2br($address);

            return [
                'id' => $q->id,
                'name' => $q->name,
                'email' => $q->email,
                'contact' => $q->contact,
                'company_name' => $q->supplier->name,
                'address' => $addressFormatted,
                'supplier_id' => $q->supplier->id ?? null,
                'total_cost_of_maid' => $q->supplier->getTotalCostOfMaid(),
                'commission' => $q->supplier->commission,
            ];
        });

        return $data;
    }

    public function show($id)
    {
        $data = User::role('supplier')->with('supplier')->find($id);

        return $data;
    }

    public function getForFilter()
    {
        $data = User::role('supplier')->with('supplier')->get()->map(function ($q) {
            return [
                'value' => $q->supplier->id ?? null,
                'label' => $q->supplier->name ?? $q->name,
            ];
        })->filter(fn ($item) => $item['value'] !== null);

        return $data;
    }

    public function getForFilterByName()
    {
        $data = User::role('supplier')->with('supplier')->get()->map(function ($q) {
            return [
                'value' => $q->supplier->name ?? $q->name,
                'label' => $q->supplier->name ?? $q->name,
            ];
        })->filter(fn ($item) => $item['value'] !== null);

        return $data;
    }
}
