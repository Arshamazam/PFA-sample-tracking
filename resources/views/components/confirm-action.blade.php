@props([
    'action',
    'method' => 'POST',
    'trigger' => 'Confirm',
    'title' => 'Please confirm',
    'message' => 'This action cannot be undone.',
    'confirm' => 'Confirm',
    'triggerClass' => 'btn-danger',
    'enctype' => null,
])

{{-- A confirmation modal that restates the SOP consequence before submitting.
     Extra form fields (e.g. notes, photo) go in the default slot. --}}
<div x-data="{ open: false }" class="inline-block">
    <button type="button" @click="open = true" class="{{ $triggerClass }}">{{ $trigger }}</button>

    <template x-teleport="body">
        <div x-show="open" x-cloak @keydown.escape.window="open = false"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-md rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ $message }}</p>

                <form method="POST" action="{{ $action }}" @if ($enctype) enctype="{{ $enctype }}" @endif class="mt-4 space-y-3">
                    @csrf
                    @if (! in_array(strtoupper($method), ['GET', 'POST'])) @method($method) @endif
                    {{ $slot }}
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="open = false" class="btn-secondary">{{ __('panel.cancel') }}</button>
                        <button type="submit" class="{{ $triggerClass }}">{{ $confirm }}</button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
