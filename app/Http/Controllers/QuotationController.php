<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Services\MaidService;
use App\Http\Controllers\Controller;
use App\Models\Maid;
use App\Rules\NoteRule;
use App\Rules\QuotationRule;
use App\Services\CustomerService;
use App\Services\NoteService;
use App\Services\QuotationItemService;
use App\Services\QuotationService;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Quotation;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class QuotationController extends Controller
{
    protected $quotationService, $maidService, $customerService, $quotationRule, $quotationItemService, $noteService;

    public function __construct(QuotationService $quotationService, MaidService $maidService, CustomerService $customerService, QuotationRule $quotationRule, QuotationItemService $quotationItemService, NoteService $noteService)
    {
        $this->quotationService = $quotationService;
        $this->maidService = $maidService;
        $this->customerService = $customerService;
        $this->quotationRule = $quotationRule;
        $this->quotationItemService = $quotationItemService;
        $this->noteService = $noteService;
    }

    public function index()
    {
        $data['quotationsForDatatable'] = $this->quotationService->getForDataTable();
        $data['maids'] = $this->maidService->getForFilter();
        $data['customers'] = $this->customerService->getForFilter();

        return Inertia::render('quotations/index', [
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        $data['maids'] = $this->maidService->getForFilterWithCode();
        $data['customers'] = $this->customerService->getForFilterWithCode();
        $data['quotationItems'] = $this->quotationItemService->getQuotationItemMasters(false);
        $data['quotationNotes'] = $this->noteService->get('master', 'quotation');

        $prefilledMaidId = $request->input('maid_id');
        $prefilledCustomerId = $request->input('customer_id');
        $handoverDate = $request->input('handover_date');
        $prefilledMaidData = null;
        $prefilledCustomerData = null;

        if ($prefilledMaidId) {
            try {
                $prefilledMaidData = $this->maidService->getForEditShow($prefilledMaidId);
            } catch (Exception) {
                $prefilledMaidData = null;
            }
        }

        if ($prefilledCustomerId) {
            try {
                $prefilledCustomerData = $this->customerService->getForEditShow($prefilledCustomerId);
            } catch (Exception) {
                $prefilledCustomerData = null;
            }
        }

        return Inertia::render('quotations/create', [
            'data' => $data,
            'prefilledMaidId' => $prefilledMaidId,
            'prefilledMaidData' => $prefilledMaidData,
            'prefilledCustomerId' => $prefilledCustomerId,
            'prefilledCustomerData' => $prefilledCustomerData,
            'handoverDate' => $handoverDate,
        ]);
    }

    public function store(Request $request)
    {
        if ($request->status === 'sent' || $request->status === 'revised') {
            $validated = $request->validate($this->quotationRule->sentRules());
        } else {
            $validated = $request->validate($this->quotationRule->rules());
        }

        $validatedNotes = $request->validate(NoteRule::rules());

        $quotation = $this->quotationService->store($validated);

        $this->noteService->sync($validatedNotes['model'], $quotation->id, $validatedNotes['notes']);

        activity()
            ->performedOn($quotation)
            ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number])
            ->log('Quotation created successfully #' . $quotation->quotation_number);

        return redirect()->route('quotation.index')
            ->with('success', 'Quotation created successfully.');
    }

    public function show($id)
    {
        $data['data'] = $this->quotationService->getForEditShow($id);
        $data['maids'] = $this->maidService->getForFilterWithCode();
        $data['customers'] = $this->customerService->getForFilterWithCode();

        return Inertia::render('quotations/view', [
            'data' => $data,
        ]);
    }

    public function getForShow($id)
    {
        return response()->json($this->quotationService->getForEditShow($id));
    }

    public function edit($id)
    {
        $data['data'] = $this->quotationService->getForEditShow($id);
        $data['maids'] = $this->maidService->getForFilterWithCode();
        $data['customers'] = $this->customerService->getForFilterWithCode();

        return Inertia::render('quotations/edit', [
            'data' => $data,
        ]);
    }

    public function update(Request $request, $id)
    {
        if ($request->status === 'sent' || $request->status === 'revised') {
            $validated = $request->validate($this->quotationRule->sentRules());
        } else {
            $validated = $request->validate($this->quotationRule->rules());
        }

        $validatedNotes = $request->validate(NoteRule::rules());

        $quotation = $this->quotationService->update($validated, $id);

        $this->noteService->sync($validatedNotes['model'], $quotation->id, $validatedNotes['notes']);

        activity()
            ->performedOn($quotation)
            ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number])
            ->log('Quotation updated successfully #' . $quotation->quotation_number);

        return redirect()->route('quotation.index')
            ->with('success', 'Quotation updated successfully');
    }

    public function readyQuotation($id)
    {
        $quotation = $this->quotationService->ready($id);

        activity()
            ->performedOn($quotation)
            ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number, 'status' => 'sent'])
            ->log('Quotation marked as sent successfully #' . $quotation->quotation_number);

        return redirect()->route('quotation.index')->with('success', 'Quotation marked as sent successfully.');
    }

    public function acceptQuotation($id, Request $request)
    {
        $quotation = $this->quotationService->accept($id);

        activity()
            ->performedOn($quotation)
            ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number])
            ->log('Quotation accepted successfully #' . $quotation->quotation_number);

        return redirect()->route('invoice.create', ['quotation_id' => $request['quotation_id']])->with('success', 'Quotation accepted successfully.');
    }

    public function rejectQuotation(Request $request, $id)
    {
        $quotation = $this->quotationService->reject($request, $id);

        if ($quotation->maid_id) {
            $this->maidService->revertMaidToAvailable($quotation->maid_id);
            $quotation->maid->refresh();
        }

        activity()
            ->performedOn($quotation)
            ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number])
            ->log('Quotation rejected successfully #' . $quotation->quotation_number);

        return redirect()->route('quotation.index')
            ->with('success', 'Quotation rejected successfully.');
    }

    public function expireQuotation($id)
    {
        $quotation = $this->quotationService->expire($id);

        activity()
            ->performedOn($quotation)
            ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number])
            ->log('Quotation expired successfully #' . $quotation->quotation_number);

        return redirect()->route('quotation.index')
            ->with('success', 'Quotation ended successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $deleteId) {
                $quotation = Quotation::find($deleteId);
                if ($quotation && $quotation->maid_id) {
                    $this->maidService->revertMaidToAvailable($quotation->maid_id);
                }
                $this->quotationService->delete($deleteId);
                activity()
                    ->performedOn($quotation)
                    ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number])
                    ->log('Quotation deleted successfully #' . $quotation->quotation_number);
            }
            return redirect()->route('quotation.index')
                ->with('success', 'Selected quotations deleted successfully.');
        }

        $quotation = Quotation::find($id);
        if ($quotation && $quotation->maid_id) {
            $this->maidService->revertMaidToAvailable($quotation->maid_id);
            $quotation->maid->refresh();
        }
        $this->quotationService->delete($id);

        activity()
            ->performedOn($quotation)
            ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number])
            ->log('Quotation deleted successfully #' . $quotation->quotation_number);

        return redirect()->route('quotation.index')
            ->with('success', 'Quotation deleted successfully.');
    }

    // export
    public function generatePdf($id)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $data = $this->quotationService->getForEditShow($id);

            $paymentPlan = $data['payment_plan'] ?? 'full';
            $paymentPlanLabel = match ($paymentPlan) {
                'direct' => 'Direct',
                'full' => 'Full Payment',
                'installment' => 'Installment',
                default => ucfirst($paymentPlan),
            };

            $data['payment_plan_label'] = $paymentPlanLabel;

            $items = $this->mergePlacementFeeItemsForPdf(
                $data['items'] ?? [],
                $data
            );

            $html = view('quotations.pdf', [
                'data' => $data,
                'items' => $this->sortForPdf($items),
            ])->render();

            return Pdf::loadHTML($html)
                ->setPaper('a4')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96)
                ->stream($data['quotation_number'] . '.pdf');
        } catch (\Throwable $e) {
            Log::error('PDF Generation Error', ['error' => $e]);
            return response()->json(['error' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
        }
    }

    private function mergePlacementFeeItemsForPdf(array $items, array $data): array
    {
        $items = collect($items)->sortBy('sort_order')->values();

        $placement = $items->filter(
            fn($i) =>
            !empty($i['is_placement_fee'])
        );

        if ($placement->isEmpty()) {
            return $items->all();
        }

        $totalQty = $placement->sum(fn($i) => (float) $i['quantity']);
        $rate = (float) ($data['monthly_salary'] ?? 0);
        $baseSort = $placement->first()['sort_order'];

        $filtered = $items->reject(fn($i) => !empty($i['is_placement_fee']));

        $mergedPlacement = [
            ...$placement->first(),
            'description' => 'Placement Fee',
            'quantity' => $totalQty,
            'rate' => $rate,
            'parent_id' => null,
            'parent_key' => null,
            'is_header' => false,
            'is_placement_fee' => true,
            'sort_order' => $baseSort,
        ];

        return $filtered->push($mergedPlacement)->sortBy('sort_order')->values()->all();
    }

    private function sortForPdf(array $items): array
    {
        $collection = collect($items)->sortBy('sort_order')->values();

        $roots = $collection->filter(fn($i) => empty($i['parent_id']) && empty($i['parent_key']));
        $children = $collection->filter(fn($i) => !empty($i['parent_id']) || !empty($i['parent_key']));

        $result = [];

        foreach ($roots as $r) {
            $result[] = $r;

            $pid = $r['id'] ?? $r['key'] ?? null;

            if ($pid !== null) {
                $subs = $children
                    ->filter(
                        fn($c) => ($c['parent_id'] ?? null) == $pid ||
                            ($c['parent_key'] ?? null) == $pid
                    )
                    ->sortBy('sort_order')
                    ->values();

                foreach ($subs as $s) {
                    $result[] = $s;
                }
            }
        }

        return $result;
    }
}
