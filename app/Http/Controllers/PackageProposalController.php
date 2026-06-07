<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Rules\PackageProposalRule;
use App\Services\CountryService;
use App\Services\PackageProposalService;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class PackageProposalController extends Controller
{
    public function __construct(
        private PackageProposalService $service,
        private PackageProposalRule $rule,
        private CountryService $countryService,
    ) {
        $this->middleware('permission:package-proposal view')->only(['index', 'show']);
        $this->middleware('permission:package-proposal create')->only(['create', 'store']);
        $this->middleware('permission:package-proposal edit')->only(['edit', 'update', 'submit', 'createPackage']);
        $this->middleware('permission:package-proposal delete')->only(['destroy']);
        $this->middleware('permission:package-proposal approve')->only(['approve', 'reject']);
    }

    public function index()
    {
        return Inertia::render('package-proposals/index', [
            'data' => [
                'proposalsForDatatable' => $this->service->getForDataTable(),
            ],
            'approverOptions' => $this->service->getApproverOptions(),
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        $countryOptions = $this->countryService->getForFilter();
        $assignableCountryIds = DataScope::assignableCountryIds($user);
        $countryCurrencyMap = Country::pluck('currency_symbol', 'id');

        return Inertia::render('package-proposals/create', [
            'dataCountry' => $countryOptions,
            'assignableCountryIds' => $assignableCountryIds,
            'approverOptions' => [],
            'countryCurrencyMap' => $countryCurrencyMap,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rule->rules());
        $this->service->store($validated);

        return redirect()->route('package-proposals.index')
            ->with('success', 'Proposal created successfully.');
    }

    public function show(string $id)
    {
        $proposal = $this->service->getForEditShow((int) $id);
        $approverOptions = $this->service->getApproverOptions($proposal['country_id']);
        $countryOptions = $this->countryService->getForFilter();
        $assignableCountryIds = DataScope::assignableCountryIds(auth()->user());
        $countryCurrencyMap = Country::pluck('currency_symbol', 'id');

        return Inertia::render('package-proposals/show', [
            'data' => $proposal,
            'dataCountry' => $countryOptions,
            'assignableCountryIds' => $assignableCountryIds,
            'approverOptions' => $approverOptions,
            'countryCurrencyMap' => $countryCurrencyMap,
        ]);
    }

    public function edit(string $id)
    {
        $proposal = $this->service->getForEditShow((int) $id);
        $countryOptions = $this->countryService->getForFilter();
        $assignableCountryIds = DataScope::assignableCountryIds(auth()->user());
        $approverOptions = $this->service->getApproverOptions($proposal['country_id']);
        $countryCurrencyMap = Country::pluck('currency_symbol', 'id');

        return Inertia::render('package-proposals/edit', [
            'data' => $proposal,
            'dataCountry' => $countryOptions,
            'assignableCountryIds' => $assignableCountryIds,
            'approverOptions' => $approverOptions,
            'countryCurrencyMap' => $countryCurrencyMap,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate($this->rule->rules((int) $id));
        $this->service->update($validated, (int) $id);

        if ($request->boolean('stay')) {
            return back()->with('success', 'Proposal updated successfully.');
        }

        return redirect()->route('package-proposals.index')
            ->with('success', 'Proposal updated successfully.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $ids = $request->input('ids');
        if ($ids && is_array($ids)) {
            foreach ($ids as $proposalId) {
                $this->service->delete((int) $proposalId);
            }

            return redirect()->route('package-proposals.index')
                ->with('success', 'Selected proposals deleted successfully.');
        }

        $this->service->delete((int) $id);

        return redirect()->route('package-proposals.index')
            ->with('success', 'Proposal deleted successfully.');
    }

    public function submit(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate($this->rule->submitRules());
        $this->service->submitForApproval((int) $id, $validated['approver_user_ids']);

        return redirect()->route('package-proposals.show', $id)
            ->with('success', 'Proposal submitted for approval.');
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $this->service->approve((int) $id);

        return redirect()->route('package-proposals.show', $id)
            ->with('success', 'Proposal approved successfully.');
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate($this->rule->rejectRules());
        $this->service->reject((int) $id, $validated['rejection_reason']);

        return redirect()->route('package-proposals.show', $id)
            ->with('success', 'Proposal rejected.');
    }

    public function createPackage(string $id): RedirectResponse
    {
        $package = $this->service->createPackageFromProposal((int) $id);

        return redirect()->route('packages.edit', $package->id)
            ->with('success', 'Package created from proposal successfully.');
    }
}
