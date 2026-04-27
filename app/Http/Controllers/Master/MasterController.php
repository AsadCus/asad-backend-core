<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Country;
use App\Models\FinancialYear;
use App\Models\QuotationExtensionMaster;
use App\Models\QuotationItemMaster;
use App\Models\User;
use Inertia\Inertia;

class MasterController extends Controller
{
    public function index()
    {
        $hideCustomerFromMaster = (bool) config('master.hide_customer_from_user_management', false);

        $userCountQuery = User::query()
            ->whereDoesntHave('ghostUser');

        if ($hideCustomerFromMaster) {
            $userCountQuery->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'customer');
            });
        }

        $stats = [
            'users' => $userCountQuery->count(),
            'countries' => Country::count(),
            'branches' => Branch::count(),
            'fiscalYears' => FinancialYear::count(),
            'productsAndServices' => QuotationItemMaster::count() + QuotationExtensionMaster::count(),
            'scopeMode' => strtolower((string) config('data_scope.mode', 'country')),
        ];

        return Inertia::render('masters/index', [
            'stats' => $stats,
            'hideCustomerFromMaster' => $hideCustomerFromMaster,
        ]);
    }
}
