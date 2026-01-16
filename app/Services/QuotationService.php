<?php

namespace App\Services;

use App\Helpers\FormatService;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationItemMaster;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuotationService
{
    protected $formatService, $quotationItemService;

    public function __construct(FormatService $formatService, QuotationItemService $quotationItemService)
    {
        $this->formatService = $formatService;
        $this->quotationItemService = $quotationItemService;
    }

    public function getForDataTable(array $filters = [])
    {
        $data = Quotation::with(['customer', 'maid', 'quotationItems', 'order'])
            ->when($filters['status'] ?? null, function ($q, $value) {
                $q->where('status', $value);
            })->when($filters['maid_id'] ?? null, function ($q, $value) {
                $q->where('maid_id', $value);
            })->when($filters['customer_id'] ?? null, function ($q, $value) {
                $q->where('customer_id', $value);
            })->when($filters['from_date'] ?? null, function ($q, $value) {
                $q->whereDate('created_at', '>=', $value);
            })->when($filters['to_date'] ?? null, function ($q, $value) {
                $q->whereDate('created_at', '<=', $value);
            })->orderBy('quotation_number', 'desc')->get()->map(function ($q) {
                return [
                    'id' => $q->id,
                    'quotation_number' => $q->quotation_number ?? '-',
                    'order_id' => $q->order->id ?? '-',
                    'order_number' => $q->order->order_number ?? '-',
                    'customer_id' => $q->customer_id,
                    'customer_number' => $q->customer->customer_number ?? '-',
                    'customer_name' => $q->customer->user->name ?? '-',
                    'maid_id' => $q->maid_id,
                    'maid_number' => $q->maid->maid_number ?? '-',
                    'maid_name' => $q->maid->name ?? '-',
                    'description' => $q->description ?? '-',
                    'quotation_date' => $q->quotation_date_formatted,
                    'expiry_date' => $q->expiry_date_formatted,
                    'commencement_date' => $q->commencement_date_formatted,
                    'monthly_salary' => $this->formatService->cleanDecimal($q->monthly_salary),
                    'loan_duration' => $this->formatService->cleanDecimal($q->loan_duration),
                    'rest_day_of_the_week' => json_decode($q->rest_day_of_the_week) ?? [],
                    'rest_days_per_month' => $q->rest_days_per_month ?? 0,
                    'compensation_off_in_lieu' => $this->formatService->cleanDecimal($q->compensation_off_in_lieu),
                    'items_count' => $q->quotationItems->count(),
                    'total_amount' => $this->formatService->cleanDecimal($q->total_amount),
                    'payment_plan' => $q->payment_plan_label,
                    'payment_method' => ucfirst($q->payment_method),
                    'status' => $q->status,
                    'reason' => $q->reason,
                    'have_invoices' => $q->order?->invoices()->exists() ?? false,
                    'created_at' => $q->created_at?->translatedFormat('d F Y'),
                    'updated_at' => $q->updated_at?->translatedFormat('d F Y'),
                ];
            });

        return $data;
    }

    public function getForFilter()
    {
        $data = Quotation::select('id', 'quotation_number')->get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->quotation_number,
            ];
        });

        return $data;
    }

    public function getCanCreateOrderForFilter()
    {
        $data = Quotation::select('id', 'quotation_number')->where('status', 'accepted')->whereDoesntHave('order')->get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->quotation_number,
            ];
        });

        return $data;
    }

    public function store(array $data = []): Quotation
    {
        return DB::transaction(function () use ($data) {
            if (!empty($data['quotation_date'])) {
                $data['quotation_date'] = Carbon::parse($data['quotation_date'])->format('Y-m-d');
            }
            if (!empty($data['expiry_date'])) {
                $data['expiry_date'] = Carbon::parse($data['expiry_date'])->format('Y-m-d');
            }
            if (!empty($data['commencement_date'])) {
                $data['commencement_date'] = Carbon::parse($data['commencement_date'])->format('Y-m-d');
            }

            $quotation = Quotation::create([
                'customer_id' => $data['customer_id'] ?? null,
                'maid_id' => $data['maid_id'] ?? null,
                'quotation_date' => $data['quotation_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'commencement_date' => $data['commencement_date'] ?? null,
                'monthly_salary' => $data['monthly_salary'] ?? null,
                'loan_duration' => $data['loan_duration'] ?? null,
                'rest_day_of_the_week' => json_encode($data['rest_day_of_the_week'] ?? []),
                'rest_days_per_month' => $data['rest_days_per_month'] ?? null,
                'compensation_off_in_lieu' => $data['compensation_off_in_lieu'] ?? null,
                'payment_plan' => $data['payment_plan'] ?? 'full',
                'payment_method' => $data['payment_method'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'reason' => $data['reason'] ?? null,
            ]);

            // Update maid status when quotation is created with sent status
            if (isset($data['maid_id']) && ($data['status'] ?? 'draft') === 'sent') {
                $maid = \App\Models\Maid::find($data['maid_id']);
                Log::info('Quotation store - checking maid status', [
                    'maid_id' => $data['maid_id'],
                    'quotation_status' => $data['status'] ?? 'draft',
                    'maid_current_status' => $maid?->status,
                ]);
                if ($maid && in_array($maid->status, ['available', 'interviewing', 'pending'])) {
                    $maid->update(['status' => 'assigned']);
                    $maid->refresh();
                    if ($maid->status !== 'assigned') {
                        DB::table('maids')->where('id', $maid->id)->update([
                            'status' => 'assigned',
                            'updated_at' => now(),
                        ]);
                        $maid = $maid->fresh();
                        Log::warning('Eloquent update blocked; forced DB update to assigned', [
                            'maid_id' => $maid->id,
                            'final_status' => $maid->status,
                        ]);
                    } else {
                        Log::info('Maid status updated to assigned', [
                            'maid_id' => $maid->id,
                            'new_status' => 'assigned'
                        ]);
                    }
                }
            }

            if (!empty($data['items']) && is_array($data['items'])) {
                $this->quotationItemService->storeQuotationItems($quotation->id, $data['items']);
            }

            return $quotation;
        });
    }

    public function getForEditShow($id): array
    {
        $quotation = Quotation::with(['customer.user', 'maid', 'quotationItems'])->findOrFail($id);

        return [
            'id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'customer_id' => $quotation->customer_id,
            'customer_number' => $quotation->customer->customer_number ?? '',
            'customer_name' => $quotation->customer->user->name ?? '',
            'nric_number' => $quotation->customer->nric_number ?? '',
            'customer_contact' => $quotation->customer->user->contact ?? '',
            'customer_address' => $quotation->customer->address ?? '',
            'customer_email' => $quotation->customer->user->email ?? '',
            'maid_id' => $quotation->maid_id,
            'maid_number' => $quotation->maid->maid_number ?? '',
            'maid_name' => $quotation->maid->name ?? '',
            'passport_number' => $quotation->maid->passport_number ?? '',
            'description' => $quotation->description ?? '',
            'quotation_date' => $quotation->quotation_date_formatted,
            'expiry_date' => $quotation->expiry_date_formatted,
            'commencement_date' => $quotation->commencement_date_formatted,
            'monthly_salary' => $this->formatService->cleanDecimal($quotation->monthly_salary),
            'loan_duration' => $this->formatService->cleanDecimal($quotation->loan_duration),
            'rest_day_of_the_week' => json_decode($quotation->rest_day_of_the_week ?? '[]', true),
            'rest_days_per_month' => $quotation->rest_days_per_month,
            'compensation_off_in_lieu' => $this->formatService->cleanDecimal($quotation->compensation_off_in_lieu),
            'total_amount' => $this->formatService->cleanDecimal($quotation->total_amount),
            'payment_plan' => $quotation->payment_plan,
            'payment_method' => $quotation->payment_method,
            'status' => $quotation->status,
            'reason' => $quotation->reason,
            'model' => 'quotation',
            'notes' => $quotation->quotationNotes->sortBy('sort_order')->values()->toArray(),
            'items' => $quotation->quotationItems->sortBy('sort_order')->map(function (QuotationItem $it) {
                return [
                    'id' => $it->id,
                    'parent_id' => $it->parent_id,
                    'description' => $it->description,
                    'is_header' => $it->is_header,
                    'is_optional' => $it->is_optional,
                    'is_placement_fee' => $it->is_placement_fee,
                    'quantity' => $this->formatService->cleanDecimal($it->quantity),
                    'rate' => $this->formatService->cleanDecimal($it->rate),
                    'sort_order' => $it->sort_order,
                ];
            })->values()->toArray(),
        ];
    }

    public function update(array $data, int $id): Quotation
    {
        return DB::transaction(function () use ($data, $id) {
            $quotation = Quotation::with('maid')->findOrFail($id);
            $oldStatus = $quotation->status;

            if (!empty($data['quotation_date'])) {
                $data['quotation_date'] = Carbon::parse($data['quotation_date'])->format('Y-m-d');
            }
            if (!empty($data['expiry_date'])) {
                $data['expiry_date'] = Carbon::parse($data['expiry_date'])->format('Y-m-d');
            }
            if (!empty($data['commencement_date'])) {
                $data['commencement_date'] = Carbon::parse($data['commencement_date'])->format('Y-m-d');
            }

            $quotation->update([
                'customer_id' => $data['customer_id'] ?? null,
                'maid_id' => $data['maid_id'] ?? null,
                'quotation_date' => $data['quotation_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'commencement_date' => $data['commencement_date'] ?? null,
                'monthly_salary' => $data['monthly_salary'] ?? null,
                'loan_duration' => $data['loan_duration'] ?? null,
                'rest_day_of_the_week' => json_encode($data['rest_day_of_the_week'] ?? []),
                'rest_days_per_month' => $data['rest_days_per_month'] ?? null,
                'compensation_off_in_lieu' => $data['compensation_off_in_lieu'] ?? null,
                'payment_plan' => $data['payment_plan'] ?? 'full',
                'payment_method' => $data['payment_method'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? $quotation->status,
                'reason' => $data['reason'] ?? $quotation->reason,
            ]);

            if (!isset($data['status']) || $data['status'] !== 'sent') {
                if (!$quotation->maid) {
                    Log::warning('Quotation update set to sent but no maid linked', [
                        'quotation_id' => $id,
                    ]);
                }
            }
            if ($oldStatus !== 'sent' && ($data['status'] ?? null) === 'sent' && $quotation->maid) {
                Log::info('Quotation update - status changing to sent', [
                    'quotation_id' => $id,
                    'old_status' => $oldStatus,
                    'new_status' => $data['status'] ?? null,
                    'maid_id' => $quotation->maid->id,
                    'maid_current_status' => $quotation->maid->status,
                ]);
                if (in_array($quotation->maid->status, ['available', 'interviewing', 'pending'])) {
                    $quotation->maid->update(['status' => 'assigned']);
                    $quotation->maid->refresh();
                    if ($quotation->maid->status !== 'assigned') {
                        DB::table('maids')->where('id', $quotation->maid->id)->update([
                            'status' => 'assigned',
                            'updated_at' => now(),
                        ]);
                        Log::warning('Eloquent update blocked; forced DB update to assigned (update path)', [
                            'maid_id' => $quotation->maid->id,
                        ]);
                    } else {
                        Log::info('Maid status updated to assigned via update', [
                            'maid_id' => $quotation->maid->id,
                            'new_status' => 'assigned'
                        ]);
                    }
                }
            }

            if (array_key_exists('items', $data) && is_array($data['items'])) {
                $this->quotationItemService->replaceQuotationItems($quotation->id, $data['items']);
            }

            return $quotation->fresh('maid');
        });
    }

    public function accept($id)
    {
        return DB::transaction(function () use ($id) {
            $quotation = Quotation::findOrFail($id);
            $quotation->update(['status' => 'accepted']);

            return $quotation;
        });
    }

    public function converted($id)
    {
        return DB::transaction(function () use ($id) {
            $quotation = Quotation::findOrFail($id);
            $quotation->update(['status' => 'converted']);

            return $quotation;
        });
    }

    public function reject($data = [], $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $quotation = Quotation::with('maid')->findOrFail($id);

            $quotation->update([
                'status' => 'rejected',
                'reason' => $data['reason'] ?? null,
            ]);

            if ($quotation->maid && $quotation->maid->status === 'assigned') {
                $quotation->maid->update(['status' => 'available']);
            }

            return $quotation;
        });
    }

    public function ready($id)
    {
        return DB::transaction(function () use ($id) {
            $quotation = Quotation::with('maid')->findOrFail($id);
            $quotation->update([
                'status' => 'sent'
            ]);

            if (!$quotation->maid) {
                Log::warning('Quotation sent called but no maid linked', [
                    'quotation_id' => $id,
                ]);
            }
            if ($quotation->maid) {
                Log::info('Quotation sent - updating maid status', [
                    'quotation_id' => $id,
                    'maid_id' => $quotation->maid->id,
                    'maid_current_status' => $quotation->maid->status,
                ]);
                if (in_array($quotation->maid->status, ['available', 'interviewing', 'pending'])) {
                    $quotation->maid->update(['status' => 'assigned']);
                    $quotation->maid->refresh();
                    if ($quotation->maid->status !== 'assigned') {
                        DB::table('maids')->where('id', $quotation->maid->id)->update([
                            'status' => 'assigned',
                            'updated_at' => now(),
                        ]);
                        Log::warning('Eloquent update blocked; forced DB update to assigned (sent path)', [
                            'maid_id' => $quotation->maid->id,
                        ]);
                    } else {
                        Log::info('Maid status updated to assigned via sent', [
                            'maid_id' => $quotation->maid->id,
                            'new_status' => 'assigned'
                        ]);
                    }
                }
            }

            return $quotation->fresh('maid');
        });
    }

    public function expire($id)
    {
        return DB::transaction(function () use ($id) {
            $quotation = Quotation::findOrFail($id);

            $quotation->update([
                'status' => 'expired'
            ]);

            return $quotation;
        });
    }

    public function delete($id)
    {
        $quotation = Quotation::find($id);
        if (!$quotation) {
            return false;
        }

        return $quotation->delete();
    }
}
