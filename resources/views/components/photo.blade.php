@props(['path' => null, 'label' => 'Photo', 'size' => 'h-20 w-20'])

@php $url = $path ? route('files.show', ['path' => $path]) : null; @endphp

@if ($url)
    <div x-data="{ open: false }" class="inline-block">
        <button type="button" @click="open = true" class="block">
            <img src="{{ $url }}" alt="{{ $label }}" class="{{ $size }} rounded-md object-cover ring-1 ring-gray-200 hover:opacity-90">
        </button>
        <template x-teleport="body">
            <div x-show="open" x-cloak @click="open = false" @keydown.escape.window="open = false"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-6">
                <figure @click.stop class="max-h-full max-w-3xl">
                    <img src="{{ $url }}" alt="{{ $label }}" class="max-h-[80vh] rounded-lg">
                    <figcaption class="mt-2 text-center text-sm text-white/80">{{ $label }}
                        <button @click="open = false" class="ml-3 rounded bg-white/20 px-2 py-0.5 text-xs">Close</button>
                    </figcaption>
                </figure>
            </div>
        </template>
    </div>
@else
    <span class="inline-flex {{ $size }} items-center justify-center rounded-md bg-gray-50 text-[10px] text-gray-400 ring-1 ring-gray-200">no photo</span>
@endif
