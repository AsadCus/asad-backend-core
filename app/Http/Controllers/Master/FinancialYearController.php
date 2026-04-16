<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Rules\FinancialYearRule;
use App\Services\FinancialYearService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FinancialYearController extends Controller
{
    protected $financialYearService;

    protected $financialYearRule;

    public function __construct(FinancialYearService $financialYearService, FinancialYearRule $financialYearRule)
    {
        $this->financialYearService = $financialYearService;
        $this->financialYearRule = $financialYearRule;
    }

    public function index()
    {
        $data['financialYears'] = $this->financialYearService->getForDataTable();
        $data['hasActiveFinancialYear'] = $this->financialYearService->hasActiveFinancialYear();

        return Inertia::render('masters/financial-year/index', [
            'data' => $data,
        ]);
    }

    public function create()
    {
        return Inertia::render('masters/financial-year/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->financialYearRule->rules());

        $this->financialYearService->store($validated);

        return redirect()->intended(route('master.financial-year.index'))->with('success', 'Financial year created successfully.');
    }

    public function show(string $id)
    {
        $data = $this->financialYearService->getForEditShow($id);

        return Inertia::render('masters/financial-year/view', [
            'data' => $data,
        ]);
    }

    public function edit(string $id)
    {
        $data = $this->financialYearService->getForEditShow($id);

        return Inertia::render('masters/financial-year/edit', [
            'data' => $data,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->financialYearRule->rules());

        $this->financialYearService->update($validated, $id);

        return redirect()->intended(route('master.financial-year.index'))->with('success', 'Financial year updated successfully.');
    }

    public function updateDefault(string $id)
    {
        $this->financialYearService->setDefault($id);

        return redirect()->intended(route('master.financial-year.index'))->with('success', 'Financial year updated successfully.');
    }

    public function destroy(Request $request, string $id)
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $userId) {
                $this->financialYearService->delete($userId);
            }

            return redirect()->intended(route('master.financial-year.index'))->with('success', 'Selected years deleted successfully.');
        }

        $this->financialYearService->delete($id);

        return redirect()->intended(route('master.financial-year.index'))->with('success', 'Financial year deleted successfully.');
    }
}
