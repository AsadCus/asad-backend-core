<?php

namespace App\Http\Controllers;

use App\Helpers\FormatService;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AgreementController extends Controller
{
    protected $formatService;

    public function __construct(FormatService $formatService)
    {
        $this->formatService = $formatService;
    }

    public function index()
    {
        $quotations = Quotation::with([
            'customer.user',
            'order',
        ])
            ->whereIn('status', ['accepted', 'converted'])
            ->whereNotNull('customer_id')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($q) => $this->mapQuotationToAgreement($q))
            ->filter()
            ->values();

        return Inertia::render('agreements/index', [
            'data' => $quotations,
        ]);
    }

    public function exportPdf($quotationId)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $quotation = Quotation::with([
                'customer.user',
                'order',
                'quotationItems',
            ])->findOrFail($quotationId);

            $agreement = $this->mapQuotationToAgreement($quotation);

            if (! $agreement) {
                abort(404, 'Invalid agreement data');
            }

            $html = view('agreements.pdf', [
                'agreement' => $agreement,
            ])->render();

            return Pdf::loadHTML($html)
                ->setPaper('a4')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->stream($agreement['agreement_number'].'.pdf');
        } catch (\Throwable $e) {
            Log::error('Agreement PDF error', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to generate PDF: '.$e->getMessage()], 500);
        }
    }

    private function mapQuotationToAgreement(Quotation $quotation): ?array
    {
        if (
            ! $quotation->customer ||
            ! $quotation->customer->user
        ) {
            return null;
        }

        return [
            'id' => $quotation->id,
            'agreement_number' => 'AGR-'.$quotation->id.'-'.$quotation->created_at->format('Ymd'),
            'sales_registration_number' => $quotation->sales_registration_number,
            'quotation' => [
                'id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'order' => $quotation->order
                    ? [
                        'id' => $quotation->order->id,
                        'order_number' => $quotation->order->order_number,
                    ]
                    : null,
            ],
            'agreement_date' => $quotation->quotation_date_formatted,
            'customer_name' => $quotation->customer->user->name,
            'customer_nric' => $quotation->customer->nric_number,
            'maid_name' => '-',
            'maid_passport' => '-',
            'monthly_salary' => 0,
            'loan_amount' => 0,
            'loan_duration_months' => 0,
            'monthly_loan_payment' => 0,
            'late_payment_interest_amount' => 0,
            'placement_fee_invoices' => [],
            'status' => $quotation->status?->value,
        ];
    }
}
