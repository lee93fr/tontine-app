@extends('layouts.app')
@section('title', __('app.profile.title'))

@section('content')
<div class="max-w-2xl mx-auto space-y-8">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('app.profile.title') }}</h1>
        <p class="text-gray-500 mt-1">{{ __('app.profile.subtitle') }}</p>
    </div>

    {{-- Informations personnelles --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">{{ __('app.profile.personal_info') }}</h2>
        </div>
        <form method="POST" action="{{ route('tresorier.profile.update') }}" class="px-6 py-6 space-y-5">
            @csrf @method('PATCH')

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('app.common.first_name') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('first_name') border-red-400 @enderror">
                    @error('first_name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('app.profile.last_name') }} <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror">
                    @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('app.profile.phone') }} <span class="text-red-500">*</span>
                </label>
                <input type="text" id="phone" name="phone" inputmode="tel" required
                       value="{{ old('phone', trim(preg_replace('/(\d{2})(?=\d)/', '$1 ', preg_replace('/\D/', '', $user->phone ?? '')))) }}"
                       oninput="const d=this.value.replace(/\D/g,'').slice(0,10);this.value=d.replace(/(\d{2})(?=\d)/g,'$1 ').trim()"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('phone') border-red-400 @enderror"
                       placeholder="06 12 34 56 78">
                @error('phone')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.profile.email') }}</label>
                <input type="text" value="{{ $user->email }}" disabled
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-400 cursor-not-allowed">
                <p class="text-xs text-gray-400 mt-1">{{ __('app.profile.email_readonly') }}</p>
            </div>

            {{-- Adresse postale --}}
            <div class="border-t border-gray-100 pt-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('app.address.section') }}</h3>

                <div class="space-y-4">
                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('app.address.address') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="address" name="address" value="{{ old('address', $user->address) }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                               placeholder="12 rue de la Paix">
                        @error('address')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('app.address.postal_code') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="postal_code" name="postal_code" value="{{ old('postal_code', $user->postal_code) }}" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                   placeholder="75001">
                            @error('postal_code')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('app.address.city') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="city" name="city" value="{{ old('city', $user->city) }}" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                   placeholder="Paris">
                            @error('city')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- RIB --}}
            <div class="border-t border-gray-100 pt-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('app.rib.section') }}</h3>

                <div class="space-y-4">
                    <div>
                        <label for="iban" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.rib.iban') }}</label>
                        <input type="text" id="iban" name="iban"
                               value="{{ old('iban', trim(preg_replace('/(.{4})(?=.)/', '$1 ', strtoupper(preg_replace('/\s+/', '', $user->iban ?? ''))))) }}"
                               oninput="formatIban(this)" data-iban-error="{{ __('app.rib.iban_invalid') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('iban') border-red-400 @enderror"
                               placeholder="FR76 3000 6000 0112 3456 7890 189" maxlength="42">
                        <p class="iban-err text-red-600 text-xs mt-1 hidden"></p>
                        @error('iban')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="bic" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.rib.bic') }}</label>
                        <input type="text" id="bic" name="bic" value="{{ old('bic', $user->bic) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('bic') border-red-400 @enderror"
                               placeholder="{{ __('app.rib.bic_hint') }}" maxlength="11">
                        @error('bic')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                    {{ __('app.common.save') }}
                </button>
            </div>
        </form>
    </div>

    {{-- Tontine active + switch --}}
    @if($managedTontines->count() > 1)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Tontine active</h2>
            <p class="text-sm text-gray-500 mt-0.5">Vous gérez plusieurs tontines. Sélectionnez celle sur laquelle vous souhaitez travailler.</p>
        </div>
        <div class="px-6 py-6 space-y-3">
            @foreach($managedTontines as $tontine)
                @if($tontine->id === $activeTontineId)
                    <div class="flex items-center gap-3 p-3 bg-indigo-50 border border-indigo-200 rounded-lg">
                        <span class="text-indigo-500">●</span>
                        <span class="text-sm font-medium text-indigo-900 flex-1">{{ $tontine->name }}</span>
                        <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-medium">Active</span>
                    </div>
                @else
                    <form method="POST" action="{{ route('tresorier.switch_tontine') }}" class="flex items-center gap-3 p-3 bg-gray-50 border border-gray-200 rounded-lg hover:border-indigo-200 transition">
                        @csrf
                        <input type="hidden" name="tontine_id" value="{{ $tontine->id }}">
                        <span class="text-gray-300">○</span>
                        <span class="text-sm text-gray-700 flex-1">{{ $tontine->name }}</span>
                        <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                            Activer →
                        </button>
                    </form>
                @endif
            @endforeach
        </div>
    </div>
    @endif

    {{-- Mode participant --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Mode de navigation</h2>
            <p class="text-sm text-gray-500 mt-0.5">Vous êtes actuellement en mode trésorier. Revenez en mode participant pour accéder à votre espace personnel.</p>
        </div>
        <div class="px-6 py-6 flex items-center gap-4">
            <div class="flex-1 flex items-center gap-3 p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
                <span class="text-2xl">🏦</span>
                <div>
                    <p class="text-sm font-medium text-indigo-900">Mode trésorier actif</p>
                    <p class="text-xs text-indigo-700 mt-0.5">Vous gérez les paiements et les tours de la tontine.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('tresorier.mode.exit') }}">
                @csrf @method('DELETE')
                <button type="submit"
                        class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition">
                    ← Mode participant
                </button>
            </form>
        </div>
    </div>

    @include('partials.profile-preferences')

    {{-- Changer le mot de passe --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">{{ __('app.profile.change_password') }}</h2>
        </div>
        <form method="POST" action="{{ route('tresorier.profile.password') }}" class="px-6 py-6 space-y-5">
            @csrf @method('PATCH')

            <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.profile.current_password') }}</label>
                <input type="password" id="current_password" name="current_password" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('current_password') border-red-400 @enderror">
                @error('current_password')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.profile.new_password') }}</label>
                <input type="password" id="password" name="password" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('password') border-red-400 @enderror">
                @error('password')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.profile.confirm_password') }}</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <p class="text-xs text-gray-400">{{ __('app.profile.password_hint') }}</p>

            <div class="flex justify-end pt-2">
                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                    {{ __('app.profile.change_btn') }}
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
