<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Rules\NoteRule;
use App\Rules\QuotationRule;
use App\Services\CustomerService;
use App\Services\NoteService;
use App\Services\QuotationItemService;
use App\Services\QuotationService;
use App\Services\Report\ReportTemplateService;
use App\Services\SalesService;
use App\Support\DataScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class QuotationController extends Controller
{
    protected $quotationService;

    protected $customerService;

    protected $quotationRule;

    protected $quotationItemService;

    protected $noteService;

    protected $salesService;

    protected $reportTemplateService;

    public function __construct(QuotationService $quotationService, CustomerService $customerService, QuotationRule $quotationRule, QuotationItemService $quotationItemService, NoteService $noteService, SalesService $salesService, ReportTemplateService $reportTemplateService)
    {
        $this->quotationService = $quotationService;
        $this->customerService = $customerService;
        $this->salesService = $salesService;
        $this->quotationRule = $quotationRule;
        $this->quotationItemService = $quotationItemService;
        $this->noteService = $noteService;
        $this->reportTemplateService = $reportTemplateService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = [];

        if ($user && DataScope::shouldScopeSalesOwnership($user)) {
            $filters['sales_id'] = $user->id;
        }

        $data['quotationsForDatatable'] = $this->quotationService->getForDataTable($filters);
        $data['customers'] = $this->customerService->getForFilter();
        $data['salespersons'] = $this->salesService->getForFilter();

        return Inertia::render('quotations/index', [
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        $data['customerConfirmations'] = $this->quotationService->getCustomerConfirmationCreateOptions();
        $data['activeCustomers'] = $this->quotationService->getActiveCustomerOptions();
        $data['quotationItems'] = $this->quotationItemService->getQuotationItemMasters(false);
        $data['quotationNotes'] = $this->noteService->get('master', 'quotation');
        $data['quotationExtensionMasters'] = $this->quotationService->getExtensionMastersForMasterPage();
        $data['defaultExtensions'] = collect(
            $this->quotationService->getDefaultExtensionsForCreate()
        )
            ->filter(fn ($extension) => ($extension['type'] ?? null) !== 'discount')
            ->values()
            ->all();

        $prefilledCustomerId = $request->input('customer_id');
        $prefilledCustomerData = null;

        if ($prefilledCustomerId) {
            try {
                $prefilledCustomerData = $this->customerService->getForEditShow($prefilledCustomerId);
            } catch (Exception) {
                $prefilledCustomerData = null;
            }
        }

        return Inertia::render('quotations/create', [
            'data' => $data,
            'prefilledCustomerId' => $prefilledCustomerId,
            'prefilledCustomerData' => $prefilledCustomerData,
        ]);
    }

    public function store(Request $request)
    {
        if ($request->status === 'ready' || $request->status === 'revised') {
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
            ->log('Quotation created successfully #'.$quotation->quotation_number);

        return redirect()->route('quotation.index')
            ->with('success', 'Quotation created successfully.');
    }

    public function show($id)
    {
        $data['data'] = $this->quotationService->getForEditShow($id);
        $data['customerConfirmations'] = $this->quotationService->getCustomerConfirmationCreateOptions(
            (int) ($data['data']['customer_confirmation_id'] ?? 0) ?: null
        );
        $data['activeCustomers'] = $this->quotationService->getActiveCustomerOptions();

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
        $data['customerConfirmations'] = $this->quotationService->getCustomerConfirmationCreateOptions(
            (int) ($data['data']['customer_confirmation_id'] ?? 0) ?: null
        );
        $data['activeCustomers'] = $this->quotationService->getActiveCustomerOptions();
        $data['quotationExtensionMasters'] = $this->quotationService->getExtensionMastersForMasterPage();

        return Inertia::render('quotations/edit', [
            'data' => $data,
        ]);
    }

    public function update(Request $request, $id)
    {
        if ($request->status === 'ready' || $request->status === 'revised') {
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
            ->log('Quotation updated successfully #'.$quotation->quotation_number);

        return redirect()->route('quotation.index')
            ->with('success', 'Quotation updated successfully');
    }

    public function readyQuotation($id)
    {
        $quotation = $this->quotationService->ready($id);

        activity()
            ->performedOn($quotation)
            ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number, 'status' => 'sent'])
            ->log('Quotation marked as sent successfully #'.$quotation->quotation_number);

        return redirect()->route('quotation.index')->with('success', 'Quotation marked as sent successfully.');
    }

    public function draftQuotation($id)
    {
        $quotation = $this->quotationService->draft($id);

        activity()
            ->performedOn($quotation)
            ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number, 'status' => 'draft'])
            ->log('Quotation moved back to draft successfully #'.$quotation->quotation_number);

        return redirect()->route('quotation.index')->with('success', 'Quotation moved to draft successfully.');
    }

    public function acceptQuotation($id, Request $request)
    {
        $quotation = $this->quotationService->accept($id);

        activity()
            ->performedOn($quotation)
            ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number])
            ->log('Quotation accepted successfully #'.$quotation->quotation_number);

        return redirect()->route('invoice.create', ['quotation_id' => $request['quotation_id']])->with('success', 'Quotation accepted successfully.');
    }

    public function rejectQuotation(Request $request, $id)
    {
        $quotation = $this->quotationService->reject($request, $id);

        activity()
            ->performedOn($quotation)
            ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number])
            ->log('Quotation rejected successfully #'.$quotation->quotation_number);

        return redirect()->route('quotation.index')
            ->with('success', 'Quotation rejected successfully.');
    }

    public function expireQuotation($id)
    {
        $quotation = $this->quotationService->expire($id);

        activity()
            ->performedOn($quotation)
            ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number])
            ->log('Quotation expired successfully #'.$quotation->quotation_number);

        return redirect()->route('quotation.index')
            ->with('success', 'Quotation ended successfully.');
    }

    public function cancelQuotation($id)
    {
        $quotation = $this->quotationService->cancel($id);

        activity()
            ->performedOn($quotation)
            ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number])
            ->log('Quotation cancelled successfully #'.$quotation->quotation_number);

        return redirect()->route('quotation.index')->with('success', 'Quotation voided successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            $deletedCount = 0;
            $skippedCount = 0;

            foreach ($ids as $deleteId) {
                $quotation = Quotation::find($deleteId);

                if (! $quotation) {
                    continue;
                }

                // Prevent deletion of converted or cancelled quotations
                if (in_array($quotation->status, ['converted', 'cancelled'])) {
                    $skippedCount++;

                    continue;
                }

                $this->quotationService->delete($deleteId);

                activity()
                    ->performedOn($quotation)
                    ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number])
                    ->log('Quotation deleted successfully #'.$quotation->quotation_number);

                $deletedCount++;
            }

            $message = "Deleted {$deletedCount} quotation(s).";
            if ($skippedCount > 0) {
                $message .= " Skipped {$skippedCount} quotation(s) (converted or cancelled cannot be deleted).";
            }

            return redirect()->route('quotation.index')
                ->with('success', $message);
        }

        $quotation = Quotation::find($id);

        if (! $quotation) {
            return redirect()->route('quotation.index')
                ->with('error', 'Quotation not found.');
        }

        // Prevent deletion of converted or cancelled quotations
        if (in_array($quotation->status, ['converted', 'cancelled'])) {
            return redirect()->route('quotation.index')
                ->with('error', 'Cannot delete quotation with status: '.$quotation->status);
        }

        $this->quotationService->delete($id);

        activity()
            ->performedOn($quotation)
            ->withProperties(['subject_type' => 'Quotation', 'subject_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number])
            ->log('Quotation deleted successfully #'.$quotation->quotation_number);

        return redirect()->route('quotation.index')
            ->with('success', 'Quotation deleted successfully.');
    }

    public function preview($id)
    {
        $data = $this->quotationService->getForEditShow($id);
        $reportData = $this->reportTemplateService->build('quotation', $data);

        $paymentPlan = $data['payment_plan'] ?? 'full';
        $paymentPlanLabel = match ($paymentPlan) {
            'direct' => 'Direct',
            'full' => 'Full Payment',
            'installment' => 'Installment',
            default => ucfirst($paymentPlan),
        };

        $data['payment_plan_label'] = $paymentPlanLabel;

        return view('quotations.report-content', [
            'data' => $data,
            'items' => $this->sortForPdf($data['items'] ?? []),
            'branding' => $reportData['branding'],
            'is_pdf' => false,
        ]);
    }

    // export
    public function generatePdf($id)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $data = $this->quotationService->getForEditShow($id);
            $reportData = $this->reportTemplateService->build('quotation', $data);

            $paymentPlan = $data['payment_plan'] ?? 'full';
            $paymentPlanLabel = match ($paymentPlan) {
                'direct' => 'Direct',
                'full' => 'Full Payment',
                'installment' => 'Installment',
                default => ucfirst($paymentPlan),
            };

            $data['payment_plan_label'] = $paymentPlanLabel;

            $html = view('quotations.report-content', [
                'data' => $data,
                'items' => $this->sortForPdf($data['items'] ?? []),
                'branding' => $reportData['branding'],
                'is_pdf' => true,
            ])->render();

            return Pdf::loadHTML($html)
                ->setPaper('a4')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96)
                ->stream($data['quotation_number'].'.pdf');
        } catch (\Throwable $e) {
            Log::error('PDF Generation Error', ['error' => $e]);

            return response()->json(['error' => 'Failed to generate PDF: '.$e->getMessage()], 500);
        }
    }

    private function sortForPdf(array $items): array
    {
        $collection = collect($items)->sortBy('sort_order')->values();

        $roots = $collection->filter(fn ($i) => empty($i['parent_id']) && empty($i['parent_key']));
        $children = $collection->filter(fn ($i) => ! empty($i['parent_id']) || ! empty($i['parent_key']));

        $result = [];

        foreach ($roots as $r) {
            $result[] = $r;

            $pid = $r['id'] ?? $r['key'] ?? null;

            if ($pid !== null) {
                $subs = $children
                    ->filter(
                        fn ($c) => ($c['parent_id'] ?? null) == $pid ||
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
