<?php

namespace App\Http\Controllers;

use App\Rules\OrderRule;
use App\Services\CustomerService;
use App\Services\OrderService;
use App\Services\QuotationService;
use App\Services\SalesService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    protected $orderService;

    protected $quotationService;

    protected $orderRule;

    protected $customerService;

    protected $salesService;

    public function __construct(OrderService $orderService, QuotationService $quotationService, OrderRule $orderRule, CustomerService $customerService, SalesService $salesService)
    {
        $this->orderService = $orderService;
        $this->quotationService = $quotationService;
        $this->orderRule = $orderRule;
        $this->customerService = $customerService;
        $this->salesService = $salesService;
    }

    public function index(Request $request)
    {
        $filters = [];

        $data['ordersForDatatable'] = $this->orderService->getForDataTable($filters);
        $data['customers'] = $this->customerService->getForFilter();
        $data['salespersons'] = $this->salesService->getForFilter();
        $data['convertableQuotations'] = $this->quotationService->getCanCreateOrderForFilter($filters);

        return Inertia::render('orders/index', [
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        if ($request['quotation_id']) {
            $data['quotation'] = $this->quotationService->getForEditShow($request['quotation_id']);
            $data['paymentMethods'] = $this->quotationService->getPaymentMethodOptions();
            $data['quotationExtensionMasters'] = $this->quotationService->getExtensionMastersForMasterPage();
            $data['defaultPaymentMethod'] = $this->quotationService->getDefaultPaymentMethodValue();

            return Inertia::render('orders/create', [
                'data' => $data,
            ]);
        } else {
            return redirect()->route('order.index')->with('error', 'Select quotation first to create order');
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->orderRule->rules());
        $this->orderService->store($validated);

        return redirect()->route('invoice.index')->with('success', 'Invoices created successfully.');
    }

    public function show($id)
    {
        $data['data'] = $this->orderService->getForEditShow($id);
        $data['quotation'] = $this->quotationService->getForEditShow($data['data']['quotation_id']);
        $data['paymentMethods'] = $this->quotationService->getPaymentMethodOptions();
        $data['quotationExtensionMasters'] = $this->quotationService->getExtensionMastersForMasterPage();
        $data['defaultPaymentMethod'] = $this->quotationService->getDefaultPaymentMethodValue();

        return Inertia::render('orders/view', [
            'data' => $data,
        ]);
    }

    public function edit($id)
    {
        $data['data'] = $this->orderService->getForEditShow($id);
        $data['quotation'] = $this->quotationService->getForEditShow($data['data']['quotation_id']);
        $data['paymentMethods'] = $this->quotationService->getPaymentMethodOptions();
        $data['quotationExtensionMasters'] = $this->quotationService->getExtensionMastersForMasterPage();
        $data['defaultPaymentMethod'] = $this->quotationService->getDefaultPaymentMethodValue();

        return Inertia::render('orders/edit', [
            'data' => $data,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate($this->orderRule->rules($id));
        $this->orderService->update($validated, $id);

        // return redirect()->route('order.index')->with('success', 'Order updated successfully.');
        return redirect()->route('invoice.index')->with('success', 'Invoices updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $deleteId) {
                $this->orderService->delete($deleteId);
            }

            return redirect()->route('order.index')
                ->with('success', 'Selected orders deleted successfully.');
        }

        $this->orderService->delete($id);

        return redirect()->route('order.index')
            ->with('success', 'Order deleted successfully.');
    }
}
