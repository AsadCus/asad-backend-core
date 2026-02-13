<?php

namespace App\Http\Controllers;

use App\Services\EnquiryService;
use Inertia\Inertia;

class EnquiryController extends Controller
{
    public function __construct(protected EnquiryService $enquiryService) {}

    /**
     * Display a listing of all enquiries (general + private).
     */
    public function index()
    {
        $data['enquiriesForDatatable'] = $this->enquiryService->getForDataTable();

        return Inertia::render('enquiries/index', [
            'data' => $data,
        ]);
    }
}
