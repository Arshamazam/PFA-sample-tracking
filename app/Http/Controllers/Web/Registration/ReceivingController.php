<?php

namespace App\Http\Controllers\Web\Registration;

use App\Http\Controllers\Controller;
use App\Models\SamplePart;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceivingController extends Controller
{
    public function __construct(private readonly RegistrationService $registration)
    {
    }

    public function create(): View
    {
        return view('registration.receiving.create');
    }

    public function show(string $qrToken): View|RedirectResponse
    {
        $part = SamplePart::where('qr_token', $qrToken)
            ->with(['samplingEvent.premises', 'sopViolations'])
            ->first();

        if ($part === null) {
            return redirect()->route('registration.receiving.create')
                ->with('error', 'No sample part matches that QR token.');
        }

        return view('registration.receiving.show', compact('part'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'qr_token' => ['required', 'string'],
            'seal_intact' => ['required', 'boolean'],
            'seal_number_confirmed' => ['required', 'boolean'],
            'seal_photo' => ['required', 'file', 'image', 'max:5120'],
            'temperature_c' => ['nullable', 'numeric', 'between:-50,100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $part = SamplePart::where('qr_token', $validated['qr_token'])->firstOrFail();
        $sealPhotoPath = $request->file('seal_photo')->store('receiving-seal-photos', 'local');
        $sealOk = $request->boolean('seal_intact') && $request->boolean('seal_number_confirmed');

        $result = $this->registration->receive(
            $part,
            $request->user(),
            $sealOk,
            $sealPhotoPath,
            $validated['temperature_c'] ?? null,
            $validated['notes'] ?? null,
        );

        $part->refresh()->load('sopViolations');
        $violationNote = $part->sopViolations->isNotEmpty()
            ? ' Note: '.$part->sopViolations->count().' SOP violation(s) were recorded.'
            : '';

        return redirect()->route('registration.receiving.create')
            ->with('status', "Sample {$result->label()}.".$violationNote);
    }
}
