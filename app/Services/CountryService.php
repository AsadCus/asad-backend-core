<?php

namespace App\Services;

use App\Models\Country;
use Illuminate\Support\Facades\DB;

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

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $country = Country::create([
                'name' => $data['name'],
                'adjective' => $data['adjective'] ?? null,
            ]);

            activity()
                ->performedOn($country)
                ->withProperties(['subject_type' => 'Country', 'subject_id' => $country->id ?? null])
                ->log('Country created successfully #'.($country->id ?? null));

            return $country;
        });
    }

    public function getForEditShow($id)
    {
        $country = Country::findOrFail($id);

        return [
            'id' => $country->id,
            'name' => $country->name,
            'adjective' => $country->adjective,
        ];
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $country = Country::findOrFail($id);

            $country->update([
                'name' => $data['name'],
                'adjective' => $data['adjective'] ?? null,
            ]);

            activity()
                ->performedOn($country)
                ->withProperties(['subject_type' => 'Country', 'subject_id' => $country->id ?? null])
                ->log('Country updated successfully #'.($country->id ?? null));

            return $country;
        });
    }

    public function delete($id)
    {
        $country = Country::find($id);
        if (! $country) {
            return false;
        }

        activity()
            ->performedOn($country)
            ->withProperties(['subject_type' => 'Country', 'subject_id' => $country->id ?? null])
            ->log('Country deleted successfully #'.($country->id ?? null));

        return $country->delete();
    }
}
