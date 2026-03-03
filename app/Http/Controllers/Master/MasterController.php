<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\FinancialYear;
use App\Models\User;
use Inertia\Inertia;

class MasterController extends Controller
{
    public function index()
    {
        $stats = [
            'users' => User::count(),
            'branches' => Branch::count(),
            'fiscalYears' => FinancialYear::count(),
        ];

        return Inertia::render('masters/index', [
            'stats' => $stats,
        ]);
    }
}
