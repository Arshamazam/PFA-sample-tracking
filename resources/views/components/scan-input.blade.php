@props([
    'base',            // base URL; on submit we navigate to `${base}/${token}`
    'placeholder' => null,
    'value' => null,
])

{{-- Hardware QR scanners type the value then press Enter, so an autofocused input
     doubles as the scan target. On submit we navigate to the detail path; manual
     typing is the fallback. --}}
<form x-data="{ token: @js($value ?? '') }"
      @submit.prevent="token.trim() && (window.location = '{{ rtrim($base, '/') }}/' + encodeURIComponent(token.trim()))"
      x-init="$refs.scan.focus()" class="flex gap-2">
    <input x-ref="scan" x-model="token" required autocomplete="off"
           placeholder="{{ $placeholder ?? __('panel.scan_placeholder') }}"
           class="input font-mono flex-1">
    <button type="submit" class="btn-primary">{{ __('panel.search') }}</button>
</form>
