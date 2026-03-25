<?php

namespace App\Http\Controllers;

use App\Rules\QuotationItemRule;
use App\Services\NoteService;
use App\Services\QuotationItemService;
use App\Services\QuotationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuotationItemController extends Controller
{
    protected $quotationItemService;

    protected $quotationItemRule;

    protected $noteService;

    protected $quotationService;

    public function __construct(QuotationItemService $quotationItemService, QuotationItemRule $quotationItemRule, NoteService $noteService, QuotationService $quotationService)
    {
        $this->quotationItemService = $quotationItemService;
        $this->quotationItemRule = $quotationItemRule;
        $this->noteService = $noteService;
        $this->quotationService = $quotationService;
    }

    public function index()
    {
        return Inertia::render('quotations/items/index', [
            'quotationItems' => $this->quotationItemService->getQuotationItemMasters(),
            'quotationMasterNote' => $this->noteService->get('master', 'quotation'),
            'paymentMethodMasters' => $this->quotationService->getPaymentMethodMastersForMasterPage(),
            'quotationExtensionMasters' => $this->quotationService->getExtensionMastersForMasterPage(),
            'paymentMethods' => $this->quotationService->getPaymentMethodOptions(),
        ]);
    }

    public function getQuotationItemMastersForOptions()
    {
        return response()->json($this->quotationItemService->getQuotationItemMasters());
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->quotationItemRule->rules());
        $this->quotationItemService->storeQuotationItemMaster($validated['items']);

        return redirect()->route('quotation-items.index')
            ->with('success', 'Quotation items created successfully.');
    }

    public function storeExtensionMasters(Request $request)
    {
        $validated = $request->validate([
            'extensions' => ['required', 'array'],
            'extensions.*.id' => ['nullable', 'integer', 'exists:quotation_extension_masters,id'],
            'extensions.*.name' => ['required', 'string', 'max:255'],
            'extensions.*.type' => ['required', 'string', 'in:discount,tax,credit_card'],
            'extensions.*.calculation_mode' => ['required', 'string', 'in:fixed,percentage'],
            'extensions.*.calculation_value' => ['required', 'numeric'],
            'extensions.*.payment_methods' => ['nullable', 'array'],
            'extensions.*.payment_methods.*' => ['string', 'max:100'],
            'extensions.*.is_active' => ['nullable', 'boolean'],
            'extensions.*.sort_order' => ['nullable', 'integer'],
        ]);

        $this->quotationService->storeExtensionMasters($validated['extensions']);

        return redirect()->route('quotation-items.index')
            ->with('success', 'Quotation extension defaults updated successfully.');
    }

    public function storePaymentMethodMasters(Request $request)
    {
        $validated = $request->validate([
            'payment_methods' => ['required', 'array'],
            'payment_methods.*.id' => ['nullable', 'integer', 'exists:payment_method_masters,id'],
            'payment_methods.*.name' => ['required', 'string', 'max:255'],
            'payment_methods.*.value' => ['nullable', 'string', 'max:100'],
            'payment_methods.*.is_active' => ['nullable', 'boolean'],
            'payment_methods.*.sort_order' => ['nullable', 'integer'],
        ]);

        $this->quotationService->storePaymentMethodMasters($validated['payment_methods']);

        return redirect()->route('quotation-items.index')
            ->with('success', 'Payment method defaults updated successfully.');
    }
}
