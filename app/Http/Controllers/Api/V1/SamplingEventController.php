<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SamplingEvent\AddSamplePartRequest;
use App\Http\Requests\SamplingEvent\ListSamplingEventRequest;
use App\Http\Requests\SamplingEvent\StoreSamplingEventRequest;
use App\Http\Requests\SamplingEvent\UpdateSamplingEventRequest;
use App\Http\Resources\SamplePartResource;
use App\Http\Resources\SamplingEventResource;
use App\Models\SamplingEvent;
use App\Services\SamplingEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SamplingEventController extends Controller
{
    public function __construct(private readonly SamplingEventService $events)
    {
    }

    /**
     * List the current FSO's sampling events (paginated, filterable).
     */
    public function index(ListSamplingEventRequest $request): AnonymousResourceCollection
    {
        $query = SamplingEvent::query()
            ->with('premises')
            ->where('fso_id', $request->user()->id)
            ->latest('collected_at');

        if ($request->filled('premises_license')) {
            $license = $request->string('premises_license');
            $query->whereHas('premises', fn ($q) => $q->where('license_no', $license));
        }

        if ($request->filled('status')) {
            $request->string('status')->value() === 'FINALIZED'
                ? $query->whereNotNull('finalized_at')
                : $query->whereNull('finalized_at');
        }

        if ($request->filled('from')) {
            $query->where('collected_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('collected_at', '<=', $request->date('to'));
        }

        return SamplingEventResource::collection(
            $query->paginate($request->integer('per_page', 20))->withQueryString()
        );
    }

    /**
     * Create a sampling event in DRAFT state (finalized_at null).
     */
    public function store(StoreSamplingEventRequest $request): JsonResponse
    {
        $event = $this->events->create($request->user(), $request->validated());
        $event->load('premises');

        return (new SamplingEventResource($event))->response()->setStatusCode(201);
    }

    /**
     * Show a single sampling event with parts and their custody trails.
     */
    public function show(SamplingEvent $samplingEvent): SamplingEventResource
    {
        $this->authorize('view', $samplingEvent);

        $samplingEvent->load(['premises', 'parts.custodyEvents.actor']);

        return new SamplingEventResource($samplingEvent);
    }

    /**
     * Update witness fields / corrections — only while the event is a draft.
     */
    public function update(UpdateSamplingEventRequest $request, SamplingEvent $samplingEvent): SamplingEventResource
    {
        $this->authorize('update', $samplingEvent);
        $this->events->assertDraft($samplingEvent);

        $data = $request->safe()->except('witness_signature');

        if ($request->hasFile('witness_signature')) {
            $data['witness_signature_path'] = $request->file('witness_signature')
                ->store('witness-signatures', 'local');
        }

        $samplingEvent->update($data);
        $samplingEvent->load('premises');

        return new SamplingEventResource($samplingEvent);
    }

    /**
     * Attach ONE part to the event. Creates it at COLLECTED with its opening
     * custody event via the state machine. Rejects a duplicate role with 422.
     */
    public function addPart(AddSamplePartRequest $request, SamplingEvent $samplingEvent): JsonResponse
    {
        $this->authorize('update', $samplingEvent);

        $sealPhotoPath = $request->file('seal_photo')->store('seal-photos', 'local');

        $part = $this->events->addPart(
            $samplingEvent,
            $request->user(),
            PartRole::from($request->string('role')),
            $request->string('seal_number')->value(),
            $sealPhotoPath,
        );

        $part->load('custodyEvents.actor');

        return (new SamplePartResource($part))->response()->setStatusCode(201);
    }

    /**
     * Finalize the event: enforce the Rule of Three, then seal all three parts.
     */
    public function finalize(SamplingEvent $samplingEvent): SamplingEventResource
    {
        $this->authorize('update', $samplingEvent);

        $this->events->finalize($samplingEvent, request()->user());

        $samplingEvent->load(['premises', 'parts.custodyEvents.actor']);

        return new SamplingEventResource($samplingEvent);
    }
}
