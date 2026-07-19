<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LabSection;
use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Enums\SopViolationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\AssignSectionRequest;
use App\Http\Requests\Registration\BlindCodeRequest;
use App\Http\Requests\Registration\DestroyPartRequest;
use App\Http\Requests\Registration\ReceivePartRequest;
use App\Http\Requests\Registration\RetainPartRequest;
use App\Http\Resources\RetentionPartResource;
use App\Http\Resources\SamplePartResource;
use App\Models\LabResult;
use App\Models\SamplePart;
use App\Models\Setting;
use App\Models\SopViolation;
use App\Models\TestCatalog;
use App\Services\CustodyStateMachine;
use App\Services\EventCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registration Section (Technical Wing) intake: receiving sealed parts, retaining
 * the reference part, assigning blind codes, and routing to a lab section.
 *
 * Registration officers work from the physical QR on the sample, so these endpoints
 * are keyed by qr_token. The blind wall applies from the lab workbench onwards.
 */
class RegistrationController extends Controller
{
    public function __construct(
        private readonly CustodyStateMachine $custody,
        private readonly EventCodeGenerator $codes,
    ) {
    }

    /**
     * Receive a part at the registration section.
     *
     * A broken seal or an unconfirmed seal number rejects the sample (notes
     * mandatory). Otherwise the part is accepted; a late arrival is accepted but
     * recorded as a SAME_DAY_TRANSFER violation. Cold-chain breaches are flagged by
     * the state machine.
     */
    public function receive(ReceivePartRequest $request): JsonResponse
    {
        $part = $this->partByToken($request->string('qr_token'));

        $sealPhotoPath = $request->file('seal_photo')->store('receiving-seal-photos', 'local');

        $context = [
            'photo_path' => $sealPhotoPath,
            'temperature_c' => $request->input('temperature_c'),
            'notes' => $request->input('notes'),
            'location_note' => 'Registration Section',
        ];

        $sealOk = $request->boolean('seal_intact') && $request->boolean('seal_number_confirmed');

        if (! $sealOk) {
            if (trim((string) $request->input('notes')) === '') {
                throw ValidationException::withMessages([
                    'notes' => ['Notes are required when rejecting a sample (broken seal or seal-number mismatch).'],
                ]);
            }

            $this->custody->transition($part, PartStatus::REJECTED, $request->user(), $context);
        } else {
            $this->custody->transition($part, PartStatus::RECEIVED_REGISTRATION, $request->user(), $context);
            $this->recordLateTransferIfNeeded($part);
        }

        return $this->partResponse($part);
    }

    /**
     * Move a received REFERENCE part into retention storage.
     */
    public function retain(RetainPartRequest $request): JsonResponse
    {
        $part = $this->partByToken($request->string('qr_token'));

        if ($part->role !== PartRole::REFERENCE) {
            throw ValidationException::withMessages([
                'qr_token' => ['Only the REFERENCE part is placed into retention.'],
            ]);
        }

        $this->custody->transition($part, PartStatus::IN_RETENTION, $request->user(), [
            'location_note' => $request->string('storage_location')->value(),
            'notes' => $request->input('notes'),
        ]);

        return $this->partResponse($part);
    }

    /**
     * Assign a blind code and move the part to BLIND_CODED. From here on, analysts
     * see only this code — never the business identity.
     */
    public function blindCode(BlindCodeRequest $request): JsonResponse
    {
        $part = $this->partByToken($request->string('qr_token'));

        DB::transaction(function () use ($part, $request) {
            if ($part->blind_code === null) {
                $part->update(['blind_code' => $this->codes->generateBlindCode()]);
            }

            $this->custody->transition($part, PartStatus::BLIND_CODED, $request->user(), [
                'notes' => 'Blind code assigned at registration.',
            ]);
        });

        return $this->partResponse($part);
    }

    /**
     * Route the part to a lab section and move it to ASSIGNED_TO_SECTION.
     */
    public function assignSection(AssignSectionRequest $request): JsonResponse
    {
        $part = $this->partByToken($request->string('qr_token'));
        $section = LabSection::from($request->string('lab_section')->value());

        DB::transaction(function () use ($part, $section, $request) {
            LabResult::updateOrCreate(
                ['sample_part_id' => $part->id],
                ['lab_section' => $section],
            );

            $this->custody->transition($part, PartStatus::ASSIGNED_TO_SECTION, $request->user(), [
                'notes' => 'Assigned to '.$section->label().' section.',
            ]);
        });

        return $this->partResponse($part);
    }

    /**
     * Suggest a lab section from the test catalog, based on the event's food_category.
     */
    public function suggestSection(Request $request): JsonResponse
    {
        $validated = $request->validate(['qr_token' => ['required', 'string']]);

        $part = $this->partByToken($validated['qr_token']);
        $category = $part->samplingEvent->food_category;

        $matches = $category === null
            ? collect()
            : TestCatalog::where('food_category', $category)->get();

        $suggested = $matches->first();

        return response()->json([
            'data' => [
                'food_category' => $category,
                'suggested_lab_section' => $suggested?->lab_section->value,
                'suggested_test_name' => $suggested?->test_name,
                'parameters_template' => $suggested?->parameters,
                // A category can map to more than one section (e.g. MILK is tested by
                // both Chemical and Microbiology) — the officer picks.
                'available' => $matches->map(fn (TestCatalog $c) => [
                    'lab_section' => $c->lab_section->value,
                    'test_name' => $c->test_name,
                    'tat_hours' => $c->tat_hours,
                ])->values(),
            ],
            'meta' => [
                'matched' => $matches->count(),
            ],
        ]);
    }

    /**
     * Manually destroy a retained reference part once it is destruction-eligible.
     * Photo and notes are mandatory; the eligibility guard lives in the state machine.
     */
    public function destroy(DestroyPartRequest $request): JsonResponse
    {
        $part = $this->partByToken($request->string('qr_token'));

        if ($part->role !== PartRole::REFERENCE) {
            throw ValidationException::withMessages([
                'qr_token' => ['Only the reference part is retained and destroyed.'],
            ]);
        }

        $photoPath = $request->file('photo')->store('destruction-photos', 'local');

        $this->custody->transition($part, PartStatus::DESTROYED, $request->user(), [
            'photo_path' => $photoPath,
            'notes' => $request->string('notes')->value(),
            'location_note' => 'Registration Section',
        ]);

        return $this->partResponse($part);
    }

    /**
     * List retained reference parts with their eligibility and retention age.
     */
    public function retention(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $validated = $request->validate([
            'eligible' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = SamplePart::query()
            ->where('role', PartRole::REFERENCE->value)
            ->where('status', PartStatus::IN_RETENTION->value)
            ->with(['samplingEvent', 'custodyEvents'])
            ->oldest('updated_at');

        if ($request->has('eligible')) {
            $request->boolean('eligible')
                ? $query->whereNotNull('destruction_eligible_at')->where('destruction_eligible_at', '<=', now())
                : $query->where(fn ($q) => $q->whereNull('destruction_eligible_at')->orWhere('destruction_eligible_at', '>', now()));
        }

        return RetentionPartResource::collection(
            $query->paginate($validated['per_page'] ?? 20)->withQueryString()
        );
    }

    /**
     * Record a SAME_DAY_TRANSFER violation when a sample reaches registration after
     * its collection date, or after the same-day deadline on the collection date.
     * The sample is still accepted — this only flags the deviation.
     */
    private function recordLateTransferIfNeeded(SamplePart $part): void
    {
        $collectedAt = $part->samplingEvent->collected_at;
        if ($collectedAt === null) {
            return;
        }

        $now = Carbon::now();
        $deadline = Setting::get('same_day_transfer_deadline', '20:00');

        $reason = null;
        if ($now->toDateString() > $collectedAt->toDateString()) {
            $reason = 'received_after_collection_date';
        } elseif ($now->toDateString() === $collectedAt->toDateString()) {
            [$hour, $minute] = array_pad(explode(':', $deadline), 2, '0');
            $deadlineAt = $now->copy()->setTime((int) $hour, (int) $minute, 0);

            if ($now->greaterThan($deadlineAt)) {
                $reason = 'received_after_same_day_deadline';
            }
        }

        if ($reason === null) {
            return;
        }

        SopViolation::create([
            'sample_part_id' => $part->id,
            'type' => SopViolationType::SAME_DAY_TRANSFER,
            'details' => [
                'reason' => $reason,
                'collected_at' => $collectedAt->toIso8601String(),
                'received_at' => $now->toIso8601String(),
                'deadline' => $deadline,
            ],
            'detected_at' => $now,
        ]);
    }

    private function partByToken(string $qrToken): SamplePart
    {
        return SamplePart::where('qr_token', $qrToken)->firstOrFail();
    }

    private function partResponse(SamplePart $part): JsonResponse
    {
        $part->refresh()->load('custodyEvents.actor', 'sopViolations');

        return (new SamplePartResource($part))->response();
    }
}
