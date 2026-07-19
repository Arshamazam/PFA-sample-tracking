<x-layouts.app :title="__('panel.file_dispute')">
    <div class="max-w-2xl space-y-4">
        <p class="rounded-md bg-blue-50 p-3 text-sm text-blue-800 ring-1 ring-blue-200">
            File a resampling application on behalf of a walk-in FBO. Only events with an
            <strong>UNFIT</strong> report, still inside the dispute window, can be disputed.
        </p>

        <form method="POST" action="{{ route('registration.disputes.store') }}" class="card space-y-4 p-5">
            @csrf
            <div>
                <label class="label">Event code</label>
                <input name="event_code" value="{{ old('event_code') }}" required class="input font-mono" placeholder="PFA-LHR-2026-000123">
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div><label class="label">Filed by (name)</label><input name="filed_by_name" value="{{ old('filed_by_name') }}" required class="input"></div>
                <div><label class="label">Phone</label><input name="filed_by_phone" value="{{ old('filed_by_phone') }}" required class="input"></div>
            </div>
            <div><label class="label">CNIC (optional)</label><input name="filed_by_cnic" value="{{ old('filed_by_cnic') }}" class="input"></div>
            <div><label class="label">Reason (optional)</label><textarea name="reason" rows="3" class="input">{{ old('reason') }}</textarea></div>
            <div class="flex justify-end">
                <button type="submit" class="btn-primary">File dispute</button>
            </div>
        </form>
    </div>
</x-layouts.app>
