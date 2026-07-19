<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventDetailResource;
use App\Models\SamplingEvent;
use Illuminate\Http\Request;

/**
 * The full de-blinded event detail — the complete story of a sample from collection
 * to final verdict. Officer/admin-facing (and the owning FSO); never analysts.
 */
class EventController extends Controller
{
    public function detail(Request $request, SamplingEvent $samplingEvent): EventDetailResource
    {
        $user = $request->user();

        // The collecting FSO may see their own event; other FSOs may not.
        if ($user->role === UserRole::FSO && $samplingEvent->fso_id !== $user->id) {
            abort(403, 'You can only view your own sampling events.');
        }

        $samplingEvent->load([
            'premises',
            'fso',
            'parts.custodyEvents.actor',
            'parts.labResult.analyst',
            'parts.labResult.verifiedBy',
            'parts.sopViolations',
            'rapidTests',
            'disputes.decidedBy',
            'disputes.retestLabResult.analyst',
            'disputes.retestLabResult.verifiedBy',
        ]);

        return new EventDetailResource($samplingEvent);
    }
}
