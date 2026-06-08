<?php

namespace App\Services;

use App\Helpers\NumberGenerator;
use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Enquiry;
use App\Models\GeneralEnquiry;
use App\Models\Invoice;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\Receipt;
use App\Models\User;
use App\Support\DataScope;
use App\Support\InvoiceStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ManifestImportService
{
    private const VALID_SHARING_PLANS = [
        'single', 'double', 'triple', 'quad',
        'child_with_bed', 'child_no_bed', 'infant',
    ];

    /** Tolerance (currency units) when reconciling installment totals. */
    private const RECONCILE_TOLERANCE = 0.01;

    public function __construct(
        private CustomerConfirmationService $confirmationService,
        private ManifestService $manifestService,
    ) {}

    /**
     * Import a migration-grade batch into the manifest, recreating the full
     * real-system chain:
     *
     *   per booking: Enquiry → CustomerConfirmation (+ N members)
     *                  → group Quotation(s) (one payer covers 1..N members)
     *                    → Order → installment Invoices → Receipts
     *                → ManifestMembers grouped into ManifestSharingGroups
     *
     * Members reference each other by keys:
     *   - booking_ref       groups members into ONE Enquiry+Confirmation
     *   - payer_ref         member_key of whoever pays (blank/self = self-pay)
     *   - sharing_group_key rooming group (blank = auto bin-pack by capacity)
     *
     * Each booking is committed in its own transaction: a failed booking rolls
     * back cleanly (no orphans) and is reported, while the others still import.
     *
     * @param  array<int, array<string, mixed>>  $members
     * @param  array<int, array<string, mixed>>  $payments
     * @param  array<string, mixed>  $context
     * @return array{imported_members:int, bookings:int, quotations:int, invoices:int, receipts:int, errors:array<int, array{booking_ref:?string, row:?int, message:string}>, confirmation_ids:int[]}
     */
    public function importFromPayload(Manifest $manifest, array $context, array $members, array $payments = []): array
    {
        $manifest->loadMissing('package');
        $package = $manifest->package;

        if (! $package) {
            return $this->result(errors: [['booking_ref' => null, 'row' => 0, 'message' => 'Manifest has no package assigned.']]);
        }

        // Resolve the country for the created enquiries. Prefer the package's own
        // country; otherwise fall back to the country chosen in the import form and
        // backfill it onto the package (which had none).
        $countryId = $package->country_id ?: ($this->intOrNull($context['country_id'] ?? null));

        if ($countryId === null) {
            return $this->result(errors: [['booking_ref' => null, 'row' => 0, 'message' => 'Select a package country for this import.']]);
        }

        if (! $package->country_id) {
            $package->update(['country_id' => $countryId]);
        }

        // Salesperson that owns the created enquiries/quotations. Falls back to the
        // importing user when not supplied (e.g. admin/sales importing for themselves).
        $salesId = $this->intOrNull($context['sales_id'] ?? null) ?? auth()->id();

        $duplicatePassports = $this->resolveExistingPassportsForManifest($manifest);
        $applicationDate = $this->parseDateInput($context['date_of_application'] ?? null)
            ?? now()->format('Y-m-d');

        // --- Normalize members: stable row number, synthesized keys for the minimal sheet ---
        $normalizedMembers = [];
        foreach (array_values($members) as $i => $row) {
            $row['_row'] = $i + 1;
            $row['member_key'] = $this->stringOrNull($row['member_key'] ?? null) ?? ('M'.($i + 1));
            // Blank booking_ref => one confirmation per member (decision #5).
            $row['booking_ref'] = $this->stringOrNull($row['booking_ref'] ?? null) ?? ('__auto_'.($i + 1));
            $normalizedMembers[] = $row;
        }

        // member_key uniqueness is global (payer_ref references it). Passport
        // uniqueness across the whole file prevents the same person being added
        // twice (which would also collide two payers onto one Customer).
        $memberKeyCounts = [];
        $passportCounts = [];
        foreach ($normalizedMembers as $row) {
            $memberKeyCounts[$row['member_key']] = ($memberKeyCounts[$row['member_key']] ?? 0) + 1;
            $passport = $this->stringOrNull($row['passport_number'] ?? null);
            if ($passport !== null) {
                $key = strtolower($passport);
                $passportCounts[$key] = ($passportCounts[$key] ?? 0) + 1;
            }
        }

        // --- Group members by booking ---
        $bookings = [];
        foreach ($normalizedMembers as $row) {
            $bookings[$row['booking_ref']][] = $row;
        }

        // --- Group payments by booking → payer, each list sorted by installment ---
        $paymentsByBooking = $this->groupPayments($payments);

        $imported = ['imported_members' => 0, 'bookings' => 0, 'quotations' => 0, 'invoices' => 0, 'receipts' => 0];
        $errors = [];
        $confirmationIds = [];

        // The Receipt boot hook (PaymentStatusService) would otherwise auto-link
        // paid members onto the package's manifest — creating/duplicating/deleting
        // ManifestMembers and inventing its own groups/rooms. We build manifest
        // grouping deterministically here, so suppress that side effect for the
        // whole import (member/invoice status still syncs normally).
        $previousSuppress = PaymentStatusService::$suppressManifestAutoLink;
        PaymentStatusService::$suppressManifestAutoLink = true;

        try {
            foreach ($bookings as $bookingRef => $bookingRows) {
                $bookingError = $this->validateBooking(
                    (string) $bookingRef,
                    $bookingRows,
                    $paymentsByBooking[$bookingRef] ?? [],
                    $package,
                    $duplicatePassports,
                    $memberKeyCounts,
                    $passportCounts,
                );

                if ($bookingError !== null) {
                    $errors[] = [
                        'booking_ref' => $this->displayBookingRef((string) $bookingRef),
                        'row' => $bookingError['row'] ?? null,
                        'message' => $bookingError['message'],
                    ];

                    continue;
                }

                try {
                    // Grouping happens INSIDE this transaction (see importBooking),
                    // so a grouping failure rolls back the whole booking — no
                    // "committed money, no manifest member" window, and a re-import
                    // is reliably blocked by the passport guard.
                    $bookingResult = DB::transaction(fn () => $this->importBooking(
                        $manifest,
                        $package,
                        (string) $bookingRef,
                        $bookingRows,
                        $paymentsByBooking[$bookingRef] ?? [],
                        $applicationDate,
                        $countryId,
                        $salesId,
                    ));

                    $imported['imported_members'] += $bookingResult['members'];
                    $imported['bookings'] += 1;
                    $imported['quotations'] += $bookingResult['quotations'];
                    $imported['invoices'] += $bookingResult['invoices'];
                    $imported['receipts'] += $bookingResult['receipts'];
                    $confirmationIds[] = $bookingResult['confirmation_id'];

                    foreach ($bookingRows as $row) {
                        $passport = $this->stringOrNull($row['passport_number'] ?? null);
                        if ($passport !== null) {
                            $duplicatePassports[strtolower($passport)] = true;
                        }
                    }
                } catch (\Throwable $e) {
                    $errors[] = [
                        'booking_ref' => $this->displayBookingRef((string) $bookingRef),
                        'row' => null,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        } finally {
            PaymentStatusService::$suppressManifestAutoLink = $previousSuppress;
        }

        // Orphan payments: reference a booking_ref that has no members.
        foreach (array_keys($paymentsByBooking) as $bookingRef) {
            if (! isset($bookings[$bookingRef])) {
                $errors[] = [
                    'booking_ref' => (string) $bookingRef,
                    'row' => null,
                    'message' => "Payments reference booking '{$bookingRef}', but no members use that booking_ref.",
                ];
            }
        }

        return $this->result(
            importedMembers: $imported['imported_members'],
            bookings: $imported['bookings'],
            quotations: $imported['quotations'],
            invoices: $imported['invoices'],
            receipts: $imported['receipts'],
            errors: $errors,
            confirmationIds: $confirmationIds,
        );
    }

    /**
     * Create the full chain for a single booking. Runs inside a transaction.
     *
     * @param  array<int, array<string, mixed>>  $bookingRows
     * @param  array<string, array<int, array<string, mixed>>>  $payments  payerKey => installment rows
     * @return array{confirmation_id:int, members:int, quotations:int, invoices:int, receipts:int}
     */
    private function importBooking(
        Manifest $manifest,
        Package $package,
        string $bookingRef,
        array $bookingRows,
        array $payments,
        string $applicationDate,
        int $countryId,
        int $salesId,
    ): array {
        $confirmation = $this->createBookingConfirmation($manifest, $package, $applicationDate, $bookingRef, $countryId, $salesId, count($bookingRows));

        $memberByKey = [];      // member_key => CustomerConfirmationMember
        $grouping = [];

        foreach ($bookingRows as $row) {
            $customer = $this->findOrCreateCustomer($row);
            $sharingPlan = strtolower(trim((string) $row['sharing_plan']));

            $confirmationMember = CustomerConfirmationMember::create([
                'customer_confirmation_id' => $confirmation->id,
                'customer_id' => $customer->id,
                'is_leader' => $this->boolOrFalse($row['is_leader'] ?? false),
                'status' => 'pending_payment',
                'sharing_plan' => $sharingPlan,
                'relationship' => $this->stringOrNull($row['relationship'] ?? null),
            ]);

            $memberByKey[$row['member_key']] = $confirmationMember;
            $grouping[] = [
                'customer_confirmation_member_id' => $confirmationMember->id,
                'sharing_plan' => $sharingPlan,
                'sharing_group_key' => $this->stringOrNull($row['sharing_group_key'] ?? null),
            ];
        }

        // payer member id => [covered member ids], keyed in first-seen order.
        $payerToMembers = [];
        $payerKeyByCmId = [];
        foreach ($bookingRows as $row) {
            $payerKey = $this->resolvePayerKey($row);
            $payerCm = $memberByKey[$payerKey];
            $coveredCm = $memberByKey[$row['member_key']];
            $payerToMembers[$payerCm->id][] = $coveredCm->id;
            $payerKeyByCmId[$payerCm->id] = $payerKey;
        }

        // Reuse the system's group-quotation engine (one quotation per payer).
        // It iterates $payerToMembers in order and never skips a freshly-created
        // payer (each has a customer), so the returned quotations align 1:1 with
        // the payer member ids — match installments by payer member id, not by
        // customer_id (two self-paying members could share one Customer).
        $quotations = $this->confirmationService
            ->generateQuotationsFromConfirmation($confirmation->id, $payerToMembers, $salesId);

        $counts = ['invoices' => 0, 'receipts' => 0];
        $payerCmIds = array_keys($payerToMembers);

        foreach (array_values($quotations) as $idx => $quotation) {
            $payerCmId = $payerCmIds[$idx] ?? null;
            $payerKey = $payerCmId !== null ? ($payerKeyByCmId[$payerCmId] ?? null) : null;
            $installments = $payerKey !== null ? ($payments[$payerKey] ?? []) : [];

            $this->buildOrderForQuotation($package, $quotation, $installments, $counts);
        }

        // Group this booking's members into manifest sharing groups WITHIN the
        // same transaction, so grouping is atomic with the financial chain.
        $this->manifestService->appendImportedMembers($manifest, $grouping);

        return [
            'confirmation_id' => (int) $confirmation->id,
            'members' => count($bookingRows),
            'quotations' => count($quotations),
            'invoices' => $counts['invoices'],
            'receipts' => $counts['receipts'],
        ];
    }

    /**
     * Build Order + installment Invoices + Receipts for one quotation, then
     * mark the quotation converted.
     *
     * Each installment row is one Invoice; the FULL quotation item set is
     * attached to every installment invoice (an installment is a partial
     * payment toward the same items — see plan Risk B). Payment status is
     * driven by Receipts via the Receipt boot hook, not the pivot.
     *
     * @param  array<int, array<string, mixed>>  $installments
     * @param  array{invoices:int, receipts:int}  $counts
     */
    private function buildOrderForQuotation(Package $package, Quotation $quotation, array $installments, array &$counts): void
    {
        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $quotation->loadMissing('quotationItems');
        $itemIds = $quotation->quotationItems->pluck('id')->map(fn ($id) => (int) $id)->all();

        $quotationTotal = (float) $quotation->quotationItems
            ->where('is_header', false)
            ->reduce(fn ($carry, $item) => $carry + ((float) $item->quantity * (float) $item->rate), 0.0);

        // No payment rows for this payer => one invoice for the quotation total, unpaid.
        if ($installments === []) {
            $installments = [[
                'invoice_amount' => $quotationTotal,
                'invoice_date' => null,
                'due_date' => null,
                'paid_amount' => null,
                'paid_date' => null,
                'payment_method' => null,
                'reference' => null,
            ]];
        }

        $packageLabel = $package->name ?? 'Package #'.$package->id;

        foreach (array_values($installments) as $i => $inst) {
            $invoiceDate = $this->parseDateInput($inst['invoice_date'] ?? null) ?? now()->format('Y-m-d');
            $dueDate = $this->parseDateInput($inst['due_date'] ?? null) ?? $invoiceDate;
            $invoiceAmount = $this->floatOrNull($inst['invoice_amount'] ?? null) ?? 0.0;

            $invoice = Invoice::create([
                'order_id' => $order->id,
                'description' => 'Installment '.($i + 1).' — '.$packageLabel,
                'payment_method' => $this->stringOrNull($inst['payment_method'] ?? null),
                'extensions' => [],
                'amount' => $invoiceAmount,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'status' => InvoiceStatus::Outstanding,
            ]);
            $counts['invoices']++;

            $invoice->quotationItems()->sync($itemIds);

            $paidAmount = $this->floatOrNull($inst['paid_amount'] ?? null);
            if ($paidAmount !== null && $paidAmount > 0) {
                // Receipt boot hook records the FinancialTransaction (dated to
                // receipt_date — backdated-safe) and syncs payment status.
                Receipt::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $paidAmount,
                    'receipt_date' => $this->parseDateInput($inst['paid_date'] ?? null) ?? $invoiceDate,
                    'payment_method' => $this->stringOrNull($inst['payment_method'] ?? null),
                    'reference' => $this->stringOrNull($inst['reference'] ?? null),
                    'description' => 'Imported receipt — '.$packageLabel,
                ]);
                $counts['receipts']++;
            }
        }

        $quotation->update(['status' => 'converted']);
    }

    private function createBookingConfirmation(
        Manifest $manifest,
        Package $package,
        string $applicationDate,
        string $bookingRef,
        int $countryId,
        int $salesId,
        int $memberCount,
    ): CustomerConfirmation {
        $label = Str::startsWith($bookingRef, '__auto_') ? '' : ' - '.$bookingRef;
        $slug = Str::slug($bookingRef) ?: 'b'.substr(md5($bookingRef), 0, 6);

        $enquiry = Enquiry::create([
            'enquiry_number' => NumberGenerator::generate('general_enquiry'),
            'type' => 'general',
            'status' => 'confirmed',
            'name' => 'Backfill Import - Manifest #'.$manifest->id.$label,
            'contact_number' => '-',
            'email' => 'backfill-manifest-'.$manifest->id.'-'.$slug.'@import.local',
            'package_id' => $package->id,
            'country_id' => $countryId,
            'branch_id' => DataScope::mode() === 'branch' ? $this->resolveSalesBranchId($salesId) : null,
            'handled_by' => $salesId,
            'created_by' => auth()->id(),
        ]);

        // The General Enquiry index lists the `general_enquiries` child rows, so create
        // one here or imported bookings never surface there.
        GeneralEnquiry::create([
            'enquiry_id' => $enquiry->id,
            'preferred_destinations' => $package->location ?: ($package->name ?: '-'),
            'preferred_travelling_date' => $applicationDate,
            'no_of_adults' => $memberCount,
            'no_of_children' => 0,
        ]);

        return CustomerConfirmation::create([
            'enquiry_id' => $enquiry->id,
            'created_by' => auth()->id(),
            'package_id' => $package->id,
            'date_of_application' => $applicationDate,
            'is_holding' => false,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $payments
     * @return array<string, array<string, array<int, array<string, mixed>>>> bookingRef => payerKey => installment rows
     */
    private function groupPayments(array $payments): array
    {
        $grouped = [];

        foreach (array_values($payments) as $payment) {
            $bookingRef = $this->stringOrNull($payment['booking_ref'] ?? null);
            if ($bookingRef === null) {
                continue;
            }

            $payerKey = $this->stringOrNull($payment['payer_ref'] ?? null) ?? '';
            $grouped[$bookingRef][$payerKey][] = $payment;
        }

        foreach ($grouped as $bookingRef => $byPayer) {
            foreach ($byPayer as $payerKey => $rows) {
                usort($rows, function (array $a, array $b): int {
                    $ai = (int) ($a['installment_no'] ?? 0);
                    $bi = (int) ($b['installment_no'] ?? 0);
                    if ($ai !== $bi) {
                        return $ai <=> $bi;
                    }

                    return strcmp((string) ($a['invoice_date'] ?? ''), (string) ($b['invoice_date'] ?? ''));
                });
                $grouped[$bookingRef][$payerKey] = $rows;
            }
        }

        return $grouped;
    }

    /**
     * Validate a whole booking with NO writes. Returns the first error found
     * (the booking is skipped wholesale) or null when valid.
     *
     * @param  array<int, array<string, mixed>>  $bookingRows
     * @param  array<string, array<int, array<string, mixed>>>  $payments  payerKey => rows
     * @param  array<string, true>  $duplicatePassports
     * @param  array<string, int>  $memberKeyCounts
     * @param  array<string, int>  $passportCounts
     * @return array{row:?int, message:string}|null
     */
    private function validateBooking(
        string $bookingRef,
        array $bookingRows,
        array $payments,
        Package $package,
        array $duplicatePassports,
        array $memberKeyCounts,
        array $passportCounts,
    ): ?array {
        $display = $this->displayBookingRef($bookingRef);
        $localKeys = [];
        $selfPayKeys = [];

        foreach ($bookingRows as $row) {
            $rowError = $this->validateRow($row, $duplicatePassports);
            if ($rowError !== null) {
                return ['row' => $row['_row'], 'message' => $rowError];
            }

            $key = $row['member_key'];
            if (($memberKeyCounts[$key] ?? 0) > 1) {
                return ['row' => $row['_row'], 'message' => "Duplicate member_key '{$key}'. Each member_key must be unique across the file."];
            }

            $passport = $this->stringOrNull($row['passport_number'] ?? null);
            if ($passport !== null && ($passportCounts[strtolower($passport)] ?? 0) > 1) {
                return ['row' => $row['_row'], 'message' => "Passport '{$passport}' appears on more than one row. Each person must appear once."];
            }

            $localKeys[$key] = true;
            if ($this->resolvePayerKey($row) === $key) {
                $selfPayKeys[$key] = true;
            }
        }

        // payer_ref must resolve to a self-paying member in the same booking (no chains).
        foreach ($bookingRows as $row) {
            $payerRef = $this->stringOrNull($row['payer_ref'] ?? null);
            if ($payerRef === null || $payerRef === $row['member_key']) {
                continue;
            }

            if (! isset($localKeys[$payerRef])) {
                return ['row' => $row['_row'], 'message' => "payer_ref '{$payerRef}' does not match any member_key in booking '{$display}'."];
            }
            if (! isset($selfPayKeys[$payerRef])) {
                return ['row' => $row['_row'], 'message' => "payer_ref '{$payerRef}' must point to a self-paying member (no payer chains)."];
            }
        }

        // Every payment must reference a self-paying payer in this booking.
        foreach ($payments as $payerKey => $rows) {
            if ($payerKey === '' || ! isset($selfPayKeys[$payerKey])) {
                return ['row' => null, 'message' => "Booking '{$display}': payment payer_ref '{$payerKey}' must reference a self-paying member."];
            }
        }

        // Reconciliation (decision #6): installment totals must equal the quotation total.
        $payerCovered = [];
        foreach ($bookingRows as $row) {
            $payerCovered[$this->resolvePayerKey($row)][] = $row;
        }

        foreach ($payerCovered as $payerKey => $coveredRows) {
            $installments = $payments[$payerKey] ?? [];
            if ($installments === []) {
                continue; // single full invoice — nothing to reconcile.
            }

            $expected = 0.0;
            foreach ($coveredRows as $coveredRow) {
                $expected += $this->getPackagePriceForSharingPlan($package, strtolower(trim((string) $coveredRow['sharing_plan'])));
            }

            $sum = 0.0;
            $paid = 0.0;
            foreach ($installments as $inst) {
                $sum += $this->floatOrNull($inst['invoice_amount'] ?? null) ?? 0.0;
                $paid += $this->floatOrNull($inst['paid_amount'] ?? null) ?? 0.0;
            }

            if (abs($sum - $expected) > self::RECONCILE_TOLERANCE) {
                return [
                    'row' => null,
                    'message' => "Booking '{$display}', payer '{$payerKey}': installment total ("
                        .number_format($sum, 2).') does not match quotation total ('
                        .number_format($expected, 2).'). Adjust invoice_amount or package pricing.',
                ];
            }

            // Can't pay more than was billed (closes the over-payment hole).
            if ($paid - $sum > self::RECONCILE_TOLERANCE) {
                return [
                    'row' => null,
                    'message' => "Booking '{$display}', payer '{$payerKey}': paid total ("
                        .number_format($paid, 2).') exceeds the billed total ('
                        .number_format($sum, 2).').',
                ];
            }
        }

        return null;
    }

    /** The member_key of whoever pays for this row (own key when self-pay). */
    private function resolvePayerKey(array $row): string
    {
        $payerRef = $this->stringOrNull($row['payer_ref'] ?? null);
        $own = (string) $row['member_key'];

        if ($payerRef === null || $payerRef === $own) {
            return $own;
        }

        return $payerRef;
    }

    private function findOrCreateCustomer(array $row): Customer
    {
        $passportNumber = $this->stringOrNull($row['passport_number'] ?? null);
        $email = $this->stringOrNull($row['email'] ?? null);

        if ($passportNumber !== null) {
            $existing = Customer::whereRaw('LOWER(passport_number) = ?', [strtolower($passportNumber)])
                ->with('user')
                ->first();

            if ($existing && $existing->user) {
                return $existing;
            }
        }

        $resolvedEmail = $email ?? $this->generateSyntheticEmail($row);
        $existingUser = User::where('email', $resolvedEmail)->first();

        if ($existingUser?->customer) {
            return $existingUser->customer->load('user');
        }

        if ($existingUser) {
            return $this->createCustomerForUser($existingUser, $row);
        }

        $user = User::create([
            'name' => (string) $row['name'],
            'email' => $resolvedEmail,
            'contact' => $this->stringOrNull($row['contact'] ?? null),
            'password' => Hash::make('password'),
        ]);

        $user->assignRole('customer');

        return $this->createCustomerForUser($user, $row);
    }

    private function generateSyntheticEmail(array $row): string
    {
        $passport = $this->stringOrNull($row['passport_number'] ?? null);
        $nric = $this->stringOrNull($row['nric_number'] ?? null);
        $seed = $passport ?? $nric ?? bin2hex(random_bytes(6));

        $slug = preg_replace('/[^a-z0-9]+/i', '', strtolower($seed));
        if ($slug === '') {
            $slug = bin2hex(random_bytes(6));
        }

        return "import-{$slug}@import.local";
    }

    private function createCustomerForUser(User $user, array $row): Customer
    {
        return Customer::create([
            'user_id' => $user->id,
            'nric_number' => $this->stringOrNull($row['nric_number'] ?? null),
            'address' => $this->stringOrNull($row['address'] ?? null),
            'nationality' => $this->stringOrNull($row['nationality'] ?? null),
            'passport_number' => $this->stringOrNull($row['passport_number'] ?? null),
            'passport_issue_date' => $this->parseDateInput($row['passport_issue_date'] ?? null),
            'passport_expiry_date' => $this->parseDateInput($row['passport_expiry_date'] ?? null),
            'passport_place_of_issue' => $this->stringOrNull($row['passport_place_of_issue'] ?? null),
            'gender' => $this->stringOrNull($row['gender'] ?? null),
            'date_of_birth' => $this->parseDateInput($row['date_of_birth'] ?? null),
            'has_chronic_disease' => $this->boolOrFalse($row['has_chronic_disease'] ?? null),
            'is_using_wheelchair' => $this->boolOrFalse($row['is_using_wheelchair'] ?? null),
        ])->load('user');
    }

    /**
     * Per-member field validation (no cross-row checks).
     *
     * @param  array<string, true>  $duplicatePassports
     */
    private function validateRow(array $row, array $duplicatePassports): ?string
    {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            return 'Name is required.';
        }

        $sharingPlan = strtolower(trim((string) ($row['sharing_plan'] ?? '')));
        if ($sharingPlan === '') {
            return 'Sharing plan is required.';
        }
        if (! in_array($sharingPlan, self::VALID_SHARING_PLANS, true)) {
            return 'Sharing plan must be one of: '.implode(', ', self::VALID_SHARING_PLANS).'.';
        }

        $passport = $this->stringOrNull($row['passport_number'] ?? null);
        if ($passport !== null && isset($duplicatePassports[strtolower($passport)])) {
            return "Passport {$passport} is already a member of this manifest.";
        }

        $email = $this->stringOrNull($row['email'] ?? null);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return "Email {$email} is not valid.";
        }

        return null;
    }

    /**
     * @return array<string, true>
     */
    private function resolveExistingPassportsForManifest(Manifest $manifest): array
    {
        return ManifestMember::query()
            ->where('manifest_id', $manifest->id)
            ->whereNotNull('passport_number')
            ->where('passport_number', '!=', '')
            ->pluck('passport_number')
            ->mapWithKeys(fn (string $p) => [strtolower($p) => true])
            ->all();
    }

    private function getPackagePriceForSharingPlan(Package $package, string $sharingPlan): float
    {
        return match ($sharingPlan) {
            'single' => (float) ($package->price_single ?? 0),
            'double' => (float) ($package->price_double ?? 0),
            'triple' => (float) ($package->price_triple ?? 0),
            'quad' => (float) ($package->price_quad ?? 0),
            'child_with_bed' => (float) ($package->child_with_bed_price ?? 0),
            'child_no_bed' => (float) ($package->child_no_bed_price ?? 0),
            'infant' => (float) ($package->infant_price ?? 0),
            default => 0.0,
        };
    }

    /** Strip the synthesized "__auto_*" booking ref so users never see it. */
    private function displayBookingRef(string $bookingRef): ?string
    {
        return Str::startsWith($bookingRef, '__auto_') ? null : $bookingRef;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    /** Resolve the salesperson's branch for branch-scoped enquiries (null if unknown). */
    private function resolveSalesBranchId(int $salesId): ?int
    {
        $user = User::with('sales')->find($salesId);

        return $user?->sales?->branch_id !== null ? (int) $user->sales->branch_id : null;
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function boolOrFalse(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['yes', 'true', '1', 'y'], true);
        }
        if (is_numeric($value)) {
            return (float) $value > 0;
        }

        return false;
    }

    private function parseDateInput(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, array{booking_ref:?string, row:?int, message:string}>  $errors
     * @param  int[]  $confirmationIds
     * @return array{imported_members:int, bookings:int, quotations:int, invoices:int, receipts:int, errors:array<int, array{booking_ref:?string, row:?int, message:string}>, confirmation_ids:int[]}
     */
    private function result(
        int $importedMembers = 0,
        int $bookings = 0,
        int $quotations = 0,
        int $invoices = 0,
        int $receipts = 0,
        array $errors = [],
        array $confirmationIds = [],
    ): array {
        return [
            'imported_members' => $importedMembers,
            'bookings' => $bookings,
            'quotations' => $quotations,
            'invoices' => $invoices,
            'receipts' => $receipts,
            'errors' => $errors,
            'confirmation_ids' => $confirmationIds,
        ];
    }
}
