<x-layouts.app :title="__('panel.retention')">
    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="th">Event</th>
                    <th class="th">Storage</th>
                    <th class="th">Days held</th>
                    <th class="th">Eligibility</th>
                    <th class="th text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($parts as $part)
                    @php
                        $retained = $part->custodyEvents->firstWhere(fn ($e) => $e->status->value === 'IN_RETENTION');
                        $eligible = $part->destruction_eligible_at && ! $part->destruction_eligible_at->isFuture();
                    @endphp
                    <tr>
                        <td class="td font-mono text-xs">{{ $part->samplingEvent->event_code }}</td>
                        <td class="td">{{ $retained?->location_note ?? '—' }}</td>
                        <td class="td">{{ $retained?->created_at ? intval($retained->created_at->diffInDays(now())) : '—' }}</td>
                        <td class="td">
                            @if ($eligible)
                                <span class="rounded bg-red-50 px-2 py-0.5 text-xs text-red-700">Eligible for destruction</span>
                            @elseif ($part->destruction_eligible_at)
                                <span class="rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-700">Eligible {{ $part->destruction_eligible_at->format('d M Y') }}</span>
                            @else
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Retained</span>
                            @endif
                        </td>
                        <td class="td text-right">
                            @if ($eligible)
                                <x-confirm-action
                                    :action="route('registration.retention.destroy')"
                                    enctype="multipart/form-data"
                                    trigger="Destroy" confirm="Confirm destruction"
                                    title="Destroy reference sample"
                                    message="This will mark the reference part DESTROYED. This is a permanent, legally-recorded action and cannot be undone.">
                                    <input type="hidden" name="qr_token" value="{{ $part->qr_token }}">
                                    <div>
                                        <label class="label">Destruction photo <span class="text-red-500">*</span></label>
                                        <input type="file" name="photo" accept="image/*" capture="environment" required class="input">
                                    </div>
                                    <div>
                                        <label class="label">Notes <span class="text-red-500">*</span></label>
                                        <textarea name="notes" rows="2" required class="input" placeholder="e.g. Incinerated per SOP, batch 12."></textarea>
                                    </div>
                                </x-confirm-action>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="td text-center text-gray-400">No parts in retention.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $parts->links() }}</div>
</x-layouts.app>
