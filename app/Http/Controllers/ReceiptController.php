<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkSendEmailRequest;
use App\Http\Requests\SendEmailRequest;
use App\Services\CustomerService;
use App\Services\InvoiceService;
use App\Services\ReceiptService;
use App\Services\Report\ReportTemplateService;
use App\Services\SalesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
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
        $filters = [];

        $data['receiptsForDatatable'] = $this->receiptService->getForDataTable($filters);
        $data['invoices'] = $this->invoiceService->getForFilter($filters);
        $data['customers'] = $this->customerService->getForFilter();
        $data['salespersons'] = $this->salesService->getForFilter();
        $data['paymentMethods'] = $this->receiptService->getPaymentMethodOptions();

        return Inertia::render('receipts/index', [
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        $filters = [];

        $data['invoiceId'] = $request->invoice_id;
        $data['defaultPaymentMethod'] = $this->receiptService->getDefaultPaymentMethodValue();
        $data['paymentMethods'] = $this->receiptService->getPaymentMethodOptions();
        if ($data['invoiceId']) {
            $data['invoiceData'] = $this->invoiceService->getForEditShow($request->invoice_id);
        }
        $data['invoiceOptions'] = $this->invoiceService->getForFilter($filters);

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
            'refund_to' => ['nullable', 'string', 'max:255'],
        ]);

        $this->receiptService->store($validated);

        // return redirect()->route('receipt.index')
        return redirect()->route('invoice.index')
            ->with('success', 'Receipt created successfully.');
    }

    public function show($id)
    {
        $filters = [];

        $data['data'] = $this->receiptService->getForEditShow($id);
        $data['invoiceOptions'] = $this->invoiceService->getForFilter($filters);

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
        $filters = [];

        $data['data'] = $this->receiptService->getForEditShow($id);
        $data['invoiceOptions'] = $this->invoiceService->getForFilter($filters);
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
            'refund_to' => ['nullable', 'string', 'max:255'],
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

    public function getEmailData($id)
    {
        $receipt = \App\Models\Receipt::with('invoice.order.quotation.customer.user')->findOrFail($id);

        $customerEmail = $receipt->invoice?->order?->quotation?->customer?->user?->email;
        $customerName = $receipt->invoice?->order?->quotation?->customer?->user?->name ?? 'Customer';

        $templates = [
            [
                'value' => 'receipt_send',
                'label' => 'Receipt Send',
                'message' => "Here is your receipt {$receipt->receipt_number} from ".config('app.name').".\n\nPlease find the PDF attached to this email, or you can download it via the link below.",
            ],
            [
                'value' => 'payment_confirmation',
                'label' => 'Payment Confirmation',
                'message' => "Thank you for your payment. Your receipt {$receipt->receipt_number} is attached to this email.\n\nWe appreciate your business.",
            ],
        ];

        return response()->json([
            'to' => $customerEmail ?? '',
            'cc' => '',
            'subject' => 'Receipt '.$receipt->receipt_number.' from '.config('app.name'),
            'templates' => $templates,
            'default_template' => 'receipt_send',
            'customer_name' => $customerName,
        ]);
    }

    public function previewEmail(SendEmailRequest $request, $id)
    {
        $receipt = \App\Models\Receipt::with('invoice.order.quotation.customer.user')->findOrFail($id);

        $html = view('mail.receipt-email', [
            'receipt' => $receipt,
            'customMessage' => $request->input('message'),
        ])->render();

        return response()->json([
            'html' => $html,
        ]);
    }

    public function sendEmail(SendEmailRequest $request, $id)
    {
        try {
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

            $receipt = \App\Models\Receipt::with('invoice.order.quotation.customer.user')->findOrFail($id);
            $pdfContent = $pdf->output();

            $pdfAttachments = [
                ['content' => $pdfContent, 'filename' => 'receipt_'.$receipt->receipt_number.'.pdf'],
            ];

            $to = $request->input('to');
            $cc = $request->input('cc');
            $subject = $request->input('subject');
            $messageBody = $request->input('message');

            $mail = \Illuminate\Support\Facades\Mail::to($to);

            if ($cc) {
                $ccList = array_map('trim', explode(',', $cc));
                $mail->cc($ccList);
            }

            $mail->send(new \App\Mail\ReceiptMail($receipt, $pdfAttachments, $subject, $messageBody, false));

            $receipt->update(['email_sent_at' => now()]);

            return redirect()->back()->with('success', 'Email sent to '.$to.' successfully.');
        } catch (\Exception $e) {
            Log::error('Receipt Email Sending Error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to send receipt email: '.$e->getMessage());
        }
    }

    public function getBulkEmailData(Request $request)
    {
        $ids = array_map('trim', explode(',', $request->query('ids', '')));
        $ids = array_filter($ids, 'is_numeric');

        if (empty($ids)) {
            return response()->json(['error' => 'No valid IDs provided'], 400);
        }

        $receipts = \App\Models\Receipt::with('invoice.order.quotation.customer.user')->whereIn('id', $ids)->get();

        $groupedReceipts = $receipts->groupBy(function ($receipt) {
            return $receipt->invoice?->order?->quotation?->customer?->user?->email ?? 'unknown';
        });

        $recipientGroups = [];
        foreach ($groupedReceipts as $email => $customerReceipts) {
            if ($email === 'unknown' || empty($email)) {
                continue;
            }
            $recipientGroups[] = [
                'email' => $email,
                'name' => $customerReceipts->first()->invoice?->order?->quotation?->customer?->user?->name ?? 'Customer',
                'documents' => $customerReceipts->pluck('receipt_number')->toArray(),
            ];
        }

        $firstReceipt = $receipts->first();
        $customerName = $firstReceipt->invoice?->order?->quotation?->customer?->user?->name ?? 'Customer';

        $templates = [
            [
                'value' => 'receipt_send',
                'label' => 'Receipt Send',
                'message' => 'Here is your receipt from '.config('app.name').".\n\nPlease find the PDF attached to this email, or you can download it via the link below.",
            ],
            [
                'value' => 'payment_confirmation',
                'label' => 'Payment Confirmation',
                'message' => "Thank you for your payment. Your receipt is attached to this email.\n\nWe appreciate your business.",
            ],
        ];

        return response()->json([
            'to' => '', // Read-only badge shown in UI for bulk
            'cc' => '',
            'subject' => 'Receipt from '.config('app.name'),
            'templates' => $templates,
            'default_template' => 'receipt_send',
            'customer_name' => $customerName,
            'recipient_groups' => $recipientGroups,
        ]);
    }

    public function previewBulkEmail(BulkSendEmailRequest $request)
    {
        $ids = $request->input('ids');
        if (empty($ids)) {
            return response()->json(['error' => 'No valid IDs provided'], 400);
        }

        // Use first ID to generate preview
        $firstId = reset($ids);
        $receipt = \App\Models\Receipt::with('invoice.order.quotation.customer.user')->findOrFail($firstId);

        $html = view('mail.receipt-email', [
            'receipt' => $receipt,
            'customMessage' => $request->input('message'),
        ])->render();

        return response()->json([
            'html' => $html,
        ]);
    }

    public function sendBulkEmail(BulkSendEmailRequest $request)
    {
        $ids = $request->input('ids');
        $subject = $request->input('subject');
        $messageBody = $request->input('message');

        $successCount = 0;
        $errorCount = 0;

        $receipts = \App\Models\Receipt::with('invoice.order.quotation.customer.user')->whereIn('id', $ids)->get();
        $groupedReceipts = $receipts->groupBy(function ($receipt) {
            return $receipt->invoice?->order?->quotation?->customer?->user?->email;
        });

        foreach ($groupedReceipts as $email => $customerReceipts) {
            if (empty($email)) {
                foreach ($customerReceipts as $rec) {
                    Log::warning('Skipping bulk email for receipt '.$rec->id.': no customer email.');
                    $errorCount++;
                }

                continue;
            }

            try {
                $pdfAttachments = [];
                $firstReceipt = $customerReceipts->first();

                foreach ($customerReceipts as $receipt) {
                    $data = $this->receiptService->getForEditShow($receipt->id);
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

                    $pdfAttachments[] = [
                        'content' => $pdf->output(),
                        'filename' => 'receipt_'.$receipt->receipt_number.'.pdf',
                    ];
                }

                $individualSubject = str_replace('{receipt_number}', $customerReceipts->pluck('receipt_number')->implode(', '), $subject);
                $individualMessage = str_replace('{receipt_number}', $customerReceipts->pluck('receipt_number')->implode(', '), $messageBody);

                $mail = \Illuminate\Support\Facades\Mail::to($email);
                $mail->send(new \App\Mail\ReceiptMail($firstReceipt, $pdfAttachments, $individualSubject, $individualMessage, true));

                foreach ($customerReceipts as $receipt) {
                    $receipt->update(['email_sent_at' => now()]);
                }
                $successCount++;

            } catch (\Exception $e) {
                Log::error('Receipt Bulk Email Sending Error (Customer '.$email.'): '.$e->getMessage());
                $errorCount += $customerReceipts->count();
            }
        }

        if ($errorCount > 0 && $successCount > 0) {
            return redirect()->back()->with('success', "Successfully sent $successCount emails, but some documents failed to send. Check logs.");
        } elseif ($errorCount > 0) {
            return redirect()->back()->with('error', 'Failed to send emails. Check logs for details.');
        } else {
            return redirect()->back()->with('success', "Successfully sent all $successCount emails.");
        }
    }

    public function generatePublicLink($id)
    {
        $receipt = \App\Models\Receipt::findOrFail($id);

        $url = URL::temporarySignedRoute(
            'public.receipt.view',
            now()->addDays(7),
            ['id' => $id]
        );

        return response()->json([
            'url' => $url,
        ]);
    }

    public function viewPublicDocument($id)
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
}
