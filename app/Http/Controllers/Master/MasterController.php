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
        $stats = [
            'users' => User::count(),
            'countries' => Country::count(),
            'branches' => Branch::count(),
            'fiscalYears' => FinancialYear::count(),
            'productsAndServices' => QuotationItemMaster::count() + QuotationExtensionMaster::count(),
        ];

        return Inertia::render('masters/index', [
            'stats' => $stats,
        ]);
    }
}
