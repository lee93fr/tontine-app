<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('app.auth.login_btn') }} — TontineApp</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-indigo-50 to-purple-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full">
        <div class="text-center mb-8">
            <img src="{{ asset('favicon.svg') }}" alt="TontineApp" class="w-16 h-16 mx-auto mb-3 rounded-lg shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">TontineApp</h1>
            <p class="text-gray-500 mt-1 text-sm">{{ __('app.auth.login_subtitle') }}</p>
        </div>

        {{-- Sélecteur de langue --}}
        <div class="flex justify-center gap-2 mb-6">
            <a href="{{ route('lang.switch', 'fr') }}"
               class="text-xs px-3 py-1 rounded-full border font-medium transition
                   {{ app()->getLocale() === 'fr' ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-500 border-gray-300 hover:border-indigo-400' }}">
                FR
            </a>
            <a href="{{ route('lang.switch', 'en') }}"
               class="text-xs px-3 py-1 rounded-full border font-medium transition
                   {{ app()->getLocale() === 'en' ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-500 border-gray-300 hover:border-indigo-400' }}">
                EN
            </a>
        </div>

        @if(session('status'))
            <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('status') }}</div>
        @endif
        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Email ou numéro de téléphone
                </label>
                <input type="text" name="identifier" value="{{ old('identifier', old('email')) }}" required autofocus
                       autocomplete="username"
                       placeholder="vous@exemple.com ou 06 12 34 56 78"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none text-sm">
                @error('identifier')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.auth.password') }}</label>
                <input type="password" name="password" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none text-sm">
                @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="remember" class="rounded border-gray-300">
                    {{ __('app.auth.remember_me') }}
                </label>
                @if(Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm text-indigo-600 hover:text-indigo-800">{{ __('app.auth.forgot_password') }}</a>
                @endif
            </div>
            <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-lg font-medium transition">
                {{ __('app.auth.login_btn') }}
            </button>
        </form>
    </div>
</body>
</html>
