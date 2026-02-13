<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function getTotalCount()
    {
        return User::role('customer')->count();
    }

    public function getDailyStats($days = 30)
    {
        $stats = collect();
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = User::role('customer')
                ->whereDate('created_at', $date->format('Y-m-d'))
                ->count();

            $stats->push([
                'date' => $date->format('Y-m-d'),
                'count' => $count,
                'label' => $date->format('M d'),
            ]);
        }

        return $stats;
    }

    public function getMonthlyStats()
    {
        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $count = User::role('customer')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();

            $months->push([
                'date' => $date->format('Y-m-d'),
                'count' => $count,
                'label' => $date->format('M Y'),
            ]);
        }

        return $months;
    }

    public function getForDataTable($request)
    {
        $data = User::role('customer')->with('customer')
            ->when($request->user()->hasRole('sales'), function ($query) use ($request) {
                $query->whereHas('customer', function ($q) use ($request) {
                    $q->where('handled_by', $request->user()->id)->orWhereNull('handled_by');
                });
            })
            ->orderBy('created_at', 'desc')
            ->get()->map(function ($q) {
                $agePrefs = null;
                if ($q->customer->age_preferences) {
                    $decoded = json_decode($q->customer->age_preferences);
                    $agePrefs = is_array($decoded) ? implode(', ', $decoded) : $q->customer->age_preferences;
                }

                $countryPrefs = null;
                if ($q->customer->country_preferences) {
                    $decoded = json_decode($q->customer->country_preferences);
                    $countryPrefs = is_array($decoded)
                        ? collect($decoded)->filter()->implode(', ')
                        : $q->customer->country_preferences;
                }

                $expPrefs = null;
                if ($q->customer->experience_preferences) {
                    $decoded = json_decode($q->customer->experience_preferences);
                    $expPrefs = is_array($decoded)
                        ? collect($decoded)->implode(', ')
                        : $q->customer->experience_preferences;
                }

                $address = $q->customer->address ?? '';
                $addressFormatted = nl2br($address);

                return [
                    'id' => $q->id,
                    'customer_id' => $q->customer->id,
                    'customer_number' => $q->customer->customer_number,
                    'name' => $q->name,
                    'email' => $q->email,
                    'contact' => $q->contact ?? '-',
                    'nric_number' => $q->customer->nric_number,
                    'address' => $addressFormatted,
                    'handled_by' => $q->customer->handled_by ?? null,
                    'handler_name' => $q->customer->handledBy->name ?? '-',
                    'last_login' => $q->customer->last_login ?? null,
                    'is_active' => $q->customer->is_active ?? true,
                ];
            });

        return $data;
    }

    public function getCustomerMaidIds($id)
    {
        $user = User::with('roles')->findOrFail($id);
        $data = $user->customer->maids()->pluck('maids.id')->toArray();

        return $data;
    }

    public function getForFilter()
    {
        $data = Customer::whereHas('user', function ($q) {
            $q->where('deleted_at', null);
        })->get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => $q->user->name,
            ];
        });

        return $data;
    }

    public function getForFilterWithCode()
    {
        $data = Customer::whereHas('user', function ($q) {
            $q->where('deleted_at', null);
        })->get()->map(function ($q) {
            return [
                'value' => $q->id,
                'label' => "{$q->customer_number} - {$q->user->name}",
            ];
        });

        return $data;
    }

    public function getForEditShow($id)
    {
        $customer = Customer::findOrFail($id);

        $data = [
            'role' => 'customer',
            'customer_number' => $customer->customer_number ?? '',
            'id' => $customer->user->id,
            'name' => $customer->user->name,
            'email' => $customer->user->email,
            'contact' => $customer->user->contact ?? '',
            'nric_number' => $customer->nric_number ?? '',
            'address' => $customer->address ?? '',
            'handled_by' => (string) $customer->handled_by,
        ];

        return $data;
    }

    public function handleCustomer($request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $user = User::findOrFail($id);

            $customer = Customer::findOrFail($user->customer->id);
            $customer->update([
                'handled_by' => $request->user()->id,
            ]);

            return $user;
        });
    }

    public function storeRecommendMaid(int $userId, array $maidIds)
    {
        return DB::transaction(function () use ($userId, $maidIds) {
            $user = User::findOrFail($userId);

            if (! $user->hasRole(['customer'])) {
                throw new \Exception('User is not a customer.');
            }

            $user->customer->maids()->sync($maidIds);

            return [
                'success' => true,
                'message' => 'Recommended maids updated successfully.',
                'data' => $user->customer->maids()->get(),
            ];
        });
    }

    public function updateLastLogin(int $userId)
    {
        return DB::transaction(function () use ($userId) {
            $user = User::findOrFail($userId);

            if ($user->hasRole(['customer'])) {
                $user->customer->update([
                    'last_login' => now(),
                ]);
            }
        });
    }

    public function getSalesCustomersData(int $salesUserId)
    {
        $customers = User::role('customer')
            ->with('customer.handledBy')
            ->whereHas('customer', function ($query) use ($salesUserId) {
                $query->whereNull('handled_by')
                    ->orWhere('handled_by', $salesUserId);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($customer) {
                $salesName = $customer->customer->handledBy->name ?? null;
                $isAssigned = $customer->customer->handled_by !== null;

                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'contact' => $customer->contact ?? '-',
                    'assigned_sales' => $salesName,
                    'status' => $isAssigned ? 'Assigned' : 'Unassigned',
                ];
            });

        return $customers;
    }

    public function enableCustomer(int $customerId)
    {
        return DB::transaction(function () use ($customerId) {
            $customer = Customer::findOrFail($customerId);
            $customer->update(['is_active' => true]);

            return $customer;
        });
    }

    public function disableCustomer(int $customerId)
    {
        return DB::transaction(function () use ($customerId) {
            $customer = Customer::findOrFail($customerId);
            $customer->update(['is_active' => false]);

            return $customer;
        });
    }
}
