<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\LabSection;
use App\Http\Controllers\Controller;
use App\Models\TestCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TestCatalogController extends Controller
{
    public function index(): View
    {
        $entries = TestCatalog::orderBy('food_category')->orderBy('test_name')->paginate(20);

        return view('admin.catalog.index', compact('entries'));
    }

    public function create(): View
    {
        return view('admin.catalog.create', ['sections' => LabSection::cases(), 'entry' => new TestCatalog()]);
    }

    public function store(Request $request): RedirectResponse
    {
        TestCatalog::create($this->validated($request));

        return redirect()->route('admin.catalog.index')->with('status', 'Test catalog entry created.');
    }

    public function edit(TestCatalog $testCatalog): View
    {
        return view('admin.catalog.edit', ['entry' => $testCatalog, 'sections' => LabSection::cases()]);
    }

    public function update(Request $request, TestCatalog $testCatalog): RedirectResponse
    {
        $testCatalog->update($this->validated($request));

        return redirect()->route('admin.catalog.index')->with('status', 'Test catalog entry updated.');
    }

    public function destroy(TestCatalog $testCatalog): RedirectResponse
    {
        $testCatalog->delete();

        return redirect()->route('admin.catalog.index')->with('status', 'Test catalog entry deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'food_category' => ['required', 'string', 'max:255'],
            'lab_section' => ['required', Rule::in(LabSection::values())],
            'test_name' => ['required', 'string', 'max:255'],
            'tat_hours' => ['required', 'integer', 'min:1'],
            'parameters' => ['required', 'array', 'min:1'],
            'parameters.*.name' => ['required', 'string', 'max:255'],
            'parameters.*.unit' => ['nullable', 'string', 'max:64'],
            'parameters.*.permissible_limit' => ['nullable', 'string', 'max:64'],
        ]);

        // Drop blank parameter rows the dynamic editor may submit.
        $data['parameters'] = collect($data['parameters'])
            ->filter(fn ($p) => filled($p['name'] ?? null))
            ->values()
            ->all();

        return $data;
    }
}
