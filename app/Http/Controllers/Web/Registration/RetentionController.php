<?php

namespace App\Http\Controllers\Web\Registration;

use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Http\Controllers\Controller;
use App\Models\SamplePart;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RetentionController extends Controller
{
    public function __construct(private readonly RegistrationService $registration)
    {
    }

    public function index(): View
    {
        $parts = SamplePart::query()
            ->where('role', PartRole::REFERENCE->value)
            ->where('status', PartStatus::IN_RETENTION->value)
            ->with(['samplingEvent', 'custodyEvents'])
            ->oldest('updated_at')
            ->paginate(20);

        return view('registration.retention.index', compact('parts'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'qr_token' => ['required', 'string'],
            'photo' => ['required', 'file', 'image', 'max:5120'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $part = SamplePart::where('qr_token', $validated['qr_token'])->firstOrFail();
        $photoPath = $request->file('photo')->store('destruction-photos', 'local');

        $this->registration->destroy($part, $request->user(), $photoPath, $validated['notes']);

        return redirect()->route('registration.retention.index')
            ->with('status', 'Reference part marked DESTROYED and recorded.');
    }
}
