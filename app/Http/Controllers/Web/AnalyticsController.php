<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * TAT / management analytics (read-only). Server-rendered stat cards + CSS bars.
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics)
    {
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'section' => ['sometimes', 'nullable', 'string'],
        ]);

        $from = ! empty($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : null;
        $to = ! empty($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : null;
        $section = $validated['section'] ?? null;

        return view('analytics.index', [
            'pipeline' => $this->analytics->pipelineNow(),
            'tat' => $this->analytics->tatReport($from, $to, $section),
            'sop' => $this->analytics->sopSummary($from, $to),
            'volume' => $this->analytics->volume($from, $to),
            'stageLabels' => config('tracking.stages.order'),
            'sections' => \App\Enums\LabSection::cases(),
            'filters' => ['from' => $validated['from'] ?? null, 'to' => $validated['to'] ?? null, 'section' => $section],
        ]);
    }
}
