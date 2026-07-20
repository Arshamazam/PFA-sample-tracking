@props(['title' => null, 'breadcrumbs' => []])

@php
    use App\Enums\UserRole;
    $user = auth()->user();
    $role = $user->role;

    // Nav items per role: [label, route-name, active-prefix, icon-letter].
    $nav = match ($role) {
        UserRole::REGISTRATION_OFFICER => [
            [__('panel.receiving'), 'registration.receiving.create', 'registration.receiving'],
            [__('panel.blind_coding'), 'registration.blind.create', 'registration.blind'],
            [__('panel.section_assignment'), 'registration.section.create', 'registration.section'],
            [__('panel.retention'), 'registration.retention.index', 'registration.retention'],
            [__('panel.file_dispute'), 'registration.disputes.create', 'registration.disputes'],
        ],
        UserRole::LAB_ANALYST => [
            [__('panel.my_queue'), 'lab.queue', 'lab'],
        ],
        UserRole::VERIFYING_OFFICER => [
            [__('panel.verification'), 'verification.queue', 'verification'],
            [__('panel.disputes'), 'disputes.index', 'disputes'],
        ],
        UserRole::ADMIN => [
            [__('panel.users'), 'admin.users.index', 'admin.users'],
            [__('panel.test_catalog'), 'admin.catalog.index', 'admin.catalog'],
            [__('panel.sop_violations'), 'admin.violations.index', 'admin.violations'],
            [__('panel.settings'), 'admin.settings.edit', 'admin.settings'],
            [__('panel.events'), 'admin.events.index', 'admin.events'],
        ],
        UserRole::FSO, UserRole::TRANSPORT => [
            [__('panel.my_events'), 'fso.events.index', 'fso.events'],
            [__('panel.new_sample'), 'fso.events.create', 'fso.events.create'],
            [__('panel.rapid_test'), 'fso.rapid.create', 'fso.rapid'],
            [__('panel.custody_scan'), 'fso.scan.create', 'fso.scan'],
        ],
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' — ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 text-gray-800 antialiased" x-data="{ sidebar: false }">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside class="fixed inset-y-0 left-0 z-30 w-60 -translate-x-full transform bg-pfa-800 text-white transition lg:static lg:translate-x-0"
               :class="sidebar && 'translate-x-0'">
            <div class="flex h-16 items-center gap-2 px-5 border-b border-white/10">
                <div class="flex h-9 w-9 items-center justify-center rounded bg-white/15 text-sm font-bold">PFA</div>
                <div class="leading-tight">
                    <div class="text-sm font-semibold">Sample Tracking</div>
                    <div class="text-[11px] text-pfa-100/80">{{ $role->label() }}</div>
                </div>
            </div>
            <nav class="mt-4 space-y-1 px-3">
                @foreach ($nav as [$label, $routeName, $prefix])
                    <a href="{{ route($routeName) }}"
                       class="block rounded-md px-3 py-2 text-sm font-medium transition {{ request()->routeIs($prefix.'*') ? 'bg-white/15 text-white' : 'text-pfa-100/80 hover:bg-white/10 hover:text-white' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </aside>

        {{-- Backdrop for mobile --}}
        <div x-show="sidebar" x-cloak @click="sidebar = false" class="fixed inset-0 z-20 bg-black/40 lg:hidden"></div>

        {{-- Main --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 lg:px-6 no-print">
                <div class="flex items-center gap-3">
                    <button @click="sidebar = !sidebar" class="rounded p-2 text-gray-500 hover:bg-gray-100 lg:hidden">☰</button>
                    <div>
                        <h1 class="text-base font-semibold text-gray-900">{{ $title }}</h1>
                        @if (! empty($breadcrumbs))
                            <nav class="text-xs text-gray-400">
                                @foreach ($breadcrumbs as $label => $url)
                                    @if (! $loop->last)
                                        <a href="{{ $url }}" class="hover:underline">{{ $label }}</a> <span>/</span>
                                    @else
                                        <span class="text-gray-600">{{ is_string($label) ? $label : $url }}</span>
                                    @endif
                                @endforeach
                            </nav>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    @unless (app()->environment('production'))
                        <span class="rounded bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800">{{ strtoupper(app()->environment()) }}</span>
                    @endunless
                    <div class="text-right leading-tight">
                        <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                        <div class="text-[11px] text-gray-500">{{ $role->label() }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn-secondary !px-3 !py-1.5 text-xs">{{ __('panel.sign_out') }}</button>
                    </form>
                </div>
            </header>

            <main class="flex-1 p-4 lg:p-6">
                @if (session('status'))
                    <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-200">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-800 ring-1 ring-red-200">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-800 ring-1 ring-red-200">
                        <ul class="list-inside list-disc space-y-0.5">
                            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
