<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        $settings = Setting::pluck('value', 'key');

        return view('admin.settings.edit', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'dispute_window_days' => ['required', 'integer', 'min:1', 'max:365'],
            'cold_chain_min_c' => ['required', 'numeric', 'between:-50,50'],
            'cold_chain_max_c' => ['required', 'numeric', 'between:-50,50', 'gte:cold_chain_min_c'],
            'same_day_transfer_deadline' => ['required', 'date_format:H:i'],
        ]);

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        return redirect()->route('admin.settings.edit')->with('status', 'Settings saved.');
    }
}
