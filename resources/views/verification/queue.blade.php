<x-layouts.app :title="__('panel.verification')">
    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="th">Blind code</th>
                    <th class="th">Business</th>
                    <th class="th">Food</th>
                    <th class="th">Analyst</th>
                    <th class="th">Flags</th>
                    <th class="th text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($parts as $part)
                    <tr>
                        <td class="td font-mono font-medium">{{ $part->blind_code }}</td>
                        <td class="td">{{ $part->samplingEvent->premises->name }}<div class="text-xs text-gray-400">{{ $part->samplingEvent->premises->license_no }}</div></td>
                        <td class="td">{{ $part->samplingEvent->food_item }}</td>
                        <td class="td">{{ $part->labResult?->analyst?->name ?? '—' }}</td>
                        <td class="td">
                            @if ($part->sopViolations->count())
                                <span class="rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-700">{{ $part->sopViolations->count() }} SOP</span>
                            @endif
                        </td>
                        <td class="td text-right"><a href="{{ route('verification.show', $part->blind_code) }}" class="btn-secondary !py-1.5 !px-3 text-xs">Review</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="td text-center text-gray-400">Nothing awaiting verification.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $parts->links() }}</div>
</x-layouts.app>
