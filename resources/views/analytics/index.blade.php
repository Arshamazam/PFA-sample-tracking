<x-layouts.app :title="__('panel.analytics')">
    @php
        $segLabels = [
            'collected_to_received' => 'Collected → Received',
            'received_to_testing' => 'Received → Testing',
            'testing_to_verdict' => 'Testing → Verdict',
            'verdict_to_report' => 'Verdict → Report',
        ];
        $maxWeek = max(array_merge([1], $volume['per_week']));
    @endphp

    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
        <div><label class="label">From</label><input type="date" name="from" value="{{ $filters['from'] }}" class="input"></div>
        <div><label class="label">To</label><input type="date" name="to" value="{{ $filters['to'] }}" class="input"></div>
        <div>
            <label class="label">Section</label>
            <select name="section" class="input">
                <option value="">All</option>
                @foreach ($sections as $s)<option value="{{ $s->value }}" @selected($filters['section']===$s->value)>{{ $s->label() }}</option>@endforeach
            </select>
        </div>
        <button class="btn-primary">Apply</button>
        <span class="text-xs text-gray-400">District filter — placeholder (districts not yet modelled)</span>
    </form>

    {{-- Volume + quality --}}
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="card p-4"><p class="text-xs text-gray-500">FIT</p><p class="text-2xl font-bold text-green-700">{{ $volume['fit'] }}</p><p class="text-xs text-gray-400">{{ $volume['fit_pct'] }}% of decided</p></div>
        <div class="card p-4"><p class="text-xs text-gray-500">UNFIT</p><p class="text-2xl font-bold text-red-700">{{ $volume['unfit'] }}</p></div>
        <div class="card p-4"><p class="text-xs text-gray-500">Retests</p><p class="text-2xl font-bold">{{ $volume['retests'] }}</p></div>
        <div class="card p-4 {{ $volume['overturn_rate'] >= 20 ? 'ring-2 ring-red-300' : '' }}">
            <p class="text-xs text-gray-500">Overturn rate (UNFIT→FIT)</p>
            <p class="text-2xl font-bold {{ $volume['overturn_rate'] >= 20 ? 'text-red-700' : 'text-gray-800' }}">{{ $volume['overturn_rate'] }}%</p>
            <p class="text-xs text-gray-400">{{ $volume['overturns'] }}/{{ $volume['retests'] }} — lab-quality signal</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Pipeline now --}}
        <div class="card p-5">
            <h3 class="mb-3 text-sm font-semibold text-gray-800">Pipeline now</h3>
            @php $maxCount = max(array_merge([1], array_map(fn ($s) => $s['count'], $pipeline))); @endphp
            <div class="space-y-2">
                @foreach ($pipeline as $stageKey => $data)
                    <div>
                        <div class="flex justify-between text-sm">
                            <span>{{ $stageLabels[$stageKey] ?? $stageKey }}</span>
                            <span class="font-medium">{{ $data['count'] }}</span>
                        </div>
                        <div class="mt-1 h-2 rounded bg-gray-100">
                            <div class="h-2 rounded bg-pfa-500" style="width: {{ round($data['count'] / $maxCount * 100) }}%"></div>
                        </div>
                        @if ($data['oldest_hours'] !== null && $data['oldest_hours'] >= 24)
                            <p class="mt-0.5 text-xs text-red-600">Oldest: {{ $data['oldest_code'] }} ({{ $data['oldest_hours'] }}h)</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- TAT segments --}}
        <div class="card p-5">
            <h3 class="mb-1 text-sm font-semibold text-gray-800">Turnaround (hours)</h3>
            <p class="mb-3 text-xs text-gray-400">Expected catalog TAT: {{ $tat['expected_hours'] ?? '—' }}h</p>
            <table class="min-w-full text-sm">
                <thead><tr><th class="th">Segment</th><th class="th">Avg</th><th class="th">Median</th><th class="th">Max</th><th class="th">n</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($tat['segments'] as $name => $s)
                        <tr>
                            <td class="td">{{ $segLabels[$name] ?? $name }}</td>
                            <td class="td">{{ $s['avg'] ?? '—' }}</td>
                            <td class="td">{{ $s['median'] ?? '—' }}</td>
                            <td class="td">{{ $s['max'] ?? '—' }}</td>
                            <td class="td text-gray-400">{{ $s['n'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if (! empty($tat['overdue']))
                <p class="mt-3 text-xs font-medium text-red-600">Overdue ({{ count($tat['overdue']) }}):</p>
                <ul class="text-xs text-gray-500">
                    @foreach (array_slice($tat['overdue'], 0, 5) as $o)<li>{{ $o['event_code'] }} — {{ $o['hours'] }}h</li>@endforeach
                </ul>
            @endif
        </div>

        {{-- SOP violations --}}
        <div class="card p-5">
            <h3 class="mb-3 text-sm font-semibold text-gray-800">SOP violations</h3>
            <p class="text-sm">Total: <strong>{{ $sop['total'] }}</strong> · Resolved: {{ $sop['resolved'] }} ({{ $sop['resolution_rate'] }}%)</p>
            <div class="mt-2 space-y-1">
                @forelse ($sop['by_type'] as $type => $count)
                    <div class="flex justify-between text-sm"><span>{{ ucwords(strtolower(str_replace('_',' ',$type))) }}</span><span class="font-medium">{{ $count }}</span></div>
                @empty
                    <p class="text-sm text-gray-400">No violations in range.</p>
                @endforelse
            </div>
        </div>

        {{-- Volume per week --}}
        <div class="card p-5">
            <h3 class="mb-3 text-sm font-semibold text-gray-800">Samples per week</h3>
            <div class="flex items-end gap-1" style="height: 120px">
                @forelse ($volume['per_week'] as $week => $count)
                    <div class="flex flex-1 flex-col items-center justify-end" title="{{ $week }}: {{ $count }}">
                        <div class="w-full rounded-t bg-pfa-500" style="height: {{ round($count / $maxWeek * 100) }}%"></div>
                        <span class="mt-1 truncate text-[9px] text-gray-400">{{ \Illuminate\Support\Str::afterLast($week, '-') }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No finalized samples in range.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.app>
