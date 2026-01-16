<?php

namespace App\Services;

use App\Models\Country;

class CountryService
{
    public function get()
    {
        $data = Country::get();

        return $data;
    }

    public function getForDataTable()
    {
        $data = Country::get()->map(function ($q) {
            return [
                'id' => $q->id,
                'name' => $q->name,
            ];
        });

        return $data;
    }

    public function getForFilter($label = 'name')
    {
        $data = Country::get()->map(function ($q) use ($label) {
            return [
                'value' => $q->id,
                'label' => $label === 'name' ? $q->name : $q->adjective,
            ];
        });

        return $data;
    }

    public function getForFilterByName()
    {
        $data = Country::get()->map(function ($q) {
            return [
                'value' => $q->name,
                'label' => $q->name,
            ];
        });

        return $data;
    }

    public function getForFilterByAdjective()
    {
        $data = Country::get()->map(function ($q) {
            return [
                'value' => $q->adjective,
                'label' => $q->adjective,
            ];
        });

        return $data;
    }
}
