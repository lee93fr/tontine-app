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
        <form method="POST" action="{{ route('participant.profile.update') }}" class="px-6 py-6 space-y-5">
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

            {{-- Adresse postale (partagée dans un binôme) --}}
            <div class="border-t border-gray-100 pt-5">
                <div class="flex items-center gap-2 mb-4">
                    <h3 class="text-sm font-semibold text-gray-700">{{ __('app.address.section') }}</h3>
                    @if($user->partner_id)
                        <span class="text-xs bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full">Partagée avec {{ $primaryUser->full_name }}</span>
                    @elseif($primaryUser->partnerOf)
                        <span class="text-xs bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full">Partagée avec {{ $primaryUser->partnerOf->full_name }}</span>
                    @endif
                </div>

                <div class="space-y-4">
                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('app.address.address') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="address" name="address" value="{{ old('address', $primaryUser->address) }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                               placeholder="12 rue de la Paix">
                        @error('address')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('app.address.postal_code') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="postal_code" name="postal_code" value="{{ old('postal_code', $primaryUser->postal_code) }}" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                   placeholder="75001">
                            @error('postal_code')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('app.address.city') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="city" name="city" value="{{ old('city', $primaryUser->city) }}" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                   placeholder="Paris">
                            @error('city')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- RIB (partagé dans un binôme) --}}
            <div class="border-t border-gray-100 pt-5">
                <div class="flex items-center gap-2 mb-4">
                    <h3 class="text-sm font-semibold text-gray-700">{{ __('app.rib.section') }}</h3>
                    @if($user->partner_id)
                        <span class="text-xs bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full">Partagé avec {{ $primaryUser->full_name }}</span>
                    @elseif($primaryUser->partnerOf)
                        <span class="text-xs bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full">Partagé avec {{ $primaryUser->partnerOf->full_name }}</span>
                    @endif
                </div>

                <div class="space-y-4">
                    <div>
                        <label for="iban" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.rib.iban') }}</label>
                        <input type="text" id="iban" name="iban"
                               value="{{ old('iban', trim(preg_replace('/(.{4})(?=.)/', '$1 ', strtoupper(preg_replace('/\s+/', '', $primaryUser->iban ?? ''))))) }}"
                               oninput="formatIban(this)" data-iban-error="{{ __('app.rib.iban_invalid') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('iban') border-red-400 @enderror"
                               placeholder="FR76 3000 6000 0112 3456 7890 189" maxlength="42">
                        <p class="iban-err text-red-600 text-xs mt-1 hidden"></p>
                        @error('iban')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="bic" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.rib.bic') }}</label>
                        <input type="text" id="bic" name="bic" value="{{ old('bic', $primaryUser->bic) }}"
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

    @include('partials.profile-preferences')

    {{-- Délégation --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">🔁 Délégation d'enchères</h2>
            <p class="text-sm text-gray-400 mt-0.5">Autorisez quelqu'un à enchérir en votre nom lorsque vous êtes absent.</p>
        </div>
        <div class="px-6 py-6">
            @if($primaryUser->delegate)
                <div class="flex items-center justify-between gap-4 p-3 bg-amber-50 border border-amber-200 rounded-lg mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 font-bold text-sm">
                            {{ strtoupper(mb_substr($primaryUser->delegate->first_name ?: $primaryUser->delegate->name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-amber-900">{{ $primaryUser->delegate->full_name }}</p>
                            <p class="text-xs text-amber-600">peut enchérir en votre nom</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('participant.profile.delegate') }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="delegate_id" value="">
                        <button class="text-xs text-red-500 hover:text-red-700 border border-red-200 px-3 py-1 rounded-lg hover:bg-red-50">
                            Retirer
                        </button>
                    </form>
                </div>
            @endif

            <form method="POST" action="{{ route('participant.profile.delegate') }}" class="flex flex-col sm:flex-row gap-2">
                @csrf @method('PATCH')
                <select name="delegate_id"
                        class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">— Aucune délégation —</option>
                    @foreach($availableDelegates as $d)
                        <option value="{{ $d->id }}" {{ $primaryUser->delegate_id == $d->id ? 'selected' : '' }}>
                            {{ $d->full_name }} ({{ $d->email }})
                        </option>
                    @endforeach
                </select>
                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition whitespace-nowrap">
                    Enregistrer
                </button>
            </form>
            <p class="text-xs text-gray-400 mt-2">La délégation ne donne accès qu'aux enchères, pas à votre compte ou vos données.</p>
        </div>
    </div>

    {{-- Changer le mot de passe --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">{{ __('app.profile.change_password') }}</h2>
        </div>
        <form method="POST" action="{{ route('participant.profile.password') }}" class="px-6 py-6 space-y-5">
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

<x-help-panel title="Mon profil">
    <p><strong>Informations</strong> — gardez votre numéro de téléphone et adresse à jour pour faciliter les virements.</p>
    <p><strong>RIB / IBAN</strong> — renseignez votre IBAN pour que l'administrateur puisse vous virer les gains de la tontine.</p>
    <p><strong>Délégation</strong> — choisissez quelqu'un qui pourra enchérir en votre nom si vous êtes absent. La délégation ne donne aucun autre accès.</p>
    <p><strong>Mot de passe</strong> — choisissez un mot de passe d'au moins 8 caractères.</p>
</x-help-panel>

@endsection
