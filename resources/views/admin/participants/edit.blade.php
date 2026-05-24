@extends('layouts.app')
@section('title', __('app.participant.edit_title'))

@section('content')
<div class="max-w-lg mx-auto space-y-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.participants.show', $user) }}" class="text-gray-400 hover:text-gray-600 text-sm">← {{ $user->full_name }}</a>
    </div>

    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('app.participant.edit_title') }}</h1>
        <p class="text-gray-500 mt-1">{{ $user->email }}</p>
    </div>

    <form method="POST" action="{{ route('admin.participants.update', $user) }}" class="space-y-6">
        @csrf @method('PATCH')

        {{-- Informations personnelles --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-lg font-semibold text-gray-800">{{ __('app.participant.personal_info') }}</h2>
            </div>
            <div class="px-6 py-6 space-y-4">

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
                            {{ __('app.common.name') }} <span class="text-red-500">*</span>
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
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.common.status') }}</label>
                    <span class="{{ $user->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }} text-xs px-2 py-1 rounded-full">
                        {{ $user->active ? __('app.participant.active') : __('app.participant.inactive') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Adresse postale (partagée avec le principal si binôme) --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">{{ __('app.address.section') }}</h2>
                @if($user->partner_id)
                    <span class="text-xs bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full">Partagée avec {{ $primaryUser->full_name }}</span>
                @endif
            </div>
            <div class="px-6 py-6 space-y-4">

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

        {{-- RIB (partagé avec le principal si binôme) --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">{{ __('app.rib.section') }}</h2>
                @if($user->partner_id)
                    <span class="text-xs bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full">Partagé avec {{ $primaryUser->full_name }}</span>
                @endif
            </div>
            <div class="px-6 py-6 space-y-4">

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

        <div class="flex items-center justify-between">
            <a href="{{ route('admin.participants.show', $user) }}"
               class="text-sm text-gray-500 hover:text-gray-700">{{ __('app.common.cancel') }}</a>
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                {{ __('app.common.save') }}
            </button>
        </div>
    </form>

</div>
@endsection
