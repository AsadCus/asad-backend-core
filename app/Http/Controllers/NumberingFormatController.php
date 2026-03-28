<?php

namespace App\Http\Controllers;

use App\Models\NumberingFormat;
use App\Services\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NumberingFormatController extends Controller
{
    public function __construct(private NumberingService $numberingService) {}

    public function index(Request $request)
    {
        $modelKey = (string) $request->query('model_key');

        if ($modelKey === '') {
            return response()->json([
                'supported_model_keys' => $this->numberingService->supportedModelKeys(),
            ]);
        }

        $formats = $this->numberingService->listFormats($modelKey)
            ->map(fn (NumberingFormat $format) => $this->serializeFormat($format))
            ->values();

        return response()->json([
            'model_key' => $modelKey,
            'formats' => $formats,
        ]);
    }

    public function suggest(Request $request)
    {
        $validated = $request->validate([
            'model_key' => ['required', 'string'],
            'format_id' => ['nullable', 'integer', 'exists:numbering_formats,id'],
        ]);

        return response()->json(
            $this->numberingService->suggestNextNumber(
                (string) $validated['model_key'],
                isset($validated['format_id']) ? (int) $validated['format_id'] : null,
            )
        );
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $format = $this->numberingService->createFormat($validated);

        return response()->json($this->serializeFormat($format), 201);
    }

    public function update(Request $request, NumberingFormat $numberingFormat)
    {
        $validated = $this->validatePayload($request, $numberingFormat);
        $format = $this->numberingService->updateFormat($numberingFormat, $validated);

        return response()->json($this->serializeFormat($format));
    }

    public function destroy(NumberingFormat $numberingFormat)
    {
        $this->numberingService->deleteFormat($numberingFormat);

        return response()->json([
            'deleted' => true,
        ]);
    }

    private function validatePayload(Request $request, ?NumberingFormat $format = null): array
    {
        $modelKey = (string) $request->input('model_key', $format?->model_key ?? '');

        return $request->validate([
            'model_key' => ['required', 'string', Rule::in($this->numberingService->supportedModelKeys())],
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/%I%/',
                Rule::unique('numbering_formats', 'name')
                    ->where(fn ($query) => $query->where('model_key', $modelKey))
                    ->ignore($format?->id),
            ],
            'increment_padding' => ['nullable', 'integer', 'min:1', 'max:12'],
            'increment_start' => ['nullable', 'integer', 'min:1'],
            'increment_scope' => ['nullable', 'string', Rule::in(['format', 'model'])],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function serializeFormat(NumberingFormat $format): array
    {
        return [
            'id' => (int) $format->id,
            'model_key' => (string) $format->model_key,
            'name' => (string) $format->name,
            'increment_padding' => (int) $format->increment_padding,
            'increment_start' => (int) $format->increment_start,
            'increment_scope' => (string) $format->increment_scope,
            'is_default' => (bool) $format->is_default,
            'is_active' => (bool) $format->is_active,
            'sort_order' => (int) $format->sort_order,
        ];
    }
}
