<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Country;
use App\Models\FinancialYear;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class MasterStatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $hideCustomerFromMaster = (bool) config('master.hide_customer_from_user_management', false);

        $userCountQuery = User::query()->whereDoesntHave('ghostUser');

        if ($hideCustomerFromMaster) {
            $userCountQuery->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'customer');
            });
        }

        return response()->json([
            'users' => $userCountQuery->count(),
            'countries' => Country::count(),
            'branches' => Branch::count(),
            'fiscal_years' => FinancialYear::count(),
            'hide_customer_from_master' => $hideCustomerFromMaster,
        ]);
    }
}
