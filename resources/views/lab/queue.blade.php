<x-layouts.app :title="__('panel.my_queue')">
    <form method="GET" class="mb-4 flex items-center gap-2">
        <label class="text-sm text-gray-600">Section</label>
        <select name="section" onchange="this.form.submit()" class="input max-w-xs">
            <option value="">All my sections</option>
            @foreach ($sections as $s)
                <option value="{{ $s->value }}" @selected($section === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
    </form>

    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="th">Blind code</th>
                    <th class="th">Food</th>
                    <th class="th">Section</th>
                    <th class="th">Status</th>
                    <th class="th">Aging</th>
                    <th class="th text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    @php
                        $assigned = $row['assigned_at'] ? \Illuminate\Support\Carbon::parse($row['assigned_at']) : null;
                        $ageDays = $assigned ? intval($assigned->diffInDays(now())) : null;
                    @endphp
                    <tr>
                        <td class="td font-mono font-medium">{{ $row['blind_code'] }}</td>
                        <td class="td">{{ $row['food_item'] }} <span class="text-gray-400">({{ $row['food_category'] }})</span></td>
                        <td class="td">{{ $row['lab_section_label'] }}</td>
                        <td class="td"><x-status-badge :status="$row['status']" /></td>
                        <td class="td">
                            @if (! is_null($ageDays))
                                <span class="rounded px-2 py-0.5 text-xs {{ $ageDays >= 3 ? 'bg-red-50 text-red-700' : ($ageDays >= 1 ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600') }}">
                                    {{ $ageDays }}d
                                </span>
                            @else — @endif
                        </td>
                        <td class="td text-right">
                            <a href="{{ route('lab.show', $row['blind_code']) }}" class="btn-secondary !py-1.5 !px-3 text-xs">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="td text-center text-gray-400">Your queue is empty.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $paginator->links() }}</div>
</x-layouts.app>
