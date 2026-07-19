<?php

namespace App\Http\Controllers\Web\Registration;

use App\Enums\LabSection;
use App\Http\Controllers\Controller;
use App\Models\SamplePart;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function __construct(private readonly RegistrationService $registration)
    {
    }

    public function create(): View
    {
        return view('registration.section.create');
    }

    public function show(string $qrToken): View|RedirectResponse
    {
        $part = SamplePart::where('qr_token', $qrToken)->with('samplingEvent')->first();

        if ($part === null) {
            return redirect()->route('registration.section.create')
                ->with('error', 'No sample part matches that QR token.');
        }

        $suggestion = $this->registration->suggestSection($part->samplingEvent->food_category);

        return view('registration.section.show', [
            'part' => $part,
            'suggestion' => $suggestion,
            'sections' => LabSection::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'qr_token' => ['required', 'string'],
            'lab_section' => ['required', Rule::in(LabSection::values())],
        ]);

        $part = SamplePart::where('qr_token', $validated['qr_token'])->firstOrFail();

        $this->registration->assignSection($part, $request->user(), LabSection::from($validated['lab_section']));

        return redirect()->route('registration.section.create')
            ->with('status', "Sample assigned to {$part->fresh()->labResult->lab_section->label()} section.");
    }
}
