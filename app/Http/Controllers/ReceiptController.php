<?php

namespace App\Http\Controllers;

use App\Services\CustomerService;
use App\Services\InvoiceService;
use App\Services\ReceiptService;
use App\Services\Report\ReportTemplateService;
use App\Services\SalesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ReceiptController extends Controller
{
    protected $receiptService;

    protected $invoiceService;

    protected $customerService;

    protected $salesService;

    protected $reportTemplateService;

    public function __construct(ReceiptService $receiptService, InvoiceService $invoiceService, CustomerService $customerService, SalesService $salesService, ReportTemplateService $reportTemplateService)
    {
        $this->receiptService = $receiptService;
        $this->invoiceService = $invoiceService;
        $this->customerService = $customerService;
        $this->salesService = $salesService;
        $this->reportTemplateService = $reportTemplateService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = [];

        if ($user->hasRole('sales')) {
            $filters['sales_id'] = $user->id;
        }

        $data['receiptsForDatatable'] = $this->receiptService->getForDataTable($filters);
        $data['invoices'] = $this->invoiceService->getForFilter();
        $data['customers'] = $this->customerService->getForFilter();
        $data['salespersons'] = $this->salesService->getForFilter();
        $data['paymentMethods'] = $this->receiptService->getPaymentMethodOptions();

        return Inertia::render('receipts/index', [
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        $data['invoiceId'] = $request->invoice_id;
        $data['defaultPaymentMethod'] = $this->receiptService->getDefaultPaymentMethodValue();
        $data['paymentMethods'] = $this->receiptService->getPaymentMethodOptions();
        if ($data['invoiceId']) {
            $data['invoiceData'] = $this->invoiceService->getForEditShow($request->invoice_id);
        }
        $data['invoiceOptions'] = $this->invoiceService->getForFilter();

        return Inertia::render('receipts/create', [
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'receipt_number' => ['nullable', 'string', 'max:100'],
            'number_format_id' => ['nullable', 'integer', 'exists:numbering_formats,id'],
            'invoice_id' => ['required', 'integer', 'exists:invoices,id', Rule::unique('receipts', 'invoice_id')],
            'amount' => ['required', 'numeric'],
            'receipt_date' => ['required', 'date'],
            'payment_method' => ['required', 'string'],
            'reference' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $this->receiptService->store($validated);

        // return redirect()->route('receipt.index')
        return redirect()->route('invoice.index')
            ->with('success', 'Receipt created successfully.');
    }

    public function show($id)
    {
        $data['data'] = $this->receiptService->getForEditShow($id);
        $data['invoiceOptions'] = $this->invoiceService->getForFilter();

        return Inertia::render('receipts/view', [
            'data' => $data,
        ]);
    }

    public function getForShow($id)
    {
        return response()->json($this->receiptService->getForEditShow($id));
    }

    public function edit($id)
    {
        $data['data'] = $this->receiptService->getForEditShow($id);
        $data['invoiceOptions'] = $this->invoiceService->getForFilter();
        $data['paymentMethods'] = $this->receiptService->getPaymentMethodOptions();

        return Inertia::render('receipts/edit', [
            'data' => $data,
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'receipt_number' => ['nullable', 'string', 'max:100'],
            'number_format_id' => ['nullable', 'integer', 'exists:numbering_formats,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id', Rule::unique('receipts', 'invoice_id')->ignore((int) $id)],
            'amount' => ['nullable', 'numeric'],
            'receipt_date' => ['required', 'date'],
            'payment_method' => ['required', 'string'],
            'reference' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $this->receiptService->update($validated, $id);

        return redirect()->route('receipt.index')
            ->with('success', 'Receipt updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $deleteId) {
                $this->receiptService->delete($deleteId);
            }

            return redirect()->route('receipt.index')
                ->with('success', 'Selected receipts deleted successfully.');
        }

        $this->receiptService->delete($id);

        return redirect()->route('receipt.index')
            ->with('success', 'Receipt deleted successfully.');
    }

    public function preview($id)
    {
        $data = $this->receiptService->getForEditShow($id);
        $reportData = $this->reportTemplateService->build('receipt', $data);

        $paymentMethod = $data['payment_method'] ?? '';
        $paymentMethodLabel = collect($this->receiptService->getPaymentMethodOptions())
            ->firstWhere('value', $paymentMethod)['label'] ?? ucfirst((string) $paymentMethod);

        $data['payment_method_label'] = $paymentMethodLabel;

        return view('receipts.report-content', [
            'data' => $data,
            'items' => $data['items'],
            'branding' => $reportData['branding'],
            'is_pdf' => false,
        ]);
    }

    public function generatePdf($id)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $data = $this->receiptService->getForEditShow($id);
            $reportData = $this->reportTemplateService->build('receipt', $data);

            $paymentMethod = $data['payment_method'] ?? '';
            $paymentMethodLabel = collect($this->receiptService->getPaymentMethodOptions())
                ->firstWhere('value', $paymentMethod)['label'] ?? ucfirst((string) $paymentMethod);

            $data['payment_method_label'] = $paymentMethodLabel;

            $html = view('receipts.report-content', [
                'data' => $data,
                'items' => $data['items'],
                'branding' => $reportData['branding'],
                'is_pdf' => true,
            ])->render();

            $pdf = Pdf::loadHTML($html)
                ->setPaper('a4')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96);

            return $pdf->stream($data['receipt_number'].'.pdf');
        } catch (\Exception $e) {
            Log::error('Receipt PDF Generation Error: '.$e->getMessage());

            return response()->json(['error' => 'Failed to generate PDF: '.$e->getMessage()], 500);
        }
    }
}
