<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkSendEmailRequest;
use App\Http\Requests\SendEmailRequest;
use App\Rules\InvoiceRule;
use App\Services\CustomerService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\QuotationService;
use App\Services\Report\ReportTemplateService;
use App\Services\SalesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
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
        $filters = [];

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

    /**
     * Delete existing receipt(s) for an invoice so receipt can be recreated.
     */
    public function recreateReceipt(Request $request, string $id)
    {
        $this->invoiceService->recreateReceipt((int) $id);

        return back()->with(
            'success',
            'Receipt deleted successfully. Invoice status has been synchronized.',
        );
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

    public function getEmailData($id)
    {
        $invoice = \App\Models\Invoice::with('order.quotation.customer.user')->findOrFail($id);

        $customerEmail = $invoice->order?->quotation?->customer?->user?->email;
        $customerName = $invoice->order?->quotation?->customer?->user?->name ?? 'Customer';

        $templates = [
            [
                'value' => 'invoice_send',
                'label' => 'Invoice Send',
                'message' => "Here is your invoice {$invoice->invoice_number} from ".config('app.name').".\n\nPlease find the PDF attached to this email, or you can download it via the link below.",
            ],
            [
                'value' => 'payment_reminder',
                'label' => 'Payment Reminder',
                'message' => "This is a friendly reminder that your invoice {$invoice->invoice_number} is currently outstanding.\n\nPlease find the PDF attached to this email. We appreciate your prompt payment.",
            ],
        ];

        return response()->json([
            'to' => $customerEmail ?? '',
            'cc' => '',
            'subject' => 'Invoice '.$invoice->invoice_number.' from '.config('app.name'),
            'templates' => $templates,
            'default_template' => 'invoice_send',
            'customer_name' => $customerName,
        ]);
    }

    public function previewEmail(SendEmailRequest $request, $id)
    {
        $invoice = \App\Models\Invoice::with('order.quotation.customer.user')->findOrFail($id);

        $html = view('mail.invoice-email', [
            'invoice' => $invoice,
            'customMessage' => $request->input('message'),
        ])->render();

        return response()->json([
            'html' => $html,
        ]);
    }

    public function sendEmail(SendEmailRequest $request, $id)
    {
        try {
            $invoiceArray = $this->invoiceService->getForEditShow($id);
            $reportData = $this->reportTemplateService->build('invoice', $invoiceArray);

            $html = view('invoices.report-content', [
                'data' => $invoiceArray,
                'items' => $invoiceArray['items'],
                'branding' => $reportData['branding'],
                'is_pdf' => true,
            ])->render();

            $pdf = Pdf::loadHTML($html)
                ->setPaper('a4')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96);

            $invoice = \App\Models\Invoice::with('order.quotation.customer.user')->findOrFail($id);
            $pdfContent = $pdf->output();

            $pdfAttachments = [
                ['content' => $pdfContent, 'filename' => 'invoice_'.$invoice->invoice_number.'.pdf'],
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

            $mail->send(new \App\Mail\InvoiceMail($invoice, $pdfAttachments, $subject, $messageBody, false));

            $invoice->update(['email_sent_at' => now()]);

            return redirect()->back()->with('success', 'Email sent to '.$to.' successfully.');
        } catch (\Exception $e) {
            Log::error('Invoice Email Sending Error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to send invoice email: '.$e->getMessage());
        }
    }

    public function getBulkEmailData(Request $request)
    {
        $ids = array_map('trim', explode(',', $request->query('ids', '')));
        $ids = array_filter($ids, 'is_numeric');

        if (empty($ids)) {
            return response()->json(['error' => 'No valid IDs provided'], 400);
        }

        $invoices = \App\Models\Invoice::with('order.quotation.customer.user')->whereIn('id', $ids)->get();

        $groupedInvoices = $invoices->groupBy(function ($invoice) {
            return $invoice->order?->quotation?->customer?->user?->email ?? 'unknown';
        });

        $recipientGroups = [];
        foreach ($groupedInvoices as $email => $customerInvoices) {
            if ($email === 'unknown' || empty($email)) {
                continue;
            }
            $recipientGroups[] = [
                'email' => $email,
                'name' => $customerInvoices->first()->order?->quotation?->customer?->user?->name ?? 'Customer',
                'documents' => $customerInvoices->pluck('invoice_number')->toArray(),
            ];
        }

        $firstInvoice = $invoices->first();
        $customerName = $firstInvoice->order?->quotation?->customer?->user?->name ?? 'Customer';

        $templates = [
            [
                'value' => 'invoice_send',
                'label' => 'Invoice Send',
                'message' => 'Here is your invoice from '.config('app.name').".\n\nPlease find the PDF attached to this email, or you can download it via the link below.",
            ],
            [
                'value' => 'payment_reminder',
                'label' => 'Payment Reminder',
                'message' => "This is a friendly reminder that your invoice is currently outstanding.\n\nPlease find the PDF attached to this email. We appreciate your prompt payment.",
            ],
        ];

        return response()->json([
            'to' => '', // Read-only badge shown in UI for bulk
            'cc' => '',
            'subject' => 'Invoice from '.config('app.name'),
            'templates' => $templates,
            'default_template' => 'invoice_send',
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
        $invoice = \App\Models\Invoice::with('order.quotation.customer.user')->findOrFail($firstId);

        $html = view('mail.invoice-email', [
            'invoice' => $invoice,
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

        $invoices = \App\Models\Invoice::with('order.quotation.customer.user')->whereIn('id', $ids)->get();
        $groupedInvoices = $invoices->groupBy(function ($invoice) {
            return $invoice->order?->quotation?->customer?->user?->email;
        });

        foreach ($groupedInvoices as $email => $customerInvoices) {
            if (empty($email)) {
                foreach ($customerInvoices as $inv) {
                    Log::warning('Skipping bulk email for invoice '.$inv->id.': no customer email.');
                    $errorCount++;
                }

                continue;
            }

            try {
                $pdfAttachments = [];
                $firstInvoice = $customerInvoices->first();

                foreach ($customerInvoices as $invoice) {
                    $invoiceArray = $this->invoiceService->getForEditShow($invoice->id);
                    $reportData = $this->reportTemplateService->build('invoice', $invoiceArray);

                    $html = view('invoices.report-content', [
                        'data' => $invoiceArray,
                        'items' => $invoiceArray['items'],
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
                        'filename' => 'invoice_'.$invoice->invoice_number.'.pdf',
                    ];
                }

                $individualSubject = str_replace('{invoice_number}', $customerInvoices->pluck('invoice_number')->implode(', '), $subject);
                $individualMessage = str_replace('{invoice_number}', $customerInvoices->pluck('invoice_number')->implode(', '), $messageBody);

                $mail = \Illuminate\Support\Facades\Mail::to($email);
                $mail->send(new \App\Mail\InvoiceMail($firstInvoice, $pdfAttachments, $individualSubject, $individualMessage, true));

                foreach ($customerInvoices as $invoice) {
                    $invoice->update(['email_sent_at' => now()]);
                }
                $successCount++;

            } catch (\Exception $e) {
                Log::error('Invoice Bulk Email Sending Error (Customer '.$email.'): '.$e->getMessage());
                $errorCount += $customerInvoices->count();
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
        $invoice = \App\Models\Invoice::findOrFail($id);

        $url = URL::temporarySignedRoute(
            'public.invoice.view',
            now()->addDays(7),
            ['id' => $id]
        );

        return response()->json([
            'url' => $url,
        ]);
    }

    public function viewPublicDocument($id)
    {
        // View invoice publicly via signed URL
        $invoice = $this->invoiceService->getForEditShow($id);
        $reportData = $this->reportTemplateService->build('invoice', $invoice);

        return view('invoices.report-content', [
            'data' => $invoice,
            'items' => $invoice['items'],
            'branding' => $reportData['branding'],
            'is_pdf' => false,
        ]);
    }
}
