<form method="POST" action="{{ $action }}" class="card max-w-2xl space-y-4 p-5"
      x-data="catalogForm(@js(old('parameters', $entry->parameters ?? [])))">
    @csrf
    @if (($method ?? 'POST') === 'PUT') @method('PUT') @endif

    <div class="grid grid-cols-2 gap-4">
        <div><label class="label">Food category</label><input name="food_category" value="{{ old('food_category', $entry->food_category) }}" required class="input" placeholder="MILK"></div>
        <div>
            <label class="label">Lab section</label>
            <select name="lab_section" class="input">
                @foreach ($sections as $s)<option value="{{ $s->value }}" @selected(old('lab_section', $entry->lab_section?->value)===$s->value)>{{ $s->label() }}</option>@endforeach
            </select>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div><label class="label">Test name</label><input name="test_name" value="{{ old('test_name', $entry->test_name) }}" required class="input"></div>
        <div><label class="label">Turnaround (hours)</label><input type="number" name="tat_hours" value="{{ old('tat_hours', $entry->tat_hours ?? 48) }}" required min="1" class="input"></div>
    </div>

    <div>
        <div class="mb-2 flex items-center justify-between">
            <label class="label mb-0">Parameters template</label>
            <button type="button" @click="add()" class="btn-secondary !py-1 !px-2 text-xs">+ Add</button>
        </div>
        <div class="space-y-2">
            <template x-for="(p, i) in params" :key="i">
                <div class="grid grid-cols-12 gap-2">
                    <input class="input col-span-5 text-sm" placeholder="Name" :name="`parameters[${i}][name]`" x-model="p.name">
                    <input class="input col-span-3 text-sm" placeholder="Unit" :name="`parameters[${i}][unit]`" x-model="p.unit">
                    <input class="input col-span-3 text-sm" placeholder="Limit" :name="`parameters[${i}][permissible_limit]`" x-model="p.permissible_limit">
                    <button type="button" @click="remove(i)" class="col-span-1 text-red-500">✕</button>
                </div>
            </template>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ route('admin.catalog.index') }}" class="btn-secondary">{{ __('panel.cancel') }}</a>
        <button class="btn-primary">{{ __('panel.save') }}</button>
    </div>
</form>

<script>
    function catalogForm(existing) {
        return {
            params: (existing && existing.length ? existing : [{ name: '', unit: '', permissible_limit: '' }])
                .map(p => ({ name: p.name ?? '', unit: p.unit ?? '', permissible_limit: p.permissible_limit ?? '' })),
            add() { this.params.push({ name: '', unit: '', permissible_limit: '' }); },
            remove(i) { this.params.splice(i, 1); },
        };
    }
</script>
