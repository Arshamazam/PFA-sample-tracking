<x-layouts.guest>
    <h2 class="mb-4 text-center text-base font-semibold text-gray-800">{{ __('panel.sign_in') }}</h2>

    @if ($errors->any())
        <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="label">{{ __('panel.email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="input">
        </div>
        <div>
            <label for="password" class="label">{{ __('panel.password') }}</label>
            <input id="password" name="password" type="password" required class="input">
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" name="remember" class="rounded border-gray-300 text-pfa-500 focus:ring-pfa-500">
            {{ __('panel.remember_me') }}
        </label>
        <button type="submit" class="btn-primary w-full">{{ __('panel.sign_in') }}</button>
    </form>
</x-layouts.guest>
