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
        $data = User::role('customer')
            ->with([
                'customer',
                'customer.confirmationMembers' => function ($q) {
                    $q->whereHas('confirmation.package', function ($pq) {
                        $pq->whereIn('status', ['open', 'ongoing']);
                    })->with(['confirmation.package']);
                },
            ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($q) {
                $activePackage = $q->customer
                    ->confirmationMembers
                    ->first()
                    ?->confirmation
                    ?->package;

                return [
                    'id' => $q->id,
                    'customer_id' => $q->customer->id,
                    'customer_number' => $q->customer->customer_number,
                    'name' => $q->name,
                    'email' => $q->email,
                    'contact' => $q->contact ?? '-',
                    'nric_number' => $q->customer->nric_number,
                    'address' => $q->customer->address ?? '-',
                    'last_login' => $q->customer->last_login ?? null,
                    'is_active' => $q->customer->is_active ?? true,
                    'package_name' => $activePackage?->name ?? null,
                ];
            });

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
        ];

        return $data;
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

    public function getSalesCustomersData(int $_salesUserId)
    {
        $customers = User::role('customer')
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'contact' => $customer->contact ?? '-',
                    'assigned_sales' => null,
                    'status' => 'N/A',
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
