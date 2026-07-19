<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LabSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispute\DecideDisputeRequest;
use App\Http\Requests\Dispute\FileDisputeRequest;
use App\Http\Resources\DisputeResource;
use App\Models\Dispute;
use App\Services\DisputeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Disputes against UNFIT verdicts and the resulting resampling.
 *
 * Filing is INTERNAL for now (an officer files on behalf of a walk-in FBO); the
 * public self-service route in Phase 6 will reuse DisputeService untouched.
 */
class DisputeController extends Controller
{
    public function __construct(private readonly DisputeService $disputes)
    {
    }

    /**
     * List disputes with their original and (if present) retest results.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Dispute::query()
            ->with([
                'decidedBy',
                'retestLabResult.analyst',
                'retestLabResult.verifiedBy',
                'samplingEvent.parts.labResult.analyst',
                'samplingEvent.parts.labResult.verifiedBy',
            ])
            ->latest('filed_at');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (isset($validated['from'])) {
            $query->where('filed_at', '>=', $request->date('from'));
        }
        if (isset($validated['to'])) {
            $query->where('filed_at', '<=', $request->date('to'));
        }

        return DisputeResource::collection(
            $query->paginate($validated['per_page'] ?? 20)->withQueryString()
        );
    }

    /**
     * File a dispute (REGISTRATION_OFFICER, ADMIN). Rules live in DisputeService.
     */
    public function store(FileDisputeRequest $request): JsonResponse
    {
        $dispute = $this->disputes->file($request->validated());

        return (new DisputeResource($this->loadDispute($dispute)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Accept or reject a dispute (VERIFYING_OFFICER, ADMIN). On accept, the
     * reference part is activated for a blind retest.
     */
    public function decide(DecideDisputeRequest $request, Dispute $dispute): DisputeResource
    {
        $section = $request->filled('lab_section')
            ? LabSection::from($request->string('lab_section')->value())
            : null;

        $dispute = $this->disputes->decide(
            $dispute,
            $request->user(),
            $request->string('decision')->value(),
            $request->string('notes')->value(),
            $section,
        );

        return new DisputeResource($this->loadDispute($dispute));
    }

    private function loadDispute(Dispute $dispute): Dispute
    {
        return $dispute->load([
            'decidedBy',
            'retestLabResult.analyst',
            'retestLabResult.verifiedBy',
            'samplingEvent.parts.labResult.analyst',
            'samplingEvent.parts.labResult.verifiedBy',
        ]);
    }
}
