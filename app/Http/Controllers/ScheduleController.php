<?php

namespace App\Http\Controllers;

use App\Helpers\FormatService;
use App\Models\Quotation;
use App\Services\PaymentScheduleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ScheduleController extends Controller
{
    protected $formatService;

    public function __construct(FormatService $formatService)
    {
        $this->formatService = $formatService;
    }

    public function index()
    {
        $quotations = Quotation::with(['customer.user', 'order'])
            ->whereIn('status', ['accepted', 'converted'])
            ->whereNotNull('customer_id')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($q) => $this->mapQuotationToSchedule($q))
            ->filter()
            ->values();

        return Inertia::render('schedules/index', [
            'data' => $quotations,
        ]);
    }

    public function exportPdf($quotationId)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $quotation = Quotation::with(['customer.user', 'order'])
                ->findOrFail($quotationId);

            $schedule = $this->mapQuotationToSchedule($quotation);

            if (! $schedule) {
                abort(404, 'Invalid schedule data');
            }

            $html = view('schedules.pdf', [
                'schedule' => $schedule,
                'company_address' => config('app.company_address', '931 Yishun Central 1'),
                'registration_no' => config('app.company_registration_no', 'R25128539'),
                'license_no' => config('app.company_license_no', '25C2708'),
            ])->render();

            return Pdf::loadHTML($html)
                ->setPaper('a4')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('dpi', 96)
                ->stream(($schedule['schedule_number'] ?? 'schedule').'.pdf');
        } catch (\Throwable $e) {
            Log::error('Schedule PDF error', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to generate schedule PDF: '.$e->getMessage()], 500);
        }
    }

    private function mapQuotationToSchedule(Quotation $quotation): ?array
    {
        if (
            ! $quotation->customer ||
            ! $quotation->customer->user
        ) {
            return null;
        }

        $paymentScheduleService = app(PaymentScheduleService::class);

        $days = json_decode($quotation->rest_day_of_the_week) ?? [];
        $map = ['Weekend' => ['Saturday', 'Sunday']];
        $mapped = array_reduce($days, function ($carry, $d) use ($map) {
            return array_merge($carry, $map[$d] ?? [$d]);
        }, []);
        $resultDays = implode(', ', $mapped);

        return [
            'id' => $quotation->id,
            'quotation_id' => $quotation->id,
            'schedule_number' => "SCH-{$quotation->id}-".$quotation->created_at->format('Ymd'),
            'sales_registration_number' => $quotation->sales_registration_number,

            'quotation' => [
                'id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'commencement_date' => $quotation->commencement_date_formatted,
                'customer' => [
                    'id' => $quotation->customer->id,
                    'nric_number' => $quotation->customer->nric_number,
                    'user' => [
                        'id' => $quotation->customer->user->id,
                        'name' => $quotation->customer->user->name,
                        'email' => $quotation->customer->user->email,
                    ],
                ],
                'order' => $quotation->order
                    ? [
                        'id' => $quotation->order->id,
                        'order_number' => $quotation->order->order_number,
                        'handover_date' => $quotation->order->handover_date_formatted,
                    ]
                    : null,
            ],

            'customer_name' => $quotation->customer->user->name,

            'monthly_salary' => $this->formatService->cleanDecimal($quotation->monthly_salary),
            'loan_amount' => $this->formatService->cleanDecimal($quotation->total_placement_fee),
            'loan_duration_months' => $this->formatService->cleanDecimal($quotation->total_placement_quantity),
            'monthly_loan_payment' => $this->formatService->cleanDecimal($quotation->monthly_placement_fee),

            'rest_day_of_the_week' => $resultDays,
            'rest_days_per_month' => $quotation->rest_days_per_month,
            'compensation_off_in_lieu' => $this->formatService->cleanDecimal($quotation->compensation_off_in_lieu),

            'breakdown' => $paymentScheduleService->generatePaymentSchedule($quotation),

            'status' => $quotation->status,
            'is_active' => true,
        ];
    }
}
