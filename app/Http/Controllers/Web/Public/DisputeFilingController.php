<?php

namespace App\Http\Controllers\Web\Public;

use App\Http\Controllers\Controller;
use App\Models\SamplingEvent;
use App\Services\DisputeService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public (FBO self-service) resampling application. Reuses DisputeService::file()
 * with ZERO new business rules — this only adds public-facing input hardening
 * (honeypot, PK phone format, and the per-IP rate limit applied on the route).
 */
class DisputeFilingController extends Controller
{
    public function __construct(private readonly DisputeService $disputes)
    {
    }

    public function create(string $eventCode): View
    {
        $event = SamplingEvent::where('event_code', $eventCode)
            ->whereNotNull('finalized_at')
            ->firstOrFail();

        return view('public.track.dispute', ['event' => $event]);
    }

    public function store(Request $request, string $eventCode): RedirectResponse
    {
        // Confirm the event exists/finalized before doing anything else.
        SamplingEvent::where('event_code', $eventCode)->whereNotNull('finalized_at')->firstOrFail();

        // Honeypot: bots fill hidden fields. Silently pretend success.
        if (filled($request->input('website'))) {
            return redirect()->route('track.event', ['event_code' => $eventCode])
                ->with('dispute_reference', 'received');
        }

        $validated = $request->validate([
            'filed_by_name' => ['required', 'string', 'max:255'],
            'filed_by_phone' => ['required', 'string', 'max:32', function ($attr, $value, $fail) {
                if (! Phone::isValidPkMobile($value)) {
                    $fail('Enter a valid Pakistani mobile number (e.g. 03001234567).');
                }
            }],
            'filed_by_cnic' => ['nullable', 'string', 'max:32'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $dispute = $this->disputes->file([
            ...$validated,
            'event_code' => $eventCode,
            'source' => 'PUBLIC',
        ]);

        return redirect()->route('track.event', ['event_code' => $eventCode])
            ->with('dispute_reference', $dispute->reference_no);
    }
}
