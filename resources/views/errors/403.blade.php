<x-layouts.guest>
    <div class="text-center">
        <p class="text-3xl font-bold text-pfa-500">403</p>
        <h2 class="mt-2 text-base font-semibold text-gray-800">Not permitted</h2>
        <p class="mt-1 text-sm text-gray-500">
            {{ $exception?->getMessage() ?: 'Your role does not have access to this screen.' }}
        </p>
        @auth
            <p class="mt-4 text-sm text-gray-600">
                Signed in as <span class="font-medium">{{ auth()->user()->name }}</span>
                ({{ auth()->user()->role->label() }}).
            </p>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button class="btn-secondary">Switch account</button>
            </form>
        @endauth
    </div>
</x-layouts.guest>
