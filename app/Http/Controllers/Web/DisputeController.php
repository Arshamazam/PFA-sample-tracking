<?php

namespace App\Http\Controllers\Web;

use App\Enums\DisputeStatus;
use App\Enums\LabSection;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Services\DisputeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DisputeController extends Controller
{
    public function __construct(private readonly DisputeService $disputes)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $disputes = Dispute::query()
            ->with(['samplingEvent', 'decidedBy'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('filed_at')
            ->paginate(20)
            ->withQueryString();

        return view('disputes.index', [
            'disputes' => $disputes,
            'statuses' => DisputeStatus::cases(),
            'status' => $status,
        ]);
    }

    public function show(Dispute $dispute): View
    {
        $dispute->load([
            'decidedBy',
            'retestLabResult.analyst', 'retestLabResult.verifiedBy',
            'samplingEvent.parts.labResult.analyst', 'samplingEvent.parts.labResult.verifiedBy',
            'samplingEvent.premises',
        ]);

        $original = $this->disputes->originalResult($dispute->samplingEvent);
        $windowExpiry = $original?->verdict_at ? $this->disputes->windowExpiry($original->verdict_at) : null;

        return view('disputes.show', [
            'dispute' => $dispute,
            'original' => $original,
            'retest' => $dispute->retestLabResult,
            'windowExpiry' => $windowExpiry,
            'sections' => LabSection::cases(),
        ]);
    }

    public function decide(Request $request, Dispute $dispute): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in([DisputeStatus::ACCEPTED->value, DisputeStatus::REJECTED->value])],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
            'lab_section' => ['nullable', Rule::in(LabSection::values())],
        ]);

        $section = ! empty($validated['lab_section']) ? LabSection::from($validated['lab_section']) : null;

        $this->disputes->decide($dispute, $request->user(), $validated['decision'], $validated['notes'], $section);

        return redirect()->route('disputes.show', $dispute)
            ->with('status', "Dispute {$validated['decision']}.");
    }
}
