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
            'moduleSlug' => null,
            'procedureSlug' => null,
        ]);
    }

    public function showModule(string $moduleSlug): Response
    {
        return Inertia::render('documentations/v3/index', [
            'documentation' => $this->documentationService->getIndexData(),
            'moduleSlug' => $moduleSlug,
            'procedureSlug' => null,
        ]);
    }

    public function showProcedure(string $moduleSlug, string $procedureSlug): Response
    {
        return Inertia::render('documentations/v3/index', [
            'documentation' => $this->documentationService->getIndexData(),
            'moduleSlug' => $moduleSlug,
            'procedureSlug' => $procedureSlug,
        ]);
    }
}
