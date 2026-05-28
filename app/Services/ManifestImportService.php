<?php

namespace App\Services;

use App\Helpers\NumberGenerator;
use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\Manifest;
use App\Models\ManifestMember;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Receipt;
use App\Models\User;
use App\Support\InvoiceStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ManifestImportService
{
    private const VALID_SHARING_PLANS = [
        'single', 'double', 'triple', 'quad',
        'child_with_bed', 'child_no_bed', 'infant',
    ];

    /**
     * Import a batch of members (with full chain: customer, confirmation, quotation,
     * order, invoice, receipt) into the given manifest.
     *
     * Each import creates ONE shared Enquiry + CustomerConfirmation that contains
     * all members of this batch. Subsequent imports on the same manifest create
     * additional batches (separate confirmations) — this keeps each import
     * traceable and avoids cross-batch coupling.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $context
     * @return array{imported:int, errors:array<int, array{row:int, message:string}>, confirmation_id:?int}
     */
    public function importFromPayload(Manifest $manifest, array $context, array $rows): array
    {
        $manifest->loadMissing('package');
        $package = $manifest->package;

        if (! $package) {
            return [
                'imported' => 0,
                'errors' => [['row' => 0, 'message' => 'Manifest has no package assigned.']],
                'confirmation_id' => null,
            ];
        }

        $duplicatePassports = $this->resolveExistingPassportsForManifest($manifest);
        $applicationDate = $this->parseDateInput($context['date_of_application'] ?? null)
            ?? now()->format('Y-m-d');

        $imported = 0;
        $errors = [];
        $confirmation = null;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;
            $rowError = $this->validateRow($row, $duplicatePassports);

            if ($rowError !== null) {
                $errors[] = ['row' => $rowNumber, 'message' => $rowError];

                continue;
            }

            try {
                DB::transaction(function () use (
                    &$confirmation,
                    $manifest,
                    $package,
                    $row,
                    $applicationDate,
                ): void {
                    if ($confirmation === null) {
                        $confirmation = $this->createSharedConfirmation(
                            $manifest,
                            $package,
                            $applicationDate,
                        );
                    }

                    $this->importRow($manifest, $package, $confirmation, $row);
                });

                $passport = $this->stringOrNull($row['passport_number'] ?? null);
                if ($passport !== null) {
                    $duplicatePassports[strtolower($passport)] = true;
                }

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNumber, 'message' => $e->getMessage()];
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
            'confirmation_id' => $confirmation?->id,
        ];
    }

    private function importRow(
        Manifest $manifest,
        Package $package,
        CustomerConfirmation $confirmation,
        array $row,
    ): void {
        $sharingPlan = strtolower(trim((string) $row['sharing_plan']));
        $customer = $this->findOrCreateCustomer($row);

        $confirmationMember = CustomerConfirmationMember::create([
            'customer_confirmation_id' => $confirmation->id,
            'customer_id' => $customer->id,
            'is_leader' => (bool) ($row['is_leader'] ?? false),
            'status' => 'pending_payment',
            'sharing_plan' => $sharingPlan,
        ]);

        $packagePrice = $this->getPackagePriceForSharingPlan($package, $sharingPlan);
        $invoiceAmount = $this->floatOrNull($row['invoice_amount'] ?? null) ?? $packagePrice;

        $quotation = $this->createQuotation(
            $package,
            $customer,
            $confirmation,
            $confirmationMember,
            $sharingPlan,
            $packagePrice,
        );

        $order = Order::create([
            'quotation_id' => $quotation->id,
            'payment_plan' => 'full',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'description' => 'Payment for travel package — '.($package->name ?? 'Package #'.$package->id),
            'payment_method' => $this->stringOrNull($row['receipt_method'] ?? null),
            'extensions' => [],
            'amount' => $invoiceAmount,
            'invoice_date' => $this->parseDateInput($row['receipt_date'] ?? null) ?? now()->format('Y-m-d'),
            'due_date' => $this->parseDateInput($row['receipt_date'] ?? null) ?? now()->format('Y-m-d'),
            'status' => InvoiceStatus::Outstanding,
        ]);

        $itemIds = $quotation->quotationItems->pluck('id')->map(fn ($id) => (int) $id)->all();
        $invoice->quotationItems()->sync($itemIds);

        $receiptAmount = $this->floatOrNull($row['receipt_amount'] ?? null);

        if ($receiptAmount !== null && $receiptAmount > 0) {
            Receipt::create([
                'invoice_id' => $invoice->id,
                'amount' => $receiptAmount,
                'receipt_date' => $this->parseDateInput($row['receipt_date'] ?? null) ?? now()->format('Y-m-d'),
                'payment_method' => $this->stringOrNull($row['receipt_method'] ?? null),
                'reference' => $this->stringOrNull($row['receipt_reference'] ?? null),
                'description' => 'Imported receipt — '.($package->name ?? 'Package #'.$package->id),
            ]);
        }

        ManifestMember::create([
            'manifest_id' => $manifest->id,
            'customer_confirmation_member_id' => $confirmationMember->id,
            'sharing_plan' => $sharingPlan,
            'name' => $this->stringOrNull($row['name']),
            'contact_number' => $this->stringOrNull($row['contact'] ?? null),
            'nationality' => $this->stringOrNull($row['nationality'] ?? null),
            'passport_number' => $this->stringOrNull($row['passport_number'] ?? null),
            'gender' => $this->stringOrNull($row['gender'] ?? null),
            'date_of_birth' => $this->parseDateInput($row['date_of_birth'] ?? null),
            'passport_issue_date' => $this->parseDateInput($row['passport_issue_date'] ?? null),
            'passport_expiry_date' => $this->parseDateInput($row['passport_expiry_date'] ?? null),
            'passport_place_of_issue' => $this->stringOrNull($row['passport_place_of_issue'] ?? null),
            'address' => $this->stringOrNull($row['address'] ?? null),
            'has_chronic_disease' => $this->boolOrFalse($row['has_chronic_disease'] ?? null),
            'is_using_wheelchair' => $this->boolOrFalse($row['is_using_wheelchair'] ?? null),
            'sort_order' => ((int) ManifestMember::where('manifest_id', $manifest->id)->max('sort_order')) + 1,
        ]);
    }

    private function createSharedConfirmation(
        Manifest $manifest,
        Package $package,
        string $applicationDate,
    ): CustomerConfirmation {
        $enquiry = Enquiry::create([
            'enquiry_number' => NumberGenerator::generate('general_enquiry'),
            'type' => 'general',
            'status' => 'confirmed',
            'name' => 'Backfill Import - Manifest #'.$manifest->id,
            'contact_number' => '-',
            'email' => 'backfill-manifest-'.$manifest->id.'@import.local',
            'package_id' => $package->id,
            'created_by' => auth()->id(),
        ]);

        return CustomerConfirmation::create([
            'enquiry_id' => $enquiry->id,
            'created_by' => auth()->id(),
            'package_id' => $package->id,
            'date_of_application' => $applicationDate,
            'is_holding' => false,
        ]);
    }

    private function createQuotation(
        Package $package,
        Customer $customer,
        CustomerConfirmation $confirmation,
        CustomerConfirmationMember $member,
        string $sharingPlan,
        float $rate,
    ): Quotation {
        $quotation = Quotation::create([
            'customer_id' => $customer->id,
            'customer_confirmation_id' => $confirmation->id,
            'handled_by' => auth()->id(),
            'quotation_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addDays(30)->format('Y-m-d'),
            'payment_plan' => 'full',
            'status' => 'converted',
            'description' => 'Payment for travel package — '.($package->name ?? 'Package #'.$package->id),
        ]);

        $header = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'parent_id' => null,
            'description' => 'Umrah Packages',
            'is_header' => true,
            'sort_order' => 1,
        ]);

        $memberName = $customer->user?->name ?? 'Member #'.$member->id;
        $planLabel = $this->formatSharingPlanLabel($sharingPlan);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'customer_confirmation_member_id' => $member->id,
            'parent_id' => $header->id,
            'description' => "{$package->name} - {$memberName} - {$planLabel} sharing",
            'is_header' => false,
            'quantity' => 1,
            'rate' => $rate,
            'sort_order' => 2,
        ]);

        return $quotation->load('quotationItems');
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
            return "Sharing plan must be one of: ".implode(', ', self::VALID_SHARING_PLANS).".";
        }

        $passport = $this->stringOrNull($row['passport_number'] ?? null);
        if ($passport !== null && isset($duplicatePassports[strtolower($passport)])) {
            return "Passport {$passport} is already a member of this manifest.";
        }

        $email = $this->stringOrNull($row['email'] ?? null);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return "Email {$email} is not valid.";
        }

        $receiptAmount = $this->floatOrNull($row['receipt_amount'] ?? null);
        if ($receiptAmount !== null && $receiptAmount < 0) {
            return 'Receipt amount cannot be negative.';
        }

        $invoiceAmount = $this->floatOrNull($row['invoice_amount'] ?? null);
        if ($invoiceAmount !== null && $invoiceAmount < 0) {
            return 'Invoice amount cannot be negative.';
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

    private function formatSharingPlanLabel(string $sharingPlan): string
    {
        return match ($sharingPlan) {
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

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
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
}
