<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Country;
use App\Models\Enquiry;
use App\Models\Operation;
use App\Models\Sales;
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
                'adjective' => $q->adjective,
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
        return DB::transaction(function () use ($id) {
            $country = Country::find($id);
            if (! $country) {
                return false;
            }

            Enquiry::query()
                ->where('country_id', $country->id)
                ->update(['country_id' => null]);

            Sales::query()
                ->where('country_id', $country->id)
                ->update(['country_id' => null]);

            Admin::query()
                ->where('country_id', $country->id)
                ->update(['country_id' => null]);

            Operation::query()
                ->where('country_id', $country->id)
                ->update(['country_id' => null]);

            $this->pruneCountryScopeListFromRoleAssignments((int) $country->id);

            activity()
                ->performedOn($country)
                ->withProperties(['subject_type' => 'Country', 'subject_id' => $country->id ?? null])
                ->log('Country deleted successfully #'.($country->id ?? null));

            return $country->delete();
        });
    }

    private function pruneCountryScopeListFromRoleAssignments(int $countryId): void
    {
        foreach (Admin::query()->whereJsonContains('country_ids', $countryId)->get() as $admin) {
            $nextCountryIds = collect($admin->country_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => $id === $countryId)
                ->values()
                ->all();

            $admin->update(['country_ids' => $nextCountryIds]);
        }

        foreach (Operation::query()->whereJsonContains('country_ids', $countryId)->get() as $operation) {
            $nextCountryIds = collect($operation->country_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => $id === $countryId)
                ->values()
                ->all();

            $operation->update(['country_ids' => $nextCountryIds]);
        }

        foreach (Sales::query()->whereJsonContains('country_ids', $countryId)->get() as $sales) {
            $nextCountryIds = collect($sales->country_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => $id === $countryId)
                ->values()
                ->all();

            $sales->update(['country_ids' => $nextCountryIds]);
        }
    }
}
