<?php

namespace App\Http\Controllers\Web\Fso;

use App\Enums\PartRole;
use App\Http\Controllers\Controller;
use App\Models\SamplingEvent;
use App\Services\QrService;
use App\Services\SamplingEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Interim FSO web fallback (mobile-responsive) until the Flutter app ships.
 * Reuses SamplingEventService — no new business logic.
 */
class EventController extends Controller
{
    public function __construct(private readonly SamplingEventService $events)
    {
    }

    public function index(Request $request): View
    {
        $events = SamplingEvent::query()
            ->with('premises')
            ->where('fso_id', $request->user()->id)
            ->latest('collected_at')
            ->paginate(20);

        return view('fso.events.index', compact('events'));
    }

    public function create(): View
    {
        return view('fso.events.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'premises_license' => ['required', 'string', 'max:255'],
            'premises_name' => ['nullable', 'string', 'max:255'],
            'premises_address' => ['nullable', 'string', 'max:255'],
            'premises_city' => ['nullable', 'string', 'max:255'],
            'food_item' => ['required', 'string', 'max:255'],
            'food_category' => ['nullable', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'is_perishable' => ['sometimes', 'boolean'],
            'witness_name' => ['nullable', 'string', 'max:255'],
            'witness_cnic' => ['nullable', 'string', 'max:32'],
            'collected_at' => ['required', 'date'],
        ]);

        $event = $this->events->create($request->user(), $validated);

        return redirect()->route('fso.events.show', $event)->with('status', "Draft event {$event->event_code} created. Add the three parts.");
    }

    public function show(Request $request, SamplingEvent $samplingEvent): View
    {
        abort_unless($samplingEvent->fso_id === $request->user()->id, 403);

        $samplingEvent->load(['premises', 'parts.custodyEvents']);

        return view('fso.events.show', [
            'event' => $samplingEvent,
            'roles' => PartRole::cases(),
            'existingRoles' => $samplingEvent->parts->pluck('role')->map(fn ($r) => $r->value)->all(),
        ]);
    }

    public function update(Request $request, SamplingEvent $samplingEvent): RedirectResponse
    {
        abort_unless($samplingEvent->fso_id === $request->user()->id, 403);
        $this->events->assertDraft($samplingEvent);

        $validated = $request->validate([
            'witness_name' => ['nullable', 'string', 'max:255'],
            'witness_cnic' => ['nullable', 'string', 'max:32'],
            'witness_signature' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        $data = collect($validated)->except('witness_signature')->all();
        if ($request->hasFile('witness_signature')) {
            $data['witness_signature_path'] = $request->file('witness_signature')->store('witness-signatures', 'local');
        }

        $samplingEvent->update($data);

        return redirect()->route('fso.events.show', $samplingEvent)->with('status', 'Witness details saved.');
    }

    public function storePart(Request $request, SamplingEvent $samplingEvent): RedirectResponse
    {
        abort_unless($samplingEvent->fso_id === $request->user()->id, 403);

        $validated = $request->validate([
            'role' => ['required', Rule::in(PartRole::values())],
            'seal_number' => ['required', 'string', 'max:255'],
            'seal_photo' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $sealPhotoPath = $request->file('seal_photo')->store('seal-photos', 'local');

        $this->events->addPart(
            $samplingEvent,
            $request->user(),
            PartRole::from($validated['role']),
            $validated['seal_number'],
            $sealPhotoPath,
        );

        return redirect()->route('fso.events.show', $samplingEvent)->with('status', "{$validated['role']} part added.");
    }

    public function finalize(Request $request, SamplingEvent $samplingEvent): RedirectResponse
    {
        abort_unless($samplingEvent->fso_id === $request->user()->id, 403);

        $this->events->finalize($samplingEvent, $request->user());

        return redirect()->route('fso.events.labels', $samplingEvent)->with('status', 'Event finalized. Print the sample labels.');
    }

    public function labels(Request $request, SamplingEvent $samplingEvent, QrService $qr): View
    {
        abort_unless($samplingEvent->fso_id === $request->user()->id, 403);
        $samplingEvent->load('parts');

        $labels = $samplingEvent->parts->map(fn ($part) => [
            'role' => $part->role->value,
            'seal_number' => $part->seal_number,
            'qr' => $qr->svg($part, 200),
        ]);

        return view('fso.events.labels', ['event' => $samplingEvent, 'labels' => $labels]);
    }
}
