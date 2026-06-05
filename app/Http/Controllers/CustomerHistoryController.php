<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
     * Get the travel history for a specific customer.
     */
    public function show(int $customerId): JsonResponse
    {
        $customer = Customer::with([
            'confirmationMembers.confirmation' => function ($query) {
                $query->withTrashed()->with(['package.country']);
            },
        ])->findOrFail($customerId);

        $sharingPriceMap = [
            'single' => 'price_single',
            'double' => 'price_double',
            'triple' => 'price_triple',
            'quad' => 'price_quad',
            'child_with_bed' => 'child_with_bed_price',
            'child_no_bed' => 'child_no_bed_price',
            'infant' => 'infant_price',
        ];

        $records = $customer->confirmationMembers
            ->map(function ($member) use ($sharingPriceMap) {
                $confirmation = $member->confirmation;
                $package = $confirmation?->package;
                $country = $package?->country;

                $sharingPrice = null;
                if ($member->sharing_plan && $package) {
                    $priceField = $sharingPriceMap[$member->sharing_plan] ?? null;
                    $sharingPrice = $priceField ? $package->{$priceField} : null;
                }

                return [
                    'confirmation_id' => $confirmation?->id,
                    'confirmation_number' => $confirmation?->number,
                    'date_of_application' => $confirmation?->date_of_application?->format('Y-m-d'),
                    'room_type' => $confirmation?->package_room_type,
                    'category' => $confirmation?->package_category,
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
                    'created_at' => $confirmation?->created_at?->toIso8601String(),
                ];
            })
            ->sortByDesc('created_at')
            ->values();

        return response()->json($records);
    }
}
