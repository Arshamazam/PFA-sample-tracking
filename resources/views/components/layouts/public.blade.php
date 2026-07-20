@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' — ' : '' }}Track — {{ config('pfa.report.authority_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-800 antialiased">
    <header class="bg-pfa-500 text-white">
        <div class="mx-auto flex max-w-2xl items-center justify-between px-4 py-3">
            <a href="{{ route('track.landing') }}" class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded bg-white/20 text-sm font-bold">PFA</span>
                <span class="text-sm font-semibold leading-tight">{{ config('pfa.report.authority_name') }}<br><span class="text-[11px] font-normal text-pfa-100/90">Sample Tracking</span></span>
            </a>
            {{-- Urdu toggle stub — language switching wired later via lang/ files. --}}
            <div class="flex overflow-hidden rounded border border-white/30 text-xs">
                <span class="bg-white/20 px-2 py-1">EN</span>
                <span class="px-2 py-1 text-white/60" title="Coming soon">اردو</span>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-2xl px-4 py-6">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-2xl px-4 pb-8 pt-4 text-center text-xs text-gray-400">
        {{ config('pfa.report.authority_name') }} · {{ config('pfa.report.authority_subtitle') }}
    </footer>
</body>
</html>
