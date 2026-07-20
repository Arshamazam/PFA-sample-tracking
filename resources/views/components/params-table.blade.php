@props(['parameters' => []])

<div class="overflow-hidden rounded-md ring-1 ring-gray-200">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="th">Parameter</th>
                <th class="th">Result</th>
                <th class="th">Unit</th>
                <th class="th">Limit</th>
                <th class="th">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($parameters ?? [] as $p)
                <tr class="{{ ($p['within_limit'] ?? true) ? '' : 'bg-red-50' }}">
                    <td class="td">
                        {{ $p['name'] ?? '—' }}
                        @if (! empty($p['is_additional']))<span class="ml-1 text-[11px] italic text-amber-600">(additional)</span>@endif
                    </td>
                    <td class="td font-medium">{{ $p['value'] ?? '—' }}</td>
                    <td class="td text-gray-500">{{ $p['unit'] ?? '—' }}</td>
                    <td class="td text-gray-500">{{ $p['permissible_limit'] ?? '—' }}</td>
                    <td class="td">
                        @if ($p['within_limit'] ?? true)
                            <span class="font-medium text-green-700">Within limit</span>
                        @else
                            <span class="font-medium text-red-700">Out of limit</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="td text-center text-gray-400">No parameters recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
