<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\SopViolationType;
use App\Http\Controllers\Controller;
use App\Models\SopViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SopViolationController extends Controller
{
    public function index(Request $request): View
    {
        $query = SopViolation::query()->with('samplePart.samplingEvent')->latest('detected_at');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($request->filled('resolved')) {
            $request->boolean('resolved') ? $query->whereNotNull('resolved_at') : $query->whereNull('resolved_at');
        }

        return view('admin.violations.index', [
            'violations' => $query->paginate(20)->withQueryString(),
            'types' => SopViolationType::cases(),
            'filters' => $request->only('type', 'resolved'),
        ]);
    }

    public function resolve(Request $request, SopViolation $sopViolation): RedirectResponse
    {
        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:2000'],
        ]);

        $sopViolation->update([
            'resolved_at' => now(),
            'resolved_by_id' => $request->user()->id,
            'resolution_notes' => $validated['resolution_notes'],
        ]);

        return redirect()->route('admin.violations.index')->with('status', 'Violation marked resolved.');
    }
}
