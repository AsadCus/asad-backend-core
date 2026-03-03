<?php

namespace App\Http\Controllers;

use App\Rules\QuotationItemRule;
use App\Services\NoteService;
use App\Services\QuotationItemService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuotationItemController extends Controller
{
    protected $quotationItemService;

    protected $quotationItemRule;

    protected $noteService;

    public function __construct(QuotationItemService $quotationItemService, QuotationItemRule $quotationItemRule, NoteService $noteService)
    {
        $this->quotationItemService = $quotationItemService;
        $this->quotationItemRule = $quotationItemRule;
        $this->noteService = $noteService;
    }

    public function index()
    {
        return Inertia::render('quotations/items/index', [
            'quotationItems' => $this->quotationItemService->getQuotationItemMasters(),
            'quotationMasterNote' => $this->noteService->get('master', 'quotation'),
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
}
