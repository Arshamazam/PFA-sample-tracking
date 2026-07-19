<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center px-4">
        <div class="mb-6 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-pfa-500 text-white text-xl font-bold">PFA</div>
            <h1 class="mt-3 text-lg font-semibold text-gray-800">{{ config('pfa.report.authority_name') }}</h1>
            <p class="text-sm text-gray-500">Sample Testing &amp; Tracking</p>
        </div>

        <div class="w-full max-w-sm card p-6">
            {{ $slot }}
        </div>

        @unless (app()->environment('production'))
            <p class="mt-4 text-xs text-gray-400">Environment: {{ app()->environment() }}</p>
        @endunless
    </div>
</body>
</html>
