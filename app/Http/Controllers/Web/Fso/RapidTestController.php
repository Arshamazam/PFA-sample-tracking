<?php

namespace App\Http\Controllers\Web\Fso;

use App\Enums\RapidTestDevice;
use App\Http\Controllers\Controller;
use App\Models\RapidTest;
use App\Services\PremisesResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RapidTestController extends Controller
{
    public function __construct(private readonly PremisesResolver $premisesResolver)
    {
    }

    public function create(): View
    {
        return view('fso.rapid.create', ['devices' => RapidTestDevice::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'premises_license' => ['required', 'string', 'max:255'],
            'premises_name' => ['nullable', 'string', 'max:255'],
            'premises_address' => ['nullable', 'string', 'max:255'],
            'premises_city' => ['nullable', 'string', 'max:255'],
            'device' => ['required', Rule::in(RapidTestDevice::values())],
            'reading' => ['required', 'string', 'max:255'],
            'passed' => ['required', 'boolean'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
            'tested_at' => ['required', 'date'],
        ]);

        $premises = $this->premisesResolver->resolveByLicense($validated['premises_license'], [
            'name' => $validated['premises_name'] ?? null,
            'address' => $validated['premises_address'] ?? null,
            'city' => $validated['premises_city'] ?? null,
        ]);

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('rapid-tests', 'local')
            : null;

        RapidTest::create([
            'premises_id' => $premises->id,
            'fso_id' => $request->user()->id,
            'device' => $validated['device'],
            'reading' => $validated['reading'],
            'passed' => $request->boolean('passed'),
            'photo_path' => $photoPath,
            'tested_at' => $validated['tested_at'],
        ]);

        return redirect()->route('fso.rapid.create')
            ->with('status', $request->boolean('passed')
                ? 'Rapid test recorded (passed).'
                : 'Rapid test recorded (failed) — proceed to formal sampling.');
    }
}
