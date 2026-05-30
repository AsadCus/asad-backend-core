<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethodMaster;
use App\Models\QuotationExtensionMaster;
use App\Rules\QuotationItemRule;
use App\Services\NoteService;
use App\Services\QuotationItemService;
use App\Services\QuotationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
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

        $this->middleware('permission:product-services view')->only(['index']);
        $this->middleware('permission:product-services edit')->only([
            'store', 'storePaymentMethodMasters', 'storeExtensionMasters',
        ]);
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

    public function quickCreate(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric'],
            'rate' => ['nullable', 'numeric'],
        ]);

        $payload = $this->quotationItemService->quickCreateItemGroup($validated);

        if ($request->expectsJson()) {
            return response()->json($payload, 201);
        }

        return back()
            ->with('result', $payload)
            ->with('success', 'Product/service item created.');
    }

    public function quickCreateExtensionMaster(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:discount,tax,credit_card,other'],
            'calculation_mode' => ['required', 'string', 'in:fixed,percentage'],
            'calculation_value' => ['required', 'numeric'],
        ]);

        $nextSortOrder = ((int) QuotationExtensionMaster::query()->max('sort_order')) + 1;

        $master = QuotationExtensionMaster::query()->create([
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'discount',
            'calculation_mode' => $validated['calculation_mode'],
            'calculation_value' => $validated['calculation_value'],
            'payment_methods' => [],
            'is_active' => true,
            'sort_order' => $nextSortOrder,
        ]);

        $payload = [
            'id' => $master->id,
            'name' => $master->name,
            'type' => $master->type,
            'calculation_mode' => $master->calculation_mode,
            'calculation_value' => $master->calculation_value,
            'is_active' => $master->is_active,
            'sort_order' => $master->sort_order,
        ];

        if ($request->expectsJson()) {
            return response()->json($payload, 201);
        }

        return back()
            ->with('result', $payload)
            ->with('success', 'Quotation extension created.');
    }

    public function quickCreatePaymentMethodMaster(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $name = trim((string) $validated['name']);
        $baseValue = Str::of($name)->lower()->slug('_')->value();
        $value = $baseValue;

        if ($value === '') {
            return response()->json([
                'message' => 'Invalid payment method name.',
            ], 422);
        }

        $suffix = 1;
        while (PaymentMethodMaster::query()->where('value', $value)->exists()) {
            $value = $baseValue.'_'.$suffix;
            $suffix++;
        }

        $nextSortOrder = ((int) PaymentMethodMaster::query()->max('sort_order')) + 1;

        $master = PaymentMethodMaster::query()->create([
            'name' => $name,
            'value' => $value,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => $nextSortOrder,
        ]);

        $payload = [
            'id' => $master->id,
            'name' => $master->name,
            'value' => $master->value,
            'is_active' => (bool) $master->is_active,
            'is_default' => (bool) $master->is_default,
            'sort_order' => (int) $master->sort_order,
        ];

        if ($request->expectsJson()) {
            return response()->json($payload, 201);
        }

        return back()
            ->with('result', $payload)
            ->with('success', 'Payment method created.');
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
            'extensions.*.type' => ['required', 'string', 'in:discount,tax,credit_card,other'],
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
            'payment_methods.*.is_default' => ['nullable', 'boolean'],
            'payment_methods.*.sort_order' => ['nullable', 'integer'],
        ]);

        $this->quotationService->storePaymentMethodMasters($validated['payment_methods']);

        return redirect()->route('quotation-items.index')
            ->with('success', 'Payment method defaults updated successfully.');
    }
}
