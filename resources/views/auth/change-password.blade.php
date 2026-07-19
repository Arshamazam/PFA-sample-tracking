<x-layouts.guest>
    <h2 class="mb-1 text-center text-base font-semibold text-gray-800">{{ __('panel.change_password') }}</h2>
    <p class="mb-4 text-center text-xs text-gray-500">
        You are using an interim password and must set a new one before continuing.
    </p>

    @if ($errors->any())
        <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <div>
            <label for="current_password" class="label">{{ __('panel.current_password') }}</label>
            <input id="current_password" name="current_password" type="password" required class="input">
        </div>
        <div>
            <label for="password" class="label">{{ __('panel.new_password') }}</label>
            <input id="password" name="password" type="password" required class="input">
        </div>
        <div>
            <label for="password_confirmation" class="label">{{ __('panel.confirm_password') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required class="input">
        </div>
        <div class="flex items-center justify-between">
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-sm text-gray-500 hover:underline">{{ __('panel.sign_out') }}</button></form>
            <button type="submit" class="btn-primary">{{ __('panel.update_password') }}</button>
        </div>
    </form>
</x-layouts.guest>
