<?php

namespace App\Services;

use App\Models\Religion;

class ReligionService
{
    public function get()
    {
        $data = Religion::get();

        return $data;
    }

    public function getForDataTable()
    {
        $data = Religion::get()->map(function ($q) {
            return [
                'id' => $q->id,
                'name' => $q->name,
            ];
        });

        return $data;
    }

    public function getForFilter()
    {
        $data = Religion::get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->name,
            ];
        });

        return $data;
    }

    public function getForFilterByName()
    {
        $data = Religion::get()->map(function ($q) {
            return [
                'value' => $q->name,
                'label' => $q->name,
            ];
        });

        return $data;
    }
}
