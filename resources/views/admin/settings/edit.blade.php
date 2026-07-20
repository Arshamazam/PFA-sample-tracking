<x-layouts.app :title="__('panel.settings')">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="card max-w-xl space-y-4 p-5">
        @csrf @method('PUT')
        <div>
            <label class="label">Dispute window (days)</label>
            <input type="number" name="dispute_window_days" min="1" max="365" value="{{ old('dispute_window_days', $settings['dispute_window_days'] ?? 7) }}" required class="input">
            <p class="mt-1 text-xs text-gray-400">Days an FBO has to dispute an UNFIT verdict.</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="label">Cold-chain min (°C)</label>
                <input type="number" step="0.1" name="cold_chain_min_c" value="{{ old('cold_chain_min_c', $settings['cold_chain_min_c'] ?? 0) }}" required class="input">
            </div>
            <div>
                <label class="label">Cold-chain max (°C)</label>
                <input type="number" step="0.1" name="cold_chain_max_c" value="{{ old('cold_chain_max_c', $settings['cold_chain_max_c'] ?? 8) }}" required class="input">
            </div>
        </div>
        <div>
            <label class="label">Same-day transfer deadline</label>
            <input type="time" name="same_day_transfer_deadline" value="{{ old('same_day_transfer_deadline', $settings['same_day_transfer_deadline'] ?? '20:00') }}" required class="input">
            <p class="mt-1 text-xs text-gray-400">Samples reaching registration later than this on the collection day are flagged.</p>
        </div>
        <div class="flex justify-end"><button class="btn-primary">{{ __('panel.save') }}</button></div>
    </form>
</x-layouts.app>
