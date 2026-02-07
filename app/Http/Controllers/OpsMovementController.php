<?php

namespace App\Http\Controllers;

use App\Services\OpsMovementService;
use Inertia\Inertia;

class OpsMovementController extends Controller
{
    protected $opsMovementService;

    public function __construct(OpsMovementService $opsMovementService)
    {
        $this->opsMovementService = $opsMovementService;
    }

    /**
     * Display a listing of ops movements (read-only view from packages + manifests).
     */
    public function index()
    {
        $data['opsMovementsForDatatable'] = $this->opsMovementService->getForDataTable();

        return Inertia::render('ops-movements/index', [
            'data' => $data,
        ]);
    }

    /**
     * Display the specified ops movement detail (read-only).
     */
    public function show(string $id)
    {
        $opsMovement = $this->opsMovementService->getForShow($id);

        return Inertia::render('ops-movements/show', [
            'data' => $opsMovement,
        ]);
    }
}
