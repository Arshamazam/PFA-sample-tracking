<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PartStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Custody\ScanCustodyRequest;
use App\Http\Resources\SamplePartResource;
use App\Http\Resources\SamplingEventResource;
use App\Models\SamplePart;
use App\Services\CustodyStateMachine;
use Illuminate\Http\JsonResponse;

class CustodyController extends Controller
{
    public function __construct(private readonly CustodyStateMachine $custody)
    {
    }

    /**
     * Scan a part's QR and record a custody transition. This single endpoint drives
     * all scan-based movements; Phase 3 reuses it for registration/lab moves.
     */
    public function scan(ScanCustodyRequest $request): JsonResponse
    {
        $part = SamplePart::where('qr_token', $request->string('qr_token'))->firstOrFail();

        $context = [
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'location_note' => $request->input('location_note'),
            'temperature_c' => $request->input('temperature_c'),
            'notes' => $request->input('notes'),
        ];

        if ($request->hasFile('photo')) {
            $context['photo_path'] = $request->file('photo')->store('custody-photos', 'local');
        }

        $this->custody->transition(
            $part,
            PartStatus::from($request->string('to_status')),
            $request->user(),
            $context,
        );

        $part->load('custodyEvents.actor');

        return (new SamplePartResource($part))->response();
    }

    /**
     * Internal (authenticated) view of a part: its details, full custody timeline,
     * and a summary of the parent sampling event. The PUBLIC version is Phase 6.
     */
    public function showPart(string $qrToken): JsonResponse
    {
        $part = SamplePart::where('qr_token', $qrToken)
            ->with(['custodyEvents.actor', 'samplingEvent.premises'])
            ->firstOrFail();

        return response()->json([
            'data' => [
                'part' => new SamplePartResource($part),
                'sampling_event' => new SamplingEventResource($part->samplingEvent),
            ],
            'meta' => [
                'allowed_transitions' => array_map(
                    fn (PartStatus $s) => $s->value,
                    $this->custody->allowedTransitions($part),
                ),
            ],
        ]);
    }
}
