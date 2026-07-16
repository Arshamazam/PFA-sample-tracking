<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SopViolationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\SopViolationResource;
use App\Models\SopViolation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AdminSopViolationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'type' => ['sometimes', Rule::in(SopViolationType::values())],
            'resolved' => ['sometimes', 'boolean'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = SopViolation::query()->latest('detected_at');

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if ($request->has('resolved')) {
            $request->boolean('resolved')
                ? $query->whereNotNull('resolved_at')
                : $query->whereNull('resolved_at');
        }

        if (isset($validated['from'])) {
            $query->where('detected_at', '>=', $request->date('from'));
        }

        if (isset($validated['to'])) {
            $query->where('detected_at', '<=', $request->date('to'));
        }

        return SopViolationResource::collection(
            $query->paginate($validated['per_page'] ?? 20)->withQueryString()
        );
    }

    /**
     * Mark a violation resolved (or re-open it) with notes.
     */
    public function update(Request $request, SopViolation $sopViolation): SopViolationResource
    {
        $validated = $request->validate([
            'resolved' => ['required', 'boolean'],
            'resolution_notes' => ['required_if:resolved,true', 'nullable', 'string', 'max:2000'],
        ]);

        $sopViolation->update([
            'resolved_at' => $validated['resolved'] ? now() : null,
            'resolved_by_id' => $validated['resolved'] ? $request->user()->id : null,
            'resolution_notes' => $validated['resolution_notes'] ?? null,
        ]);

        return new SopViolationResource($sopViolation->refresh());
    }
}
