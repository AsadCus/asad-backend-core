<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Receipt;
use App\Services\ReceiptService;
use App\Services\InvoiceService;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class ReceiptController extends Controller
{
    protected $receiptService, $invoiceService;

    public function __construct(ReceiptService $receiptService, InvoiceService $invoiceService)
    {
        $this->receiptService = $receiptService;
        $this->invoiceService = $invoiceService;
    }

    public function index()
    {
        $data['data'] = $this->receiptService->getForDataTable();
        $data['invoiceOptions'] = $this->invoiceService->getForFilter();

        return Inertia::render('receipts/index', [
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        $data['invoiceId'] = $request->invoice_id;
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
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric',
            // 'amount' => 'required|numeric|min:0',
            'receipt_date' => 'required|date',
            'payment_method' => 'nullable|string',
            'reference' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $this->receiptService->store($validated);

        return redirect()->route('receipt.index')
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

        return Inertia::render('receipts/edit', [
            'data' => $data,
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'description' => 'nullable|string',
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

    public function generatePdf($id)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $data = $this->receiptService->getForEditShow($id);

            $paymentMethod = $data['payment_method'] ?? 'full';
            $paymentMethodLabel = match ($paymentMethod) {
                'cash' => 'Cash',
                'transfer' => 'Bank Transfer',
                'paynow' => 'Paynow',
                default => ucfirst($paymentMethod),
            };

            $data['payment_method_label'] = $paymentMethodLabel;

            $html = view('receipts.pdf', [
                'data' => $data,
                'items' => $data['items'],
            ])->render();

            $pdf = Pdf::loadHTML($html)
                ->setPaper('a4')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96);

            return $pdf->stream($data['receipt_number'] . '.pdf');
        } catch (\Exception $e) {
            Log::error('Receipt PDF Generation Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
        }
    }
}
