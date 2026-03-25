<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Rules\CountryRule;
use App\Services\CountryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CountryController extends Controller
{
    protected $countryService;

    protected $countryRule;

    public function __construct(CountryService $countryService, CountryRule $countryRule)
    {
        $this->countryService = $countryService;
        $this->countryRule = $countryRule;
    }

    public function index()
    {
        return Inertia::render('masters/country/index', [
            'dataCountry' => $this->countryService->getForDataTable(),
        ]);
    }

    public function create()
    {
        return Inertia::render('masters/country/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->countryRule->rules());
        $this->countryService->store($validated);

        return redirect()->route('master.country.index')
            ->with('success', 'Country created successfully.');
    }

    public function show(string $id)
    {
        return Inertia::render('masters/country/view', [
            'data' => $this->countryService->getForEditShow($id),
        ]);
    }

    public function edit(string $id)
    {
        return Inertia::render('masters/country/edit', [
            'data' => $this->countryService->getForEditShow($id),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->countryRule->rules());
        $this->countryService->update($validated, $id);

        return redirect()->route('master.country.index')
            ->with('success', 'Country updated successfully.');
    }

    public function destroy(Request $request, string $id)
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $countryId) {
                $this->countryService->delete($countryId);
            }

            return redirect()->route('master.country.index')
                ->with('success', 'Selected countries deleted successfully.');
        }

        $this->countryService->delete($id);

        return redirect()->route('master.country.index')
            ->with('success', 'Country deleted successfully.');
    }
}
