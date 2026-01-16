<?php

namespace App\Http\Controllers;

use App\Rules\MaidRule;
use App\Services\CountryService;
use App\Services\CustomerService;
use App\Services\EducationLevelService;
use App\Services\MaidManagement\DataExtractor\MedicalExtractor;
use App\Services\MaidManagement\DataExtractor\PersonalInformationExtractor;
use App\Services\MaidManagement\DataExtractor\SectionExtractor;
use App\Services\MaidManagement\DataExtractor\SkillsAssessmentExtractor;
use App\Services\MaidManagement\DataExtractor\EmploymentExtractor;
use App\Services\MaidManagement\FileParser\DocxParser;
use App\Services\MaidManagement\FileParser\PdfParser;
use App\Services\MaidService;
use App\Services\ReligionService;
use App\Services\SupplierService;
use App\Services\MaidDocumentGeneratorService;
use App\Services\MaidStatusService;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Exception;
use Illuminate\Support\Facades\Log;

class MaidController extends Controller
{
    protected $maidService, $countryService, $religionService, $educationLevelService, $maidRule, $customerService, $supplierService;
    protected $docxParser, $pdfParser, $personalInformationExtractor, $medicalExtractor, $sectionExtractor, $skillsExtractor, $employmentExtractor;
    protected $documentGeneratorService, $maidStatusService;

    public function __construct(
        MaidService $maidService,
        CountryService $countryService,
        ReligionService $religionService,
        EducationLevelService $educationLevelService,
        MaidRule $maidRule,
        DocxParser $docxParser,
        PdfParser $pdfParser,
        PersonalInformationExtractor $personalInformationExtractor,
        MedicalExtractor $medicalExtractor,
        SectionExtractor $sectionExtractor,
        SkillsAssessmentExtractor $skillsExtractor,
        EmploymentExtractor $employmentExtractor,
        CustomerService $customerService,
        SupplierService $supplierService,
        MaidDocumentGeneratorService $documentGeneratorService,
        MaidStatusService $maidStatusService
    ) {
        $this->maidService = $maidService;
        $this->countryService = $countryService;
        $this->religionService = $religionService;
        $this->educationLevelService = $educationLevelService;
        $this->supplierService = $supplierService;
        $this->maidRule = $maidRule;
        $this->docxParser = $docxParser;
        $this->pdfParser = $pdfParser;
        $this->personalInformationExtractor = $personalInformationExtractor;
        $this->medicalExtractor = $medicalExtractor;
        $this->sectionExtractor = $sectionExtractor;
        $this->skillsExtractor = $skillsExtractor;
        $this->employmentExtractor = $employmentExtractor;
        $this->customerService = $customerService;
        $this->documentGeneratorService = $documentGeneratorService;
        $this->maidStatusService = $maidStatusService;

        $this->middleware('permission:maid view', ['only' => ['index', 'show']]);
        $this->middleware('permission:maid create', ['only' => ['create', 'store', 'uploadDocument']]);
        $this->middleware('permission:maid edit', ['only' => ['edit', 'update', 'scheduleInterview', 'completeInterview', 'finalizeDocuments', 'updateStatus']]);
        $this->middleware('permission:maid delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('customer')) {
            $customerMaidIds = $this->customerService->getCustomerMaidIds($user->id);

            if (!empty($customerMaidIds)) {
                $data = $this->maidService->getForDataTable($customerMaidIds);
            } else {
                $data = [];
            }
        } else {
            $data = $this->maidService->getForDataTable();
        }

        $dataNationality = $this->countryService->getForFilterByAdjective();
        $dataReligion = $this->religionService->getForFilterByName();
        $dataEducationLevel = $this->educationLevelService->getForFilterByName();
        $dataSupplier = $this->supplierService->getForFilterByName();

        return Inertia::render('maid/index', [
            'data' => $data,
            'dataNationality' => $dataNationality,
            'dataReligion' => $dataReligion,
            'dataEducationLevel' => $dataEducationLevel,
            'dataSupplier' => $dataSupplier,
            'misc' => [
                'nationalities' => $this->countryService->getForFilter(),
                'religions' => $this->religionService->getForFilter(),
                'education_levels' => $this->educationLevelService->getForFilter(),
                'suppliers' => $this->supplierService->getForFilter(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $dataNationality = $this->countryService->getForFilter('adjective');
        $dataReligion = $this->religionService->getForFilter();
        $dataEducationLevel = $this->educationLevelService->getForFilter();
        $dataSupplier = $this->supplierService->getForFilter();

        return Inertia::render('maid/create', [
            'dataNationality' => $dataNationality,
            'dataReligion' => $dataReligion,
            'dataEducationLevel' => $dataEducationLevel,
            'dataSupplier' => $dataSupplier,
            'prefilledSupplierId' => $request->input('supplier_id'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->maidRule->rules());

        $this->maidService->store($validated, $request);

        return redirect()->intended(route('maid.index'))->with('success', 'Maid created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = $this->maidService->getForEditShow($id);
        $dataNationality = $this->countryService->getForFilter('adjective');
        $dataReligion = $this->religionService->getForFilter();
        $dataEducationLevel = $this->educationLevelService->getForFilter();
        $dataSupplier = $this->supplierService->getForFilter();

        return Inertia::render('maid/view', [
            'data' => $data,
            'dataNationality' => $dataNationality,
            'dataReligion' => $dataReligion,
            'dataEducationLevel' => $dataEducationLevel,
            'dataSupplier' => $dataSupplier
        ]);
    }

    public function getForShow($id)
    {
        return response()->json($this->maidService->getForEditShow($id));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = $this->maidService->getForEditShow($id);
        $dataNationality = $this->countryService->getForFilter('adjective');
        $dataReligion = $this->religionService->getForFilter();
        $dataEducationLevel = $this->educationLevelService->getForFilter();
        $dataSupplier = $this->supplierService->getForFilter();

        return Inertia::render('maid/edit', [
            'data' => $data,
            'dataNationality' => $dataNationality,
            'dataReligion' => $dataReligion,
            'dataEducationLevel' => $dataEducationLevel,
            'dataSupplier' => $dataSupplier
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate($this->maidRule->rules());

        $this->maidService->update($validated, $id, $request);

        return redirect()->intended(route('maid.index'))->with('success', 'Maid updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $userId) {
                $this->maidService->delete($userId);
            }

            return redirect()->intended(route('maid.index'))->with('success', 'Selected maids deleted successfully.');
        }

        $this->maidService->delete($id);

        return redirect()->intended(route('maid.index'))->with('success', 'Maid deleted successfully.');
    }

    /**
     * Upload and parse document for extraction.
     */
    public function uploadDocument(Request $request)
    {
        $request->validate($this->maidRule->uploadDocumentRules());

        try {
            $file = $request->file('document');

            // Prepare parsers and extractors
            $parsers = [
                'pdf' => $this->pdfParser,
                'docx' => $this->docxParser,
            ];

            $extractors = [
                'section' => $this->sectionExtractor,
                'personal' => $this->personalInformationExtractor,
                'medical' => $this->medicalExtractor,
                'skills' => $this->skillsExtractor,
                'employment' => $this->employmentExtractor,
            ];

            // Parse document using MaidService
            $result = $this->maidService->parseDocument($file, $parsers, $extractors);

            return back()->with('result', $result);
        } catch (Exception $e) {
            return back()->with(['error' => 'Failed to upload and parse document: ' . $e->getMessage()]);
        }
    }

    /**
     * Save scan result directly to database.
     * This endpoint is used when user wants to save the scanned data immediately.
     */
    public function saveScanResult(Request $request)
    {
        try {
            // Validate the scan result data
            $validated = $request->validate($this->maidRule->rules());

            // Save to database
            $maid = $this->maidService->store($validated, $request);

            return response()->json([
                'success' => true,
                'message' => 'Maid data from scan saved successfully.',
                'data' => $maid,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to save scan result', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save scan result: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate PDF biodata for a maid
     */
    public function generatePdf(string $id)
    {
        try {
            $maid = \App\Models\Maid::with(['country', 'religion', 'educationLevel', 'attributes'])->findOrFail($id);
            return $this->documentGeneratorService->generatePdf($maid, true);
        } catch (Exception $e) {
            Log::error('Failed to generate PDF', [
                'maid_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Preview biodata HTML
     */
    public function previewBiodata(string $id)
    {
        try {
            $maid = \App\Models\Maid::with(['country', 'religion', 'educationLevel', 'attributes'])->findOrFail($id);

            $html = $this->documentGeneratorService->generateBiodataHtml($maid);

            return response($html)->header('Content-Type', 'text/html');
        } catch (Exception $e) {
            Log::error('Failed to preview biodata', [
                'maid_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to preview biodata: ' . $e->getMessage());
        }
    }

    /**
     * Schedule an interview for a maid
     * Changes status from Available to Interviewing
     * Auto-revert to Available after 1 day if not completed
     */
    public function scheduleInterview(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'interview_date' => 'required|date|after_or_equal:today',
                'interview_end_date' => 'nullable|date|after:interview_date',
            ]);

            $interviewDate = Carbon::parse($validated['interview_date']);
            $interviewEndDate = isset($validated['interview_end_date'])
                ? Carbon::parse($validated['interview_end_date'])
                : null;

            $result = $this->maidStatusService->scheduleInterview(
                (int) $id,
                $interviewDate,
                $interviewEndDate
            );

            return back()->with('success', $result['message']);
        } catch (Exception $e) {
            Log::error('Failed to schedule interview', [
                'maid_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to schedule interview: ' . $e->getMessage());
        }
    }

    /**
     * Complete an interview
     * If success: Interviewing -> Pending -> Redirect to Quotation
     * If failed: Interviewing -> Available
     */
    public function completeInterview(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'success' => 'required|boolean',
                'handover_date' => 'nullable|date|after_or_equal:today',
                'reason' => 'nullable|string|max:1000',
            ]);

            $result = $this->maidStatusService->completeInterview(
                (int) $id,
                $validated['success'],
                $validated['handover_date'] ?? null,
                $validated['reason'] ?? null
            );

            // If successful (status is now pending), redirect to quotation creation
            if ($validated['success'] && $result['data']['status'] === 'pending') {
                return redirect()->route('quotation.create', [
                    'maid_id' => $id,
                    'handover_date' => $validated['handover_date'] ?? null
                ])->with('success', $result['message'] . ' Redirected to create quotation.');
            }

            return back()->with('success', $result['message']);
        } catch (Exception $e) {
            Log::error('Failed to complete interview', [
                'maid_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to complete interview: ' . $e->getMessage());
        }
    }

    /**
     * Finalize documents
     * Changes status from Pending to Assigned
     * Then redirects to quotation creation with maid data
     */
    public function finalizeDocuments(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'success' => 'required|boolean',
            ]);

            $result = $this->maidStatusService->finalizeDocuments(
                (int) $id,
                $validated['success']
            );

            // If successful and maid is now assigned, redirect to quotation creation
            if ($validated['success'] && $result['data']['status'] === 'assigned') {
                return redirect()->route('quotation.create', ['maid_id' => $id])
                    ->with('success', $result['message'] . ' Redirected to create quotation.');
            }

            return back()->with('success', $result['message']);
        } catch (Exception $e) {
            Log::error('Failed to finalize documents', [
                'maid_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to finalize documents: ' . $e->getMessage());
        }
    }

    /**
     * Manual status update with validation
     */
    public function updateStatus(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:available,interviewing,pending,assigned',
            ]);

            $result = $this->maidStatusService->updateStatus(
                (int) $id,
                $validated['status']
            );

            return back()->with('success', $result['message']);
        } catch (Exception $e) {
            Log::error('Failed to update status', [
                'maid_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to update status: ' . $e->getMessage());
        }
    }

    /**
     * Update maid status (used by quotation flow)
     */
    public function updateMaidStatus(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:available,interviewing,pending,assigned',
                'reason' => 'nullable|string|max:1000',
            ]);

            $maid = $this->maidService->updateStatus($id, $validated['status'], $validated['reason'] ?? null);

            return response()->json([
                'success' => true,
                'message' => "Maid status updated to {$validated['status']} successfully.",
                'data' => $maid
            ]);
        } catch (Exception $e) {
            Log::error('Failed to update maid status', [
                'maid_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update maid status: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Cancel scheduled interview
     * Changes status from Interviewing to Available and cancels the job
     */
    public function cancelInterview(string $id)
    {
        try {
            $result = $this->maidStatusService->cancelInterview((int) $id);

            return back()->with('success', $result['message']);
        } catch (Exception $e) {
            Log::error('Failed to cancel interview', [
                'maid_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to cancel interview: ' . $e->getMessage());
        }
    }
}
