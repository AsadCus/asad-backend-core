<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkSendEmailRequest;
use App\Http\Requests\SendEmailRequest;
use App\Mail\QuotationMail;
use App\Models\Quotation;
use App\Rules\NoteRule;
use App\Rules\QuotationRule;
use App\Services\CustomerService;
use App\Services\NoteService;
use App\Services\QuotationItemService;
use App\Services\QuotationService;
use App\Services\Report\ReportTemplateService;
use App\Services\SalesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
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
        $filters = [];

        $data['quotationsForDatatable'] = $this->quotationService->getForDataTable($filters);
        $data['customers'] = $this->customerService->getForFilter();
        $data['salespersons'] = $this->salesService->getForQuotationAssignment();

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
        $data['salespersons'] = $this->salesService->getForQuotationAssignment();

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
        $data['salespersons'] = $this->salesService->getForQuotationAssignment();

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
        $quotationModel = Quotation::with('customerConfirmation.package')->findOrFail($id);
        $forceCountryId = $quotationModel->customerConfirmation?->package?->country_id ?? $quotationModel->country_id;
        $forceBranchId = $quotationModel->branch_id;
        $includeUserId = $quotationModel->handled_by;

        $data['salespersons'] = $this->salesService->getForQuotationAssignment(
            null,
            $forceCountryId ? (int) $forceCountryId : null,
            $forceBranchId ? (int) $forceBranchId : null,
            $includeUserId ? (int) $includeUserId : null,
        );

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

    public function handle(Request $request, $id)
    {
        $validated = $request->validate([
            'salesperson_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $quotation = $this->quotationService->handleAssignment(
            (int) $id,
            isset($validated['salesperson_id']) ? (int) $validated['salesperson_id'] : null,
        );

        activity()
            ->performedOn($quotation)
            ->withProperties([
                'subject_type' => 'Quotation',
                'subject_id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'assigned_to' => $quotation->handled_by,
            ])
            ->log('Quotation assigned to salesperson #'.$quotation->handled_by.' for quotation #'.$quotation->quotation_number);

        return redirect()->route('quotation.index')->with('success', 'Quotation handled successfully.');
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

        return redirect()->route('order.create', ['quotation_id' => $request['quotation_id']])->with('success', 'Quotation accepted successfully.');
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
            'installment' => 'Instalment',
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
                'installment' => 'Instalment',
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

    public function getEmailData($id)
    {
        $quotation = Quotation::with('customer.user')->findOrFail($id);

        $customerEmail = $quotation->customer?->user?->email;
        $customerName = $quotation->customer?->user?->name ?? 'Customer';

        $templates = [
            [
                'value' => 'quotation_send',
                'label' => 'Quotation Send',
                'message' => "Here is your quotation {$quotation->quotation_number} from ".config('app.name').".\n\nPlease find the PDF attached to this email. If you have any questions or would like to proceed, please let us know.",
            ],
            [
                'value' => 'quotation_followup',
                'label' => 'Quotation Follow Up',
                'message' => "We are following up on the quotation {$quotation->quotation_number} sent to you earlier.\n\nPlease find the PDF attached to this email. We look forward to hearing from you soon.",
            ],
        ];

        return response()->json([
            'to' => $customerEmail ?? '',
            'cc' => '',
            'subject' => 'Quotation '.$quotation->quotation_number.' from '.config('app.name'),
            'templates' => $templates,
            'default_template' => 'quotation_send',
            'customer_name' => $customerName,
        ]);
    }

    public function previewEmail(SendEmailRequest $request, $id)
    {
        $quotation = Quotation::with('customer.user')->findOrFail($id);

        $html = view('mail.quotation-email', [
            'quotation' => $quotation,
            'customMessage' => $request->input('message'),
        ])->render();

        return response()->json([
            'html' => $html,
        ]);
    }

    public function sendEmail(SendEmailRequest $request, $id)
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
                'installment' => 'Instalment',
                default => ucfirst($paymentPlan),
            };

            $data['payment_plan_label'] = $paymentPlanLabel;

            $html = view('quotations.report-content', [
                'data' => $data,
                'items' => $this->sortForPdf($data['items'] ?? []),
                'branding' => $reportData['branding'],
                'is_pdf' => true,
            ])->render();

            $pdf = Pdf::loadHTML($html)
                ->setPaper('a4')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96);

            $quotation = Quotation::with('customer.user')->findOrFail($id);
            $pdfContent = $pdf->output();

            $pdfAttachments = [
                ['content' => $pdfContent, 'filename' => $quotation->quotation_number.'.pdf'],
            ];

            $to = $request->input('to');
            $cc = $request->input('cc');
            $subject = $request->input('subject');
            $messageBody = $request->input('message');

            $mail = Mail::to($to);

            if ($cc) {
                $ccList = array_map('trim', explode(',', $cc));
                $mail->cc($ccList);
            }

            $mail->send(new QuotationMail($quotation, $pdfAttachments, $subject, $messageBody, false));

            $quotation->update(['email_sent_at' => now()]);

            return redirect()->back()->with('success', 'Email sent to '.$to.' successfully.');
        } catch (\Exception $e) {
            Log::error('Quotation Email Sending Error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to send quotation email: '.$e->getMessage());
        }
    }

    public function getBulkEmailData(Request $request)
    {
        $ids = array_map('trim', explode(',', $request->query('ids', '')));
        $ids = array_filter($ids, 'is_numeric');

        if (empty($ids)) {
            return response()->json(['error' => 'No valid IDs provided'], 400);
        }

        $quotations = Quotation::with('customer.user')->whereIn('id', $ids)->get();

        $groupedQuotations = $quotations->groupBy(function ($quotation) {
            return $quotation->customer?->user?->email ?? 'unknown';
        });

        $recipientGroups = [];
        foreach ($groupedQuotations as $email => $customerQuotations) {
            if ($email === 'unknown' || empty($email)) {
                continue;
            }
            $recipientGroups[] = [
                'email' => $email,
                'name' => $customerQuotations->first()->customer?->user?->name ?? 'Customer',
                'documents' => $customerQuotations->pluck('quotation_number')->toArray(),
            ];
        }

        $firstQuotation = $quotations->first();
        $customerName = $firstQuotation->customer?->user?->name ?? 'Customer';

        $templates = [
            [
                'value' => 'quotation_send',
                'label' => 'Quotation Send',
                'message' => 'Here is your quotation from '.config('app.name').".\n\nPlease find the PDF attached to this email. If you have any questions or would like to proceed, please let us know.",
            ],
            [
                'value' => 'quotation_followup',
                'label' => 'Quotation Follow Up',
                'message' => "We are following up on the quotation sent to you earlier.\n\nPlease find the PDF attached to this email. We look forward to hearing from you soon.",
            ],
        ];

        return response()->json([
            'to' => '', // Read-only badge shown in UI for bulk
            'cc' => '',
            'subject' => 'Quotation from '.config('app.name'),
            'templates' => $templates,
            'default_template' => 'quotation_send',
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
        $quotation = Quotation::with('customer.user')->findOrFail($firstId);

        $html = view('mail.quotation-email', [
            'quotation' => $quotation,
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

        $quotations = Quotation::with('customer.user')->whereIn('id', $ids)->get();
        $groupedQuotations = $quotations->groupBy(function ($quotation) {
            return $quotation->customer?->user?->email;
        });

        foreach ($groupedQuotations as $email => $customerQuotations) {
            if (empty($email)) {
                foreach ($customerQuotations as $quo) {
                    Log::warning('Skipping bulk email for quotation '.$quo->id.': no customer email.');
                    $errorCount++;
                }

                continue;
            }

            try {
                $pdfAttachments = [];
                $firstQuotation = $customerQuotations->first();

                foreach ($customerQuotations as $quotation) {
                    $data = $this->quotationService->getForEditShow($quotation->id);
                    $reportData = $this->reportTemplateService->build('quotation', $data);

                    $paymentPlan = $data['payment_plan'] ?? 'full';
                    $paymentPlanLabel = match ($paymentPlan) {
                        'direct' => 'Direct',
                        'full' => 'Full Payment',
                        'installment' => 'Instalment',
                        default => ucfirst($paymentPlan),
                    };

                    $data['payment_plan_label'] = $paymentPlanLabel;

                    $html = view('quotations.report-content', [
                        'data' => $data,
                        'items' => $this->sortForPdf($data['items'] ?? []),
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
                        'filename' => $quotation->quotation_number.'.pdf',
                    ];
                }

                $individualSubject = str_replace('{quotation_number}', $customerQuotations->pluck('quotation_number')->implode(', '), $subject);
                $individualMessage = str_replace('{quotation_number}', $customerQuotations->pluck('quotation_number')->implode(', '), $messageBody);

                $mail = Mail::to($email);
                $mail->send(new QuotationMail($firstQuotation, $pdfAttachments, $individualSubject, $individualMessage, true));

                foreach ($customerQuotations as $quotation) {
                    $quotation->update(['email_sent_at' => now()]);
                }
                $successCount++;

            } catch (\Exception $e) {
                Log::error('Quotation Bulk Email Sending Error (Customer '.$email.'): '.$e->getMessage());
                $errorCount += $customerQuotations->count();
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
        $quotation = Quotation::findOrFail($id);

        $url = URL::temporarySignedRoute(
            'public.quotation.view',
            now()->addDays(7),
            ['id' => $id]
        );

        return response()->json([
            'url' => $url,
        ]);
    }

    public function viewPublicDocument($id)
    {
        $data = $this->quotationService->getForEditShow($id);
        $reportData = $this->reportTemplateService->build('quotation', $data);

        $paymentPlan = $data['payment_plan'] ?? 'full';
        $paymentPlanLabel = match ($paymentPlan) {
            'direct' => 'Direct',
            'full' => 'Full Payment',
            'installment' => 'Instalment',
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
}
