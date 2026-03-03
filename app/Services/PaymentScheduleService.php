<?php

namespace App\Services;

use App\Models\Quotation;
use Carbon\Carbon;

class PaymentScheduleService
{
    public function generatePaymentSchedule(Quotation $quotation): array
    {
        $startDate = $quotation->commencement_date ?? $quotation->created_at;
        $startDate = Carbon::parse($startDate);

        $monthlySalary = (float) ($quotation->monthly_salary ?? 0);
        $loanDuration = (float) ($quotation->loan_duration ?? 0);
        $compensationOff = (float) ($quotation->compensation_off_in_lieu ?? 0) * (4 - ($quotation->rest_days_per_month <= 4 ? $quotation->rest_days_per_month : 4));
        $originalPaymentDay = (int) $startDate->format('d');

        $fullLoanMonths = (int) floor($loanDuration);
        $partialMonthFraction = max(0, $loanDuration - $fullLoanMonths);

        $schedule = [];

        for ($i = 0; $i < 24; $i++) {
            $periodDate = Carbon::create($startDate->year, $startDate->month, 1)->addMonths($i + 1);

            $lastDayOfMonth = (int) $periodDate->copy()->endOfMonth()->format('d');

            $actualPaymentDay = min($originalPaymentDay, $lastDayOfMonth);

            $periodDate->day($actualPaymentDay);

            $loanPayment = 0.0;
            $salary = $monthlySalary;

            if ($i < $fullLoanMonths) {
                $loanPayment = $monthlySalary;
                $salary = 0.0;
            } elseif ($i === $fullLoanMonths && $partialMonthFraction > 0) {
                $loanPayment = round($monthlySalary * $partialMonthFraction, 2);
                $salary = round($monthlySalary * (1 - $partialMonthFraction), 2);
            }

            $schedule[] = [
                'month' => $i + 1,
                'day' => $actualPaymentDay,
                'month_name' => $periodDate->format('F Y'),
                'salary' => round($salary, 2),
                'loan_payment' => round($loanPayment, 2),
                'amount' => round($loanPayment, 2),
                'compensation_off' => round($compensationOff, 2),
                'total_payment' => round($salary + $compensationOff, 2),
                'due_date' => $periodDate->format('Y-m-d'),
                'remarks' => $i === 0 ? 'Paid upon Handover' : '',
            ];
        }

        return $schedule;
    }
}
