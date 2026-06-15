<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerConfirmationMember;
use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CustomerHistoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:customer view');
    }

    /**
     * Display the customer history search page.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $customers = [];

        if (! empty($search)) {
            $customers = User::role('customer')
                ->with('customer')
                ->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('nric_number', 'like', "%{$search}%")
                                ->orWhere('customer_number', 'like', "%{$search}%");
                        });
                })
                ->orderBy('name')
                ->limit(50)
                ->get()
                ->map(function (User $user) {
                    return [
                        'id' => $user->id,
                        'customer_id' => $user->customer?->id,
                        'customer_number' => $user->customer?->customer_number,
                        'name' => $user->name,
                        'email' => $user->email,
                        'contact' => $user->contact ?? '-',
                        'nric_number' => $user->customer?->nric_number,
                        'address' => $user->customer?->address,
                    ];
                });
        }

        return Inertia::render('customer-history/index', [
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    /**
     * Get the full history record for a customer, grouped by journey.
     *
     * Each journey is either a package journey (enquiry -> confirmation ->
     * travel/manifest -> payments) or a standalone non-package journey (a
     * direct quotation with no confirmation). Payments are nested as
     * quotation -> invoices -> receipts.
     */
    public function show(int $customerId): JsonResponse
    {
        $customer = Customer::with([
            'confirmationMembers' => function ($query) {
                $query->with([
                    'confirmation' => function ($query) {
                        $query->withTrashed()->with([
                            'enquiry',
                            'package.country',
                            'quotations.order.invoices.receipt',
                        ]);
                    },
                    'manifestMembers.manifest.package',
                ]);
            },
            'quotations' => function ($query) {
                $query->whereNull('customer_confirmation_id')
                    ->with('order.invoices.receipt');
            },
        ])->findOrFail($customerId);

        $packageJourneys = $customer->confirmationMembers
            ->filter(fn ($member) => $member->confirmation !== null)
            ->groupBy(fn ($member) => $member->confirmation->id)
            ->map(fn ($members) => $this->mapPackageJourney($members))
            ->values();

        $nonPackageJourneys = $customer->quotations
            ->map(fn ($quotation) => $this->mapNonPackageJourney($quotation))
            ->values();

        $records = $packageJourneys
            ->concat($nonPackageJourneys)
            ->sortByDesc('created_at')
            ->values();

        return response()->json($records);
    }

    /**
     * @param  Collection<int, CustomerConfirmationMember>  $members
     * @return array<string, mixed>
     */
    private function mapPackageJourney(Collection $members): array
    {
        $member = $members->first();
        $confirmation = $member->confirmation;
        $package = $confirmation?->package;
        $country = $package?->country;
        $enquiry = $confirmation?->enquiry;

        $sharingPriceMap = [
            'single' => 'price_single',
            'double' => 'price_double',
            'triple' => 'price_triple',
            'quad' => 'price_quad',
            'child_with_bed' => 'child_with_bed_price',
            'child_no_bed' => 'child_no_bed_price',
            'infant' => 'infant_price',
        ];

        $sharingPrice = null;
        if ($member->sharing_plan && $package) {
            $priceField = $sharingPriceMap[$member->sharing_plan] ?? null;
            $sharingPrice = $priceField ? $package->{$priceField} : null;
        }

        $travel = $members
            ->flatMap(fn ($confirmationMember) => $confirmationMember->manifestMembers)
            ->filter(fn ($manifestMember) => $manifestMember->manifest !== null)
            ->map(fn ($manifestMember) => [
                'manifest_id' => $manifestMember->manifest?->id,
                'manifest_number' => $manifestMember->manifest?->manifest_number,
                'member_name' => $manifestMember->name,
            ])
            ->values();

        return [
            'type' => 'package',
            'key' => 'confirmation-'.$confirmation->id,
            'confirmation_id' => $confirmation->id,
            'confirmation_number' => $confirmation->number,
            'date_of_application' => $confirmation->date_of_application?->format('Y-m-d'),
            'room_type' => $confirmation->package_room_type,
            'category' => $confirmation->package_category,
            'member_status' => $member->status,
            'is_leader' => (bool) $member->is_leader,
            'relationship' => $member->relationship,
            'sharing_plan' => $member->sharing_plan,
            'sharing_price' => $sharingPrice ? (float) $sharingPrice : null,
            'package_id' => $package?->id,
            'package_name' => $package?->name,
            'package_number' => $package?->package_number,
            'package_status' => $package?->status,
            'country_name' => $country?->name,
            'currency_symbol' => $country?->currency_symbol,
            'departure_date' => $package?->departure_date?->format('Y-m-d') ?? $package?->departure_date,
            'return_date' => $package?->return_date?->format('Y-m-d') ?? $package?->return_date,
            'enquiry' => $this->mapEnquiry($enquiry),
            'travel' => $travel,
            'payments' => $confirmation->quotations
                ->map(fn ($quotation) => $this->mapPayment($quotation))
                ->values(),
            'created_at' => $confirmation->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapNonPackageJourney(Quotation $quotation): array
    {
        return [
            'type' => 'non_package',
            'key' => 'quotation-'.$quotation->id,
            'package_name' => $quotation->description,
            'enquiry' => null,
            'travel' => [],
            'payments' => [$this->mapPayment($quotation)],
            'created_at' => $quotation->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapEnquiry(?Enquiry $enquiry): ?array
    {
        if (! $enquiry) {
            return null;
        }

        return [
            'id' => $enquiry->id,
            'enquiry_number' => $enquiry->enquiry_number,
            'type' => $enquiry->type,
            'status' => $enquiry->status,
            'name' => $enquiry->name,
            'email' => $enquiry->email,
            'contact_number' => $enquiry->contact_number,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPayment(Quotation $quotation): array
    {
        $order = $quotation->order;

        return [
            'quotation' => [
                'id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'status' => $quotation->status,
                'payment_plan' => $quotation->payment_plan,
                'quotation_date' => $quotation->quotation_date?->format('Y-m-d'),
            ],
            'order' => $order ? [
                'id' => $order->id,
                'order_number' => $order->order_number,
            ] : null,
            'invoices' => $order
                ? $order->invoices->map(fn ($invoice) => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => (float) $invoice->amount,
                    'status' => $invoice->status,
                    'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                    'due_date' => $invoice->due_date?->format('Y-m-d'),
                    'receipts' => $invoice->receipt->map(fn ($receipt) => [
                        'id' => $receipt->id,
                        'receipt_number' => $receipt->receipt_number,
                        'amount' => (float) $receipt->amount,
                        'receipt_date' => $receipt->receipt_date?->format('Y-m-d'),
                        'payment_method' => $receipt->payment_method,
                    ])->values(),
                ])->values()
                : collect(),
        ];
    }
}
