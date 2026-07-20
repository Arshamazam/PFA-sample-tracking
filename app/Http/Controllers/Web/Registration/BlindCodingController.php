<?php

namespace App\Http\Controllers\Web\Registration;

use App\Enums\PartStatus;
use App\Http\Controllers\Controller;
use App\Models\SamplePart;
use App\Services\QrService;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlindCodingController extends Controller
{
    public function __construct(private readonly RegistrationService $registration)
    {
    }

    public function create(): View
    {
        return view('registration.blind.create');
    }

    public function show(string $qrToken): View|RedirectResponse
    {
        $part = SamplePart::where('qr_token', $qrToken)->with('samplingEvent')->first();

        if ($part === null) {
            return redirect()->route('registration.blind.create')
                ->with('error', 'No sample part matches that QR token.');
        }

        return view('registration.blind.show', compact('part'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['qr_token' => ['required', 'string']]);
        $part = SamplePart::where('qr_token', $validated['qr_token'])->firstOrFail();

        $this->registration->blindCode($part, $request->user());

        return redirect()->route('registration.blind.label', $part->refresh())
            ->with('status', "Blind code {$part->blind_code} assigned.");
    }

    /**
     * Printable label sheet (blind code large + QR + section).
     */
    public function label(SamplePart $samplePart, QrService $qr): View
    {
        abort_if($samplePart->blind_code === null, 404);
        $samplePart->load('labResult');

        return view('registration.blind.label', [
            'part' => $samplePart,
            'qrSvg' => $qr->svg($samplePart, 220),
            'isRetestSafe' => true, // label is registration-side, business identity omitted anyway
        ]);
    }
}
