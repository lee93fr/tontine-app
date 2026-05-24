<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('app.invitation.title') }} — TontineApp</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-indigo-50 to-purple-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full">

        <div class="text-center mb-8">
            <img src="{{ asset('favicon.svg') }}" alt="TontineApp" class="w-16 h-16 mx-auto mb-3 rounded-lg shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">{{ __('app.invitation.title') }}</h1>
            @if($invitation->tontine)
            <p class="text-gray-500 mt-2 text-sm">
                {{ __('app.invitation.invited_to') }}<br>
                <strong class="text-gray-800">{{ $invitation->tontine->name }}</strong>
            </p>
            @else
            <p class="text-gray-500 mt-2 text-sm">Créez votre compte pour rejoindre la plateforme.</p>
            @endif
        </div>

        <div class="flex justify-center gap-2 mb-6">
            <a href="{{ route('lang.switch', 'fr') }}"
               class="text-xs px-3 py-1 rounded-full border font-medium transition
                   {{ app()->getLocale() === 'fr' ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-500 border-gray-300 hover:border-indigo-400' }}">FR</a>
            <a href="{{ route('lang.switch', 'en') }}"
               class="text-xs px-3 py-1 rounded-full border font-medium transition
                   {{ app()->getLocale() === 'en' ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-500 border-gray-300 hover:border-indigo-400' }}">EN</a>
        </div>

        @if($invitation->tontine)
        <div class="bg-indigo-50 rounded-xl p-4 mb-6 text-sm text-indigo-700 space-y-1">
            <div>💰 {{ __('app.tontine.cotisation') }} : <strong>{{ number_format($invitation->tontine->cotisation_amount, 2) }} {{ __('app.common.months') }}</strong></div>
            <div>👥 {{ __('app.tontine.max_participants') }} : <strong>{{ $invitation->tontine->max_participants }}</strong></div>
            <div>📊 {{ __('app.tontine.bid_cap') }} : <strong>{{ $invitation->tontine->bid_cap }}%</strong></div>
        </div>
        @endif

        @foreach (['success' => 'green', 'error' => 'red', 'info' => 'blue'] as $msgType => $msgColor)
            @if(session($msgType))
            <div class="bg-{{ $msgColor }}-50 border border-{{ $msgColor }}-200 rounded-lg p-4 mb-4 text-sm text-{{ $msgColor }}-700">
                {{ session($msgType) }}
            </div>
            @endif
        @endforeach

        @if ($userExists)
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4 text-sm text-blue-700">
                {{ __('app.invitation.user_exists') }}
            </div>
        @endif

        <form method="POST" action="{{ route('invitation.accept', $invitation->token) }}" class="space-y-4">
            @csrf

            @if ($preCreated)
                {{-- Compte pré-créé : identité en lecture seule, seulement le mot de passe à choisir --}}
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-700">
                    Votre compte a été pré-créé. Choisissez un mot de passe pour l'activer.
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom complet</label>
                    <input type="text" value="{{ $existingUser->full_name }}" disabled
                           class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-500 cursor-not-allowed">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.auth.email') }}</label>
                    <input type="email" value="{{ $invitation->email }}" disabled
                           class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-400 cursor-not-allowed">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.auth.password') }} <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required autofocus
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none @error('password') border-red-400 @enderror">
                    @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.profile.confirm_password') }} <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>

            @elseif (!auth()->check() && !$userExists)
                {{-- Aucun compte existant : inscription complète --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Prénom <span class="text-red-500">*</span></label>
                        <input type="text" name="first_name" value="{{ old('first_name') }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none @error('first_name') border-red-400 @enderror">
                        @error('first_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none @error('name') border-red-400 @enderror">
                        @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone <span class="text-red-500">*</span></label>
                    <input type="tel" name="phone" value="{{ old('phone') }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none @error('phone') border-red-400 @enderror">
                    @error('phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Adresse <span class="text-red-500">*</span></label>
                    <input type="text" name="address" value="{{ old('address') }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none @error('address') border-red-400 @enderror">
                    @error('address')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Code postal <span class="text-red-500">*</span></label>
                        <input type="text" name="postal_code" value="{{ old('postal_code') }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none @error('postal_code') border-red-400 @enderror">
                        @error('postal_code')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ville <span class="text-red-500">*</span></label>
                        <input type="text" name="city" value="{{ old('city') }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none @error('city') border-red-400 @enderror">
                        @error('city')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.auth.email') }}</label>
                    <input type="email" value="{{ $invitation->email }}" disabled
                           class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-400 cursor-not-allowed">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.auth.password') }} <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none @error('password') border-red-400 @enderror">
                    @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.profile.confirm_password') }} <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>
            @endif

            <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-xl font-bold text-base transition">
                @if ($preCreated)
                    Activer mon compte
                @elseif ($invitation->tontine)
                    {{ ($userExists || auth()->check()) ? __('app.invitation.join_btn') : __('app.invitation.create_join_btn') }}
                @else
                    {{ ($userExists || auth()->check()) ? 'Accéder à mon espace' : 'Créer mon compte' }}
                @endif
            </button>
        </form>

        <p class="text-center text-xs text-gray-400 mt-6">
            {{ __('app.invitation.expires') }} {{ $invitation->expires_at->format('d/m/Y') }}
        </p>
    </div>
</body>
</html>
