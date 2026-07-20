<?php

namespace App\Http\Controllers\Web\Fso;

use App\Enums\PartStatus;
use App\Http\Controllers\Controller;
use App\Models\SamplePart;
use App\Services\CustodyStateMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Custody scan for FSO/TRANSPORT — the web fallback for the field scan endpoint.
 * Reuses the same CustodyStateMachine as the API scan.
 */
class ScanController extends Controller
{
    public function __construct(private readonly CustodyStateMachine $custody)
    {
    }

    public function create(Request $request): View
    {
        $part = null;
        if ($token = $request->query('qr_token')) {
            $part = SamplePart::where('qr_token', $token)->with('samplingEvent')->first();
        }

        return view('fso.scan.create', ['part' => $part]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'qr_token' => ['required', 'string'],
            'to_status' => ['required', Rule::in(PartStatus::values())],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_note' => ['nullable', 'string', 'max:255'],
            'temperature_c' => ['nullable', 'numeric', 'between:-50,100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        $part = SamplePart::where('qr_token', $validated['qr_token'])->firstOrFail();

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('custody-photos', 'local')
            : null;

        $this->custody->transition($part, PartStatus::from($validated['to_status']), $request->user(), [
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'location_note' => $validated['location_note'] ?? null,
            'temperature_c' => $validated['temperature_c'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'photo_path' => $photoPath,
        ]);

        return redirect()->route('fso.scan.create')
            ->with('status', "Scan recorded: sample now {$part->fresh()->status->label()}.");
    }
}
