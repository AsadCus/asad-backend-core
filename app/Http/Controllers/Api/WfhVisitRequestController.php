<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WfhVisitRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WfhVisitRequestController extends Controller
{
    public function __construct(private WfhVisitRequestService $service) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canany([
            'hris.wfh-visit-request view-all',
            'hris.wfh-visit-request view-team',
            'hris.wfh-visit-request view-own',
        ]), 403);

        return response()->json(
            $this->service->getForDataTable($user, $request->only('status', 'type', 'from', 'to')),
        );
    }

    public function my(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->getMyList($request->user(), $request->only('status', 'type')),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'in:wfh,visit'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'gte:start_date'],
            'reason' => ['required', 'string', 'min:5'],
            'location_address' => ['nullable', 'string', 'max:500'],
            'location_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'location_radius' => ['nullable', 'integer', 'min:50', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ]);

        return response()->json(
            $this->service->store(
                $request->user(),
                $request->only('type', 'start_date', 'end_date', 'reason', 'location_address', 'location_lat', 'location_lng', 'location_radius'),
                $request->file('attachments', []),
            ),
            201,
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canany([
            'hris.wfh-visit-request view-all',
            'hris.wfh-visit-request view-team',
            'hris.wfh-visit-request view-own',
        ]), 403);

        return response()->json($this->service->getDetail($user, $id));
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.wfh-visit-request approve-supervisor'), 403);

        $request->validate([
            'note' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ]);

        return response()->json(
            $this->service->approve($user, $id, $request->input('note'), $request->file('attachments', [])),
        );
    }

    public function verify(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.wfh-visit-request verify-hr'), 403);

        $request->validate([
            'note' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ]);

        return response()->json(
            $this->service->verify($user, $id, $request->input('note'), $request->file('attachments', [])),
        );
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canany([
            'hris.wfh-visit-request approve-supervisor',
            'hris.wfh-visit-request verify-hr',
        ]), 403);

        $request->validate([
            'note' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ]);

        return response()->json(
            $this->service->reject($user, $id, $request->input('note'), $request->file('attachments', [])),
        );
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        return response()->json($this->service->cancel($request->user(), $id));
    }
}
