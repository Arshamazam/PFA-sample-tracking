<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LabSection;
use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\AssignSectionRequest;
use App\Http\Requests\Registration\BlindCodeRequest;
use App\Http\Requests\Registration\DestroyPartRequest;
use App\Http\Requests\Registration\ReceivePartRequest;
use App\Http\Requests\Registration\RetainPartRequest;
use App\Http\Resources\RetentionPartResource;
use App\Http\Resources\SamplePartResource;
use App\Models\SamplePart;
use App\Models\TestCatalog;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Registration Section (Technical Wing) intake: receiving sealed parts, retaining
 * the reference part, assigning blind codes, and routing to a lab section.
 *
 * Registration officers work from the physical QR on the sample, so these endpoints
 * are keyed by qr_token. All business logic lives in RegistrationService, which the
 * web panel shares.
 */
class RegistrationController extends Controller
{
    public function __construct(private readonly RegistrationService $registration)
    {
    }

    public function receive(ReceivePartRequest $request): JsonResponse
    {
        $part = $this->partByToken($request->string('qr_token'));
        $sealPhotoPath = $request->file('seal_photo')->store('receiving-seal-photos', 'local');

        $sealOk = $request->boolean('seal_intact') && $request->boolean('seal_number_confirmed');

        $this->registration->receive(
            $part,
            $request->user(),
            $sealOk,
            $sealPhotoPath,
            $request->input('temperature_c'),
            $request->input('notes'),
        );

        return $this->partResponse($part);
    }

    public function retain(RetainPartRequest $request): JsonResponse
    {
        $part = $this->partByToken($request->string('qr_token'));

        $this->registration->retain(
            $part,
            $request->user(),
            $request->string('storage_location')->value(),
            $request->input('notes'),
        );

        return $this->partResponse($part);
    }

    public function blindCode(BlindCodeRequest $request): JsonResponse
    {
        $part = $this->partByToken($request->string('qr_token'));

        $this->registration->blindCode($part, $request->user());

        return $this->partResponse($part);
    }

    public function assignSection(AssignSectionRequest $request): JsonResponse
    {
        $part = $this->partByToken($request->string('qr_token'));
        $section = LabSection::from($request->string('lab_section')->value());

        $this->registration->assignSection($part, $request->user(), $section);

        return $this->partResponse($part);
    }

    public function suggestSection(Request $request): JsonResponse
    {
        $validated = $request->validate(['qr_token' => ['required', 'string']]);
        $part = $this->partByToken($validated['qr_token']);

        $suggestion = $this->registration->suggestSection($part->samplingEvent->food_category);
        $suggested = $suggestion['suggested'];

        return response()->json([
            'data' => [
                'food_category' => $suggestion['food_category'],
                'suggested_lab_section' => $suggested?->lab_section->value,
                'suggested_test_name' => $suggested?->test_name,
                'parameters_template' => $suggested?->parameters,
                'available' => $suggestion['matches']->map(fn (TestCatalog $c) => [
                    'lab_section' => $c->lab_section->value,
                    'test_name' => $c->test_name,
                    'tat_hours' => $c->tat_hours,
                ])->values(),
            ],
            'meta' => ['matched' => $suggestion['matches']->count()],
        ]);
    }

    public function destroy(DestroyPartRequest $request): JsonResponse
    {
        $part = $this->partByToken($request->string('qr_token'));
        $photoPath = $request->file('photo')->store('destruction-photos', 'local');

        $this->registration->destroy($part, $request->user(), $photoPath, $request->string('notes')->value());

        return $this->partResponse($part);
    }

    public function retention(Request $request): AnonymousResourceCollection
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
