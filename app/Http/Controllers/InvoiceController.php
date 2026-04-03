<?php

namespace App\Http\Controllers;

use App\Rules\InvoiceRule;
use App\Services\CustomerService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\QuotationService;
use App\Services\Report\ReportTemplateService;
use App\Services\SalesService;
use App\Support\DataScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    protected $invoiceService;

    protected $orderService;

    protected $quotationService;

    protected $customerService;

    protected $salesService;

    protected $reportTemplateService;

    public function __construct(InvoiceService $invoiceService, OrderService $orderService, QuotationService $quotationService, CustomerService $customerService, SalesService $salesService, ReportTemplateService $reportTemplateService)
    {
        $this->invoiceService = $invoiceService;
        $this->orderService = $orderService;
        $this->quotationService = $quotationService;
        $this->customerService = $customerService;
        $this->salesService = $salesService;
        $this->reportTemplateService = $reportTemplateService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = [];

        if ($user && DataScope::shouldScopeSalesOwnership($user)) {
            $filters['sales_id'] = $user->id;
        }

        $data['invoicesForDatatable'] = $this->invoiceService->getForDataTable($filters);
        $data['quotations'] = $this->quotationService->getForFilter($filters);
        $data['customers'] = $this->customerService->getForFilter();
        $data['salespersons'] = $this->salesService->getForFilter();

        return Inertia::render('invoices/index', [
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        if ($request['quotation_id']) {
            $data['quotation'] = $this->quotationService->getForEditShow($request['quotation_id']);
            $data['paymentMethods'] = $this->quotationService->getPaymentMethodOptions();
            $data['quotationExtensionMasters'] = $this->quotationService->getExtensionMastersForMasterPage();
            $data['defaultPaymentMethod'] = $this->quotationService->getDefaultPaymentMethodValue();

            $paymentPlan = strtolower((string) ($data['quotation']['payment_plan'] ?? 'direct'));
            $initialInvoiceCount = $paymentPlan === 'installment' ? 3 : 1;
            $data['invoiceNumberSeed'] = $this->orderService
                ->suggestDraftInvoiceNumbers($initialInvoiceCount);

            \Log::info('[InvoiceController::create] Data being sent to frontend', [
                'invoiceNumberSeed' => $data['invoiceNumberSeed'],
                'paymentPlan' => $paymentPlan,
            ]);

            return Inertia::render('invoices/create', [
                'data' => $data,
            ]);
        } else {
            return redirect()->route('invoice.index')->with('error', 'Select quotation first to create invoice');
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate(array_merge([
            'order_id' => 'required|exists:orders,id',
        ], (new InvoiceRule)->singleRules()));

        $this->invoiceService->store($validated);

        return redirect()->route('invoice.index')
            ->with('success', 'Invoice created successfully.');
    }

    public function show($id)
    {
        $data['data'] = $this->invoiceService->getForEditShow($id);
        $data['order'] = [
            'id' => $data['data']['order_id'] ?? null,
            'quotation_id' => $data['data']['quotation_id'] ?? null,
        ];
        $data['paymentMethods'] = $this->quotationService->getPaymentMethodOptions();
        $data['quotationExtensionMasters'] = $this->quotationService->getExtensionMastersForMasterPage();
        $data['defaultPaymentMethod'] = $this->quotationService->getDefaultPaymentMethodValue();

        return Inertia::render('invoices/view', [
            'data' => $data,
        ]);
    }

    public function getForShow($id)
    {
        return response()->json($this->invoiceService->getForEditShow($id));
    }

    public function edit($id)
    {
        if ($this->invoiceService->isRefundInvoice((int) $id)) {
            return redirect()->route('invoice.index')
                ->with('error', 'Refund invoice cannot be edited.');
        }

        $data['data'] = $this->invoiceService->getForEditShow($id);
        $data['order'] = [
            'id' => $data['data']['order_id'] ?? null,
            'quotation_id' => $data['data']['quotation_id'] ?? null,
        ];
        $data['paymentMethods'] = $this->quotationService->getPaymentMethodOptions();
        $data['quotationExtensionMasters'] = $this->quotationService->getExtensionMastersForMasterPage();
        $data['defaultPaymentMethod'] = $this->quotationService->getDefaultPaymentMethodValue();

        return Inertia::render('invoices/edit', [
            'data' => $data,
        ]);
    }

    public function update(Request $request, $id)
    {
        if ($this->invoiceService->isRefundInvoice((int) $id)) {
            return redirect()->route('invoice.index')
                ->with('error', 'Refund invoice cannot be edited.');
        }

        $validated = $request->validate(array_merge([
            'order_id' => 'required|exists:orders,id',
        ], (new InvoiceRule)->singleRules()));

        $this->invoiceService->update($validated, $id);

        return redirect()->route('invoice.index')
            ->with('success', 'Invoice updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            $hasRefundInvoice = collect($ids)
                ->map(fn ($invoiceId) => (int) $invoiceId)
                ->contains(fn (int $invoiceId) => $this->invoiceService->isRefundInvoice($invoiceId));

            if ($hasRefundInvoice) {
                return redirect()->route('invoice.index')
                    ->with('error', 'Refund invoice cannot be deleted.');
            }

            foreach ($ids as $deleteId) {
                $this->invoiceService->delete($deleteId);
            }

            return redirect()->route('invoice.index')
                ->with('success', 'Selected invoices deleted successfully.');
        }

        if ($this->invoiceService->isRefundInvoice((int) $id)) {
            return redirect()->route('invoice.index')
                ->with('error', 'Refund invoice cannot be deleted.');
        }

        $this->invoiceService->delete($id);

        return redirect()->route('invoice.index')
            ->with('success', 'Invoice deleted successfully.');
    }

    public function preview($id)
    {
        $invoice = $this->invoiceService->getForEditShow($id);
        $reportData = $this->reportTemplateService->build('invoice', $invoice);

        return view('invoices.report-content', [
            'data' => $invoice,
            'items' => $invoice['items'],
            'branding' => $reportData['branding'],
            'is_pdf' => false,
        ]);
    }

    public function generatePdf($id)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $invoice = $this->invoiceService->getForEditShow($id);
            $reportData = $this->reportTemplateService->build('invoice', $invoice);

            $html = view('invoices.report-content', [
                'data' => $invoice,
                'items' => $invoice['items'],
                'branding' => $reportData['branding'],
                'is_pdf' => true,
            ])->render();

            $pdf = Pdf::loadHTML($html)
                ->setPaper('a4')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96);

            return $pdf->stream($invoice['invoice_number'].'.pdf');
        } catch (\Exception $e) {
            Log::error('Invoice PDF Generation Error: '.$e->getMessage());

            return response()->json(['error' => 'Failed to generate PDF: '.$e->getMessage()], 500);
        }
    }
}
