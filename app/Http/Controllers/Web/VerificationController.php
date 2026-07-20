<?php

namespace App\Http\Controllers\Web;

use App\Enums\PartStatus;
use App\Enums\Verdict;
use App\Http\Controllers\Controller;
use App\Models\SamplePart;
use App\Services\VerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Verifying-officer web screens. This role sees the FULL de-blinded record because
 * a verdict is a legal determination about a named business.
 */
class VerificationController extends Controller
{
    public function __construct(private readonly VerificationService $verification)
    {
    }

    public function queue(): View
    {
        $parts = SamplePart::query()
            ->where('status', PartStatus::RESULT_ENTERED->value)
            ->with(['labResult.analyst', 'samplingEvent.premises', 'sopViolations'])
            ->oldest('created_at')
            ->paginate(20);

        return view('verification.queue', compact('parts'));
    }

    public function show(string $blindCode): View
    {
        $part = $this->partByBlindCode($blindCode);

        return view('verification.show', compact('part'));
    }

    public function verdict(Request $request, string $blindCode): RedirectResponse
    {
        $validated = $request->validate([
            'verdict' => ['required', Rule::in(Verdict::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $part = $this->partByBlindCode($blindCode);

        $this->verification->recordVerdict(
            $part,
            $request->user(),
            Verdict::from($validated['verdict']),
            $validated['notes'] ?? null,
        );

        return redirect()->route('verification.queue')
            ->with('status', "Verdict {$validated['verdict']} recorded for {$blindCode}. Report queued.");
    }

    public function returnToAnalyst(Request $request, string $blindCode): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $part = $this->partByBlindCode($blindCode);
        $this->verification->returnToAnalyst($part, $request->user(), $validated['notes']);

        return redirect()->route('verification.queue')
            ->with('status', "Sample {$blindCode} returned to the analyst.");
    }

    private function partByBlindCode(string $blindCode): SamplePart
    {
        return SamplePart::where('blind_code', $blindCode)
            ->with([
                'labResult.analyst', 'labResult.verifiedBy',
                'samplingEvent.premises', 'samplingEvent.fso',
                'custodyEvents.actor', 'sopViolations',
            ])
            ->firstOrFail();
    }
}
