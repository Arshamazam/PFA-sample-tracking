<?php

namespace App\Http\Controllers\Web\Registration;

use App\Http\Controllers\Controller;
use App\Services\DisputeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * An officer files a dispute on behalf of a walk-in FBO. Reuses DisputeService,
 * exactly as the API does.
 */
class DisputeFilingController extends Controller
{
    public function __construct(private readonly DisputeService $disputes)
    {
    }

    public function create(): View
    {
        return view('registration.disputes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_code' => ['required', 'string'],
            'filed_by_name' => ['required', 'string', 'max:255'],
            'filed_by_phone' => ['required', 'string', 'max:32'],
            'filed_by_cnic' => ['nullable', 'string', 'max:32'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $dispute = $this->disputes->file($validated);

        return redirect()->route('registration.disputes.create')
            ->with('status', "Dispute filed for {$dispute->samplingEvent->event_code}. It will be reviewed by a verifying officer.");
    }
}
