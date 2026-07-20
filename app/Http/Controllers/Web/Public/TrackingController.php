<?php

namespace App\Http\Controllers\Web\Public;

use App\Enums\PartStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicEventResource;
use App\Models\SamplePart;
use App\Models\SamplingEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unauthenticated public tracking. Every input is treated as hostile: rate-limited
 * upstream, only FINALIZED events are ever visible, and all data goes through
 * PublicEventResource (the public wall). Pages are noindex.
 */
class TrackingController extends Controller
{
    public function landing(): View
    {
        return view('public.track.landing');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:license,event'],
            'q' => ['required', 'string', 'max:255'],
        ]);

        $q = trim($validated['q']);

        return $validated['mode'] === 'license'
            ? redirect()->route('track.license', ['license_no' => $q])
            : redirect()->route('track.event', ['event_code' => $q]);
    }

    /**
     * The QR-label URL printed since Phase 2: resolve the part -> its event view.
     */
    public function byQrToken(string $qrToken): View
    {
        $part = SamplePart::where('qr_token', $qrToken)->firstOrFail();
        $event = $this->finalizedEventOrFail($part->sampling_event_id);

        return $this->eventView($event);
    }

    public function byEvent(string $eventCode): View
    {
        $event = SamplingEvent::where('event_code', $eventCode)
            ->whereNotNull('finalized_at')
            ->firstOrFail();

        return $this->eventView($event);
    }

    public function byLicense(string $licenseNo): View
    {
        $events = SamplingEvent::query()
            ->whereNotNull('finalized_at')
            ->whereHas('premises', fn ($q) => $q->where('license_no', $licenseNo))
            ->with('premises')
            ->latest('collected_at')
            ->paginate(10);

        abort_if($events->total() === 0, 404, 'No records found for this license number.');

        return view('public.track.license', [
            'events' => $events,
            'licenseNo' => $licenseNo,
            'premisesName' => $events->first()?->premises?->name,
        ]);
    }

    /**
     * Short link used in SMS: /t/{event_code} -> canonical event page.
     */
    public function shortRedirect(string $eventCode): RedirectResponse
    {
        return redirect()->route('track.event', ['event_code' => $eventCode]);
    }

    /**
     * Serve ONLY the report photo of a REPORT_ISSUED part. Never an arbitrary path.
     */
    public function reportPhoto(SamplePart $part): StreamedResponse
    {
        abort_unless($part->status === PartStatus::REPORT_ISSUED, 404);

        $path = $part->labResult?->report_photo_path;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    private function finalizedEventOrFail(string $eventId): SamplingEvent
    {
        return SamplingEvent::where('id', $eventId)
            ->whereNotNull('finalized_at')
            ->firstOrFail();
    }

    private function eventView(SamplingEvent $event): View
    {
        $event->load([
            'premises',
            'parts.labResult',
            'parts.custodyEvents',
            'disputes',
        ]);

        return view('public.track.event', [
            'event' => $event,
            'public' => (new PublicEventResource($event))->resolve(),
        ]);
    }
}
