<?php

namespace App\Http\Controllers;

use App\Rules\NoteRule;
use App\Services\NoteService;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    protected NoteService $noteService;

    public function __construct(NoteService $noteService)
    {
        $this->noteService = $noteService;
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'model' => ['required', 'string'],
            'id'    => ['nullable'],
        ]);

        return response()->json($this->noteService->get($validated['model'], $validated['id'] ?? null));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(NoteRule::rules());

        $this->noteService->sync($validated['model'], $validated['id'] ?? null, $validated['notes']);
    }

    public function destroy(Request $request, int $id)
    {
        $request->validate(['model' => ['required', 'string']]);

        $this->noteService->delete($request->string('model'), $id);
    }
}
