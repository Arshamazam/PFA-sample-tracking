<x-layouts.app :title="'Sample '.$blind['blind_code']"
    :breadcrumbs="[__('panel.my_queue') => route('lab.queue'), $blind['blind_code'] => '#']">

    @php $canEnter = in_array($blind['status'], ['TESTING', 'RESULT_ENTERED'], true); @endphp

    <div class="max-w-3xl space-y-4">
        {{-- Blind sample header — no identity, ever --}}
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400">Blind sample</p>
                    <p class="font-mono text-2xl font-bold">{{ $blind['blind_code'] }}</p>
                </div>
                <x-status-badge :status="$blind['status']" />
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
                <div><dt class="text-gray-500">Food</dt><dd class="font-medium">{{ $blind['food_item'] }}</dd></div>
                <div><dt class="text-gray-500">Category</dt><dd class="font-medium">{{ $blind['food_category'] }}</dd></div>
                <div><dt class="text-gray-500">Section</dt><dd class="font-medium">{{ $blind['lab_section_label'] }}</dd></div>
                <div><dt class="text-gray-500">Perishable</dt><dd class="font-medium">{{ $blind['is_perishable'] ? 'Yes' : 'No' }}</dd></div>
                <div><dt class="text-gray-500">Received</dt><dd class="font-medium">{{ $blind['received_at'] ? \Illuminate\Support\Carbon::parse($blind['received_at'])->format('d M, H:i') : '—' }}</dd></div>
            </dl>
        </div>

        @if (! $canEnter)
            <form method="POST" action="{{ route('lab.start', $blind['blind_code']) }}" class="card p-5">
                @csrf
                <p class="mb-4 text-sm text-gray-600">Claim this sample and begin testing.</p>
                <button class="btn-primary">Start testing</button>
            </form>
        @else
            <form method="POST" action="{{ route('lab.results', $blind['blind_code']) }}" enctype="multipart/form-data"
                  x-data="labForm(@js($blind['parameters_template'] ?? []), @js($blind['parameters'] ?? []))"
                  class="card space-y-4 p-5">
                @csrf
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-800">Analytical parameters</h3>
                    <button type="button" @click="addRow()" class="btn-secondary !py-1.5 !px-3 text-xs">+ Add parameter</button>
                </div>

                <div class="space-y-2">
                    <template x-for="(row, i) in rows" :key="i">
                        <div class="grid grid-cols-12 items-center gap-2 rounded border border-gray-100 p-2">
                            <input class="input col-span-3 text-sm" placeholder="Parameter" :name="`parameters[${i}][name]`" x-model="row.name">
                            <input class="input col-span-2 text-sm" placeholder="Value" :name="`parameters[${i}][value]`" x-model="row.value" @input="autoLimit(row)">
                            <input class="input col-span-2 text-sm" placeholder="Unit" :name="`parameters[${i}][unit]`" x-model="row.unit">
                            <input class="input col-span-2 text-sm" placeholder="Limit" :name="`parameters[${i}][permissible_limit]`" x-model="row.permissible_limit" @input="autoLimit(row)">
                            <label class="col-span-2 flex items-center gap-1 text-xs">
                                <input type="hidden" :name="`parameters[${i}][within_limit]`" :value="row.within_limit ? 1 : 0">
                                <input type="checkbox" x-model="row.within_limit" class="rounded border-gray-300 text-pfa-500">
                                <span :class="row.within_limit ? 'text-green-700' : 'text-red-700'" x-text="row.within_limit ? 'within' : 'out'"></span>
                            </label>
                            <label class="col-span-1 flex items-center gap-1 text-[11px] text-gray-500">
                                <input type="hidden" :name="`parameters[${i}][is_additional]`" :value="row.is_additional ? 1 : 0">
                                <input type="checkbox" x-model="row.is_additional" class="rounded border-gray-300 text-amber-500">+
                            </label>
                            <button type="button" @click="removeRow(i)" class="col-span-12 text-right text-xs text-red-500 hover:underline">remove</button>
                        </div>
                    </template>
                </div>

                <div>
                    <label class="label">Bench report photo <span class="text-red-500">*</span></label>
                    <input type="file" name="report_photo" accept="image/*" capture="environment" required class="input">
                </div>

                <div class="flex items-center justify-between">
                    <p class="text-xs text-gray-400">"+" marks a parameter outside the catalog template.</p>
                    <button type="submit" class="btn-primary">Submit results</button>
                </div>
            </form>
        @endif
    </div>

    <script>
        function labForm(template, existing) {
            const seed = (existing && existing.length ? existing : (template || [])).map(p => ({
                name: p.name ?? '',
                value: p.value ?? '',
                unit: p.unit ?? '',
                permissible_limit: p.permissible_limit ?? '',
                within_limit: p.within_limit ?? true,
                is_additional: p.is_additional ?? false,
            }));
            return {
                rows: seed.length ? seed : [{ name: '', value: '', unit: '', permissible_limit: '', within_limit: true, is_additional: false }],
                addRow() { this.rows.push({ name: '', value: '', unit: '', permissible_limit: '', within_limit: true, is_additional: true }); },
                removeRow(i) { this.rows.splice(i, 1); },
                // Best-effort client-side within-limit; the analyst can override, and the server records the final value.
                autoLimit(row) {
                    const limit = (row.permissible_limit || '').toLowerCase().trim();
                    const v = parseFloat(row.value);
                    if (!limit) return;
                    let m;
                    if ((m = limit.match(/max\s*([\d.]+)/))) row.within_limit = !isNaN(v) && v <= parseFloat(m[1]);
                    else if ((m = limit.match(/min\s*([\d.]+)/))) row.within_limit = !isNaN(v) && v >= parseFloat(m[1]);
                    else if ((m = limit.match(/([\d.]+)\s*-\s*([\d.]+)/))) row.within_limit = !isNaN(v) && v >= parseFloat(m[1]) && v <= parseFloat(m[2]);
                    else if (['absent', 'nil', 'none', 'negative', '0'].includes(limit)) {
                        const val = (row.value || '').toLowerCase().trim();
                        row.within_limit = ['absent', 'nil', 'none', 'negative', '0', ''].includes(val) || v === 0;
                    }
                },
            };
        }
    </script>
</x-layouts.app>
