<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Sales;
use App\Rules\UserRule;
use App\Services\BranchService;
use App\Services\CountryService;
use App\Services\Report\ReportTemplateService;
use App\Services\SalesService;
use App\Services\UserRoles\SalesUserService;
use App\Services\UserService;
use App\Support\InvoiceStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class SalesController extends Controller
{
    protected $salesService;

    protected $salesUserService;

    protected $branchService;

    protected $userService;

    protected $countryService;

    protected $userRule;

    protected ReportTemplateService $reportTemplateService;

    public function __construct(
        SalesService $salesService,
        SalesUserService $salesUserService,
        BranchService $branchService,
        UserService $userService,
        CountryService $countryService,
        UserRule $userRule,
        ReportTemplateService $reportTemplateService,
    ) {
        $this->salesService = $salesService;
        $this->salesUserService = $salesUserService;
        $this->branchService = $branchService;
        $this->userService = $userService;
        $this->countryService = $countryService;
        $this->userRule = $userRule;
        $this->reportTemplateService = $reportTemplateService;

        $this->middleware('permission:sales view', ['only' => ['index', 'show', 'preview', 'generatePdf']]);
        $this->middleware('permission:sales create', ['only' => ['create', 'store']]);
        $this->middleware('permission:sales edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:sales delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = $this->salesUserService->getForDataTable();
        $dataBranch = $this->branchService->getForFilter();
        $dataCountry = $this->countryService->getForFilter();
        $dataScopeMode = strtolower((string) config('data_scope.mode', 'country'));

        return Inertia::render('sales/index', [
            'data' => $data,
            'dataBranch' => $dataBranch,
            'dataCountry' => $dataCountry,
            'dataScopeMode' => $dataScopeMode,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $dataRole = $this->userService->getRoleForFilter();
        $dataBranch = $this->branchService->getForFilter();
        $dataCountry = $this->countryService->getForFilter();
        $dataSales = $this->salesService->getForFilter();

        return Inertia::render('masters/users/create', [
            'dataRole' => $dataRole,
            'dataBranch' => $dataBranch,
            'dataCountry' => $dataCountry,
            'dataSales' => $dataSales,
            'isSales' => true,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->userRule->rules($request->role));

        $validated['role'] = 'sales';

        $this->salesUserService->store($validated);

        return redirect()->intended(route('sales.index'))->with('success', 'Sales created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = $this->salesUserService->getForEditShow($id);
        $dataRole = $this->userService->getRoleForFilter();
        $dataBranch = $this->branchService->getForFilter();
        $dataCountry = $this->countryService->getForFilter();
        $dataSales = $this->salesService->getForFilter();

        return Inertia::render('masters/users/view', [
            'data' => $data,
            'dataRole' => $dataRole,
            'dataBranch' => $dataBranch,
            'dataCountry' => $dataCountry,
            'dataSales' => $dataSales,
            'isSales' => true,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = $this->salesUserService->getForEditShow($id);
        $dataRole = $this->userService->getRoleForFilter();
        $dataBranch = $this->branchService->getForFilter();
        $dataCountry = $this->countryService->getForFilter();
        $dataSales = $this->salesService->getForFilter();

        return Inertia::render('masters/users/edit', [
            'data' => $data,
            'dataRole' => $dataRole,
            'dataBranch' => $dataBranch,
            'dataCountry' => $dataCountry,
            'dataSales' => $dataSales,
            'isSales' => true,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->userRule->rules($request->role, 'update', $id));

        $validated['role'] = 'sales';

        $this->salesUserService->update($validated, $id);

        return redirect()->intended(route('sales.index'))->with('success', 'Sales updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $userId) {
                $this->userService->delete($userId);
            }

            return redirect()->intended(route('sales.index'))->with('success', 'Selected sales deleted successfully.');
        }

        $this->userService->delete($id);

        return redirect()->intended(route('sales.index'))->with('success', 'Sales deleted successfully.');
    }

    public function preview(string $id)
    {
        $data = $this->buildSalesReportData($id);
        $reportData = $this->reportTemplateService->build('sales', $data);

        return view('sales.report-content', [
            'data' => $data,
            'branding' => $reportData['branding'],
            'is_pdf' => false,
        ]);
    }

    /**
     * Generate a PDF profile for the given salesperson.
     */
    public function generatePdf(string $id)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            $data = $this->buildSalesReportData($id);
            $reportData = $this->reportTemplateService->build('sales', $data);
            $branding = $reportData['branding'];

            $html = view('sales.report-content', [
                'data' => $data,
                'branding' => $branding,
                'is_pdf' => true,
            ])->render();

            $filename = 'sales-profile-'.str()->slug($data['name']).'.pdf';

            return Pdf::loadHTML($html)
                ->setPaper('a4')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->stream($filename);
        } catch (\Throwable $e) {
            Log::error('Sales PDF error', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to generate PDF: '.$e->getMessage()], 500);
        }
    }

    private function buildSalesReportData(string $id): array
    {
        $userData = $this->salesUserService->getForEditShow($id);

        $branchName = '-';
        if (! empty($userData['branch_id'])) {
            $branch = $this->branchService->getForFilter()
                ->firstWhere('id', $userData['branch_id']);
            $branchName = $branch['name'] ?? '-';
        }

        return [
            'name' => $userData['name'],
            'email' => $userData['email'],
            'contact' => $userData['contact'] ?? '-',
            'branch_name' => $branchName,
            'registration_number' => $this->resolveSalesRegistrationNumber((int) $id),
            'payment_info' => $this->buildSalesPaymentInfoRows((int) $id),
        ];
    }

    private function resolveSalesRegistrationNumber(int $salesUserId): string
    {
        if (! Schema::hasColumn('sales', 'registration_number')) {
            return '-';
        }

        $registrationNumber = Sales::query()
            ->where('user_id', $salesUserId)
            ->value('registration_number');

        return is_string($registrationNumber) && trim($registrationNumber) !== ''
            ? $registrationNumber
            : '-';
    }

    /**
     * @return array<int, array{label: string, amount_paid: float, total_amount: float, status: string}>
     */
    private function buildSalesPaymentInfoRows(int $salesUserId): array
    {
        $invoices = Invoice::query()
            ->whereHas('order.quotation', function ($query) use ($salesUserId): void {
                $query->where('handled_by', $salesUserId);
            })
            ->whereNotIn('status', [InvoiceStatus::Cancelled, InvoiceStatus::Refund])
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        return $invoices
            ->values()
            ->map(function (Invoice $invoice, int $index): array {
                $invoiceAmount = $this->resolveInvoiceTotalWithExtensions($invoice);
                $normalizedStatus = strtolower(trim((string) ($invoice->status ?? '')));
                $isPaid = $normalizedStatus === InvoiceStatus::Paid;

                return [
                    'label' => $this->toOrdinal($index + 1).' Payment',
                    'amount_paid' => $isPaid ? $invoiceAmount : 0.0,
                    'total_amount' => $invoiceAmount,
                    'status' => $isPaid ? 'Paid' : 'Outstanding',
                ];
            })
            ->all();
    }

    private function resolveInvoiceTotalWithExtensions(Invoice $invoice): float
    {
        $baseAmount = round((float) ($invoice->amount ?? 0), 2);

        if ($baseAmount !== 0.0) {
            return $baseAmount;
        }

        $extensions = is_array($invoice->extensions ?? null) ? $invoice->extensions : [];
        $extensionsTotal = collect($extensions)->sum(function ($extension): float {
            if (! is_array($extension)) {
                return 0;
            }

            return (float) ($extension['amount'] ?? 0);
        });

        return round($baseAmount + (float) $extensionsTotal, 2);
    }

    private function toOrdinal(int $number): string
    {
        $mod100 = $number % 100;

        if ($mod100 >= 11 && $mod100 <= 13) {
            return $number.'th';
        }

        return match ($number % 10) {
            1 => $number.'st',
            2 => $number.'nd',
            3 => $number.'rd',
            default => $number.'th',
        };
    }
}
