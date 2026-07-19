<?php

namespace App\Http\Controllers\Web;

use App\Enums\LabSection;
use App\Enums\PartStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BlindSamplePartResource;
use App\Models\SamplePart;
use App\Services\LabService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The lab analyst's web workbench — BEHIND THE BLIND WALL.
 *
 * Every value shown to the analyst is passed through BlindSamplePartResource, the
 * SAME allow-list the API uses, so business identity can never leak into the HTML.
 * Locked by tests/Feature/PanelBlindWallTest.php.
 */
class LabController extends Controller
{
    public function __construct(private readonly LabService $lab)
    {
    }

    public function queue(Request $request): View
    {
        $validated = $request->validate([
            'section' => ['sometimes', 'nullable', \Illuminate\Validation\Rule::in(LabSection::values())],
        ]);
        $section = $validated['section'] ?? null;

        $parts = SamplePart::query()
            ->whereIn('status', [
                PartStatus::ASSIGNED_TO_SECTION->value,
                PartStatus::ACTIVATED_FOR_RETEST->value,
                PartStatus::TESTING->value,
            ])
            ->when($section, fn ($q) => $q->whereHas('labResult', fn ($r) => $r->where('lab_section', $section)))
            ->whereHas('labResult')
            ->with(['labResult', 'samplingEvent', 'custodyEvents'])
            ->oldest('created_at')
            ->paginate(20)
            ->withQueryString();

        // Shape through the blind allow-list, then hand only arrays to the view.
        $rows = $parts->getCollection()->map(fn ($p) => (new BlindSamplePartResource($p))->resolve());

        return view('lab.queue', [
            'paginator' => $parts,
            'rows' => $rows,
            'sections' => LabSection::cases(),
            'section' => $section,
        ]);
    }

    public function show(string $blindCode): View
    {
        $part = $this->partByBlindCode($blindCode);
        $blind = (new BlindSamplePartResource($part))->resolve();

        return view('lab.show', ['blind' => $blind]);
    }

    public function start(Request $request, string $blindCode): RedirectResponse
    {
        $part = $this->partByBlindCode($blindCode);
        $this->lab->start($part, $request->user());

        return redirect()->route('lab.show', $blindCode)->with('status', 'Testing started.');
    }

    public function results(Request $request, string $blindCode): RedirectResponse
    {
        $validated = $request->validate([
            'parameters' => ['required', 'array', 'min:1'],
            'parameters.*.name' => ['required', 'string', 'max:255'],
            'parameters.*.value' => ['required'],
            'parameters.*.unit' => ['nullable', 'string', 'max:64'],
            'parameters.*.permissible_limit' => ['nullable', 'string', 'max:64'],
            'parameters.*.within_limit' => ['required', 'boolean'],
            'parameters.*.is_additional' => ['sometimes', 'boolean'],
            'report_photo' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $part = $this->partByBlindCode($blindCode);
        $reportPhotoPath = $request->file('report_photo')->store('lab-reports', 'local');

        $this->lab->submitResults($part, $request->user(), $validated['parameters'], $reportPhotoPath);

        return redirect()->route('lab.show', $blindCode)->with('status', 'Results submitted for verification.');
    }

    private function partByBlindCode(string $blindCode): SamplePart
    {
        return SamplePart::where('blind_code', $blindCode)
            ->with(['labResult', 'samplingEvent', 'custodyEvents'])
            ->firstOrFail();
    }
}
