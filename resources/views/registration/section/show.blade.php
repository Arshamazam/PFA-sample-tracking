<x-layouts.app :title="__('panel.section_assignment')"
    :breadcrumbs="[__('panel.section_assignment') => route('registration.section.create'), 'Assign' => '#']">
    @php $suggested = $suggestion['suggested']?->lab_section?->value; @endphp
    <div class="max-w-2xl space-y-4">
        <x-registration.part-summary :part="$part" />

        @if ($part->status->value !== 'BLIND_CODED')
            <div class="rounded-md bg-amber-50 p-3 text-sm text-amber-800 ring-1 ring-amber-200">
                This sample is at <strong>{{ $part->status->label() }}</strong>; section assignment follows blind coding.
            </div>
        @else
            <form method="POST" action="{{ route('registration.section.store') }}" class="card space-y-4 p-5">
                @csrf
                <input type="hidden" name="qr_token" value="{{ $part->qr_token }}">

                @if ($suggested)
                    <p class="rounded bg-pfa-50 px-3 py-2 text-sm text-pfa-700">
                        Suggested: <strong>{{ $suggestion['suggested']->lab_section->label() }}</strong>
                        — {{ $suggestion['suggested']->test_name }}
                    </p>
                @endif

                <div>
                    <label class="label">Lab section</label>
                    <select name="lab_section" class="input">
                        @foreach ($sections as $section)
                            <option value="{{ $section->value }}" @selected($section->value === $suggested)>{{ $section->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary">Assign to section</button>
                </div>
            </form>
        @endif
    </div>
</x-layouts.app>
