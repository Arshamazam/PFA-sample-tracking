<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\SamplingEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only sampling-events explorer. The detail view uses the same complete
 * event story as the Phase 4 API event-detail endpoint.
 */
class EventController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $events = SamplingEvent::query()
            ->with('premises')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('event_code', 'like', "%{$search}%")
                        ->orWhereHas('premises', fn ($p) => $p->where('license_no', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('collected_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('collected_at', '<=', $request->date('to')))
            ->latest('collected_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.events.index', ['events' => $events, 'search' => $search, 'filters' => $request->only('from', 'to')]);
    }

    public function show(SamplingEvent $samplingEvent): View
    {
        $samplingEvent->load([
            'premises', 'fso',
            'parts.custodyEvents.actor', 'parts.labResult.analyst', 'parts.labResult.verifiedBy', 'parts.sopViolations',
            'rapidTests', 'disputes.decidedBy', 'disputes.retestLabResult',
        ]);

        return view('admin.events.show', ['event' => $samplingEvent]);
    }
}
