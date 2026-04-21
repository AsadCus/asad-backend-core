<?php

namespace App\Services;

use App\Enums\EnquiryStatus;
use App\Enums\QuotationStatus;
use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\ManifestRoom;
use App\Models\ManifestSharingGroup;
use App\Models\ModelFile;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationItemTax;
use App\Models\QuotationNotes;
use App\Models\Receipt;
use App\Models\User;
use App\Support\DataScope;
use App\Support\InvoiceStatus;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CustomerConfirmationService
{
    public function __construct(
        private NoteService $noteService,
        private NumberingService $numberingService,
        private PackageSeatService $packageSeatService,
    ) {}

    public function isAutoBillingSyncEnabled(): bool
    {
        return (bool) config('customer_confirmation.auto_sync_billing_mutations', true);
    }

    /** Create a customer confirmation from request data. */
    public function createGroup(array $data): CustomerConfirmation
    {
        return DB::transaction(function () use ($data) {
            $enquiryId = $data['enquiry_id'] ?? null;
            $enquiry = null;
            $isPrivateEnquiry = false;

            if ($enquiryId) {
                $enquiry = Enquiry::findOrFail($enquiryId);
                $isPrivateEnquiry = strtolower((string) ($enquiry->type ?? '')) === 'private';
            }

            $resolvedPackageId = (int) ($data['package_id'] ?? ($enquiryId ? ($enquiry?->package_id ?? null) : null) ?? 0);
            $existingEnquiryPackageId = (int) ($enquiry?->package_id ?? 0);

            if ($resolvedPackageId > 0 && $resolvedPackageId !== $existingEnquiryPackageId) {
                $this->assertPackageSelectionAllowed($resolvedPackageId, $isPrivateEnquiry);
            }

            $group = CustomerConfirmation::create([
                'number' => $this->numberingService->ensureNumber(
                    'customer_confirmation',
                    $data['number'] ?? null,
                    null,
                    isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                ),
                'enquiry_id' => $enquiryId,
                'created_by' => auth()->id(),
                'package_id' => $resolvedPackageId > 0 ? $resolvedPackageId : null,
                'package_room_type' => $data['package_room_type'] ?? null,
                'date_of_application' => $data['date_of_application'] ?? null,
            ]);

            foreach ($data['members'] as $member) {
                $customer = $this->findOrCreateCustomer($member);
                $this->processFileUploads($customer, $member);

                CustomerConfirmationMember::create([
                    'customer_confirmation_id' => $group->id,
                    'customer_id' => $customer->id,
                    'is_leader' => (bool) ($member['is_leader'] ?? false),
                    'status' => $this->normalizePaymentStatus($member['status'] ?? null),
                    'sharing_plan' => $member['sharing_plan'] ?? null,
                    'relationship' => $member['relationship'] ?? $member['role'] ?? null,
                ]);
            }

            $group->load('members.customer.user', 'enquiry', 'package');

            $newSnapshot = $this->sanitizeSnapshot(
                $this->buildGroupSnapshot($group),
            );

            activity()
                ->performedOn($group)
                ->withProperties([
                    'subject_type' => 'CustomerConfirmation',
                    'subject_id' => $group->id,
                    'old' => [],
                    'attributes' => $newSnapshot,
                    'context' => $this->buildLogContext(
                        operation: 'create',
                        enquiryId: $enquiryId,
                        packageId: $group->package_id,
                    ),
                ])
                ->log('Customer confirmation created'.($enquiryId ? ' for enquiry #'.$enquiryId : ''));

            return $group;
        });
    }

    /** Find an existing customer by email or create one. */
    private function findOrCreateCustomer(array $customerData): Customer
    {
        $email = $customerData['email'] ?? null;
        $biodata = $this->extractBiodata($customerData);

        if ($email) {
            $existingUser = User::where('email', $email)->first();
            if ($existingUser && $existingUser->customer) {
                $this->updateCustomerIfNeeded($existingUser->customer, $customerData);

                return $existingUser->customer;
            }

            if ($existingUser) {
                $customer = Customer::create(array_merge([
                    'user_id' => $existingUser->id,
                    'nric_number' => $this->normalizeNullableString($customerData['nric_number'] ?? null),
                    'address' => $this->normalizeNullableString($customerData['address'] ?? null),
                ], $biodata));

                return $customer;
            }
        }

        $user = User::create([
            'name' => $customerData['name'] ?? '',
            'email' => $email,
            'contact' => $customerData['contact_number'] ?? null,
            'password' => Hash::make('password'),
        ]);

        $user->assignRole('customer');

        $customer = Customer::create(array_merge([
            'user_id' => $user->id,
            'nric_number' => $this->normalizeNullableString($customerData['nric_number'] ?? null),
            'address' => $this->normalizeNullableString($customerData['address'] ?? null),
        ], $biodata));

        return $customer;
    }

    /** Extract biodata fields from input data. */
    private function extractBiodata(array $data): array
    {
        $fields = [
            'nationality',
            'passport_number',
            'passport_issue_date',
            'passport_expiry_date',
            'passport_place_of_issue',
            'gender',
            'marital_status',
            'date_of_birth',
            'place_of_birth',
            'first_time_umrah',
            'has_chronic_disease',
            'is_using_wheelchair',
            'chronic_disease_details',
        ];

        $biodata = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $biodata[$field] = $this->normalizeNullableFieldValue($data[$field]);
            }
        }

        return $biodata;
    }

    /** Update customer and user fields when provided. */
    private function updateCustomerIfNeeded(Customer $customer, array $data): void
    {
        $customerUpdates = [];
        $customerFields = [
            'nric_number',
            'address',
            'nationality',
            'passport_number',
            'passport_issue_date',
            'passport_expiry_date',
            'passport_place_of_issue',
            'gender',
            'marital_status',
            'date_of_birth',
            'place_of_birth',
            'first_time_umrah',
            'has_chronic_disease',
            'is_using_wheelchair',
            'chronic_disease_details',
        ];

        foreach ($customerFields as $field) {
            if (array_key_exists($field, $data)) {
                $customerUpdates[$field] = $this->normalizeNullableFieldValue($data[$field]);
            }
        }

        if (! empty($customerUpdates)) {
            $customer->update($customerUpdates);
        }

        if ($customer->user) {
            $userUpdates = [];
            if (! empty($data['name'])) {
                $userUpdates['name'] = $data['name'];
            }
            if (! empty($data['contact_number'])) {
                $userUpdates['contact'] = $data['contact_number'];
            }
            if (! empty($userUpdates)) {
                $customer->user->update($userUpdates);
            }
        }
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeNullableFieldValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->normalizeNullableString($value);
        }

        return $value;
    }

    /** Search customers for autocomplete options. */
    public function searchCustomers(string $query): array
    {
        return User::query()
            ->whereHas('customer')
            ->where(function ($q) use ($query) {
                $q->where('email', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%")
                    ->orWhere('contact', 'like', "%{$query}%");
            })
            ->with('customer')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->customer->id,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'contact_number' => $user->contact,
                    'customer_number' => $user->customer->customer_number,
                    'nric_number' => $user->customer->nric_number,
                    'address' => $user->customer->address,
                ];
            })
            ->all();
    }

    /** Get confirmation details by enquiry ID. */
    public function getByEnquiryId(int $enquiryId): ?CustomerConfirmation
    {
        return CustomerConfirmation::with('members.customer.user')
            ->where('enquiry_id', $enquiryId)
            ->first();
    }

    /** Get grouped customer data for index listing. */
    public function getForGroupedIndex(?bool $withPackage = null): array
    {
        return CustomerConfirmation::with([
            'members.customer.user',
            'members.quotationItems',
            'members.quotationItems.invoices.receipt',
            'members.quotationItems.invoices.quotationItems',
            'members.quotationItems.invoices.quotationItems.taxes',
            'members.quotationItems.quotation.quotationItems',
            'members.quotationItems.quotation.order.invoices.quotationItems',
            'members.quotationItems.quotation.order.invoices.quotationItems.taxes',
            'enquiry.handledBy:id,name',
            'package',
        ])
            ->when(DataScope::shouldScopeEnquiryAndConfirmation(), function ($query) {
                $this->applySalesEnquiryScopeToConfirmationQuery($query);
            })
            ->when($withPackage, function ($query) {
                $query->whereNotNull('package_id');
            })
            ->when($withPackage === false, function ($query) {
                $query->whereNull('package_id');
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (CustomerConfirmation $group) {
                $leader = $group->members->firstWhere('is_leader', true);

                $activeMembers = $group->members->filter(
                    fn (CustomerConfirmationMember $member) => $member->status !== 'cancelled'
                );

                $memberSummaries = $activeMembers
                    ->mapWithKeys(function (CustomerConfirmationMember $member) use ($group) {
                        return [
                            (int) $member->id => $this->resolveMemberFinancialSnapshot($member, $group->package),
                        ];
                    });

                $groupTotalAmount = (float) $memberSummaries->sum('total_amount');
                $groupPaidAmount = (float) $memberSummaries->sum('paid_amount');
                $groupOverpaidAmount = (float) $memberSummaries->sum('overpaid_amount');
                $groupRefundedAmount = (float) $memberSummaries->sum('refunded_amount');

                $quotedMemberCount = $activeMembers->filter(
                    fn (CustomerConfirmationMember $member) => $this->hasActiveQuotationItemLink($member)
                )->count();

                $canCreateQuotation = $activeMembers
                    ->contains(fn (CustomerConfirmationMember $member) => ! $this->hasActiveQuotationItemLink($member));
                $groupRefundCancelDate = $this->resolveGroupRefundCancelDate($group);

                return [
                    'id' => $group->id,
                    'number' => $group->number,
                    'enquiry_id' => $group->enquiry_id,
                    'package_id' => $group->package_id,
                    'package_name' => $group->package?->name ?? '-',
                    'package_status' => $group->package?->status,
                    'is_holding' => (bool) $group->is_holding,
                    'date_of_application' => $group->date_of_application_formatted,
                    'enquiry_type' => $group->enquiry?->type ? ucfirst($group->enquiry->type) : null,
                    'enquiry_status' => $group->enquiry?->status?->label(),
                    'customer_name' => $leader?->customer?->user?->name ?? '-',
                    'customer_number' => $leader?->customer?->customer_number ?? '-',
                    'enquiry_email' => $group->enquiry?->email ?? ($leader?->customer?->user?->email ?? '-'),
                    'enquiry_contact' => $group->enquiry?->contact_number ?? ($leader?->customer?->user?->contact ?? '-'),
                    'member_count' => $group->members->count(),
                    'active_member_count' => $activeMembers->count(),
                    'quoted_member_count' => $quotedMemberCount,
                    'paid_amount' => round($groupPaidAmount, 2),
                    'total_amount' => round($groupTotalAmount, 2),
                    'refunded_amount' => round($groupRefundedAmount, 2),
                    'overpaid_amount' => round($groupOverpaidAmount, 2),
                    'refund_cancel_date' => $groupRefundCancelDate,
                    'can_create_quotation' => $canCreateQuotation,
                    'can_delete' => $activeMembers->count() === 0,
                    'created_at' => $group->created_at?->translatedFormat('d F Y'),
                    'members' => $group->members->map(function (CustomerConfirmationMember $member) use ($group) {
                        $summary = $this->resolveMemberFinancialSnapshot($member, $group->package);
                        $orderSnapshot = $this->resolveMemberOrderSnapshot($member);

                        return [
                            'id' => $member->id,
                            'group_id' => $member->customer_confirmation_id,
                            'customer_id' => $member->customer_id,
                            'is_leader' => $member->is_leader,
                            'status' => $summary['status'],
                            'raw_status' => $this->normalizePaymentStatus($member->status ?? null),
                            'sharing_plan' => $member->sharing_plan,
                            'relationship' => $member->relationship,
                            'has_quotation' => $this->hasActiveQuotationItemLink($member),
                            'paid_amount' => round((float) $summary['paid_amount'], 2),
                            'invoice_paid_amount' => round((float) ($summary['invoice_paid_amount'] ?? 0), 2),
                            'total_amount' => round((float) $summary['total_amount'], 2),
                            'discount' => round((float) ($summary['discount'] ?? 0), 2),
                            'overpaid_amount' => round((float) $summary['overpaid_amount'], 2),
                            'refunded_amount' => round((float) $summary['refunded_amount'], 2),
                            'billed_amount' => round((float) $summary['billed_amount'], 2),
                            'balance_invoice_amount' => round(max(0.0, (float) $summary['total_amount'] - (float) $summary['billed_amount']), 2),
                            'name' => $member->customer?->user?->name ?? '-',
                            'email' => $member->customer?->user?->email ?? '-',
                            'contact' => $member->customer?->user?->contact ?? '-',
                            'customer_number' => $member->customer?->customer_number ?? '-',
                            'nric_number' => $member->customer?->nric_number ?? '-',
                            'nationality' => $member->customer?->nationality ?? '-',
                            'passport_number' => $member->customer?->passport_number ?? '-',
                            'refund_cancel_date' => $this->resolveMemberRefundCancelDate($member),
                            'latest_invoice_payment_method' => $this->resolveMemberLatestInvoicePaymentMethod($member),
                            'order_id' => $orderSnapshot['order_id'],
                            'order_number' => $orderSnapshot['order_number'],
                        ];
                    })->all(),
                ];
            })
            ->all();
    }

    public function getForConfirmedIndex(): array
    {
        return CustomerConfirmation::with([
            'members.customer.user',
            'members.quotationItems',
            'members.quotationItems.invoices.receipt',
            'members.quotationItems.invoices.quotationItems',
            'members.quotationItems.invoices.quotationItems.taxes',
            'members.quotationItems.quotation.quotationItems',
            'members.quotationItems.quotation.order.invoices.quotationItems',
            'members.quotationItems.quotation.order.invoices.quotationItems.taxes',
            'enquiry.handledBy:id,name',
            'package',
        ])
            ->when(DataScope::shouldScopeEnquiryAndConfirmation(), function ($query) {
                $this->applySalesEnquiryScopeToConfirmationQuery($query);
            })
            ->where('is_holding', false)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (CustomerConfirmation $group) {
                $activeMembers = $group->members->filter(
                    fn (CustomerConfirmationMember $member) => $member->status !== 'cancelled'
                )->values();

                $leader = $activeMembers->firstWhere('is_leader', true)
                    ?? $activeMembers->first();

                $memberSummaries = $activeMembers
                    ->mapWithKeys(function (CustomerConfirmationMember $member) use ($group) {
                        return [
                            (int) $member->id => $this->resolveMemberFinancialSnapshot($member, $group->package),
                        ];
                    });

                $groupTotalAmount = (float) $memberSummaries->sum('total_amount');
                $groupPaidAmount = (float) $memberSummaries->sum('paid_amount');
                $groupOverpaidAmount = (float) $memberSummaries->sum('overpaid_amount');
                $groupRefundedAmount = (float) $memberSummaries->sum('refunded_amount');

                $quotedMemberCount = $activeMembers->filter(
                    fn (CustomerConfirmationMember $member) => $this->hasActiveQuotationItemLink($member)
                )->count();

                $canCreateQuotation = $activeMembers
                    ->contains(fn (CustomerConfirmationMember $member) => ! $this->hasActiveQuotationItemLink($member));

                return [
                    'id' => $group->id,
                    'number' => $group->number,
                    'enquiry_id' => $group->enquiry_id,
                    'package_id' => $group->package_id,
                    'package_name' => $group->package?->name ?? '-',
                    'package_status' => $group->package?->status,
                    'date_of_application' => $group->date_of_application_formatted,
                    'enquiry_type' => $group->enquiry?->type ? ucfirst($group->enquiry->type) : null,
                    'enquiry_status' => $group->enquiry?->status?->label(),
                    'customer_name' => $leader?->customer?->user?->name ?? '-',
                    'customer_number' => $leader?->customer?->customer_number ?? '-',
                    'enquiry_email' => $group->enquiry?->email ?? ($leader?->customer?->user?->email ?? '-'),
                    'enquiry_contact' => $group->enquiry?->contact_number ?? ($leader?->customer?->user?->contact ?? '-'),
                    'member_count' => $activeMembers->count(),
                    'active_member_count' => $activeMembers->count(),
                    'quoted_member_count' => $quotedMemberCount,
                    'paid_amount' => round($groupPaidAmount, 2),
                    'total_amount' => round($groupTotalAmount, 2),
                    'refunded_amount' => round($groupRefundedAmount, 2),
                    'overpaid_amount' => round($groupOverpaidAmount, 2),
                    'can_create_quotation' => $canCreateQuotation,
                    'can_delete' => $activeMembers->count() === 0,
                    'created_at' => $group->created_at?->translatedFormat('d F Y'),
                    'members' => $activeMembers->map(function (CustomerConfirmationMember $member) use ($group) {
                        $summary = $this->resolveMemberFinancialSnapshot($member, $group->package);
                        $orderSnapshot = $this->resolveMemberOrderSnapshot($member);

                        return [
                            'id' => $member->id,
                            'group_id' => $member->customer_confirmation_id,
                            'customer_id' => $member->customer_id,
                            'is_leader' => $member->is_leader,
                            'status' => $summary['status'],
                            'raw_status' => $this->normalizePaymentStatus($member->status ?? null),
                            'sharing_plan' => $member->sharing_plan,
                            'relationship' => $member->relationship,
                            'has_quotation' => $this->hasActiveQuotationItemLink($member),
                            'paid_amount' => round((float) $summary['paid_amount'], 2),
                            'invoice_paid_amount' => round((float) ($summary['invoice_paid_amount'] ?? 0), 2),
                            'total_amount' => round((float) $summary['total_amount'], 2),
                            'discount' => round((float) ($summary['discount'] ?? 0), 2),
                            'overpaid_amount' => round((float) $summary['overpaid_amount'], 2),
                            'refunded_amount' => round((float) $summary['refunded_amount'], 2),
                            'billed_amount' => round((float) $summary['billed_amount'], 2),
                            'balance_invoice_amount' => round(max(0.0, (float) $summary['total_amount'] - (float) $summary['billed_amount']), 2),
                            'name' => $member->customer?->user?->name ?? '-',
                            'email' => $member->customer?->user?->email ?? '-',
                            'contact' => $member->customer?->user?->contact ?? '-',
                            'customer_number' => $member->customer?->customer_number ?? '-',
                            'nric_number' => $member->customer?->nric_number ?? '-',
                            'nationality' => $member->customer?->nationality ?? '-',
                            'passport_number' => $member->customer?->passport_number ?? '-',
                            'latest_invoice_payment_method' => $this->resolveMemberLatestInvoicePaymentMethod($member),
                            'order_id' => $orderSnapshot['order_id'],
                            'order_number' => $orderSnapshot['order_number'],
                        ];
                    })->all(),
                ];
            })
            ->filter(fn (array $group) => (int) ($group['active_member_count'] ?? 0) > 0)
            ->filter(fn (array $group) => ! $this->isCompletedGroupRowForIndex($group))
            ->map(function (array $group): array {
                unset($group['package_status']);

                return $group;
            })
            ->values()
            ->all();
    }

    public function getForHoldingIndex(): array
    {
        return CustomerConfirmation::with([
            'members.customer.user',
            'members.quotationItems',
            'members.quotationItems.invoices.receipt',
            'members.quotationItems.invoices.quotationItems',
            'members.quotationItems.invoices.quotationItems.taxes',
            'members.quotationItems.quotation.quotationItems',
            'members.quotationItems.quotation.order.invoices.quotationItems',
            'members.quotationItems.quotation.order.invoices.quotationItems.taxes',
            'enquiry.handledBy:id,name',
            'package',
        ])
            ->when(DataScope::shouldScopeEnquiryAndConfirmation(), function ($query) {
                $this->applySalesEnquiryScopeToConfirmationQuery($query);
            })
            ->where('is_holding', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (CustomerConfirmation $group) {
                $leader = $group->members->firstWhere('is_leader', true);

                $activeMembers = $group->members->filter(
                    fn (CustomerConfirmationMember $member) => $member->status !== 'cancelled'
                );

                $memberSummaries = $activeMembers
                    ->mapWithKeys(function (CustomerConfirmationMember $member) use ($group) {
                        return [
                            (int) $member->id => $this->resolveMemberFinancialSnapshot($member, $group->package),
                        ];
                    });

                $groupTotalAmount = (float) $memberSummaries->sum('total_amount');
                $groupPaidAmount = (float) $memberSummaries->sum('paid_amount');
                $groupOverpaidAmount = (float) $memberSummaries->sum('overpaid_amount');
                $groupRefundedAmount = (float) $memberSummaries->sum('refunded_amount');

                $quotedMemberCount = $activeMembers->filter(
                    fn (CustomerConfirmationMember $member) => $this->hasActiveQuotationItemLink($member)
                )->count();

                $canCreateQuotation = $activeMembers
                    ->contains(fn (CustomerConfirmationMember $member) => ! $this->hasActiveQuotationItemLink($member));

                return [
                    'id' => $group->id,
                    'number' => $group->number,
                    'enquiry_id' => $group->enquiry_id,
                    'package_id' => $group->package_id,
                    'package_name' => $group->package?->name ?? '-',
                    'package_status' => $group->package?->status,
                    'date_of_application' => $group->date_of_application_formatted,
                    'enquiry_type' => $group->enquiry?->type ? ucfirst($group->enquiry->type) : null,
                    'enquiry_status' => $group->enquiry?->status?->label(),
                    'customer_name' => $leader?->customer?->user?->name ?? '-',
                    'customer_number' => $leader?->customer?->customer_number ?? '-',
                    'enquiry_email' => $group->enquiry?->email ?? ($leader?->customer?->user?->email ?? '-'),
                    'enquiry_contact' => $group->enquiry?->contact_number ?? ($leader?->customer?->user?->contact ?? '-'),
                    'member_count' => $group->members->count(),
                    'active_member_count' => $activeMembers->count(),
                    'quoted_member_count' => $quotedMemberCount,
                    'paid_amount' => round($groupPaidAmount, 2),
                    'total_amount' => round($groupTotalAmount, 2),
                    'refunded_amount' => round($groupRefundedAmount, 2),
                    'overpaid_amount' => round($groupOverpaidAmount, 2),
                    'can_create_quotation' => $canCreateQuotation,
                    'can_delete' => $activeMembers->count() === 0,
                    'created_at' => $group->created_at?->translatedFormat('d F Y'),
                    'members' => $group->members->map(function (CustomerConfirmationMember $member) use ($group) {
                        $summary = $this->resolveMemberFinancialSnapshot($member, $group->package);
                        $orderSnapshot = $this->resolveMemberOrderSnapshot($member);

                        return [
                            'id' => $member->id,
                            'group_id' => $member->customer_confirmation_id,
                            'customer_id' => $member->customer_id,
                            'is_leader' => $member->is_leader,
                            'status' => $summary['status'],
                            'raw_status' => $this->normalizePaymentStatus($member->status ?? null),
                            'sharing_plan' => $member->sharing_plan,
                            'relationship' => $member->relationship,
                            'has_quotation' => $this->hasActiveQuotationItemLink($member),
                            'paid_amount' => round((float) $summary['paid_amount'], 2),
                            'invoice_paid_amount' => round((float) ($summary['invoice_paid_amount'] ?? 0), 2),
                            'total_amount' => round((float) $summary['total_amount'], 2),
                            'discount' => round((float) ($summary['discount'] ?? 0), 2),
                            'overpaid_amount' => round((float) $summary['overpaid_amount'], 2),
                            'refunded_amount' => round((float) $summary['refunded_amount'], 2),
                            'billed_amount' => round((float) $summary['billed_amount'], 2),
                            'balance_invoice_amount' => round(max(0.0, (float) $summary['total_amount'] - (float) $summary['billed_amount']), 2),
                            'name' => $member->customer?->user?->name ?? '-',
                            'email' => $member->customer?->user?->email ?? '-',
                            'contact' => $member->customer?->user?->contact ?? '-',
                            'customer_number' => $member->customer?->customer_number ?? '-',
                            'nric_number' => $member->customer?->nric_number ?? '-',
                            'nationality' => $member->customer?->nationality ?? '-',
                            'passport_number' => $member->customer?->passport_number ?? '-',
                            'latest_invoice_payment_method' => $this->resolveMemberLatestInvoicePaymentMethod($member),
                            'order_id' => $orderSnapshot['order_id'],
                            'order_number' => $orderSnapshot['order_number'],
                        ];
                    })->all(),
                ];
            })
            ->filter(fn (array $group) => ! $this->isCompletedGroupRowForIndex($group))
            ->map(function (array $group): array {
                unset($group['package_status']);

                return $group;
            })
            ->values()
            ->all();
    }

    public function getForCompletedIndex(): array
    {
        return collect($this->getForGroupedIndex())
            ->filter(fn (array $group) => $this->isCompletedGroupRowForIndex($group))
            ->map(function (array $group): array {
                unset($group['package_status']);

                return $group;
            })
            ->values()
            ->all();
    }

    public function getForCancelledIndex(): array
    {
        return collect($this->getForGroupedIndex())
            ->filter(fn (array $group) => ! (bool) ($group['is_holding'] ?? false))
            ->filter(fn (array $group) => $this->isCancelledGroupRowForIndex($group))
            ->map(function (array $group): array {
                unset($group['package_status']);

                return $group;
            })
            ->values()
            ->all();
    }

    private function applySalesEnquiryScopeToConfirmationQuery($query): void
    {
        $scopeMode = DataScope::mode();
        $countryIds = DataScope::scopedCountryIds();
        $branchIds = DataScope::scopedBranchIds();

        if ($scopeMode === 'branch' && ! empty($branchIds)) {
            $query->where(function ($visibilityQuery) use ($branchIds, $countryIds): void {
                $visibilityQuery
                    ->whereHas('enquiry', function ($enquiryQuery) use ($branchIds, $countryIds): void {
                        $enquiryQuery->where(function ($locationQuery) use ($branchIds, $countryIds): void {
                            $locationQuery->whereIn('branch_id', $branchIds);

                            if (! empty($countryIds)) {
                                $locationQuery->orWhereIn('country_id', $countryIds);
                            }
                        });
                    })
                    ->orWhere(function ($noEnquiryQuery) use ($countryIds): void {
                        $noEnquiryQuery
                            ->whereDoesntHave('enquiry')
                            ->whereHas('package', function ($packageQuery) use ($countryIds): void {
                                $packageQuery->whereIn('country_id', $countryIds);
                            });
                    });
            });

            return;
        }

        if (! empty($countryIds)) {
            $query->where(function ($visibilityQuery) use ($countryIds): void {
                $visibilityQuery
                    ->whereHas('enquiry', function ($enquiryQuery) use ($countryIds): void {
                        $enquiryQuery->whereIn('country_id', $countryIds);
                    })
                    ->orWhere(function ($noEnquiryQuery) use ($countryIds): void {
                        $noEnquiryQuery
                            ->whereDoesntHave('enquiry')
                            ->whereHas('package', function ($packageQuery) use ($countryIds): void {
                                $packageQuery->whereIn('country_id', $countryIds);
                            });
                    });
            });

            return;
        }

        $resolvedUser = DataScope::user();

        if ($resolvedUser?->hasRole('sales')) {
            $query->where(function ($visibilityQuery): void {
                $visibilityQuery
                    ->whereHas('enquiry', function ($enquiryQuery): void {
                        $enquiryQuery->where('handled_by', auth()->id());
                    })
                    ->orWhereDoesntHave('enquiry');
            });
        }
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private function isCompletedGroupRowForIndex(array $group): bool
    {
        $members = collect($group['members'] ?? []);
        $memberCount = $members->count();

        if ($memberCount === 0) {
            return false;
        }

        // $activeMembers = $members
        //     ->filter(function ($member): bool {
        //         if (! is_array($member)) {
        //             return false;
        //         }

        //         return $this->normalizePaymentStatus((string) ($member['status'] ?? null)) !== 'cancelled';
        //     })
        //     ->values();

        // if ($activeMembers->isEmpty()) {
        //     return true;
        // }

        $isPackageCompleted = strtolower(trim((string) ($group['package_status'] ?? ''))) === 'completed';

        return $isPackageCompleted;
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private function isCancelledGroupRowForIndex(array $group): bool
    {
        $members = collect($group['members'] ?? []);

        if ($members->isEmpty()) {
            return false;
        }

        return $members->every(function ($member): bool {
            if (! is_array($member)) {
                return false;
            }

            return $this->normalizePaymentStatus((string) ($member['status'] ?? null)) === 'cancelled';
        });
    }

    /**
     * Get customer info and all receipts for a specific confirmation member — for PDF export.
     *
     * @return array{
     *   customer_name: string,
     *   customer_number: string,
     *   customer_email: string,
     *   customer_contact: string,
     *   customer_address: string,
     *   package_name: string,
     *   package_number: string,
     *   date_of_application: string|null,
     *   payment_status: string,
     *   sharing_plan: string|null,
     *   receipts: array
     * }
     */
    public function getMemberReceiptsForPdf(int $confirmationId, int $memberId, array $paymentMethodMap = []): array
    {
        $member = CustomerConfirmationMember::with([
            'customer.user',
            'confirmation.package',
            'confirmation.enquiry',
            'quotationItems.invoices.receipt.receiptNotes',
            'quotationItems.invoices.quotationItems.taxes',
            'quotationItems.invoices.order.invoices',
        ])->where('customer_confirmation_id', $confirmationId)
            ->findOrFail($memberId);

        $group = $member->confirmation;
        $package = $group?->package;
        $customer = $member->customer;
        $user = $customer?->user;

        // Collect all invoices linked to this member's quotation items
        $invoiceIds = collect();
        foreach ($member->quotationItems as $qi) {
            foreach ($qi->invoices as $invoice) {
                $invoiceIds->push((int) $invoice->id);
            }
        }
        $invoiceIds = $invoiceIds->unique()->values();

        // Collect all receipts from those invoices
        $receipts = [];

        foreach ($invoiceIds as $invoiceId) {
            // Find the invoice across all loaded relations
            $invoice = null;
            foreach ($member->quotationItems as $qi) {
                foreach ($qi->invoices as $inv) {
                    if ((int) $inv->id === $invoiceId) {
                        $invoice = $inv;
                        break 2;
                    }
                }
            }

            if (! $invoice) {
                continue;
            }

            // Use first receipt on each invoice
            $receipt = $invoice->receipt->first();
            if (! $receipt) {
                continue;
            }

            $invoiceItems = collect($invoice->quotationItems ?? []);
            $subtotalAmount = (float) $invoiceItems
                ->where('is_header', false)
                ->sum(fn ($item): float => (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0));

            $totalAmount = (float) ($receipt->amount ?? 0);

            $paymentMethodValue = (string) ($receipt->payment_method ?? '');
            $paymentMethodLabel = $paymentMethodMap[$paymentMethodValue] ?? ucfirst($paymentMethodValue);

            $receiptNotes = $receipt->receiptNotes ?? collect();

            $receipts[] = [
                'receipt_number' => (string) ($receipt->receipt_number ?? '-'),
                'receipt_date' => (string) ($receipt->receipt_date_formatted ?? ''),
                'invoice_number' => (string) ($invoice->invoice_number ?? '-'),
                'invoice_description' => (string) ($invoice->description ?? '-'),
                'order_number' => (string) ($invoice->order?->order_number ?? '-'),
                'amount' => (float) ($receipt->amount ?? 0),
                'payment_method' => $paymentMethodValue,
                'payment_method_label' => $paymentMethodLabel,
                'reference' => (string) ($receipt->reference ?? ''),
                'description' => (string) ($receipt->description ?? ''),
                'subtotal_amount' => round($subtotalAmount, 2),
                'total_amount' => round($totalAmount, 2),
                'extensions' => [],
                'notes' => $receiptNotes->sortBy('sort_order')->values()->toArray(),
                'items' => $invoiceItems->map(fn ($item) => [
                    'id' => $item->id,
                    'parent_id' => $item->parent_id,
                    'type' => $item->type ?? null,
                    'description' => $item->description,
                    'is_header' => (bool) ($item->is_header ?? false),
                    'quantity' => (float) ($item->quantity ?? 0),
                    'rate' => (float) ($item->rate ?? 0),
                    'sort_order' => $item->sort_order ?? 0,
                    '_key' => 'item-'.$item->id,
                    'parent_key' => null,
                ])->values()->all(),
            ];
        }

        // Sort receipts by receipt_number
        usort($receipts, fn ($a, $b) => strcmp((string) ($a['receipt_number'] ?? ''), (string) ($b['receipt_number'] ?? '')));

        $statusSnapshot = $this->resolveMemberFinancialSnapshot($member, $package);

        return [
            'customer_name' => $user?->name ?? '-',
            'customer_number' => $customer?->customer_number ?? '-',
            'customer_email' => $user?->email ?? '-',
            'customer_contact' => $user?->contact ?? '-',
            'customer_address' => $customer?->address ?? '-',
            'nric_number' => $customer?->nric_number ?? '-',
            'passport_number' => $customer?->passport_number ?? '-',
            'package_name' => $package?->name ?? '-',
            'package_number' => $package?->package_number ?? '-',
            'date_of_application' => $group?->date_of_application_formatted ?? '-',
            'payment_status' => (string) ($statusSnapshot['status'] ?? '-'),
            'paid_amount' => round((float) ($statusSnapshot['paid_amount'] ?? 0), 2),
            'total_amount' => round((float) ($statusSnapshot['total_amount'] ?? 0), 2),
            'sharing_plan' => $member->sharing_plan ?? null,
            'is_leader' => (bool) $member->is_leader,
            'receipts' => $receipts,
        ];
    }

    private function resolveMemberPaidAmount(CustomerConfirmationMember $member): float
    {

        $memberItems = $member->quotationItems
            ->filter(fn ($item): bool => ! (bool) $item->is_header)
            ->values();

        if ($memberItems->isEmpty()) {
            return 0.0;
        }

        $paidAmount = 0.0;

        $invoices = $memberItems
            ->flatMap(fn ($item) => $item->invoices)
            ->unique('id')
            ->values();

        foreach ($invoices as $invoice) {
            $invoiceItems = $invoice->quotationItems
                ->filter(fn ($item): bool => ! (bool) $item->is_header)
                ->values();

            if ($invoiceItems->isEmpty()) {
                continue;
            }

            $invoiceSubtotal = (float) $invoiceItems->sum(function ($item): float {
                return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
            });

            $invoiceSubtotalAbsolute = abs($invoiceSubtotal);

            if ($invoiceSubtotalAbsolute <= 0) {
                continue;
            }

            $memberSubtotal = (float) $invoiceItems
                ->where('customer_confirmation_member_id', $member->id)
                ->sum(function ($item): float {
                    return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
                });

            $memberSubtotalAbsolute = abs($memberSubtotal);

            if ($memberSubtotalAbsolute <= 0) {
                continue;
            }

            $receiptTotal = (float) $invoice->receipt->sum(function ($receipt): float {
                return (float) ($receipt->amount ?? 0);
            });

            if (InvoiceStatus::isRefund($invoice->status) || (float) ($invoice->amount ?? 0) < 0) {
                if ($receiptTotal === 0.0) {
                    continue;
                }

                $paidAmount += $receiptTotal * ($memberSubtotalAbsolute / $invoiceSubtotalAbsolute);

                continue;
            }

            $fallbackPaidAmount = strtolower((string) ($invoice->status ?? '')) === 'paid'
                ? $invoiceSubtotalAbsolute
                : 0.0;

            $invoicePaidAmount = $receiptTotal !== 0.0
                ? min($receiptTotal, $invoiceSubtotalAbsolute)
                : $fallbackPaidAmount;

            if ($invoicePaidAmount === 0.0) {
                continue;
            }

            $paidAmount += $invoicePaidAmount * ($memberSubtotalAbsolute / $invoiceSubtotalAbsolute);
        }

        return round($paidAmount, 2);
    }

    /**
     * @param  Collection<int, QuotationItem>  $invoiceItems
     */
    private function resolvePositiveItemTaxTotalFromInvoiceItems(Collection $invoiceItems): float
    {
        $taxTotal = 0.0;

        foreach ($invoiceItems as $invoiceItem) {
            $lineAmount = (float) ($invoiceItem->quantity ?? 0) * (float) ($invoiceItem->rate ?? 0);

            foreach ($invoiceItem->taxes ?? [] as $tax) {
                $calculationMode = strtolower(trim((string) ($tax->calculation_mode ?? '')));
                $calculationValue = (float) ($tax->calculation_value ?? 0);

                if ($calculationValue <= 0 || ! in_array($calculationMode, ['fixed', 'percentage'], true)) {
                    continue;
                }

                $taxAmount = $calculationMode === 'percentage'
                    ? ($lineAmount * $calculationValue / 100)
                    : $calculationValue;

                if ($taxAmount > 0) {
                    $taxTotal += $taxAmount;
                }
            }
        }

        return round($taxTotal, 2);
    }

    private function resolveMemberTotalAmount(CustomerConfirmationMember $member, ?Package $package): float
    {
        $packagePayable = (float) $this->getPackagePriceForSharingPlan($package, $member->sharing_plan);

        return max(0.0, round($packagePayable, 2));
    }

    private function resolveMemberBilledAmount(CustomerConfirmationMember $member): float
    {
        $memberItems = $this->activeMemberQuotationItemsQuery((int) $member->id)
            ->where('is_header', false)
            ->get();

        if ($memberItems->isEmpty()) {
            return 0.0;
        }

        $billedAmount = (float) $memberItems->sum(function (QuotationItem $item): float {
            return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
        });

        return round($billedAmount, 2);
    }

    /**
     * @return array{status:string,paid_amount:float,invoice_paid_amount:float,total_amount:float,discount:float,overpaid_amount:float,billed_amount:float,refunded_amount:float}
     */
    private function resolveMemberFinancialSnapshot(CustomerConfirmationMember $member, ?Package $package): array
    {
        $normalizedStatus = $this->normalizePaymentStatus($member->status ?? null);

        $packagePrice = $package ? $this->resolveMemberTotalAmount($member, $package) : 0.0;
        $discountAmount = $this->resolveNegativeExtensionDiscountShareForMember(
            $member,
            $package,
            $packagePrice,
        );
        $payableAmount = round(max($packagePrice - $discountAmount, 0.0), 2);

        $invoiceAmountsByOrder = $this->resolveMemberInvoiceItemAmountsByInvoiceOrder($member);
        $firstInvoiceAmount = round((float) ($invoiceAmountsByOrder->get(0) ?? 0), 2);
        $secondInvoiceAmount = round((float) ($invoiceAmountsByOrder->get(1) ?? 0), 2);
        $thirdAndLaterInvoiceAmount = round((float) $invoiceAmountsByOrder->slice(2)->sum(), 2);

        [$depositPayment, $secondPayment, $thirdPayment] = $this->applyDiscountOffsetToPaymentBuckets(
            [$firstInvoiceAmount, $secondInvoiceAmount, $thirdAndLaterInvoiceAmount],
            $discountAmount,
        );

        $paidAmount = round(
            $depositPayment + $secondPayment + $thirdPayment,
            2,
        );
        $billedAmount = $this->resolveMemberBilledAmount($member);
        $overpaidAmount = max(0.0, round($paidAmount - $payableAmount, 2));
        $refundedAmount = $this->resolveMemberRefundedAmount($member);
        $invoicePaidAmount = max(0.0, round($this->resolveMemberPaidAmount($member) + $refundedAmount, 2));

        return [
            'status' => $this->resolveComputedMemberStatus($normalizedStatus, $package, $paidAmount, $payableAmount),
            'paid_amount' => round($paidAmount, 2),
            'invoice_paid_amount' => round($invoicePaidAmount, 2),
            'total_amount' => round($payableAmount, 2),
            'discount' => round($discountAmount, 2),
            'overpaid_amount' => round($overpaidAmount, 2),
            'billed_amount' => round($billedAmount, 2),
            'refunded_amount' => round($refundedAmount, 2),
        ];
    }

    /**
     * Resolve the total refunded amount for a member.
     * Refunds are identified by invoices with status='refund' or negative amounts.
     * Returns the absolute value of all negative receipts from refund invoices.
     */
    private function resolveMemberRefundedAmount(CustomerConfirmationMember $member): float
    {
        $refundedAmount = 0.0;

        $refundInvoices = $member->quotationItems
            ->flatMap(fn ($item) => $item->invoices)
            ->unique('id')
            ->values()
            ->filter(function ($invoice): bool {
                return InvoiceStatus::isRefund($invoice->status ?? null)
                    || (float) ($invoice->amount ?? 0) < 0;
            });

        foreach ($refundInvoices as $invoice) {
            $invoiceItems = $invoice->quotationItems
                ->filter(fn ($item): bool => ! (bool) ($item->is_header ?? false))
                ->values();

            if ($invoiceItems->isEmpty()) {
                continue;
            }

            $invoiceSubtotalAbsolute = abs((float) $invoiceItems->sum(function ($item): float {
                return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
            }));

            if ($invoiceSubtotalAbsolute <= 0) {
                continue;
            }

            $memberSubtotalAbsolute = abs((float) $invoiceItems
                ->where('customer_confirmation_member_id', $member->id)
                ->sum(function ($item): float {
                    return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
                }));

            if ($memberSubtotalAbsolute <= 0) {
                continue;
            }

            $receiptTotal = abs((float) $invoice->receipt->sum(function ($receipt): float {
                return (float) ($receipt->amount ?? 0);
            }));

            $effectiveRefundTotal = $receiptTotal > 0
                ? $receiptTotal
                : abs((float) ($invoice->amount ?? 0));

            if ($effectiveRefundTotal <= 0) {
                continue;
            }

            $refundedAmount += $effectiveRefundTotal * ($memberSubtotalAbsolute / $invoiceSubtotalAbsolute);
        }

        return round($refundedAmount, 2);
    }

    private function resolveMemberLatestRefundReceiptDate(
        CustomerConfirmationMember $member,
    ): ?CarbonInterface {
        $latestRefundReceipt = $member->quotationItems
            ->flatMap(fn ($item) => $item->invoices)
            ->unique('id')
            ->values()
            ->filter(function ($invoice): bool {
                return InvoiceStatus::isRefund($invoice->status ?? null)
                    || (float) ($invoice->amount ?? 0) < 0;
            })
            ->flatMap(fn ($invoice) => $invoice->receipt ?? collect())
            ->filter(fn ($receipt): bool => $receipt->receipt_date !== null)
            ->sortByDesc(
                fn ($receipt): int => $receipt->receipt_date?->getTimestamp() ?? 0,
            )
            ->first();

        $receiptDate = $latestRefundReceipt?->receipt_date;

        return $receiptDate instanceof CarbonInterface ? $receiptDate : null;
    }

    private function resolveMemberRefundCancelDate(
        CustomerConfirmationMember $member,
    ): ?string {
        $latestRefundDate = $this->resolveMemberLatestRefundReceiptDate($member);
        $fallbackDate = $member->updated_at;

        return ($latestRefundDate ?? $fallbackDate)?->translatedFormat('d F Y');
    }

    private function resolveGroupRefundCancelDate(
        CustomerConfirmation $group,
    ): ?string {
        $latestRefundDate = $group->members
            ->map(
                fn (CustomerConfirmationMember $member): ?CarbonInterface => $this->resolveMemberLatestRefundReceiptDate($member),
            )
            ->filter()
            ->sortByDesc(fn (CarbonInterface $date): int => $date->getTimestamp())
            ->first();

        if ($latestRefundDate instanceof CarbonInterface) {
            return $latestRefundDate->translatedFormat('d F Y');
        }

        $latestCancellationDate = $group->members
            ->map(fn (CustomerConfirmationMember $member) => $member->updated_at)
            ->filter()
            ->sortByDesc(fn (CarbonInterface $date): int => $date->getTimestamp())
            ->first();

        return $latestCancellationDate?->translatedFormat('d F Y');
    }

    /**
     * @return Collection<int, float>
     */
    private function resolveMemberInvoiceItemAmountsByInvoiceOrder(CustomerConfirmationMember $member): Collection
    {
        $memberItems = $member->quotationItems
            ->filter(fn ($item): bool => ! (bool) ($item->is_header ?? false))
            ->values();

        if ($memberItems->isEmpty()) {
            return collect();
        }

        return $memberItems
            ->flatMap(fn ($item) => $item->invoices)
            ->unique('id')
            ->values()
            ->map(function ($invoice) use ($member): ?array {
                $invoiceItems = $invoice->quotationItems
                    ->filter(fn ($item): bool => ! (bool) ($item->is_header ?? false))
                    ->values();

                if ($invoiceItems->isEmpty()) {
                    return null;
                }

                $memberSubtotal = (float) $invoiceItems
                    ->where('customer_confirmation_member_id', $member->id)
                    ->sum(function ($item): float {
                        return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
                    });

                if ($memberSubtotal === 0.0) {
                    return null;
                }

                $hasReceiptDate = $invoice->receipt
                    ->pluck('receipt_date')
                    ->contains(fn ($date): bool => ! empty($date));

                $normalizedStatus = strtolower(trim((string) ($invoice->status ?? '')));
                $isPaid = $normalizedStatus === InvoiceStatus::Paid;
                $isRefund = $normalizedStatus === InvoiceStatus::Refund;

                if (! $isPaid && ! $isRefund && ! $hasReceiptDate) {
                    return null;
                }

                return [
                    'amount' => round($memberSubtotal, 2),
                    'invoice_id' => (int) ($invoice->id ?? 0),
                ];
            })
            ->filter(fn ($row): bool => is_array($row))
            ->sortBy(fn (array $row): int => (int) ($row['invoice_id'] ?? 0))
            ->pluck('amount')
            ->map(fn ($amount): float => round((float) $amount, 2))
            ->values();
    }

    /**
     * @param  array<int, float>  $paymentBuckets
     * @return array<int, float>
     */
    private function applyDiscountOffsetToPaymentBuckets(array $paymentBuckets, float $discountAmount): array
    {
        $remainingDiscount = max($discountAmount, 0.0);
        $adjustedBuckets = [];

        foreach ($paymentBuckets as $amount) {
            $bucketAmount = round((float) $amount, 2);

            if ($remainingDiscount > 0.0 && $bucketAmount > 0.0) {
                $discountOffset = min($remainingDiscount, $bucketAmount);
                $bucketAmount -= $discountOffset;
                $remainingDiscount -= $discountOffset;
            }

            $adjustedBuckets[] = round($bucketAmount, 2);
        }

        return $adjustedBuckets;
    }

    private function resolveNegativeExtensionDiscountShareForMember(
        CustomerConfirmationMember $member,
        ?Package $package,
        float $memberPackagePrice,
    ): float {
        $memberItems = $member->quotationItems
            ->filter(fn ($item): bool => ! (bool) $item->is_header)
            ->values();

        if ($memberItems->isEmpty()) {
            return 0.0;
        }

        $discountByMemberId = [];

        $memberItemsByQuotation = $memberItems
            ->filter(fn ($item): bool => ! empty($item->quotation_id))
            ->groupBy('quotation_id');

        foreach ($memberItemsByQuotation as $groupedItems) {
            $quotation = $groupedItems->first()?->quotation;

            if (! $quotation || $quotation->trashed()) {
                continue;
            }

            $quotationItems = $quotation->quotationItems
                ->where('is_header', false)
                ->sortBy(function ($item): array {
                    return [
                        (int) ($item->sort_order ?? PHP_INT_MAX),
                        (int) ($item->id ?? 0),
                    ];
                })
                ->values();

            $orderedMemberIds = $quotationItems
                ->pluck('customer_confirmation_member_id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values();

            if ($orderedMemberIds->isEmpty()) {
                continue;
            }

            $memberCustomerIds = CustomerConfirmationMember::query()
                ->whereIn('id', $orderedMemberIds->all())
                ->pluck('customer_id', 'id');

            $memberSharingPlans = CustomerConfirmationMember::query()
                ->whereIn('id', $orderedMemberIds->all())
                ->pluck('sharing_plan', 'id');

            $memberQuotationSubtotals = $quotationItems
                ->groupBy(fn ($item): int => (int) ($item->customer_confirmation_member_id ?? 0))
                ->map(function (Collection $items): float {
                    return (float) $items->sum(function ($item): float {
                        return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
                    });
                });

            $memberCapsById = [];

            foreach ($orderedMemberIds as $memberId) {
                $sharingPlan = $memberSharingPlans->get($memberId);
                $packagePriceCap = $package
                    ? $this->getPackagePriceForSharingPlan($package, is_string($sharingPlan) ? $sharingPlan : null)
                    : 0;

                $fallbackSubtotalCap = (float) ($memberQuotationSubtotals->get($memberId) ?? 0);

                $memberCapsById[$memberId] = round(max(
                    $packagePriceCap > 0 ? (float) $packagePriceCap : $fallbackSubtotalCap,
                    0,
                ), 2);
            }

            if (! array_key_exists((int) $member->id, $memberCapsById)) {
                $memberCapsById[(int) $member->id] = round(max($memberPackagePrice, 0), 2);
            }

            $payerMemberId = $orderedMemberIds->first(function (int $id) use ($memberCustomerIds, $quotation): bool {
                return (int) ($memberCustomerIds->get($id) ?? 0) === (int) ($quotation->customer_id ?? 0);
            });

            if (! is_int($payerMemberId)) {
                $payerMemberId = $orderedMemberIds->first();
            }

            $quotationDiscountTotal = (float) collect(is_array($quotation->extensions) ? $quotation->extensions : [])
                ->sum(function ($extension): float {
                    if (! is_array($extension)) {
                        return 0;
                    }

                    $amount = (float) ($extension['amount'] ?? 0);

                    return $amount < 0 ? abs($amount) : 0;
                });

            $remainingCapByMemberId = [];
            foreach ($memberCapsById as $memberId => $memberCap) {
                $remainingCapByMemberId[(int) $memberId] = round(max((float) $memberCap, 0), 2);
            }

            $orderInvoices = collect($quotation->order?->invoices ?? collect())->values();

            $quotationItemDiscountByMemberId = $orderInvoices->isEmpty()
                ? $this->resolveNegativeItemDiscountByMemberFromItems($quotationItems)
                : [];

            foreach ($quotationItemDiscountByMemberId as $memberId => $itemDiscountAmount) {
                $memberCapRemaining = round((float) ($remainingCapByMemberId[$memberId] ?? 0), 2);

                if ($memberCapRemaining <= 0) {
                    continue;
                }

                $allocatedItemDiscount = round(min($itemDiscountAmount, $memberCapRemaining), 2);

                if ($allocatedItemDiscount <= 0) {
                    continue;
                }

                $discountByMemberId[$memberId] = round(
                    (float) ($discountByMemberId[$memberId] ?? 0) + $allocatedItemDiscount,
                    2,
                );

                $remainingCapByMemberId[$memberId] = round(max($memberCapRemaining - $allocatedItemDiscount, 0), 2);
            }

            $invoiceDiscountTotal = (float) $orderInvoices
                ->sum(function ($invoice): float {
                    $invoiceExtensionDiscountTotal = (float) collect(is_array($invoice->extensions) ? $invoice->extensions : [])
                        ->sum(function ($extension): float {
                            if (! is_array($extension)) {
                                return 0;
                            }

                            $amount = (float) ($extension['amount'] ?? 0);

                            return $amount < 0 ? abs($amount) : 0;
                        });

                    return $invoiceExtensionDiscountTotal;
                });

            foreach ($orderInvoices as $invoice) {
                $invoiceItems = $invoice->quotationItems
                    ->filter(fn ($item): bool => ! (bool) $item->is_header)
                    ->values();

                $invoiceItemDiscountByMemberId = $this->resolveNegativeItemDiscountByMemberFromItems($invoiceItems);

                foreach ($invoiceItemDiscountByMemberId as $memberId => $itemDiscountAmount) {
                    $memberCapRemaining = round((float) ($remainingCapByMemberId[$memberId] ?? 0), 2);

                    if ($memberCapRemaining <= 0) {
                        continue;
                    }

                    $allocatedItemDiscount = round(min($itemDiscountAmount, $memberCapRemaining), 2);

                    if ($allocatedItemDiscount <= 0) {
                        continue;
                    }

                    $discountByMemberId[$memberId] = round(
                        (float) ($discountByMemberId[$memberId] ?? 0) + $allocatedItemDiscount,
                        2,
                    );

                    $remainingCapByMemberId[$memberId] = round(max($memberCapRemaining - $allocatedItemDiscount, 0), 2);
                }
            }

            $totalDiscount = round($quotationDiscountTotal + $invoiceDiscountTotal, 2);

            if ($totalDiscount <= 0 || ! is_int($payerMemberId)) {
                continue;
            }

            $allocationOrder = $orderedMemberIds
                ->sortBy(fn (int $memberId): int => $memberId === $payerMemberId ? 0 : 1)
                ->values();

            $remainingDiscount = round($totalDiscount, 2);

            foreach ($allocationOrder as $memberId) {
                if ($remainingDiscount <= 0) {
                    break;
                }

                $memberCap = round((float) ($remainingCapByMemberId[$memberId] ?? 0), 2);

                if ($memberCap <= 0) {
                    continue;
                }

                $allocatedDiscount = round(min($remainingDiscount, $memberCap), 2);

                if ($allocatedDiscount <= 0) {
                    continue;
                }

                $discountByMemberId[$memberId] = round(
                    (float) ($discountByMemberId[$memberId] ?? 0) + $allocatedDiscount,
                    2,
                );

                $remainingCapByMemberId[$memberId] = round(max($memberCap - $allocatedDiscount, 0), 2);
                $remainingDiscount = round($remainingDiscount - $allocatedDiscount, 2);
            }
        }

        return round((float) ($discountByMemberId[(int) $member->id] ?? 0), 2);
    }

    private function resolveComputedMemberStatus(
        string $currentStatus,
        ?Package $package,
        float $paidAmount,
        float $requiredAmount,
    ): string {
        if ($currentStatus === 'cancelled') {
            return 'cancelled';
        }

        if (! $package) {
            return $currentStatus;
        }

        if ($paidAmount <= 0) {
            return 'pending_payment';
        }

        if ($paidAmount > $requiredAmount) {
            return 'overpaid';
        }

        if ($requiredAmount > 0 && $paidAmount >= $requiredAmount) {
            return 'fully_paid';
        }

        return 'partially_paid';
    }

    /**
     * @param  Collection<int, QuotationItem>  $memberItems
     */
    private function resolveNegativeExtensionDiscountShareFromMemberItems(Collection $memberItems): float
    {
        if ($memberItems->isEmpty()) {
            return 0.0;
        }

        $quotationIds = $memberItems
            ->pluck('quotation_id')
            ->filter()
            ->unique()
            ->values();

        if ($quotationIds->isEmpty()) {
            return 0.0;
        }

        $discountShare = 0.0;

        foreach ($quotationIds as $quotationId) {
            $groupedItems = $memberItems
                ->where('quotation_id', $quotationId)
                ->values();

            $quotation = $groupedItems
                ->firstWhere('quotation_id', $quotationId)
                ?->quotation;

            if (! $quotation || $quotation->trashed()) {
                continue;
            }

            $quotationItems = $quotation->quotationItems
                ->where('is_header', false)
                ->values();

            $quotationSubtotal = (float) $quotationItems
                ->sum(function ($item): float {
                    return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
                });

            $memberSubtotal = (float) $groupedItems->sum(function ($item): float {
                return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
            });

            if ($quotationSubtotal <= 0 || $memberSubtotal <= 0) {
                continue;
            }

            $quotationDiscountTotal = (float) collect(is_array($quotation->extensions) ? $quotation->extensions : [])
                ->sum(function ($extension): float {
                    if (! is_array($extension)) {
                        return 0;
                    }

                    $amount = (float) ($extension['amount'] ?? 0);

                    return $amount < 0 ? abs($amount) : 0;
                });

            if ($quotationDiscountTotal > 0) {
                $discountShare += $quotationDiscountTotal * ($memberSubtotal / $quotationSubtotal);
            }

            $orderInvoices = collect($quotation->order?->invoices ?? collect())->values();

            $quotationItemDiscountTotal = $orderInvoices->isEmpty()
                ? $this->resolveNegativeItemDiscountTotalFromItems($quotationItems)
                : 0.0;

            if ($quotationItemDiscountTotal > 0) {
                $discountShare += $quotationItemDiscountTotal * ($memberSubtotal / $quotationSubtotal);
            }

            foreach ($orderInvoices as $invoice) {
                $invoiceItems = $invoice->quotationItems
                    ->filter(fn ($item): bool => ! (bool) $item->is_header)
                    ->values();

                if ($invoiceItems->isEmpty()) {
                    continue;
                }

                $invoiceSubtotal = (float) $invoiceItems->sum(function ($item): float {
                    return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
                });

                $memberInvoiceSubtotal = (float) $invoiceItems
                    ->where('customer_confirmation_member_id', $groupedItems->first()?->customer_confirmation_member_id)
                    ->sum(function ($item): float {
                        return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
                    });

                if ($invoiceSubtotal <= 0 || $memberInvoiceSubtotal <= 0) {
                    continue;
                }

                $invoiceDiscountTotal = (float) collect(is_array($invoice->extensions) ? $invoice->extensions : [])
                    ->sum(function ($extension): float {
                        if (! is_array($extension)) {
                            return 0;
                        }

                        $amount = (float) ($extension['amount'] ?? 0);

                        return $amount < 0 ? abs($amount) : 0;
                    });

                $invoiceItemDiscountTotal = $this->resolveNegativeItemDiscountTotalFromItems($invoiceItems);
                $invoiceDiscountTotal += $invoiceItemDiscountTotal;

                if ($invoiceDiscountTotal <= 0) {
                    continue;
                }

                $discountShare += $invoiceDiscountTotal * ($memberInvoiceSubtotal / $invoiceSubtotal);
            }
        }

        return round($discountShare, 2);
    }

    /**
     * @param  Collection<int, QuotationItem>  $items
     */
    private function resolveNegativeItemDiscountTotalFromItems(Collection $items): float
    {
        return round((float) array_sum($this->resolveNegativeItemDiscountByMemberFromItems($items)), 2);
    }

    /**
     * @param  Collection<int, QuotationItem>  $items
     * @return array<int, float>
     */
    private function resolveNegativeItemDiscountByMemberFromItems(Collection $items): array
    {
        $discountByMemberId = [];

        foreach ($items as $item) {
            $memberId = (int) ($item->customer_confirmation_member_id ?? 0);

            if ($memberId <= 0) {
                continue;
            }

            $lineAmount = (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);

            foreach ($item->taxes ?? [] as $tax) {
                $calculationMode = strtolower(trim((string) ($tax->calculation_mode ?? '')));
                $calculationValue = (float) ($tax->calculation_value ?? 0);

                if ($calculationValue >= 0 || ! in_array($calculationMode, ['fixed', 'percentage'], true)) {
                    continue;
                }

                $discountAmount = $calculationMode === 'percentage'
                    ? ($lineAmount * $calculationValue / 100)
                    : $calculationValue;

                if ($discountAmount >= 0) {
                    continue;
                }

                $discountByMemberId[$memberId] = round(
                    (float) ($discountByMemberId[$memberId] ?? 0) + abs($discountAmount),
                    2,
                );
            }
        }

        return $discountByMemberId;
    }

    private function hasActiveQuotationItemLink(CustomerConfirmationMember $member): bool
    {
        return $member->quotationItems->contains(function ($item): bool {
            $quotation = $item->quotation;

            if (! $quotation || $quotation->trashed()) {
                return false;
            }

            $status = strtolower((string) ($quotation->status?->value ?? $quotation->status ?? ''));

            return ! in_array($status, ['cancelled', 'expired', 'rejected'], true);
        });
    }

    private function assertPackageSelectionAllowed(int $packageId, bool $allowPrivatePackage = false): void
    {
        $package = Package::query()->findOrFail($packageId);

        if (! $allowPrivatePackage && $this->packageSeatService->isPrivatePackage($package)) {
            throw ValidationException::withMessages([
                'package_id' => 'Private packages are not selectable in this workflow.',
            ]);
        }

        $normalizedStatus = $this->packageSeatService->normalizeStatus((string) $package->status);

        if ($this->packageSeatService->isBlockedForMemberIntake($normalizedStatus)) {
            throw ValidationException::withMessages([
                'package_id' => 'Selected package is '.$normalizedStatus.' and cannot accept new members.',
            ]);
        }

        if ($package->total_seats !== null && (int) ($package->seats_left ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'package_id' => 'Selected package has no available seats.',
            ]);
        }
    }

    private function normalizePaymentStatus(?string $status): string
    {
        $normalized = strtolower(trim((string) ($status ?? '')));

        return match ($normalized) {
            'cancelled' => 'cancelled',
            'partially_paid' => 'partially_paid',
            'fully_paid' => 'fully_paid',
            'overpaid' => 'overpaid',
            default => 'pending_payment',
        };
    }

    /** List active customers for selection. */
    public function listActiveCustomers(): array
    {
        return User::query()
            ->whereHas('customer')
            ->with('customer.files')
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                $customer = $user->customer;
                $documents = $customer ? $this->getCustomerDocumentsByField($customer) : collect();

                return [
                    'value' => $user->id,
                    'label' => $user->email,
                    'name' => $user->name,
                    'email' => $user->email,
                    'contact_number' => $user->contact ?? '',
                    'nric_number' => $customer->nric_number ?? '',
                    'address' => $customer->address ?? '',
                    'nationality' => $customer->nationality ?? '',
                    'passport_number' => $customer->passport_number ?? '',
                    'passport_issue_date' => $customer->passport_issue_date_formatted ?? '',
                    'passport_expiry_date' => $customer->passport_expiry_date_formatted ?? '',
                    'passport_place_of_issue' => $customer->passport_place_of_issue ?? '',
                    'gender' => $customer->gender ?? '',
                    'marital_status' => $customer->marital_status ?? '',
                    'date_of_birth' => $customer->date_of_birth_formatted ?? '',
                    'place_of_birth' => $customer->place_of_birth ?? '',
                    'first_time_umrah' => $customer->first_time_umrah ?? false,
                    'has_chronic_disease' => $customer->has_chronic_disease ?? false,
                    'is_using_wheelchair' => $customer->is_using_wheelchair ?? false,
                    'chronic_disease_details' => $customer->chronic_disease_details ?? '',
                    'passport_document' => $this->formatDocumentPayload($documents->get('passport')),
                    'photo_document' => $this->formatDocumentPayload($documents->get('photo')),
                ];
            })
            ->all();
    }

    /** Get full customer confirmation details for edit or show. */
    public function getForEditShow(int $id): array
    {
        $group = CustomerConfirmation::with(['members.customer.user', 'members.customer.files', 'members.quotationItems.quotation', 'enquiry.package', 'package'])
            ->findOrFail($id);

        $visibleMembers = $group->members
            ->filter(fn (CustomerConfirmationMember $member) => $this->normalizePaymentStatus($member->status ?? null) !== 'cancelled')
            ->values();

        return [
            'id' => $group->id,
            'enquiry_id' => $group->enquiry_id,
            'package_id' => $group->package_id,
            'package_name' => $group->package?->name,
            'package_price_single' => $group->package?->price_single,
            'package_price_double' => $group->package?->price_double,
            'package_price_triple' => $group->package?->price_triple,
            'package_price_quad' => $group->package?->price_quad,
            'child_with_bed_price' => $group->package?->child_with_bed_price,
            'child_no_bed_price' => $group->package?->child_no_bed_price,
            'infant_price' => $group->package?->infant_price,
            'package_room_type' => $group->package_room_type,
            'date_of_application' => $group->date_of_application_formatted,
            'members' => $visibleMembers->map(function (CustomerConfirmationMember $member) {
                $customer = $member->customer;
                $user = $customer?->user;
                $documents = $customer ? $this->getCustomerDocumentsByField($customer) : collect();

                return [
                    'member_id' => $member->id,
                    'id' => $member->id,
                    'customer_id' => $customer?->id,
                    'is_leader' => $member->is_leader,
                    'status' => $this->normalizePaymentStatus($member->status ?? null),
                    'has_quotation' => $this->hasActiveQuotationItemLink($member),
                    'sharing_plan' => $member->sharing_plan,
                    'relationship' => $member->relationship,
                    'name' => $user?->name ?? '',
                    'email' => $user?->email ?? '',
                    'contact_number' => $user?->contact ?? '',
                    'nric_number' => $customer?->nric_number ?? '',
                    'address' => $customer?->address ?? '',
                    'nationality' => $customer?->nationality ?? '',
                    'passport_number' => $customer?->passport_number ?? '',
                    'passport_issue_date' => $customer?->passport_issue_date_formatted ?? '',
                    'passport_expiry_date' => $customer?->passport_expiry_date_formatted ?? '',
                    'passport_place_of_issue' => $customer?->passport_place_of_issue ?? '',
                    'gender' => $customer?->gender ?? '',
                    'marital_status' => $customer?->marital_status ?? '',
                    'date_of_birth' => $customer?->date_of_birth_formatted ?? '',
                    'place_of_birth' => $customer?->place_of_birth ?? '',
                    'first_time_umrah' => $customer?->first_time_umrah ?? false,
                    'has_chronic_disease' => $customer?->has_chronic_disease ?? false,
                    'is_using_wheelchair' => $customer?->is_using_wheelchair ?? false,
                    'chronic_disease_details' => $customer?->chronic_disease_details ?? '',
                    'passport_document' => $this->formatDocumentPayload($documents->get('passport')),
                    'photo_document' => $this->formatDocumentPayload($documents->get('photo')),
                    'passport_file_name' => $documents->get('passport')?->file_name,
                    'photo_file_name' => $documents->get('photo')?->file_name,
                    'passport_file_removed' => false,
                    'photo_file_removed' => false,
                ];
            })->all(),
        ];
    }

    /** Update a customer confirmation and its members. */
    public function updateGroup(int $id, array $data): CustomerConfirmation
    {
        return DB::transaction(function () use ($id, $data) {
            $group = CustomerConfirmation::with(['enquiry', 'members.customer.user'])->findOrFail($id);
            $oldSnapshot = $this->sanitizeSnapshot(
                $this->buildGroupSnapshot($group),
            );

            $isPrivateEnquiry = strtolower((string) ($group->enquiry?->type ?? '')) === 'private';
            $hasExistingPackage = ! empty($group->package_id);
            // $requestedPackageId = $data['package_id'] ?? $group->package_id;
            $requestedPackageId = $data['package_id'] ?? null;

            if (
                $isPrivateEnquiry
                && $hasExistingPackage
                && (int) $requestedPackageId !== (int) $group->package_id
            ) {
                abort(422, 'Private enquiry package is exclusive and cannot be changed once linked.');
            }

            if (
                $requestedPackageId !== null
                && (int) $requestedPackageId > 0
                && (int) $requestedPackageId !== (int) ($group->package_id ?? 0)
            ) {
                $this->assertPackageSelectionAllowed((int) $requestedPackageId, $isPrivateEnquiry);
            }

            $group->update([
                'number' => array_key_exists('number', $data)
                    ? $this->numberingService->ensureNumber(
                        'customer_confirmation',
                        $data['number'],
                        (int) $group->id,
                        isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                    )
                    : $group->number,
                // 'package_id' => $data['package_id'] ?? $group->package_id,
                'package_id' => $data['package_id'] ?? null,
                'is_holding' => ($group->is_holding && ! empty($data['package_id'])) ? false : $group->is_holding,
                'package_room_type' => $data['package_room_type'] ?? $group->package_room_type,
                'date_of_application' => $data['date_of_application'] ?? $group->date_of_application,
            ]);

            $existingMembers = $group->members->keyBy('id');
            $updatedMemberIds = [];

            $normalizedEmails = collect($data['members'])
                ->map(fn (array $member): string => strtolower(trim((string) ($member['email'] ?? ''))))
                ->filter(fn (string $email): bool => $email !== '')
                ->values();

            $duplicateEmails = $normalizedEmails
                ->countBy()
                ->filter(fn (int $count): bool => $count > 1)
                ->keys()
                ->values();

            if ($duplicateEmails->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'members' => 'Duplicate member email detected: '.$duplicateEmails->join(', ').'. Please ensure each member uses a unique email/profile.',
                ]);
            }

            foreach ($data['members'] as $memberData) {
                $matchedMember = $this->findExistingMemberMatch($group, $memberData, $updatedMemberIds);

                $customer = $this->findOrCreateCustomer($memberData);
                $this->processFileUploads($customer, $memberData);

                if ($matchedMember) {
                    $incomingSharingPlan = $memberData['sharing_plan'] ?? null;
                    $sharingPlanChanged = $incomingSharingPlan !== $matchedMember->sharing_plan;
                    $hasAnyBilling = $this->memberHasAnyBilling($matchedMember->id);
                    $hasPaidBilling = $this->memberHasPaidBilling($matchedMember->id);

                    $resolvedSharingPlan = $incomingSharingPlan;

                    if ($sharingPlanChanged && $hasAnyBilling && ! $hasPaidBilling) {
                        $this->resetMemberBillingLinksForRecreate($matchedMember->id);
                    }

                    $matchedMember->update([
                        'customer_id' => $customer->id,
                        'is_leader' => (bool) ($memberData['is_leader'] ?? false),
                        'status' => $this->resolveMemberStatusOnGroupUpdate($matchedMember, $memberData),
                        'sharing_plan' => $resolvedSharingPlan,
                        'relationship' => $memberData['relationship'] ?? $memberData['role'] ?? null,
                    ]);

                    $matchedMember->unsetRelation('customer');
                    $matchedMember->load('customer.user');
                    $this->syncOpenManifestMemberSnapshot($matchedMember);

                    $updatedMemberIds[] = $matchedMember->id;

                    continue;
                }

                $createdMember = CustomerConfirmationMember::create([
                    'customer_confirmation_id' => $group->id,
                    'customer_id' => $customer->id,
                    'is_leader' => (bool) ($memberData['is_leader'] ?? false),
                    'status' => $this->normalizePaymentStatus($memberData['status'] ?? null),
                    'sharing_plan' => $memberData['sharing_plan'] ?? null,
                    'relationship' => $memberData['relationship'] ?? $memberData['role'] ?? null,
                ]);

                $createdMember->load('customer.user');
                $this->syncOpenManifestMemberSnapshot($createdMember);

                $updatedMemberIds[] = $createdMember->id;
            }

            $removedMembers = $existingMembers
                ->filter(fn (CustomerConfirmationMember $member) => ! in_array($member->id, $updatedMemberIds, true));

            foreach ($removedMembers as $removedMember) {
                if ($this->normalizePaymentStatus($removedMember->status ?? null) === 'cancelled') {
                    continue;
                }

                if ($this->memberHasPaidBilling($removedMember->id)) {
                    $memberName = $removedMember->customer?->user?->name ?? "#{$removedMember->id}";

                    throw ValidationException::withMessages([
                        'members' => "Cannot remove member {$memberName} because paid billing already exists.",
                    ]);
                }

                $removedMember->delete();
            }

            if ((int) ($group->package_id ?? 0) > 0) {
                $groupForSync = $group->fresh(['members.customer.user', 'package']);

                if ($groupForSync?->package) {
                    $this->syncNonConvertedQuotationItemsForConfirmation($groupForSync);

                    if ($this->isAutoBillingSyncEnabled()) {
                        $this->reconcileGroupBillingAgainstPackage($groupForSync);
                    }
                }
            }

            $this->syncMemberStatusesForConfirmation((int) $group->id);

            $group->load('members.customer.user', 'enquiry', 'package');
            $newSnapshot = $this->sanitizeSnapshot(
                $this->buildGroupSnapshot($group),
            );

            activity()
                ->performedOn($group)
                ->withProperties([
                    'subject_type' => 'CustomerConfirmation',
                    'subject_id' => $group->id,
                    'old' => $oldSnapshot,
                    'attributes' => $newSnapshot,
                    'context' => $this->buildLogContext(
                        operation: 'update',
                        enquiryId: $group->enquiry_id,
                        packageId: $group->package_id,
                    ),
                ])
                ->log('Customer confirmation #'.$group->id.' updated');

            return $group;
        });
    }

    private function resolveMemberStatusOnGroupUpdate(
        CustomerConfirmationMember $member,
        array $memberData,
    ): string {
        $incomingStatus = strtolower(trim((string) ($memberData['status'] ?? '')));

        if ($incomingStatus === '') {
            return $this->normalizePaymentStatus($member->status ?? null);
        }

        if ($incomingStatus === 'cancelled') {
            return 'cancelled';
        }

        if (! in_array($incomingStatus, ['pending_payment', 'partially_paid', 'fully_paid', 'overpaid'], true)) {
            return $this->normalizePaymentStatus($member->status ?? null);
        }

        if ($this->memberHasAnyBilling($member->id)) {
            return $this->normalizePaymentStatus($member->status ?? null);
        }

        return $this->normalizePaymentStatus($incomingStatus);
    }

    private function findExistingMemberMatch(
        CustomerConfirmation $group,
        array $memberData,
        array $updatedMemberIds,
    ): ?CustomerConfirmationMember {
        $memberId = isset($memberData['member_id']) ? (int) $memberData['member_id'] : null;
        if ($memberId) {
            $matchedByMemberId = $group->members
                ->first(fn (CustomerConfirmationMember $member) => $member->id === $memberId);

            if ($matchedByMemberId && ! in_array($matchedByMemberId->id, $updatedMemberIds, true)) {
                return $matchedByMemberId;
            }
        }

        $email = strtolower(trim((string) ($memberData['email'] ?? '')));
        if ($email !== '') {
            $matchedByEmail = $group->members
                ->first(function (CustomerConfirmationMember $member) use ($email, $updatedMemberIds) {
                    return strtolower((string) ($member->customer?->user?->email ?? '')) === $email
                        && ! in_array($member->id, $updatedMemberIds, true);
                });

            if ($matchedByEmail) {
                return $matchedByEmail;
            }
        }

        return null;
    }

    private function memberHasAnyBilling(int $memberId): bool
    {
        return $this->activeMemberQuotationItemsQuery($memberId)->exists();
    }

    private function memberHasPaidBilling(int $memberId): bool
    {
        return QuotationItem::query()
            ->where('customer_confirmation_member_id', $memberId)
            ->whereHas('invoices.receipt')
            ->exists();
    }

    private function resetMemberBillingLinksForRecreate(int $memberId): void
    {
        $activeItemIds = $this->activeMemberQuotationItemsQuery($memberId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! empty($activeItemIds)) {
            QuotationItem::query()
                ->whereIn('id', $activeItemIds)
                ->update(['customer_confirmation_member_id' => null]);
        }
    }

    private function activeMemberQuotationItemsQuery(int $memberId)
    {
        return QuotationItem::query()
            ->where('customer_confirmation_member_id', $memberId)
            ->whereHas('quotation', function ($query) {
                $query->whereNull('deleted_at')
                    ->whereNotIn('status', ['cancelled', 'expired', 'rejected']);
            });
    }

    /** Delete a customer confirmation and its members only. */
    public function deleteGroup(int $id): void
    {
        DB::transaction(function () use ($id) {
            $group = CustomerConfirmation::with(['members', 'enquiry'])->findOrFail($id);

            $activeMembersCount = $group->members
                ->filter(fn (CustomerConfirmationMember $member) => $this->normalizePaymentStatus($member->status ?? null) !== 'cancelled')
                ->count();

            if ($activeMembersCount > 0) {
                throw ValidationException::withMessages([
                    'group' => 'Customer confirmation can only be deleted when all members are cancelled.',
                ]);
            }

            $oldSnapshot = $this->sanitizeSnapshot(
                $this->buildGroupSnapshot($group),
            );

            foreach ($group->members as $member) {
                $member->delete();
            }

            $enquiry = $group->enquiry;
            $group->delete();

            if ($enquiry && $enquiry->status === EnquiryStatus::Confirmed) {
                $enquiry->update(['status' => EnquiryStatus::Contacted->value]);

                activity()
                    ->performedOn($enquiry)
                    ->withProperties([
                        'subject_type' => 'Enquiry',
                        'subject_id' => $enquiry->id,
                        'old_status' => EnquiryStatus::Confirmed->value,
                        'new_status' => EnquiryStatus::Contacted->value,
                    ])
                    ->log("Enquiry #{$enquiry->id} moved to Contacted after customer confirmation deletion");
            }

            activity()
                ->performedOn($group)
                ->withProperties([
                    'subject_type' => 'CustomerConfirmation',
                    'subject_id' => $id,
                    'old' => $oldSnapshot,
                    'attributes' => [
                        'deleted' => true,
                        'group_id' => $id,
                    ],
                    'context' => $this->buildLogContext(
                        operation: 'delete',
                        enquiryId: $group->enquiry_id,
                        packageId: $group->package_id,
                    ),
                ])
                ->log('Customer confirmation #'.$id.' deleted');
        });
    }

    /** Update one confirmation member's customer/profile/status/sharing plan. */
    public function updateMemberDetails(int $memberId, array $data): array
    {
        return DB::transaction(function () use ($memberId, $data) {
            $member = CustomerConfirmationMember::with(['customer.user', 'customer.files'])->findOrFail($memberId);
            $previousSharingPlan = strtolower(trim((string) ($member->sharing_plan ?? '')));

            $customer = $member->customer;
            if (! $customer) {
                abort(422, 'Member customer record is missing.');
            }

            $this->updateCustomerIfNeeded($customer, $data);
            $this->processFileUploads($customer, $data);

            $member->update([
                'status' => $data['status'] ?? $member->status,
                'sharing_plan' => $data['sharing_plan'] ?? $member->sharing_plan,
                'relationship' => $data['relationship'] ?? $data['role'] ?? $member->relationship,
            ]);

            $member->unsetRelation('customer');
            $member->load('customer.user');
            $this->syncOpenManifestMemberSnapshot($member);

            if ($member->customer_confirmation_id) {
                $confirmationId = (int) $member->customer_confirmation_id;

                $this->syncMemberStatusesForConfirmation($confirmationId);

                $incomingSharingPlan = strtolower(trim((string) ($data['sharing_plan'] ?? $member->sharing_plan ?? '')));
                $sharingPlanChanged = array_key_exists('sharing_plan', $data)
                    && $incomingSharingPlan !== $previousSharingPlan;

                if ($sharingPlanChanged) {
                    $confirmation = CustomerConfirmation::with('package')->find($confirmationId);

                    if ($confirmation?->package) {
                        $memberForReconciliation = CustomerConfirmationMember::with([
                            'quotationItems.invoices.receipt',
                            'quotationItems.quotation.order.invoices.quotationItems',
                            'quotationItems.quotation.order.invoices.receipt',
                        ])->find((int) $member->id);

                        if ($memberForReconciliation) {
                            $this->syncNonConvertedQuotationItemsForMember($memberForReconciliation, $confirmation->package);

                            if ($this->isAutoBillingSyncEnabled()) {
                                $this->reconcileMemberBillingAgainstPackage(
                                    $memberForReconciliation,
                                    $confirmation->package,
                                );
                            }

                            $this->syncMemberStatusesForConfirmation($confirmationId);
                        }
                    }
                }
            }

            $member->refresh();
            $member->load('customer.user', 'customer.files');
            $documents = $member->customer ? $this->getCustomerDocumentsByField($member->customer) : collect();

            return [
                'id' => $member->id,
                'customer_id' => $member->customer_id,
                'is_leader' => $member->is_leader,
                'status' => $this->normalizePaymentStatus($member->status ?? null),
                'sharing_plan' => $member->sharing_plan,
                'relationship' => $member->relationship,
                'name' => $member->customer?->user?->name ?? '',
                'email' => $member->customer?->user?->email ?? '',
                'contact_number' => $member->customer?->user?->contact ?? '',
                'nric_number' => $member->customer?->nric_number ?? '',
                'address' => $member->customer?->address ?? '',
                'nationality' => $member->customer?->nationality ?? '',
                'passport_number' => $member->customer?->passport_number ?? '',
                'passport_issue_date' => $member->customer?->passport_issue_date_formatted ?? '',
                'passport_expiry_date' => $member->customer?->passport_expiry_date_formatted ?? '',
                'passport_place_of_issue' => $member->customer?->passport_place_of_issue ?? '',
                'gender' => $member->customer?->gender ?? '',
                'marital_status' => $member->customer?->marital_status ?? '',
                'date_of_birth' => $member->customer?->date_of_birth_formatted ?? '',
                'place_of_birth' => $member->customer?->place_of_birth ?? '',
                'first_time_umrah' => $member->customer?->first_time_umrah ?? false,
                'has_chronic_disease' => $member->customer?->has_chronic_disease ?? false,
                'is_using_wheelchair' => $member->customer?->is_using_wheelchair ?? false,
                'chronic_disease_details' => $member->customer?->chronic_disease_details ?? '',
                'passport_document' => $this->formatDocumentPayload($documents->get('passport')),
                'photo_document' => $this->formatDocumentPayload($documents->get('photo')),
                'passport_file_name' => $documents->get('passport')?->file_name,
                'photo_file_name' => $documents->get('photo')?->file_name,
                'passport_file_removed' => false,
                'photo_file_removed' => false,
            ];
        });
    }

    /** Mark one member as cancelled. */
    public function cancelMember(int $memberId): void
    {
        DB::transaction(function () use ($memberId) {
            $member = CustomerConfirmationMember::with([
                'customer.user',
                'quotationItems.quotation.order.invoices.quotationItems',
                'quotationItems.quotation.order.invoices.receipt',
                'quotationItems.invoices.quotationItems',
                'quotationItems.invoices.receipt',
            ])->findOrFail($memberId);

            $paidAmount = $this->resolveMemberPaidAmount($member);

            if (abs($paidAmount) > 0.0) {
                throw ValidationException::withMessages([
                    'member' => 'Member with paid amount cannot be cancelled directly. Please use refund action.',
                ]);
            }

            $this->detachMemberFromActiveQuotationsForCancellation($member);

            $member->update([
                'status' => 'cancelled',
            ]);

            $member->load('customer.user');
            $this->syncOpenManifestMemberSnapshot($member);

            $confirmationId = (int) ($member->customer_confirmation_id ?? 0);

            if ($confirmationId > 0) {
                $this->syncMemberStatusesForConfirmation($confirmationId);
            } else {
                app(PackageSeatService::class)->recalculateForPackageId(
                    (int) ($member->confirmation?->package_id ?? 0),
                );
            }
        });
    }

    private function clearOutstandingUnpaidInvoiceLinksForMember(CustomerConfirmationMember $member): void
    {
        $member->loadMissing([
            'quotationItems.invoices.quotationItems',
            'quotationItems.invoices.receipt',
        ]);

        $memberItemIds = $member->quotationItems
            ->filter(fn (QuotationItem $item): bool => ! (bool) ($item->is_header ?? false))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($memberItemIds === []) {
            return;
        }

        $linkedInvoices = $member->quotationItems
            ->flatMap(fn (QuotationItem $item) => $item->invoices)
            ->unique('id')
            ->values();

        foreach ($linkedInvoices as $invoice) {
            if (InvoiceStatus::isRefund($invoice->status)) {
                continue;
            }

            if (strtolower((string) ($invoice->status ?? '')) === InvoiceStatus::Cancelled) {
                continue;
            }

            $receiptTotal = (float) $invoice->receipt->sum(function (Receipt $receipt): float {
                return (float) ($receipt->amount ?? 0);
            });

            if (abs($receiptTotal) > 0.0) {
                continue;
            }

            $invoiceItems = $invoice->quotationItems
                ->filter(fn (QuotationItem $item): bool => ! (bool) ($item->is_header ?? false))
                ->values();

            if ($invoiceItems->isEmpty()) {
                continue;
            }

            $memberInvoiceItems = $invoiceItems
                ->filter(fn (QuotationItem $item): bool => in_array((int) $item->id, $memberItemIds, true))
                ->values();

            if ($memberInvoiceItems->isEmpty()) {
                continue;
            }

            $memberInvoiceAmount = (float) $memberInvoiceItems->sum(function (QuotationItem $item): float {
                return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
            });

            $remainingInvoiceItemIds = $invoice->quotationItems
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->reject(fn (int $id): bool => in_array($id, $memberItemIds, true))
                ->values()
                ->all();

            if ($remainingInvoiceItemIds === []) {
                $this->deleteUnpaidInvoiceForCancellation($invoice);

                continue;
            }

            $invoice->quotationItems()->sync($remainingInvoiceItemIds);

            $nextAmount = round(max(
                0.0,
                (float) ($invoice->amount ?? 0) - $memberInvoiceAmount,
            ), 2);

            $updatePayload = [
                'amount' => $nextAmount,
            ];

            if ($nextAmount <= 0.0) {
                $updatePayload['status'] = InvoiceStatus::Cancelled;
            }

            $invoice->update($updatePayload);
        }
    }

    private function detachMemberFromActiveQuotationsForCancellation(CustomerConfirmationMember $member): void
    {
        $member->loadMissing([
            'quotationItems.quotation.order.invoices.quotationItems',
            'quotationItems.quotation.order.invoices.receipt',
            'quotationItems.invoices.quotationItems',
            'quotationItems.invoices.receipt',
        ]);

        $this->clearOutstandingUnpaidInvoiceLinksForMember($member);

        $memberItems = $this->activeMemberQuotationItemsQuery((int) $member->id)
            ->where('is_header', false)
            ->with('quotation.order.invoices.receipt')
            ->get()
            ->groupBy('quotation_id');

        foreach ($memberItems as $quotationId => $items) {
            $quotation = $items->first()?->quotation;

            if (! $quotation) {
                continue;
            }

            foreach ($items as $item) {
                $item->invoices()->detach();
                $item->delete();
            }

            $headerItems = QuotationItem::query()
                ->where('quotation_id', (int) $quotationId)
                ->where('is_header', true)
                ->get();

            foreach ($headerItems as $headerItem) {
                $hasChildren = QuotationItem::query()
                    ->where('quotation_id', (int) $quotationId)
                    ->where('parent_id', (int) $headerItem->id)
                    ->where('is_header', false)
                    ->exists();

                if (! $hasChildren) {
                    $headerItem->delete();
                }
            }

            $hasBillableItems = QuotationItem::query()
                ->where('quotation_id', (int) $quotationId)
                ->where('is_header', false)
                ->exists();

            if ($hasBillableItems) {
                continue;
            }

            $quotation->loadMissing('order.invoices.receipt');
            $hasBlockingInvoiceHistory = false;

            if ($quotation->order) {
                foreach ($quotation->order->invoices as $invoice) {
                    if (InvoiceStatus::isRefund($invoice->status)) {
                        $hasBlockingInvoiceHistory = true;

                        continue;
                    }

                    $receiptTotal = (float) $invoice->receipt->sum(function (Receipt $receipt): float {
                        return (float) ($receipt->amount ?? 0);
                    });

                    if (abs($receiptTotal) > 0.0) {
                        $hasBlockingInvoiceHistory = true;

                        continue;
                    }

                    $this->deleteUnpaidInvoiceForCancellation($invoice);
                }
            }

            if ($hasBlockingInvoiceHistory) {
                $quotation->update([
                    'status' => QuotationStatus::Cancelled->value,
                ]);

                continue;
            }

            $this->deleteQuotationForCancellation($quotation);
        }
    }

    private function deleteUnpaidInvoiceForCancellation(Invoice $invoice): void
    {
        $invoiceNumber = trim((string) ($invoice->invoice_number ?? ''));

        $invoice->quotationItems()->sync([]);
        $invoice->delete();

        if ($invoiceNumber !== '') {
            $this->numberingService->rollbackByNumbers('invoice', [$invoiceNumber]);
        }
    }

    private function deleteQuotationForCancellation(Quotation $quotation): void
    {
        $quotationNumber = trim((string) ($quotation->quotation_number ?? ''));

        $quotation->forceDelete();

        if ($quotationNumber !== '') {
            $this->numberingService->rollbackByNumbers('quotation', [$quotationNumber]);
        }
    }

    public function syncBillingForConfirmation(int $confirmationId): void
    {
        DB::transaction(function () use ($confirmationId) {
            $group = CustomerConfirmation::with(['members.customer.user', 'package'])
                ->findOrFail($confirmationId);

            if ($group->package) {
                $this->syncNonConvertedQuotationItemsForConfirmation($group);
                $this->reconcileGroupBillingAgainstPackage($group);
            }

            $this->syncMemberStatusesForConfirmation((int) $group->id);
        });
    }

    /**
     * Move selected members from an existing confirmation to a new holding confirmation.
     * Selected source members are marked as cancelled and their linked manifest members are cancelled.
     */
    public function moveMembersToHolding(
        int $sourceConfirmationId,
        array $memberIds,
        ?int $targetPackageId = null,
        ?int $sourceManifestId = null,
    ): CustomerConfirmation {
        return DB::transaction(function () use ($sourceConfirmationId, $memberIds, $targetPackageId, $sourceManifestId) {
            $sourceGroup = CustomerConfirmation::with('members.customer.user')
                ->findOrFail($sourceConfirmationId);

            $selectedMembers = CustomerConfirmationMember::query()
                ->where('customer_confirmation_id', $sourceGroup->id)
                ->whereIn('id', $memberIds)
                ->get();

            if ($selectedMembers->isEmpty()) {
                abort(422, 'No valid members selected for moving.');
            }

            $selectedMemberIds = $selectedMembers
                ->pluck('id')
                ->map(fn ($memberId): int => (int) $memberId)
                ->values()
                ->all();

            $activeSourceMemberIds = CustomerConfirmationMember::query()
                ->where('customer_confirmation_id', $sourceGroup->id)
                ->get()
                ->filter(function (CustomerConfirmationMember $member): bool {
                    return $this->normalizePaymentStatus($member->status ?? null) !== 'cancelled';
                })
                ->pluck('id')
                ->map(fn ($memberId): int => (int) $memberId)
                ->sort()
                ->values()
                ->all();

            $selectedMemberIdsSorted = collect($selectedMemberIds)
                ->sort()
                ->values()
                ->all();

            $shouldMoveInPlace =
                $activeSourceMemberIds !== []
                && $selectedMemberIdsSorted !== []
                && array_values(array_diff($selectedMemberIdsSorted, $activeSourceMemberIds)) === []
                && array_values(array_diff($activeSourceMemberIds, $selectedMemberIdsSorted)) === [];

            if ($shouldMoveInPlace) {
                $previousPackageId = (int) ($sourceGroup->package_id ?? 0);

                $sourceGroup->update([
                    'package_id' => $targetPackageId,
                    'is_holding' => $targetPackageId ? false : true,
                ]);

                $this->deleteManifestMembersForMovedMembers(
                    $selectedMemberIds,
                    (int) $sourceGroup->id,
                    $sourceManifestId,
                );

                $this->syncMemberStatusesForConfirmation((int) $sourceGroup->id);

                $sourceGroup->load('members.customer.user', 'enquiry', 'package');

                activity()
                    ->performedOn($sourceGroup)
                    ->withProperties([
                        'subject_type' => 'CustomerConfirmation',
                        'subject_id' => $sourceGroup->id,
                        'context' => $this->buildLogContext(
                            operation: 'move_members_in_place',
                            enquiryId: $sourceGroup->enquiry_id,
                            packageId: $sourceGroup->package_id,
                        ),
                        'source_confirmation_id' => $sourceGroup->id,
                        'source_member_ids' => $selectedMemberIds,
                        'source_manifest_id' => $sourceManifestId,
                        'new_member_ids' => [],
                    ])
                    ->log('Customer members moved in-place on confirmation #'.$sourceGroup->id);

                app(PackageSeatService::class)->recalculateForPackageId($previousPackageId);
                app(PackageSeatService::class)->recalculateForPackageId((int) ($targetPackageId ?? 0));

                return $sourceGroup;
            }

            $sourceMembersById = $selectedMembers->keyBy('id');
            $selectedMembers = $selectedMembers
                ->sortByDesc(fn (CustomerConfirmationMember $member): int => (int) $member->is_leader)
                ->values();

            $newGroup = CustomerConfirmation::create([
                'number' => $this->numberingService->ensureNumber('customer_confirmation', null),
                'enquiry_id' => $sourceGroup->enquiry_id,
                'created_by' => auth()->id(),
                'package_id' => $targetPackageId,
                'is_holding' => $targetPackageId ? false : true,
                'package_room_type' => $sourceGroup->package_room_type,
                'date_of_application' => now(),
            ]);

            $memberIdMap = [];

            foreach ($selectedMembers->values() as $index => $member) {
                $createdMember = CustomerConfirmationMember::create([
                    'customer_confirmation_id' => $newGroup->id,
                    'customer_id' => $member->customer_id,
                    'is_leader' => $index === 0,
                    'status' => $this->normalizePaymentStatus($sourceMembersById[$member->id]?->status ?? null),
                    'sharing_plan' => $sourceMembersById[$member->id]?->sharing_plan,
                    'relationship' => $sourceMembersById[$member->id]?->relationship,
                ]);

                $memberIdMap[$member->id] = $createdMember->id;
            }

            $this->splitMovedMembersBilling($sourceGroup, $newGroup, $memberIdMap);

            CustomerConfirmationMember::query()
                ->whereIn('id', $selectedMemberIds)
                ->update(['status' => 'cancelled']);

            $this->ensureLeaderAssignmentForActiveMembers((int) $sourceGroup->id);
            $this->ensureLeaderAssignmentForActiveMembers((int) $newGroup->id);

            $sourceGroup->load('members');

            $hasActiveSourceMembers = $sourceGroup->members->contains(function (CustomerConfirmationMember $member): bool {
                return $this->normalizePaymentStatus($member->status ?? null) !== 'cancelled';
            });

            if ((bool) $sourceGroup->is_holding && ! $hasActiveSourceMembers) {
                $sourceGroup->update([
                    'is_holding' => false,
                ]);
            }

            $this->deleteManifestMembersForMovedMembers(
                $selectedMemberIds,
                (int) $sourceGroup->id,
                $sourceManifestId,
            );

            $this->syncMemberStatusesForConfirmation((int) $sourceGroup->id);
            $this->syncMemberStatusesForConfirmation((int) $newGroup->id);

            $newGroup->load('members.customer.user', 'enquiry', 'package');

            activity()
                ->performedOn($newGroup)
                ->withProperties([
                    'subject_type' => 'CustomerConfirmation',
                    'subject_id' => $newGroup->id,
                    'context' => $this->buildLogContext(
                        operation: 'move_to_holding',
                        enquiryId: $newGroup->enquiry_id,
                        packageId: $newGroup->package_id,
                    ),
                    'source_confirmation_id' => $sourceGroup->id,
                    'source_member_ids' => $selectedMemberIds,
                    'source_manifest_id' => $sourceManifestId,
                    'new_member_ids' => array_values($memberIdMap),
                ])
                ->log('Customer members moved to holding confirmation #'.$newGroup->id);

            app(PackageSeatService::class)->recalculateForPackageId(
                (int) ($sourceGroup->package_id ?? 0),
            );
            app(PackageSeatService::class)->recalculateForPackageId(
                (int) ($newGroup->package_id ?? 0),
            );

            return $newGroup;
        });
    }

    private function ensureLeaderAssignmentForActiveMembers(int $confirmationId): void
    {
        $members = CustomerConfirmationMember::query()
            ->where('customer_confirmation_id', $confirmationId)
            ->orderBy('id')
            ->get();

        $activeMembers = $members
            ->filter(fn (CustomerConfirmationMember $member): bool => $this->normalizePaymentStatus($member->status ?? null) !== 'cancelled')
            ->values();

        if ($activeMembers->isEmpty()) {
            return;
        }

        $activeLeader = $activeMembers->first(
            fn (CustomerConfirmationMember $member): bool => (bool) $member->is_leader,
        );

        $leaderId = (int) ($activeLeader?->id ?? $activeMembers->first()->id);

        CustomerConfirmationMember::query()
            ->where('customer_confirmation_id', $confirmationId)
            ->where('id', '!=', $leaderId)
            ->where('is_leader', true)
            ->update(['is_leader' => false]);

        CustomerConfirmationMember::query()
            ->where('id', $leaderId)
            ->update(['is_leader' => true]);
    }

    /**
     * @param  array<int, int>  $selectedMemberIds
     */
    private function deleteManifestMembersForMovedMembers(
        array $selectedMemberIds,
        int $sourceConfirmationId,
        ?int $sourceManifestId,
    ): void {
        $manifestMemberIdsToDelete = ManifestMember::query()
            ->whereIn('customer_confirmation_member_id', $selectedMemberIds)
            ->whereHas('confirmationMember', function ($query) use ($sourceConfirmationId) {
                $query->where('customer_confirmation_id', $sourceConfirmationId);
            })
            ->when($sourceManifestId, function ($query) use ($sourceManifestId) {
                $query->where('manifest_id', $sourceManifestId);
            })
            ->pluck('id')
            ->map(fn ($manifestMemberId) => (int) $manifestMemberId)
            ->filter(fn ($manifestMemberId) => $manifestMemberId > 0)
            ->values()
            ->all();

        if ($manifestMemberIdsToDelete === []) {
            return;
        }

        ManifestMember::query()
            ->whereIn('id', $manifestMemberIdsToDelete)
            ->delete();
    }

    /**
     * @param  array<int, int>  $memberIdMap  source_member_id => target_member_id
     */
    private function splitMovedMembersBilling(
        CustomerConfirmation $sourceGroup,
        CustomerConfirmation $newGroup,
        array $memberIdMap,
    ): void {
        if ($memberIdMap === []) {
            return;
        }

        $sourceMemberIds = array_map('intval', array_keys($memberIdMap));
        $targetMembers = CustomerConfirmationMember::query()
            ->whereIn('id', array_values($memberIdMap))
            ->get()
            ->keyBy('id');
        $targetLeaderCustomerId = $this->resolveMovedLeaderCustomerId($targetMembers);

        $sourceQuotations = Quotation::query()
            ->with([
                'quotationItems.invoices.receipt',
                'quotationItems.parent',
                'quotationItems.taxes',
                'quotationNotes',
                'order.invoices.quotationItems',
                'order.invoices.receipt',
            ])
            ->where('customer_confirmation_id', $sourceGroup->id)
            ->whereHas('quotationItems', function ($query) use ($sourceMemberIds) {
                $query->whereIn('customer_confirmation_member_id', $sourceMemberIds);
            })
            ->get();

        foreach ($sourceQuotations as $sourceQuotation) {
            $movedItems = $sourceQuotation->quotationItems
                ->whereIn('customer_confirmation_member_id', $sourceMemberIds)
                ->where('is_header', false)
                ->values();

            if ($movedItems->isEmpty()) {
                continue;
            }

            $remainingItems = $sourceQuotation->quotationItems
                ->whereNotIn('customer_confirmation_member_id', $sourceMemberIds)
                ->where('is_header', false)
                ->values();

            $movedPaidAmount = $this->resolveMovedQuotationPaidAmount($sourceQuotation, $movedItems);
            $hasPaidMovement = abs($movedPaidAmount) > 0.0;

            if ($remainingItems->isEmpty()) {
                if ($hasPaidMovement) {
                    foreach ($movedItems as $item) {
                        $sourceMemberId = (int) ($item->customer_confirmation_member_id ?? 0);
                        $targetMemberId = $memberIdMap[$sourceMemberId] ?? null;

                        if (! $targetMemberId) {
                            continue;
                        }

                        $item->update([
                            'customer_confirmation_member_id' => $targetMemberId,
                        ]);
                    }

                    $sourceQuotation->update([
                        'customer_confirmation_id' => $newGroup->id,
                        'customer_id' => $targetLeaderCustomerId,
                    ]);

                    continue;
                }

                $this->removeMovedItemsFromSourceWithoutTargetQuotation($sourceQuotation, $movedItems);

                continue;
            }

            if (! $hasPaidMovement) {
                $this->removeMovedItemsFromSourceWithoutTargetQuotation($sourceQuotation, $movedItems);

                continue;
            }

            $sourceOrder = $sourceQuotation->order;

            $newQuotation = Quotation::create([
                'customer_id' => $targetLeaderCustomerId,
                'customer_confirmation_id' => $newGroup->id,
                'created_by' => $sourceQuotation->created_by ?? auth()->id(),
                'quotation_date' => optional($sourceQuotation->quotation_date)?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'expiry_date' => optional($sourceQuotation->expiry_date)?->format('Y-m-d') ?? now()->addDays(30)->format('Y-m-d'),
                'description' => $sourceQuotation->description,
                'payment_plan' => $sourceQuotation->payment_plan,
                'payment_method' => $sourceQuotation->payment_method,
                'status' => (string) ($sourceQuotation->status?->value ?? $sourceQuotation->status ?? 'draft'),
                'reason' => $sourceQuotation->reason,
                'is_locked' => (bool) ($sourceQuotation->is_locked ?? false),
            ]);

            foreach ($sourceQuotation->quotationNotes as $note) {
                QuotationNotes::create([
                    'quotation_id' => $newQuotation->id,
                    'description' => $note->description,
                    'sort_order' => $note->sort_order,
                ]);
            }

            $newQuotation->update([
                'extensions' => collect(is_array($sourceQuotation->extensions) ? $sourceQuotation->extensions : [])
                    ->filter(fn ($extension) => is_array($extension))
                    ->values()
                    ->map(function (array $extension, int $index): array {
                        return [
                            'id' => null,
                            'quotation_extension_master_id' => $extension['quotation_extension_master_id'] ?? null,
                            'name' => $extension['name'] ?? null,
                            'type' => $extension['type'] ?? 'discount',
                            'calculation_mode' => $extension['calculation_mode'] ?? 'fixed',
                            'calculation_value' => $extension['calculation_value'] ?? 0,
                            'amount' => $extension['amount'] ?? 0,
                            'sort_order' => $extension['sort_order'] ?? ($index + 1),
                        ];
                    })
                    ->all(),
            ]);

            $newHeaderIdsBySourceHeaderId = [];
            $newItemIdsBySourceId = [];

            foreach ($movedItems as $item) {
                $sourceMemberId = (int) ($item->customer_confirmation_member_id ?? 0);
                $targetMemberId = $memberIdMap[$sourceMemberId] ?? null;

                if (! $targetMemberId) {
                    continue;
                }

                $targetParentHeaderId = $this->resolveOrCreateTargetHeaderIdForMovedItem(
                    $item,
                    $newQuotation,
                    $newHeaderIdsBySourceHeaderId,
                );

                $createdItem = QuotationItem::create([
                    'quotation_id' => $newQuotation->id,
                    'customer_confirmation_member_id' => $targetMemberId,
                    'parent_id' => $targetParentHeaderId,
                    'description' => $item->description,
                    'is_header' => false,
                    'quantity' => $item->quantity,
                    'rate' => $item->rate,
                    'sort_order' => $item->sort_order,
                ]);

                $this->duplicateQuotationItemTaxes($item, $createdItem);

                $newItemIdsBySourceId[(int) $item->id] = (int) $createdItem->id;
            }

            if ($newItemIdsBySourceId === []) {
                $newQuotation->delete();

                continue;
            }

            $newOrder = null;

            if ($sourceOrder) {
                $newOrder = Order::create([
                    'quotation_id' => $newQuotation->id,
                    'payment_plan' => $sourceOrder->payment_plan,
                ]);

                foreach ($sourceOrder->invoices as $sourceInvoice) {
                    $sourceInvoiceItemIds = $sourceInvoice->quotationItems
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    $movedSourceInvoiceItemIds = array_values(array_intersect(
                        $sourceInvoiceItemIds,
                        array_keys($newItemIdsBySourceId),
                    ));

                    if ($movedSourceInvoiceItemIds === []) {
                        continue;
                    }

                    $remainingSourceInvoiceItemIds = array_values(array_diff(
                        $sourceInvoiceItemIds,
                        $movedSourceInvoiceItemIds,
                    ));

                    $sourceInvoiceItemsById = $sourceInvoice->quotationItems
                        ->keyBy(fn (QuotationItem $item): int => (int) $item->id);

                    $movedSourceInvoiceItems = collect($movedSourceInvoiceItemIds)
                        ->map(fn (int $itemId): ?QuotationItem => $sourceInvoiceItemsById->get($itemId))
                        ->filter(fn ($item): bool => $item instanceof QuotationItem)
                        ->values();

                    $remainingSourceInvoiceItems = collect($remainingSourceInvoiceItemIds)
                        ->map(fn (int $itemId): ?QuotationItem => $sourceInvoiceItemsById->get($itemId))
                        ->filter(fn ($item): bool => $item instanceof QuotationItem)
                        ->values();

                    $movedInvoiceBaseAmount = round((float) $movedSourceInvoiceItems
                        ->sum(fn (QuotationItem $item): float => $this->quotationItemAmount($item)), 2);

                    if ($movedInvoiceBaseAmount === 0.0) {
                        continue;
                    }

                    $remainingInvoiceBaseAmount = round((float) $remainingSourceInvoiceItems
                        ->sum(fn (QuotationItem $item): float => $this->quotationItemAmount($item)), 2);

                    $movedInvoiceItemTaxAmount = round((float) $movedSourceInvoiceItems
                        ->sum(fn (QuotationItem $item): float => $this->quotationItemTaxAmount($item)), 2);

                    $remainingInvoiceItemTaxAmount = round((float) $remainingSourceInvoiceItems
                        ->sum(fn (QuotationItem $item): float => $this->quotationItemTaxAmount($item)), 2);

                    [
                        'source_extensions' => $updatedSourceExtensions,
                        'new_extensions' => $newInvoiceExtensions,
                        'source_extensions_total' => $updatedSourceExtensionsTotal,
                        'new_extensions_total' => $newInvoiceExtensionsTotal,
                    ] = $this->splitInvoiceExtensionsForMovedItems(
                        is_array($sourceInvoice->extensions) ? $sourceInvoice->extensions : [],
                        $movedInvoiceBaseAmount,
                        $remainingInvoiceBaseAmount,
                    );

                    $movedInvoiceAmount = round(
                        $movedInvoiceBaseAmount
                        + $movedInvoiceItemTaxAmount
                        + $newInvoiceExtensionsTotal,
                        2,
                    );

                    $updatedSourceInvoiceAmount = round(
                        $remainingInvoiceBaseAmount
                        + $remainingInvoiceItemTaxAmount
                        + $updatedSourceExtensionsTotal,
                        2,
                    );

                    $newInvoiceItemIds = array_values(array_filter(array_map(
                        fn (int $sourceItemId) => $newItemIdsBySourceId[$sourceItemId] ?? null,
                        $movedSourceInvoiceItemIds,
                    )));

                    $newInvoice = Invoice::create([
                        'order_id' => $newOrder->id,
                        'description' => $sourceInvoice->description,
                        'payment_method' => $sourceInvoice->payment_method,
                        'extensions' => $newInvoiceExtensions,
                        'amount' => $movedInvoiceAmount,
                        'invoice_date' => optional($sourceInvoice->invoice_date)?->format('Y-m-d') ?? now()->format('Y-m-d'),
                        'due_date' => optional($sourceInvoice->due_date)?->format('Y-m-d'),
                        'status' => $sourceInvoice->status,
                    ]);

                    $newInvoice->quotationItems()->sync($newInvoiceItemIds);

                    $sourcePrimaryReceipt = $sourceInvoice->receipt
                        ->sortBy('id')
                        ->first();

                    if ($sourcePrimaryReceipt) {
                        $this->syncInvoiceReceiptsToAmount($sourceInvoice, $updatedSourceInvoiceAmount);
                        $this->cloneReceiptToInvoice($sourcePrimaryReceipt, $newInvoice, $movedInvoiceAmount);
                    }

                    $this->syncSourceInvoiceAfterMovedItemSplit(
                        $sourceInvoice,
                        $remainingSourceInvoiceItemIds,
                        $updatedSourceInvoiceAmount,
                        $updatedSourceExtensions,
                    );

                    app(PaymentStatusService::class)->syncAfterReceiptMutation((int) $sourceInvoice->id);
                    app(PaymentStatusService::class)->syncAfterReceiptMutation((int) $newInvoice->id);
                }
            }

            QuotationItem::query()
                ->whereIn('id', array_keys($newItemIdsBySourceId))
                ->delete();

            $this->deleteQuotationHeadersWithoutChildren((int) $sourceQuotation->id);

            if (
                $newOrder
                && $newOrder->invoices()->count() === 0
                && ! $newQuotation->quotationItems()->where('is_header', false)->exists()
            ) {
                $newOrder->delete();
                $newQuotation->delete();
            }
        }
    }

    private function resolveMovedLeaderCustomerId(Collection $targetMembers): int
    {
        $leader = $targetMembers->firstWhere('is_leader', true);

        if ($leader) {
            return (int) ($leader->customer_id ?? 0);
        }

        return (int) ($targetMembers->first()?->customer_id ?? 0);
    }

    private function removeMovedItemsFromSourceWithoutTargetQuotation(
        Quotation $sourceQuotation,
        Collection $movedItems,
    ): void {
        $sourceOrder = $sourceQuotation->order;
        $movedItemIds = $movedItems
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($sourceOrder) {
            foreach ($sourceOrder->invoices as $sourceInvoice) {
                $sourceInvoiceItemIds = $sourceInvoice->quotationItems
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $movedSourceInvoiceItemIds = array_values(array_intersect(
                    $sourceInvoiceItemIds,
                    $movedItemIds,
                ));

                if ($movedSourceInvoiceItemIds === []) {
                    continue;
                }

                $remainingSourceInvoiceItemIds = array_values(array_diff(
                    $sourceInvoiceItemIds,
                    $movedSourceInvoiceItemIds,
                ));

                $movedInvoiceAmount = round(
                    $movedItems
                        ->whereIn('id', $movedSourceInvoiceItemIds)
                        ->sum(fn (QuotationItem $item) => $this->quotationItemAmount($item)),
                    2,
                );

                $this->syncSourceInvoiceAfterMovedItemRemoval(
                    $sourceInvoice,
                    $remainingSourceInvoiceItemIds,
                    $movedInvoiceAmount,
                );
            }
        }

        QuotationItem::query()->whereIn('id', $movedItemIds)->delete();

        if (! $sourceOrder) {
            return;
        }

        $hasReceipts = $sourceOrder->invoices()
            ->whereHas('receipt')
            ->exists();

        $hasRemainingItems = $sourceQuotation->quotationItems()
            ->where('is_header', false)
            ->exists();

        if (! $hasRemainingItems && ! $hasReceipts) {
            $sourceOrder->invoices()->delete();
            $sourceOrder->delete();
            $sourceQuotation->delete();
        }
    }

    private function syncSourceInvoiceAfterMovedItemRemoval(
        Invoice $sourceInvoice,
        array $remainingSourceInvoiceItemIds,
        float $movedInvoiceAmount,
    ): void {
        $sourceInvoice->quotationItems()->sync($remainingSourceInvoiceItemIds);

        $updatedSourceAmount = round(max(
            0,
            (float) $sourceInvoice->amount - $movedInvoiceAmount,
        ), 2);

        $sourceInvoice->update([
            'amount' => $updatedSourceAmount,
        ]);

        if (
            $sourceInvoice->quotationItems()->count() === 0
            && ! $sourceInvoice->receipt()->exists()
        ) {
            $sourceInvoice->delete();
        }
    }

    private function syncSourceInvoiceAfterMovedItemSplit(
        Invoice $sourceInvoice,
        array $remainingSourceInvoiceItemIds,
        float $updatedSourceInvoiceAmount,
        array $updatedSourceExtensions,
    ): void {
        $sourceInvoice->quotationItems()->sync($remainingSourceInvoiceItemIds);

        $sourceInvoice->update([
            'amount' => round($updatedSourceInvoiceAmount, 2),
            'extensions' => $updatedSourceExtensions,
        ]);

        if (
            $sourceInvoice->quotationItems()->count() === 0
            && ! $sourceInvoice->receipt()->exists()
        ) {
            $sourceInvoice->delete();
        }
    }

    private function resolveOrCreateTargetHeaderIdForMovedItem(
        QuotationItem $sourceItem,
        Quotation $newQuotation,
        array &$newHeaderIdsBySourceHeaderId,
    ): int {
        $sourceHeader = $sourceItem->parent;

        if ($sourceHeader && (bool) ($sourceHeader->is_header ?? false)) {
            $sourceHeaderId = (int) ($sourceHeader->id ?? 0);

            if ($sourceHeaderId > 0 && isset($newHeaderIdsBySourceHeaderId[$sourceHeaderId])) {
                return (int) $newHeaderIdsBySourceHeaderId[$sourceHeaderId];
            }

            $createdHeader = QuotationItem::create([
                'quotation_id' => (int) $newQuotation->id,
                'customer_confirmation_member_id' => null,
                'parent_id' => null,
                'description' => (string) ($sourceHeader->description ?? 'Header'),
                'is_header' => true,
                'sort_order' => (int) ($sourceHeader->sort_order ?? (((int) QuotationItem::query()
                    ->where('quotation_id', (int) $newQuotation->id)
                    ->max('sort_order')) + 1)),
            ]);

            if ($sourceHeaderId > 0) {
                $newHeaderIdsBySourceHeaderId[$sourceHeaderId] = (int) $createdHeader->id;
            }

            return (int) $createdHeader->id;
        }

        return (int) $this->resolveOrCreateUmrahPackagesHeaderItem($newQuotation)->id;
    }

    private function duplicateQuotationItemTaxes(QuotationItem $sourceItem, QuotationItem $targetItem): void
    {
        foreach ($sourceItem->taxes as $tax) {
            QuotationItemTax::create([
                'quotation_item_id' => (int) $targetItem->id,
                'quotation_extension_master_id' => $tax->quotation_extension_master_id,
                'name' => $tax->name,
                'calculation_mode' => $tax->calculation_mode,
                'calculation_value' => $tax->calculation_value,
                'sort_order' => $tax->sort_order,
            ]);
        }
    }

    private function quotationItemTaxAmount(QuotationItem $item): float
    {
        $lineAmount = $this->quotationItemAmount($item);

        return round((float) $item->taxes->sum(function (QuotationItemTax $tax) use ($lineAmount): float {
            $calculationMode = strtolower(trim((string) ($tax->calculation_mode ?? '')));
            $calculationValue = (float) ($tax->calculation_value ?? 0);

            if ($calculationValue === 0.0 || ! in_array($calculationMode, ['fixed', 'percentage'], true)) {
                return 0.0;
            }

            if ($calculationMode === 'percentage') {
                return $lineAmount * $calculationValue / 100;
            }

            return $calculationValue;
        }), 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sourceExtensions
     * @return array{source_extensions: array<int, array<string, mixed>>, new_extensions: array<int, array<string, mixed>>, source_extensions_total: float, new_extensions_total: float}
     */
    private function splitInvoiceExtensionsForMovedItems(
        array $sourceExtensions,
        float $movedBaseAmount,
        float $remainingBaseAmount,
    ): array {
        $source = [];
        $target = [];
        $sourceTotal = 0.0;
        $targetTotal = 0.0;

        foreach (collect($sourceExtensions)->filter(fn ($extension): bool => is_array($extension))->values() as $index => $extension) {
            $calculationMode = strtolower(trim((string) ($extension['calculation_mode'] ?? 'fixed')));
            $calculationValue = (float) ($extension['calculation_value'] ?? 0);
            $sourceAmount = round((float) ($extension['amount'] ?? 0), 2);

            if ($calculationMode === 'percentage') {
                $targetAmount = $calculationValue !== 0.0
                    ? round($movedBaseAmount * $calculationValue / 100, 2)
                    : 0.0;

                $updatedSourceAmount = $calculationValue !== 0.0
                    ? round($remainingBaseAmount * $calculationValue / 100, 2)
                    : round($sourceAmount - $targetAmount, 2);

                $source[] = [
                    ...$extension,
                    'amount' => $updatedSourceAmount,
                    'sort_order' => (int) ($extension['sort_order'] ?? ($index + 1)),
                ];

                $target[] = [
                    ...$extension,
                    'id' => null,
                    'amount' => $targetAmount,
                    'sort_order' => (int) ($extension['sort_order'] ?? ($index + 1)),
                ];

                $sourceTotal += $updatedSourceAmount;
                $targetTotal += $targetAmount;

                continue;
            }

            $fixedSourceAmount = round($sourceAmount, 2);

            $source[] = [
                ...$extension,
                'amount' => $fixedSourceAmount,
                'sort_order' => (int) ($extension['sort_order'] ?? ($index + 1)),
            ];

            $sourceTotal += $fixedSourceAmount;
        }

        return [
            'source_extensions' => array_values($source),
            'new_extensions' => array_values($target),
            'source_extensions_total' => round($sourceTotal, 2),
            'new_extensions_total' => round($targetTotal, 2),
        ];
    }

    private function syncInvoiceReceiptsToAmount(Invoice $invoice, float $targetAmount): void
    {
        $receipts = $invoice->receipt
            ->sortBy('id')
            ->values();

        if ($receipts->isEmpty()) {
            return;
        }

        $normalizedTargetAmount = round($targetAmount, 2);

        if ($normalizedTargetAmount === 0.0) {
            foreach ($receipts as $receipt) {
                $receipt->delete();
            }

            return;
        }

        $primaryReceipt = $receipts->first();

        if (! $primaryReceipt) {
            return;
        }

        $primaryReceipt->update([
            'amount' => $normalizedTargetAmount,
        ]);

        foreach ($receipts->slice(1) as $receipt) {
            $receipt->delete();
        }
    }

    private function cloneReceiptToInvoice(Receipt $sourceReceipt, Invoice $targetInvoice, float $amount): void
    {
        $normalizedAmount = round($amount, 2);

        if ($normalizedAmount === 0.0) {
            return;
        }

        Receipt::create([
            'invoice_id' => (int) $targetInvoice->id,
            'amount' => $normalizedAmount,
            'receipt_date' => optional($sourceReceipt->receipt_date)?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'payment_method' => $sourceReceipt->payment_method,
            'reference' => $sourceReceipt->reference,
            'description' => $sourceReceipt->description,
        ]);
    }

    private function deleteQuotationHeadersWithoutChildren(int $quotationId): void
    {
        $headers = QuotationItem::query()
            ->where('quotation_id', $quotationId)
            ->where('is_header', true)
            ->get();

        foreach ($headers as $header) {
            $hasChildren = QuotationItem::query()
                ->where('quotation_id', $quotationId)
                ->where('parent_id', (int) $header->id)
                ->where('is_header', false)
                ->exists();

            if (! $hasChildren) {
                $header->delete();
            }
        }
    }

    private function resolveMovedQuotationPaidAmount(Quotation $sourceQuotation, Collection $movedItems): float
    {
        $sourceOrder = $sourceQuotation->order;

        if (! $sourceOrder) {
            return 0.0;
        }

        $movedItemIds = $movedItems
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $paidAmount = 0.0;

        foreach ($sourceOrder->invoices as $sourceInvoice) {
            $invoiceItems = $sourceInvoice->quotationItems
                ->where('is_header', false)
                ->values();

            if ($invoiceItems->isEmpty()) {
                continue;
            }

            $invoiceSubtotal = (float) $invoiceItems->sum(function (QuotationItem $item): float {
                return $this->quotationItemAmount($item);
            });

            if ($invoiceSubtotal <= 0) {
                continue;
            }

            $movedSubtotal = (float) $invoiceItems
                ->whereIn('id', $movedItemIds)
                ->sum(function (QuotationItem $item): float {
                    return $this->quotationItemAmount($item);
                });

            if ($movedSubtotal <= 0) {
                continue;
            }

            $receiptTotal = (float) $sourceInvoice->receipt->sum(function (Receipt $receipt): float {
                return (float) ($receipt->amount ?? 0);
            });

            if ($receiptTotal === 0.0) {
                continue;
            }

            $paidAmount += $receiptTotal * ($movedSubtotal / $invoiceSubtotal);
        }

        return round($paidAmount, 2);
    }

    private function quotationItemAmount(QuotationItem $item): float
    {
        return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
    }

    private function syncMemberStatusesForConfirmation(int $confirmationId): void
    {
        $group = CustomerConfirmation::with([
            'members.customer.user',
            'members.quotationItems.taxes',
            'members.quotationItems.invoices.receipt',
            'members.quotationItems.quotation.order.invoices.quotationItems',
            'package',
        ])->find($confirmationId);

        if (! $group) {
            return;
        }

        foreach ($group->members as $member) {
            $currentStatus = $this->normalizePaymentStatus($member->status ?? null);

            if ($currentStatus === 'cancelled') {
                continue;
            }

            $snapshot = $this->resolveMemberFinancialSnapshot($member, $group->package);
            $nextStatus = $snapshot['status'];

            if ($nextStatus !== $currentStatus) {
                $member->update([
                    'status' => $nextStatus,
                ]);
            }

            $member->refresh();
            $member->load('customer.user');
            $this->syncOpenManifestMemberSnapshot($member);
        }

        app(PackageSeatService::class)->recalculateForPackageId(
            (int) ($group->package_id ?? 0),
        );
    }

    private function syncNonConvertedQuotationItemsForConfirmation(CustomerConfirmation $group): void
    {
        $group->loadMissing(['members.customer.user', 'package']);

        $package = $group->package;

        if (! $package) {
            return;
        }

        foreach ($group->members as $member) {
            $memberStatus = $this->normalizePaymentStatus($member->status ?? null);

            if ($memberStatus === 'cancelled') {
                continue;
            }

            $this->syncNonConvertedQuotationItemsForMember($member, $package);
        }
    }

    private function syncNonConvertedQuotationItemsForMember(
        CustomerConfirmationMember $member,
        Package $package,
    ): void {
        $memberName = $member->customer?->user?->name ?? ('Member #'.$member->id);
        $packageName = trim((string) ($package->name ?? 'Package'));
        $sharingPlanLabel = $this->formatSharingPlanLabel($member->sharing_plan);
        $resolvedRate = round(max(0.0, (float) $this->getPackagePriceForSharingPlan($package, $member->sharing_plan)), 2);

        $quotationItems = QuotationItem::query()
            ->with('parent')
            ->where('customer_confirmation_member_id', (int) $member->id)
            ->where('is_header', false)
            ->whereHas('quotation', function ($query): void {
                $query->whereNull('deleted_at')
                    ->whereNotIn('status', [
                        QuotationStatus::Converted->value,
                        QuotationStatus::Cancelled->value,
                        QuotationStatus::Expired->value,
                        QuotationStatus::Rejected->value,
                    ]);
            })
            ->get();

        foreach ($quotationItems as $quotationItem) {
            $parent = $quotationItem->parent;

            if (! $parent || ! (bool) $parent->is_header) {
                continue;
            }

            if (strtolower(trim((string) ($parent->description ?? ''))) !== 'umrah packages') {
                continue;
            }

            $quotationItem->update([
                'description' => $packageName.' - '.$memberName.' - '.$sharingPlanLabel.' sharing',
                'quantity' => 1,
                'rate' => $resolvedRate,
            ]);
        }
    }

    private function reconcileGroupBillingAgainstPackage(CustomerConfirmation $group): void
    {
        $group->loadMissing([
            'members.quotationItems.quotation.order.invoices.quotationItems',
            'members.quotationItems.quotation.order.invoices.receipt',
            'members.quotationItems.invoices.receipt',
            'package',
        ]);

        $package = $group->package;

        if (! $package) {
            return;
        }

        foreach ($group->members as $member) {
            $memberStatus = $this->normalizePaymentStatus($member->status ?? null);

            if ($memberStatus === 'cancelled') {
                continue;
            }

            $this->reconcileMemberBillingAgainstPackage($member, $package);
        }
    }

    private function reconcileMemberBillingAgainstPackage(
        CustomerConfirmationMember $member,
        Package $package,
    ): void {
        $requiredAmount = $this->resolveMemberTotalAmount($member, $package);

        if ($requiredAmount <= 0) {
            return;
        }

        $this->applyObsoleteUnpaidBillingAdjustment($member, $package, $requiredAmount);

        $billedAmount = $this->resolveMemberBilledAmount($member);
        $shortfallAmount = round($requiredAmount - $billedAmount, 2);

        if ($shortfallAmount <= 0) {
            return;
        }

        $targetQuotation = $this->resolveLatestActiveQuotationForMember($member);

        if (! $targetQuotation) {
            return;
        }

        $targetOrder = $targetQuotation->order;

        if (! $targetOrder) {
            $targetOrder = Order::create([
                'quotation_id' => $targetQuotation->id,
                'payment_plan' => $targetQuotation->payment_plan ?? 'full',
            ]);
        }

        $memberName = $member->customer?->user?->name ?? ('Member #'.$member->id);
        $packageName = trim((string) ($package->name ?? 'Package'));
        $sharingPlanLabel = $this->formatSharingPlanLabel($member->sharing_plan);
        $headerItem = $this->resolveOrCreateUmrahPackagesHeaderItem($targetQuotation);
        $nextSortOrder = ((int) QuotationItem::query()
            ->where('quotation_id', (int) $targetQuotation->id)
            ->max('sort_order')) + 1;

        $detailItem = QuotationItem::create([
            'quotation_id' => (int) $targetQuotation->id,
            'customer_confirmation_member_id' => (int) $member->id,
            'parent_id' => (int) $headerItem->id,
            'description' => $packageName.' - '.$memberName.' - '.$sharingPlanLabel.' sharing',
            'is_header' => false,
            'quantity' => 1,
            'rate' => $shortfallAmount,
            'sort_order' => $nextSortOrder + 1,
        ]);

        $balanceInvoice = Invoice::create([
            'order_id' => (int) $targetOrder->id,
            'description' => 'Invoice For Balance',
            'payment_method' => null,
            'extensions' => [],
            'amount' => $shortfallAmount,
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'status' => InvoiceStatus::Outstanding,
        ]);

        $balanceInvoice->quotationItems()->sync([
            (int) $headerItem->id,
            (int) $detailItem->id,
        ]);
    }

    private function applyObsoleteUnpaidBillingAdjustment(
        CustomerConfirmationMember $member,
        ?Package $package,
        ?float $requiredAmount = null,
    ): void {
        if (! $package) {
            return;
        }

        $memberStatus = $this->normalizePaymentStatus($member->status ?? null);

        if ($memberStatus === 'cancelled') {
            return;
        }

        $resolvedRequiredAmount = $requiredAmount ?? $this->resolveMemberTotalAmount($member, $package);

        if ($resolvedRequiredAmount <= 0) {
            return;
        }

        $paidAmount = $this->resolveMemberPaidAmount($member);
        $billedAmount = $this->resolveMemberBilledAmount($member);

        $excessBilledAmount = round(max(0.0, $billedAmount - $resolvedRequiredAmount), 2);
        $unpaidAmount = round(max(0.0, $billedAmount - $paidAmount), 2);
        $voidAmount = round(min($excessBilledAmount, $unpaidAmount), 2);

        if ($voidAmount <= 0) {
            return;
        }

        $linkedInvoice = $this->resolveMemberOutstandingInvoice($member);

        if (! $linkedInvoice || ! $linkedInvoice->order || ! $linkedInvoice->order->quotation) {
            return;
        }

        $sourceQuotation = $linkedInvoice->order->quotation;
        $memberName = $member->customer?->user?->name ?? ('Member #'.$member->id);
        $packageName = trim((string) ($package->name ?? 'Package'));
        $sharingPlanLabel = $this->formatSharingPlanLabel($member->sharing_plan);
        $voidHeaderItem = $this->resolveOrCreateUmrahPackagesHeaderItem($sourceQuotation);

        $nextSortOrder = ((int) QuotationItem::query()
            ->where('quotation_id', (int) $sourceQuotation->id)
            ->max('sort_order')) + 1;

        $voidDetailItem = QuotationItem::create([
            'quotation_id' => (int) $sourceQuotation->id,
            'customer_confirmation_member_id' => (int) $member->id,
            'parent_id' => (int) $voidHeaderItem->id,
            'description' => 'Package pricing adjustment - '.$packageName.' - '.$memberName.' - '.$sharingPlanLabel.' sharing',
            'is_header' => false,
            'quantity' => 1,
            'rate' => -$voidAmount,
            'sort_order' => $nextSortOrder + 1,
        ]);

        $existingInvoiceItemIds = $linkedInvoice->quotationItems()
            ->pluck('quotation_items.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $linkedInvoice->quotationItems()->sync(array_values(array_unique([
            ...$existingInvoiceItemIds,
            (int) $voidHeaderItem->id,
            (int) $voidDetailItem->id,
        ])));

        $updatedAmount = round(max(
            0.0,
            (float) ($linkedInvoice->amount ?? 0) - $voidAmount,
        ), 2);

        $linkedInvoice->update([
            'amount' => $updatedAmount,
        ]);
    }

    private function resolveOrCreateUmrahPackagesHeaderItem(Quotation $quotation): QuotationItem
    {
        $existingHeader = QuotationItem::query()
            ->where('quotation_id', (int) $quotation->id)
            ->where('is_header', true)
            ->whereNull('parent_id')
            ->whereRaw('LOWER(TRIM(description)) = ?', ['umrah packages'])
            ->orderBy('id')
            ->first();

        if ($existingHeader) {
            return $existingHeader;
        }

        $nextSortOrder = ((int) QuotationItem::query()
            ->where('quotation_id', (int) $quotation->id)
            ->max('sort_order')) + 1;

        return QuotationItem::create([
            'quotation_id' => (int) $quotation->id,
            'customer_confirmation_member_id' => null,
            'parent_id' => null,
            'description' => 'Umrah Packages',
            'is_header' => true,
            'sort_order' => $nextSortOrder,
        ]);
    }

    private function resolveMemberOutstandingInvoice(CustomerConfirmationMember $member): ?Invoice
    {
        return $member->quotationItems
            ->flatMap(fn (QuotationItem $item) => $item->invoices)
            ->unique('id')
            ->filter(function (Invoice $invoice): bool {
                if (InvoiceStatus::isRefund($invoice->status)) {
                    return false;
                }

                if (strtolower((string) ($invoice->status ?? '')) === InvoiceStatus::Cancelled) {
                    return false;
                }

                $invoiceAmount = round((float) ($invoice->amount ?? 0), 2);

                if ($invoiceAmount <= 0) {
                    return false;
                }

                $receiptAmount = round((float) $invoice->receipt->sum(function ($receipt): float {
                    return (float) ($receipt->amount ?? 0);
                }), 2);

                return round($invoiceAmount - $receiptAmount, 2) > 0;
            })
            ->sortByDesc('id')
            ->first();
    }

    private function resolveLatestActiveQuotationForMember(CustomerConfirmationMember $member): ?Quotation
    {
        return $member->quotationItems
            ->filter(function (QuotationItem $item): bool {
                if ((bool) $item->is_header) {
                    return false;
                }

                $quotation = $item->quotation;
                if (! $quotation || $quotation->trashed()) {
                    return false;
                }

                $status = strtolower((string) ($quotation->status?->value ?? $quotation->status ?? ''));

                return ! in_array($status, ['cancelled', 'expired', 'rejected'], true);
            })
            ->map(fn (QuotationItem $item) => $item->quotation)
            ->filter()
            ->sortByDesc('id')
            ->first();
    }

    private function buildGroupSnapshot(CustomerConfirmation $group): array
    {
        $group->loadMissing(['members.customer.user', 'enquiry', 'package']);

        return [
            'group' => [
                'id' => $group->id,
                'enquiry_id' => $group->enquiry_id,
                'package_id' => $group->package_id,
                'package_room_type' => $group->package_room_type,
                'date_of_application' => optional($group->date_of_application)?->format('Y-m-d'),
                'member_count' => $group->members->count(),
            ],
            'members' => $group->members
                ->map(function (CustomerConfirmationMember $member) {
                    $customer = $member->customer;
                    $user = $customer?->user;

                    return [
                        'member_id' => $member->id,
                        'customer_id' => $customer?->id,
                        'is_leader' => (bool) $member->is_leader,
                        'status' => $member->status,
                        'sharing_plan' => $member->sharing_plan,
                        'relationship' => $member->relationship,
                        'name' => $user?->name,
                        'email' => $user?->email,
                        'contact_number' => $user?->contact,
                        'nric_number' => $customer?->nric_number,
                        'address' => $customer?->address,
                        'nationality' => $customer?->nationality,
                        'passport_number' => $customer?->passport_number,
                        'passport_issue_date' => optional($customer?->passport_issue_date)?->format('Y-m-d'),
                        'passport_expiry_date' => optional($customer?->passport_expiry_date)?->format('Y-m-d'),
                        'passport_place_of_issue' => $customer?->passport_place_of_issue,
                        'gender' => $customer?->gender,
                        'marital_status' => $customer?->marital_status,
                        'date_of_birth' => optional($customer?->date_of_birth)?->format('Y-m-d'),
                        'place_of_birth' => $customer?->place_of_birth,
                        'first_time_umrah' => $customer?->first_time_umrah,
                        'has_chronic_disease' => $customer?->has_chronic_disease,
                        'is_using_wheelchair' => $customer?->is_using_wheelchair,
                        'chronic_disease_details' => $customer?->chronic_disease_details,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    private function sanitizeSnapshot(array $snapshot): array
    {
        $sensitiveFields = [
            'nric_number',
            'passport_number',
            'address',
            'contact_number',
            'email',
        ];

        return $this->maskSensitiveValues($snapshot, $sensitiveFields);
    }

    /**
     * @param  array<int, string>  $sensitiveFields
     */
    private function maskSensitiveValues(array $payload, array $sensitiveFields): array
    {
        $masked = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $masked[$key] = $this->maskSensitiveValues($value, $sensitiveFields);

                continue;
            }

            if (in_array((string) $key, $sensitiveFields, true)) {
                $masked[$key] = $this->maskValue(is_scalar($value) ? (string) $value : null);

                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }

    private function maskValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $trimmedValue = trim($value);
        $length = mb_strlen($trimmedValue);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).mb_substr($trimmedValue, -4);
    }

    private function buildLogContext(
        string $operation,
        ?int $enquiryId,
        ?int $packageId,
    ): array {
        $request = request();
        $actor = auth()->user();

        return [
            'operation' => $operation,
            'actor' => [
                'id' => $actor?->id,
                'email' => $actor?->email,
            ],
            'related' => [
                'enquiry_id' => $enquiryId,
                'package_id' => $packageId,
            ],
            'request' => [
                'ip' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ],
            'logged_at' => now()->toIso8601String(),
        ];
    }

    private function getPackagePriceForSharingPlan(?Package $package, ?string $sharingPlan): float
    {
        if (! $package || ! $sharingPlan) {
            return 0;
        }

        return match ($sharingPlan) {
            'single' => (float) ($package->price_single ?? 0),
            'double' => (float) ($package->price_double ?? 0),
            'triple' => (float) ($package->price_triple ?? 0),
            'quad' => (float) ($package->price_quad ?? 0),
            'child_with_bed' => (float) ($package->child_with_bed_price ?? 0),
            'child_no_bed' => (float) ($package->child_no_bed_price ?? 0),
            'infant' => (float) ($package->infant_price ?? 0),
            default => 0,
        };
    }

    private function syncOpenManifestMemberSnapshot(CustomerConfirmationMember $member): void
    {
        $openManifestMemberQuery = ManifestMember::query()
            ->where('customer_confirmation_member_id', (int) $member->id)
            ->whereHas('manifest.package', function ($query): void {
                $query->where('status', 'open');
            });

        $manifestIds = (clone $openManifestMemberQuery)
            ->pluck('manifest_id')
            ->map(fn ($manifestId): int => (int) $manifestId)
            ->filter(fn (int $manifestId): bool => $manifestId > 0)
            ->unique()
            ->values()
            ->all();

        if ($this->normalizePaymentStatus($member->status ?? null) === 'cancelled') {
            $openManifestMemberQuery->delete();
            $this->syncIdentityDocumentsForManifests($manifestIds);

            return;
        }

        $confirmation = $member->relationLoaded('confirmation')
            ? $member->confirmation
            : $member->confirmation()->with('package')->first();

        $packageId = (int) ($confirmation?->package_id ?? 0);
        $hasPaidAmount = $this->memberHasPaidAmountForManifestAutoLink($member, $confirmation?->package);
        $isEligibleForAutoLink =
            $packageId > 0
            && $hasPaidAmount
            && in_array(
                $this->normalizePaymentStatus($member->status ?? null),
                ['partially_paid', 'fully_paid', 'overpaid'],
                true,
            )
            && $this->packageSeatService->hasAvailableSeat($packageId, (int) $member->id);

        if ($isEligibleForAutoLink) {
            $existingOpenManifestMember = ManifestMember::query()
                ->where('customer_confirmation_member_id', (int) $member->id)
                ->whereHas('manifest.package', function ($query): void {
                    $query->where('status', 'open');
                })
                ->with('manifest')
                ->first();

            if (! $existingOpenManifestMember) {
                $targetManifest = Manifest::query()
                    ->where('package_id', $packageId)
                    ->whereHas('package', function ($query): void {
                        $query->where('status', 'open');
                    })
                    ->orderBy('id')
                    ->first();

                if ($targetManifest) {
                    $createdManifestMember = ManifestMember::query()->create([
                        'manifest_id' => (int) $targetManifest->id,
                        'customer_confirmation_member_id' => (int) $member->id,
                        'sharing_plan' => $member->sharing_plan,
                        'sort_order' => ((int) ManifestMember::query()
                            ->where('manifest_id', (int) $targetManifest->id)
                            ->max('sort_order')) + 1,
                    ]);

                    $this->ensureManifestStructureForAutoLinkedMember(
                        $member,
                        $createdManifestMember,
                        $targetManifest,
                    );

                    $manifestIds[] = (int) $targetManifest->id;
                    $manifestIds = array_values(array_unique(array_map('intval', $manifestIds)));
                }
            } elseif ($existingOpenManifestMember->manifest) {
                $this->ensureManifestStructureForAutoLinkedMember(
                    $member,
                    $existingOpenManifestMember,
                    $existingOpenManifestMember->manifest,
                );
            }
        }

        $openManifestMembers = ManifestMember::query()
            ->where('customer_confirmation_member_id', (int) $member->id)
            ->whereHas('manifest.package', function ($query): void {
                $query->where('status', 'open');
            });

        $customer = $member->customer;
        $user = $customer?->user;

        if (! $customer || ! $user) {
            return;
        }

        $openManifestMembers->update([
            'relationship' => $member->relationship,
            'sharing_plan' => $member->sharing_plan,
            'name' => $user->name,
            'contact_number' => $user->contact,
            'nationality' => $customer->nationality,
            'passport_number' => $customer->passport_number,
            'gender' => $customer->gender,
            'date_of_birth' => $customer->date_of_birth,
            'passport_issue_date' => $customer->passport_issue_date,
            'passport_expiry_date' => $customer->passport_expiry_date,
            'passport_place_of_issue' => $customer->passport_place_of_issue,
            'place_of_birth' => $customer->place_of_birth,
            'address' => $customer->address,
            'first_time_umrah' => $customer->first_time_umrah,
            'has_chronic_disease' => $customer->has_chronic_disease,
            'is_using_wheelchair' => $customer->is_using_wheelchair,
            'chronic_disease_details' => $customer->chronic_disease_details,
            'passport_path' => $this->resolveCustomerDocumentPath($customer, 'passport'),
            'photo_path' => $this->resolveCustomerDocumentPath($customer, 'photo'),
        ]);

        if ($isEligibleForAutoLink) {
            $openManifestMembersCollection = $openManifestMembers
                ->with('manifest')
                ->get();

            foreach ($openManifestMembersCollection as $openManifestMember) {
                if (! $openManifestMember->manifest) {
                    continue;
                }

                $this->ensureManifestStructureForAutoLinkedMember(
                    $member,
                    $openManifestMember,
                    $openManifestMember->manifest,
                );

                $manifestIds[] = (int) $openManifestMember->manifest_id;
            }

            $manifestIds = array_values(array_unique(array_map('intval', $manifestIds)));
        }

        $this->syncIdentityDocumentsForManifests($manifestIds);
    }

    private function ensureManifestStructureForAutoLinkedMember(
        CustomerConfirmationMember $member,
        ManifestMember $manifestMember,
        Manifest $manifest,
    ): void {
        $confirmation = $member->relationLoaded('confirmation')
            ? $member->confirmation
            : $member->confirmation()->first();

        $confirmationId = (int) ($confirmation?->id ?? 0);

        if ($confirmationId > 0 && (int) ($manifestMember->manifest_sharing_group_id ?? 0) <= 0) {
            $sharingGroup = ManifestSharingGroup::query()
                ->where('manifest_id', (int) $manifest->id)
                ->where('customer_confirmation_id', $confirmationId)
                ->orderBy('id')
                ->first();

            if (! $sharingGroup) {
                $sharingGroup = ManifestSharingGroup::query()->create([
                    'manifest_id' => (int) $manifest->id,
                    'customer_confirmation_id' => $confirmationId,
                    'sort_order' => ((int) ManifestSharingGroup::query()
                        ->where('manifest_id', (int) $manifest->id)
                        ->max('sort_order')) + 1,
                    'remarks' => 'Auto-linked from confirmation #'.$confirmationId,
                ]);
            }

            $manifestMember->update([
                'manifest_sharing_group_id' => (int) $sharingGroup->id,
            ]);
        }

        if ($manifestMember->roomAssignments()->exists()) {
            return;
        }

        $normalizedSharingPlan = strtolower(trim((string) ($member->sharing_plan ?? $manifestMember->sharing_plan ?? 'single')));

        if ($normalizedSharingPlan === '') {
            $normalizedSharingPlan = 'single';
        }

        $normalizedRoomType = $this->resolveManifestRoomTypeFromSharingPlan($normalizedSharingPlan);
        $roomLabel = $confirmationId > 0
            ? 'Auto Room - Confirmation #'.$confirmationId
            : 'Auto Room';

        $room = ManifestRoom::query()
            ->where('manifest_id', (int) $manifest->id)
            ->where('room_label', $roomLabel)
            ->orderBy('id')
            ->first();

        if (! $room) {
            $room = ManifestRoom::query()->create([
                'manifest_id' => (int) $manifest->id,
                'sort_order' => ((int) ManifestRoom::query()
                    ->where('manifest_id', (int) $manifest->id)
                    ->max('sort_order')) + 1,
                'room_label' => $roomLabel,
                'room_type' => $normalizedRoomType,
                'sharing_plan' => $normalizedSharingPlan,
                'capacity' => $this->resolveManifestRoomCapacityFromSharingPlan($normalizedSharingPlan),
                'status' => 'pending',
                'remarks' => 'Auto-linked from confirmation member #'.$member->id,
            ]);
        }

        $existingAssignment = $room->roomMembers()
            ->where('manifest_member_id', (int) $manifestMember->id)
            ->first();

        if ($existingAssignment) {
            return;
        }

        $room->roomMembers()->create([
            'manifest_member_id' => (int) $manifestMember->id,
            'sort_order' => ((int) $room->roomMembers()->max('sort_order')) + 1,
        ]);
    }

    private function resolveManifestRoomTypeFromSharingPlan(?string $sharingPlan): string
    {
        $normalized = strtolower(trim((string) $sharingPlan));

        return match ($normalized) {
            'double' => 'double',
            'triple' => 'triple',
            'quad' => 'quad',
            default => 'single',
        };
    }

    private function resolveManifestRoomCapacityFromSharingPlan(?string $sharingPlan): int
    {
        $normalized = strtolower(trim((string) $sharingPlan));

        return match ($normalized) {
            'double' => 2,
            'triple' => 3,
            'quad' => 4,
            default => 1,
        };
    }

    private function memberHasPaidAmountForManifestAutoLink(
        CustomerConfirmationMember $member,
        ?Package $package,
    ): bool {
        if (! $package) {
            return false;
        }

        $member->loadMissing([
            'quotationItems.taxes',
            'quotationItems.invoices.receipt',
            'quotationItems.quotation.order.invoices.quotationItems',
        ]);

        $snapshot = $this->resolveMemberFinancialSnapshot($member, $package);

        return (float) ($snapshot['paid_amount'] ?? 0) > 0;
    }

    /**
     * @param  array<int, int>  $manifestIds
     */
    private function syncIdentityDocumentsForManifests(array $manifestIds): void
    {
        if ($manifestIds === []) {
            return;
        }

        $manifests = Manifest::query()
            ->whereIn('id', $manifestIds)
            ->with(['members'])
            ->get();

        foreach ($manifests as $manifest) {
            $passportRows = $this->buildIdentityDocumentRowsForManifest($manifest, 'passport');
            $photoRows = $this->buildIdentityDocumentRowsForManifest($manifest, 'photo');

            $manifest->files()->whereIn('field', ['passport', 'photo'])->delete();

            foreach ($passportRows as $row) {
                $manifest->files()->create($row);
            }

            foreach ($photoRows as $row) {
                $manifest->files()->create($row);
            }
        }
    }

    /**
     * @return array<int, array{field: string, file_name: string, file_path: string}>
     */
    private function buildIdentityDocumentRowsForManifest(Manifest $manifest, string $field): array
    {
        return $manifest->members
            ->values()
            ->map(function (ManifestMember $manifestMember, int $index) use ($field): ?array {
                $path = $field === 'passport'
                    ? $this->normalizeNullableString($manifestMember->passport_path)
                    : $this->normalizeNullableString($manifestMember->photo_path);

                if (! $path) {
                    return null;
                }

                $memberName = trim((string) ($manifestMember->name ?? ''));
                $resolvedMemberName = $memberName !== '' ? $memberName : 'Member '.($index + 1);
                $fileName = $field === 'passport'
                    ? $resolvedMemberName.' Passport'
                    : $resolvedMemberName.' Photo';

                return [
                    'field' => $field,
                    'file_name' => $fileName,
                    'file_path' => $path,
                ];
            })
            ->filter(fn ($row) => is_array($row))
            ->values()
            ->all();
    }

    /** Store one uploaded file and return its hashed path. */
    private function handleFileUpload(mixed $file, string $field): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        return $file->store("customers/{$field}", 'public');
    }

    /** Process member file uploads and update model file records. */
    private function processFileUploads(Customer $customer, array $memberData): void
    {
        $documentConfigs = [
            [
                'field' => 'passport',
                'file_key' => 'passport_file',
                'name_key' => 'passport_file_name',
                'removed_key' => 'passport_file_removed',
            ],
            [
                'field' => 'photo',
                'file_key' => 'photo_file',
                'name_key' => 'photo_file_name',
                'removed_key' => 'photo_file_removed',
            ],
        ];

        $customerName = $customer->user?->name ?? 'customer';
        $existingFiles = $this->getCustomerDocumentsByField($customer);
        $customerPathUpdates = [];

        foreach ($documentConfigs as $documentConfig) {
            $field = $documentConfig['field'];
            $fileKey = $documentConfig['file_key'];
            $nameKey = $documentConfig['name_key'];
            $removedKey = $documentConfig['removed_key'];

            $existingFile = $existingFiles->get($field);
            $path = $this->handleFileUpload($memberData[$fileKey] ?? null, $field);
            $isMarkedAsRemoved = (bool) ($memberData[$removedKey] ?? false);

            if ($path) {
                if ($existingFile?->file_path) {
                    Storage::disk('public')->delete($existingFile->file_path);
                }

                $uploadedFile = $memberData[$fileKey];
                $requestedFileName = $this->normalizeNullableString($memberData[$nameKey] ?? null);
                $defaultFileName = $uploadedFile instanceof UploadedFile
                    ? $this->buildDefaultDocumentName($field, $customerName, $uploadedFile)
                    : null;

                $customer->files()->updateOrCreate(
                    ['field' => $field],
                    [
                        'file_name' => $requestedFileName ?? $defaultFileName ?? $field,
                        'file_path' => $path,
                    ],
                );

                $customerPathUpdates[$field.'_path'] = $path;

                continue;
            }

            if ($isMarkedAsRemoved) {
                if ($existingFile) {
                    Storage::disk('public')->delete($existingFile->file_path);
                    $existingFile->delete();
                }

                $customerPathUpdates[$field.'_path'] = null;
            }
        }

        if ($customerPathUpdates !== []) {
            $customer->update($customerPathUpdates);
        }
    }

    private function resolveCustomerDocumentPath(Customer $customer, string $field): ?string
    {
        $column = match ($field) {
            'passport' => 'passport_path',
            'photo' => 'photo_path',
            default => null,
        };

        if ($column === null) {
            return null;
        }

        $columnPath = $this->normalizeNullableString($customer->{$column});

        if ($columnPath !== null) {
            return $columnPath;
        }

        $files = $customer->relationLoaded('files')
            ? $customer->files
            : $customer->files()->get();

        $matchingFile = $files->firstWhere('field', $field);

        if (! $matchingFile instanceof ModelFile) {
            return null;
        }

        return $this->normalizeNullableString($matchingFile->file_path);
    }

    private function buildDefaultDocumentName(string $field, string $customerName, UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $fieldLabel = ucfirst($field);
        $safeCustomerName = trim($customerName) !== '' ? trim($customerName) : 'Customer';

        return $fieldLabel.' '.$safeCustomerName.($extension !== '' ? '.'.$extension : '');
    }

    /**
     * @return Collection<string, ModelFile>
     */
    private function getCustomerDocumentsByField(Customer $customer): Collection
    {
        if ($customer->relationLoaded('files')) {
            return $customer->files->keyBy('field');
        }

        return $customer->files()->get()->keyBy('field');
    }

    private function formatDocumentPayload(?ModelFile $modelFile): ?array
    {
        if (! $modelFile) {
            return null;
        }

        return [
            'field' => $modelFile->field,
            'file_name' => $modelFile->file_name,
            'file_path' => $modelFile->file_path,
        ];
    }

    /**
     * Generate quotation(s) from a customer confirmation.
     *
     * Each payer gets one quotation. Each member they pay for becomes a quotation item
     * with the cost derived from the package sharing-plan price.
     *
     * @param  array<int, int[]>  $payerToMembers  Maps payer member ID → array of member IDs they pay for.
     * @return \App\Models\Quotation[]
     */
    public function generateQuotationsFromConfirmation(int $confirmationId, array $payerToMembers): array
    {
        return DB::transaction(function () use ($confirmationId, $payerToMembers) {
            $group = CustomerConfirmation::with(['members.customer.user', 'package'])
                ->findOrFail($confirmationId);

            $package = $group->package;
            if (! $package) {
                throw ValidationException::withMessages([
                    'payer_to_members' => 'Cannot generate quotation: selected customer confirmation does not have a package.',
                ]);
            }

            $membersById = $group->members->keyBy('id');

            $createdQuotations = [];

            foreach ($payerToMembers as $payerMemberId => $coveredMemberIds) {
                $payerMember = $membersById->get((int) $payerMemberId);
                if (! $payerMember || ! $payerMember->customer) {
                    continue;
                }

                $quotation = Quotation::create([
                    'customer_id' => $payerMember->customer->id,
                    'customer_confirmation_id' => $confirmationId,
                    'created_by' => auth()->id(),
                    'quotation_date' => now()->format('Y-m-d'),
                    'expiry_date' => now()->addDays(30)->format('Y-m-d'),
                    'payment_plan' => 'full',
                    'status' => 'draft',
                    'description' => 'Payment for travel package — '
                        .($package->name ?? 'Package #'.$package->id),
                ]);

                $masterQuotationNotes = $this->noteService->get('master', 'quotation')
                    ->map(function ($note) {
                        return [
                            'description' => $note->description,
                            'sort_order' => $note->sort_order,
                        ];
                    })
                    ->values()
                    ->all();

                if (! empty($masterQuotationNotes)) {
                    $this->noteService->sync('quotation', (int) $quotation->id, $masterQuotationNotes);
                }

                $sortOrder = 1;
                $umrahPackageHeader = QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'parent_id' => null,
                    'description' => 'Umrah Packages',
                    'is_header' => true,
                    'sort_order' => $sortOrder++,
                ]);

                foreach ($coveredMemberIds as $memberId) {
                    $member = $membersById->get((int) $memberId);
                    if (! $member || ! $member->customer) {
                        continue;
                    }

                    $sharingPlan = strtolower(trim((string) ($member->sharing_plan ?? '')));
                    if ($sharingPlan === '') {
                        $memberName = $member->customer->user->name ?? 'Member #'.$member->id;

                        throw ValidationException::withMessages([
                            'payer_to_members' => "Cannot generate quotation: {$memberName} does not have a sharing plan selected in customer confirmation.",
                        ]);
                    }

                    if ($this->memberHasAnyBilling((int) $member->id)) {
                        $memberName = $member->customer->user->name ?? 'Member #'.$member->id;

                        throw ValidationException::withMessages([
                            'payer_to_members' => "Cannot generate quotation: {$memberName} is already linked to an active quotation item.",
                        ]);
                    }

                    $rate = $this->getPackagePriceForSharingPlan($package, $sharingPlan);
                    $planLabel = $this->formatSharingPlanLabel($sharingPlan);
                    $memberName = $member->customer->user->name ?? 'Member #'.$member->id;

                    QuotationItem::create([
                        'quotation_id' => $quotation->id,
                        'customer_confirmation_member_id' => $member->id,
                        'parent_id' => $umrahPackageHeader->id,
                        'description' => "{$package->name} - {$memberName} - {$planLabel} sharing",
                        'is_header' => false,
                        'quantity' => 1,
                        'rate' => $rate,
                        'sort_order' => $sortOrder++,
                    ]);
                }

                // Update covered members to pending_payment
                CustomerConfirmationMember::whereIn('id', $coveredMemberIds)
                    ->whereIn('status', ['pending_payment'])
                    ->update(['status' => 'pending_payment']);

                $quotation->load('quotationItems');
                $createdQuotations[] = $quotation;
            }

            return $createdQuotations;
        });
    }

    private function formatSharingPlanLabel(?string $sharingPlan): string
    {
        $normalized = strtolower(trim((string) $sharingPlan));

        return match ($normalized) {
            'single' => 'Single',
            'double' => 'Double',
            'triple' => 'Triple',
            'quad' => 'Quad',
            'child_with_bed' => 'Child with Bed',
            'child_no_bed' => 'Child without Bed',
            'infant' => 'Infant',
            default => 'Standard',
        };
    }

    /**
     * Check if all members in a confirmation are being refunded.
     *
     * @param  array<int, array<string, mixed>>  $memberRefunds
     */
    public function areAllMembersBeingRefunded(int $confirmationId, array $memberRefunds): bool
    {
        $group = CustomerConfirmation::query()->with('members')->findOrFail($confirmationId);

        $refundMemberIds = collect($memberRefunds)
            ->map(fn ($refund) => (int) ($refund['member_id'] ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $totalMembers = count($group->members ?? []);
        $refundingMembers = count($refundMemberIds);

        return $totalMembers > 0 && $refundingMembers === $totalMembers;
    }

    /**
     * Create refund receipts for selected members.
     *
     * Important: This method updates members in the EXISTING confirmation, does NOT create a new CC.
     * When refunding all members in a confirmation, they will be marked as cancelled in the original CC,
     * and the is_holding flag will be set to false if no active members remain.
     *
     * To prevent CC duplication:
     * - When ALL members are being refunded, don't call moveMembersToHolding beforehand
     * - Only call moveMembersToHolding when refunding SOME members (keeping others active)
     * - Use areAllMembersBeingRefunded() to check before deciding to call moveMembersToHolding
     *
     * @param  array<int, array<string, mixed>>  $memberRefunds
     * @return array{count:int, receipt_ids:array<int, int>, invoice_ids:array<int, int>}
     */
    public function createRefundReceipts(int $confirmationId, array $memberRefunds, string $refundType = 'cancel'): array
    {
        return DB::transaction(function () use ($confirmationId, $memberRefunds, $refundType) {
            $group = CustomerConfirmation::with([
                'members.customer.user',
                'members.quotationItems',
                'members.quotationItems.invoices.receipt',
                'package',
            ])->findOrFail($confirmationId);

            $membersById = $group->members->keyBy('id');
            $createdReceiptIds = [];
            $createdInvoiceIds = [];
            $normalizedRefundType = in_array($refundType, ['cancel', 'overpaid'], true)
                ? $refundType
                : 'cancel';
            $refundPurposeLabel = $normalizedRefundType === 'cancel'
                ? 'Trip Cancelled-Refund'
                : 'Overpaid Refund';
            $defaultRefundDescription = $normalizedRefundType === 'cancel'
                ? 'Receipt For Trip Cancelled-Refund'
                : 'Receipt For Overpaid Refund';

            foreach ($memberRefunds as $refundPayload) {
                $memberId = (int) ($refundPayload['member_id'] ?? 0);
                $member = $membersById->get($memberId);

                if (! $member) {
                    throw ValidationException::withMessages([
                        'member_refunds' => 'Selected member is invalid for this customer confirmation.',
                    ]);
                }

                $paidAmount = $this->resolveMemberPaidAmount($member);

                if ($paidAmount <= 0) {
                    throw ValidationException::withMessages([
                        'member_refunds' => 'Refund is only available for members with paid amount.',
                    ]);
                }

                $snapshot = $this->resolveMemberFinancialSnapshot($member, $group->package);
                $overpaidAmount = round((float) ($snapshot['overpaid_amount'] ?? 0), 2);

                if ($normalizedRefundType === 'overpaid' && $overpaidAmount <= 0) {
                    throw ValidationException::withMessages([
                        'member_refunds' => 'Overpaid refund is only available for members with overpaid amount.',
                    ]);
                }

                $maxRefundAmount = $normalizedRefundType === 'overpaid'
                    ? $overpaidAmount
                    : $paidAmount;

                $refundAmount = $this->resolveRequestedRefundAmount($maxRefundAmount, $refundPayload);

                $linkedInvoice = $this->resolveMemberLatestInvoice($member);

                if (! $linkedInvoice || ! $linkedInvoice->order || ! $linkedInvoice->order->quotation) {
                    throw ValidationException::withMessages([
                        'member_refunds' => 'Unable to resolve source invoice for refund generation.',
                    ]);
                }

                $sourceQuotation = $linkedInvoice->order->quotation;
                $memberName = $member->customer?->user?->name ?? ('Member #'.$member->id);

                $paymentMethod = trim((string) ($refundPayload['payment_method'] ?? ''));
                if ($paymentMethod === '') {
                    $paymentMethod = trim((string) ($this->resolveMemberLatestInvoicePaymentMethod($member) ?? ''));
                }

                if ($paymentMethod === '') {
                    $paymentMethod = 'refund';
                }

                $refundDescription = trim((string) ($refundPayload['description'] ?? ''));

                if ($refundDescription === '') {
                    $refundDescription = $defaultRefundDescription;
                }

                $refundSortOrder = ((int) QuotationItem::query()
                    ->where('quotation_id', (int) $sourceQuotation->id)
                    ->max('sort_order')) + 1;

                $refundHeaderItem = QuotationItem::create([
                    'quotation_id' => (int) $sourceQuotation->id,
                    'customer_confirmation_member_id' => null,
                    'parent_id' => null,
                    'description' => $refundPurposeLabel,
                    'is_header' => true,
                    'sort_order' => $refundSortOrder,
                ]);

                $refundDetailItem = QuotationItem::create([
                    'quotation_id' => (int) $sourceQuotation->id,
                    'customer_confirmation_member_id' => (int) $member->id,
                    'parent_id' => (int) $refundHeaderItem->id,
                    'description' => $refundPurposeLabel.' - '.$memberName,
                    'is_header' => false,
                    'quantity' => 1,
                    'rate' => -$refundAmount,
                    'sort_order' => $refundSortOrder + 1,
                ]);

                $refundInvoice = Invoice::create([
                    'order_id' => (int) $linkedInvoice->order_id,
                    'invoice_number' => null,
                    'description' => $refundDescription,
                    'payment_method' => $paymentMethod,
                    'extensions' => [],
                    'amount' => -$refundAmount,
                    'invoice_date' => now()->format('Y-m-d'),
                    'due_date' => now()->format('Y-m-d'),
                    'status' => InvoiceStatus::Refund,
                ]);

                $refundInvoice->quotationItems()->sync([
                    (int) $refundHeaderItem->id,
                    (int) $refundDetailItem->id,
                ]);

                $refundReceipt = Receipt::create([
                    'invoice_id' => $refundInvoice->id,
                    'receipt_number' => $this->numberingService->ensureNumber('receipt', null),
                    'amount' => -$refundAmount,
                    'receipt_date' => now()->format('Y-m-d'),
                    'payment_method' => $paymentMethod,
                    'reference' => null,
                    'description' => $refundDescription,
                ]);

                if ($normalizedRefundType === 'cancel') {
                    $this->clearOutstandingUnpaidInvoiceLinksForMember($member);

                    $member->update([
                        'status' => 'cancelled',
                    ]);
                }

                $this->syncOpenManifestMemberSnapshot($member->fresh());

                $createdReceiptIds[] = (int) $refundReceipt->id;
                $createdInvoiceIds[] = (int) $refundInvoice->id;
            }

            app(PackageSeatService::class)->recalculateForPackageId(
                (int) ($group->package_id ?? 0),
            );

            if ($normalizedRefundType === 'cancel') {
                $group->load('members');

                $hasActiveMembers = $group->members->contains(function (CustomerConfirmationMember $member): bool {
                    return $this->normalizePaymentStatus($member->status ?? null) !== 'cancelled';
                });

                if ((bool) $group->is_holding && ! $hasActiveMembers) {
                    $group->update([
                        'is_holding' => false,
                    ]);
                }
            }

            $this->syncMemberStatusesForConfirmation((int) $group->id);

            return [
                'count' => count($createdReceiptIds),
                'receipt_ids' => $createdReceiptIds,
                'invoice_ids' => $createdInvoiceIds,
            ];
        });
    }

    /**
     * @param  array<int, int>  $memberIds
     * @return array{count:int, receipt_ids:array<int, int>, invoice_ids:array<int, int>}
     */
    public function createOverpaymentRefundReceipts(int $confirmationId, array $memberIds): array
    {
        $group = CustomerConfirmation::with([
            'members.customer.user',
            'members.quotationItems',
            'members.quotationItems.invoices.receipt',
            'package',
        ])->findOrFail($confirmationId);

        $requestedMemberIds = collect($memberIds)
            ->map(fn ($memberId) => (int) $memberId)
            ->filter(fn (int $memberId) => $memberId > 0)
            ->unique()
            ->values();

        if ($requestedMemberIds->isEmpty()) {
            throw ValidationException::withMessages([
                'member_ids' => 'At least one member is required for overpayment refund.',
            ]);
        }

        $membersById = $group->members->keyBy('id');

        $memberRefundPayloads = $requestedMemberIds
            ->map(function (int $memberId) use ($membersById, $group): array {
                $member = $membersById->get($memberId);

                if (! $member) {
                    throw ValidationException::withMessages([
                        'member_ids' => 'Selected member is invalid for this customer confirmation.',
                    ]);
                }

                $summary = $this->resolveMemberFinancialSnapshot($member, $group->package);
                $overpaidAmount = (float) ($summary['overpaid_amount'] ?? 0);

                if ($overpaidAmount <= 0) {
                    throw ValidationException::withMessages([
                        'member_ids' => 'Selected member does not have overpaid amount.',
                    ]);
                }

                $memberName = $member->customer?->user?->name ?? ('Member #'.$member->id);

                return [
                    'member_id' => $memberId,
                    'mode' => 'fixed',
                    'amount' => round($overpaidAmount, 2),
                    'payment_method' => 'overpayment_refund',
                    'description' => 'Overpayment Refund - '.$memberName,
                ];
            })
            ->values()
            ->all();

        return $this->createRefundReceipts(
            $confirmationId,
            $memberRefundPayloads,
            'overpaid',
        );
    }

    public function createBalanceInvoiceForUnderpaidMember(int $confirmationId, int $memberId): Invoice
    {
        return DB::transaction(function () use ($confirmationId, $memberId) {
            $group = CustomerConfirmation::with([
                'members.customer.user',
                'members.quotationItems',
                'members.quotationItems.invoices.receipt',
                'members.quotationItems.quotation.order.invoices.quotationItems',
                'members.quotationItems.quotation.order.invoices.receipt',
                'package',
            ])->findOrFail($confirmationId);

            $member = $group->members->firstWhere('id', $memberId);

            if (! $member) {
                throw ValidationException::withMessages([
                    'member' => 'Selected member is invalid for this customer confirmation.',
                ]);
            }

            $normalizedStatus = $this->normalizePaymentStatus($member->status ?? null);

            if ($normalizedStatus === 'cancelled') {
                throw ValidationException::withMessages([
                    'member' => 'Cancelled member cannot create balance invoice.',
                ]);
            }

            $snapshot = $this->resolveMemberFinancialSnapshot($member, $group->package);
            $paidAmount = round((float) ($snapshot['paid_amount'] ?? 0), 2);
            $totalAmount = round((float) ($snapshot['total_amount'] ?? 0), 2);
            $billedAmount = round((float) ($snapshot['billed_amount'] ?? 0), 2);

            $underpaidAmount = round(max(0.0, $totalAmount - $paidAmount), 2);
            $unbilledAmount = round(max(0.0, $totalAmount - $billedAmount), 2);

            if ($underpaidAmount <= 0) {
                throw ValidationException::withMessages([
                    'member' => 'Selected member is not underpaid.',
                ]);
            }

            if ($unbilledAmount <= 0) {
                throw ValidationException::withMessages([
                    'member' => 'Outstanding amount already covered by existing invoices.',
                ]);
            }

            $linkedInvoice = $this->resolveMemberLatestInvoice($member);

            if (! $linkedInvoice || ! $linkedInvoice->order || ! $linkedInvoice->order->quotation) {
                throw ValidationException::withMessages([
                    'member' => 'Unable to resolve source order for balance invoice.',
                ]);
            }

            $sourceQuotation = $linkedInvoice->order->quotation;
            $memberName = $member->customer?->user?->name ?? ('Member #'.$member->id);
            $packageName = trim((string) ($group->package?->name ?? 'Package'));
            $sharingPlanLabel = $this->formatSharingPlanLabel($member->sharing_plan);

            $nextSortOrder = ((int) QuotationItem::query()
                ->where('quotation_id', (int) $sourceQuotation->id)
                ->max('sort_order')) + 1;

            $headerItem = QuotationItem::create([
                'quotation_id' => (int) $sourceQuotation->id,
                'customer_confirmation_member_id' => null,
                'parent_id' => null,
                'description' => 'Umrah Packages',
                'is_header' => true,
                'sort_order' => $nextSortOrder,
            ]);

            $detailItem = QuotationItem::create([
                'quotation_id' => (int) $sourceQuotation->id,
                'customer_confirmation_member_id' => (int) $member->id,
                'parent_id' => (int) $headerItem->id,
                'description' => $packageName.' - '.$memberName.' - '.$sharingPlanLabel.' sharing',
                'is_header' => false,
                'quantity' => 1,
                'rate' => $unbilledAmount,
                'sort_order' => $nextSortOrder + 1,
            ]);

            $balanceInvoice = Invoice::create([
                'order_id' => (int) $linkedInvoice->order_id,
                'description' => 'Invoice For Balance',
                'payment_method' => $linkedInvoice->payment_method,
                'extensions' => [],
                'amount' => $unbilledAmount,
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->format('Y-m-d'),
                'status' => InvoiceStatus::Outstanding,
            ]);

            $balanceInvoice->quotationItems()->sync([
                (int) $headerItem->id,
                (int) $detailItem->id,
            ]);

            $member->update([
                'status' => 'partially_paid',
            ]);

            $member->refresh();
            $member->load('customer.user');
            $this->syncOpenManifestMemberSnapshot($member);
            $this->syncMemberStatusesForConfirmation((int) $group->id);

            return $balanceInvoice;
        });
    }

    /**
     * @param  array<string, mixed>  $refundPayload
     */
    private function resolveRequestedRefundAmount(float $paidAmount, array $refundPayload): float
    {
        $mode = strtolower(trim((string) ($refundPayload['mode'] ?? 'fixed')));

        if ($mode === 'percentage') {
            $percentage = (float) ($refundPayload['percentage'] ?? 0);

            if ($percentage < 0 || $percentage > 100) {
                throw ValidationException::withMessages([
                    'member_refunds' => 'Refund percentage must be between 0 and 100.',
                ]);
            }

            return round(($paidAmount * $percentage) / 100, 2);
        }

        $amount = (float) ($refundPayload['amount'] ?? 0);

        if ($amount < 0) {
            throw ValidationException::withMessages([
                'member_refunds' => 'Refund amount must be at least 0.',
            ]);
        }

        if ($amount > $paidAmount) {
            throw ValidationException::withMessages([
                'member_refunds' => 'Refund amount cannot exceed paid amount.',
            ]);
        }

        return round($amount, 2);
    }

    private function resolveMemberLatestInvoice(CustomerConfirmationMember $member): ?Invoice
    {
        return $member->quotationItems
            ->flatMap(fn ($item) => $item->invoices)
            ->reject(fn (Invoice $invoice) => InvoiceStatus::isRefund($invoice->status))
            ->sortByDesc('id')
            ->first();
    }

    private function resolveMemberLatestInvoicePaymentMethod(CustomerConfirmationMember $member): ?string
    {
        $latestInvoice = $this->resolveMemberLatestInvoice($member);

        if (! $latestInvoice) {
            return null;
        }

        $invoicePaymentMethod = trim((string) ($latestInvoice->payment_method ?? ''));
        if ($invoicePaymentMethod !== '') {
            return $invoicePaymentMethod;
        }

        $latestReceiptPaymentMethod = trim((string) ($latestInvoice->receipt
            ->sortByDesc('id')
            ->first()?->payment_method ?? ''));

        return $latestReceiptPaymentMethod !== '' ? $latestReceiptPaymentMethod : null;
    }

    /**
     * @return array{order_id: int|null, order_number: string|null}
     */
    private function resolveMemberOrderSnapshot(CustomerConfirmationMember $member): array
    {
        $linkedOrder = $member->quotationItems
            ->first(function ($quotationItem): bool {
                if (! $quotationItem instanceof QuotationItem) {
                    return false;
                }

                return $this->normalizePaymentStatus($quotationItem->status ?? null) !== 'cancelled';
            })?->quotation?->order;

        if (! $linkedOrder) {
            $linkedOrder = $member->quotationItems
                ->first()?->quotation?->order;
        }

        return [
            'order_id' => $linkedOrder?->id,
            'order_number' => $linkedOrder?->order_number,
        ];
    }

    /**
     * Recalculate confirmation member statuses based on payment state.
     *
     * Called after a receipt is created, updated, or deleted.
     * Walks from invoice → order → quotation → quotation_items to find
     * linked confirmation members and sets:
     *   - fully_paid:      all invoices on the order are fully paid
     *   - partially_paid:  at least one invoice is paid but not all
     *   - pending_payment: no invoices are paid
     */
    public function syncMemberPaymentStatus(int $invoiceId): void
    {
        app(PaymentStatusService::class)
            ->syncAfterReceiptMutation($invoiceId);
    }
}
