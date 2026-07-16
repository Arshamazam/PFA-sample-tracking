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
use App\Models\RapidTest;
use App\Models\SamplePart;
use App\Models\SamplingEvent;
use App\Services\CustodyStateMachine;
use App\Services\EventCodeGenerator;
use App\Services\PremisesResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SamplingEventController extends Controller
{
    // TODO: derive the district from the FSO's assigned district / config once modelled.
    private const DISTRICT = 'LHR';

    public function __construct(
        private readonly PremisesResolver $premisesResolver,
        private readonly EventCodeGenerator $eventCodes,
        private readonly CustodyStateMachine $custody,
    ) {
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
        $premises = $this->premisesResolver->resolveByLicense(
            $request->string('premises_license'),
            [
                'name' => $request->input('premises_name'),
                'address' => $request->input('premises_address'),
                'city' => $request->input('premises_city'),
            ],
        );

        $event = SamplingEvent::create([
            'event_code' => $this->eventCodes->generate(self::DISTRICT),
            'premises_id' => $premises->id,
            'fso_id' => $request->user()->id,
            'food_item' => $request->string('food_item'),
            'food_category' => $request->input('food_category'),
            'brand_name' => $request->input('brand_name'),
            'is_perishable' => $request->boolean('is_perishable'),
            'witness_name' => $request->input('witness_name', ''),
            'witness_cnic' => $request->input('witness_cnic'),
            'collected_at' => $request->date('collected_at'),
        ]);

        // Optionally link a prior rapid test to this event.
        if ($request->filled('rapid_test_id')) {
            RapidTest::where('id', $request->string('rapid_test_id'))
                ->update(['sampling_event_id' => $event->id]);
        }

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
        $this->assertDraft($samplingEvent);

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
        $this->assertDraft($samplingEvent);

        $role = PartRole::from($request->string('role'));

        if ($samplingEvent->parts()->where('role', $role)->exists()) {
            throw ValidationException::withMessages([
                'role' => ["A {$role->value} part already exists for this event."],
            ]);
        }

        $sealPhotoPath = $request->file('seal_photo')->store('seal-photos', 'local');

        $part = DB::transaction(function () use ($request, $samplingEvent, $role, $sealPhotoPath) {
            $part = $samplingEvent->parts()->create([
                'role' => $role,
                'qr_token' => $this->uniqueQrToken(),
                'seal_number' => $request->string('seal_number'),
                'seal_photo_path' => $sealPhotoPath,
                'status' => PartStatus::COLLECTED,
            ]);

            // Opening custody entry (COLLECTED), attributed to the sampling FSO.
            $this->custody->recordInitialCollection($part, $request->user(), [
                'notes' => 'Part collected and split before witness.',
            ]);

            return $part;
        });

        $part->load('custodyEvents.actor');

        return (new SamplePartResource($part))->response()->setStatusCode(201);
    }

    /**
     * Finalize the event: enforce the Rule of Three, then seal all three parts.
     */
    public function finalize(SamplingEvent $samplingEvent): SamplingEventResource
    {
        $this->authorize('update', $samplingEvent);
        $this->assertDraft($samplingEvent);

        $this->assertRuleOfThree($samplingEvent);

        DB::transaction(function () use ($samplingEvent) {
            $samplingEvent->update(['finalized_at' => now()]);

            foreach ($samplingEvent->parts as $part) {
                $this->custody->transition($part, PartStatus::SEALED, request()->user(), [
                    'notes' => 'Sealed at finalization of sampling event.',
                ]);
            }
        });

        $samplingEvent->load(['premises', 'parts.custodyEvents.actor']);

        return new SamplingEventResource($samplingEvent);
    }

    /**
     * Enforce that the event has exactly the three required, properly sealed parts,
     * plus witness name and signature. Throws a 422 with clear errors otherwise.
     */
    private function assertRuleOfThree(SamplingEvent $samplingEvent): void
    {
        $errors = [];
        $parts = $samplingEvent->parts()->get();

        $roles = $parts->pluck('role')->map(fn (PartRole $r) => $r->value)->sort()->values()->all();
        $expected = collect(PartRole::values())->sort()->values()->all();

        if ($roles !== $expected) {
            $errors['parts'][] = 'The event must have exactly 3 parts: LAB, REFERENCE, and FBO_COPY.';
        }

        foreach ($parts as $part) {
            if (trim((string) $part->seal_number) === '' || trim((string) $part->seal_photo_path) === '') {
                $errors['parts'][] = "The {$part->role->value} part is missing a seal number or seal photo.";
            }
        }

        if (trim((string) $samplingEvent->witness_name) === '') {
            $errors['witness_name'][] = 'A witness name is required before finalization.';
        }

        if (trim((string) $samplingEvent->witness_signature_path) === '') {
            $errors['witness_signature'][] = 'A witness signature must be uploaded before finalization.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function assertDraft(SamplingEvent $samplingEvent): void
    {
        if (! $samplingEvent->isDraft()) {
            throw ValidationException::withMessages([
                'event' => ['This sampling event is finalized and can no longer be modified.'],
            ]);
        }
    }

    private function uniqueQrToken(): string
    {
        do {
            $token = Str::random(32);
        } while (SamplePart::where('qr_token', $token)->exists());

        return $token;
    }
}
