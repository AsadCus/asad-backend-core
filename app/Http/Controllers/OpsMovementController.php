<?php

namespace App\Http\Controllers;

use App\Rules\OpsMovementRule;
use App\Services\OpsMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OpsMovementController extends Controller
{
    protected $opsMovementService;

    protected OpsMovementRule $opsMovementRule;

    public function __construct(OpsMovementService $opsMovementService, OpsMovementRule $opsMovementRule)
    {
        $this->opsMovementService = $opsMovementService;
        $this->opsMovementRule = $opsMovementRule;
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

    /**
     * Update editable ops movement fields.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate($this->opsMovementRule->rules());
        $this->opsMovementService->update((int) $id, $validated);

        return redirect()->route('ops-movements.show', $id)
            ->with('success', 'Ops movement updated successfully.');
    }
}
