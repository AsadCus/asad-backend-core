<?php

namespace App\Http\Controllers;

use App\Services\DocumentationService;
use Inertia\Inertia;
use Inertia\Response;

class DocumentationController extends Controller
{
    public function __construct(private DocumentationService $documentationService) {}

    public function index(): Response
    {
        return Inertia::render('documentations/v3/index', [
            'documentation' => $this->documentationService->getIndexData(),
        ]);
    }
}
