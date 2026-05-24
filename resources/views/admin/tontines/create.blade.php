@extends('layouts.app')
@section('title', __('app.tontine.create_title'))
@section('content')
<div class="max-w-2xl mx-auto"
     x-data="{
         hasPreliminary: {{ old('has_preliminary') ? 'true' : 'false' }},
         hasBidding: {{ old('has_bidding', '1') !== '0' ? 'true' : 'false' }},
         hasPenalties: {{ old('has_penalties', '1') !== '0' ? 'true' : 'false' }},
     }">

    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('app.tontine.create_title') }}</h1>

    <form method="POST" action="{{ route('admin.tontines.store') }}" class="space-y-6">
        @csrf

        {{-- 1. Informations de base --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ __('app.participant.personal_info') }}</h2>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.tontine.name_label') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none">
                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.common.description') }} <span class="text-gray-400 font-normal">({{ __('app.common.optional') }})</span></label>
                <textarea name="description" rows="2"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.tontine.cotisation') }}</label>
                    <input type="number" name="cotisation_amount" value="{{ old('cotisation_amount') }}"
                           min="1" step="0.01" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    @error('cotisation_amount')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.tontine.max_participants') }}</label>
                    <input type="number" name="max_participants" value="{{ old('max_participants', 20) }}"
                           min="2" max="100" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    @error('max_participants')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- 2. Options : enchères, pénalités --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ __('app.schedule.section') }}</h2>

            {{-- Tour préliminaire --}}
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="has_preliminary" value="1"
                       x-model="hasPreliminary" {{ old('has_preliminary') ? 'checked' : '' }}
                       class="w-4 h-4 rounded text-indigo-600 border-gray-300 focus:ring-indigo-500">
                <span class="text-sm font-medium text-gray-700">{{ __('app.schedule.has_preliminary') }}</span>
            </label>

            <div x-show="hasPreliminary" x-cloak class="pl-7 space-y-4 border-l-2 border-amber-200">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.schedule.preliminary_amount') }}</label>
                    <input type="number" name="preliminary_amount" value="{{ old('preliminary_amount', 800) }}"
                           min="1" step="0.01"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-amber-500">
                    @error('preliminary_amount')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.schedule.preliminary_period') }} — début</label>
                        <input type="date" name="preliminary_period_start" value="{{ old('preliminary_period_start') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-amber-500">
                        @error('preliminary_period_start')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.schedule.preliminary_period') }} — fin</label>
                        <input type="date" name="preliminary_period_end" value="{{ old('preliminary_period_end') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-amber-500">
                        @error('preliminary_period_end')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- Enchères --}}
            <div class="border-t border-gray-100 pt-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="has_bidding" value="1"
                           x-model="hasBidding" {{ old('has_bidding', '1') !== '0' ? 'checked' : '' }}
                           class="w-4 h-4 rounded text-indigo-600 border-gray-300 focus:ring-indigo-500">
                    <span class="text-sm font-medium text-gray-700">{{ __('app.schedule.has_bidding') }}</span>
                </label>

                <div x-show="hasBidding" x-cloak class="pl-7 mt-3 space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.tontine.bid_cap') }}</label>
                        <input type="number" name="bid_cap" value="{{ old('bid_cap', 15) }}"
                               min="1" max="100" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                        @error('bid_cap')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="bid_requires_signature" value="1"
                               {{ old('bid_requires_signature') ? 'checked' : '' }}
                               class="w-4 h-4 rounded text-indigo-600 border-gray-300 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Enchères réservées aux participants ayant signé le règlement</span>
                    </label>
                </div>
                <p x-show="!hasBidding" x-cloak class="pl-7 mt-2 text-xs text-amber-600">{{ __('app.schedule.no_bidding_hint') }}</p>
            </div>

            {{-- Pénalités --}}
            <div class="border-t border-gray-100 pt-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="has_penalties" value="1"
                           x-model="hasPenalties" {{ old('has_penalties', '1') !== '0' ? 'checked' : '' }}
                           class="w-4 h-4 rounded text-indigo-600 border-gray-300 focus:ring-indigo-500">
                    <span class="text-sm font-medium text-gray-700">{{ __('app.schedule.has_penalties') }}</span>
                </label>

                <div x-show="hasPenalties" x-cloak class="pl-7 mt-3 grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.tontine.penalty_per_day') }}</label>
                        <input type="number" name="penalty_per_day" value="{{ old('penalty_per_day', 1) }}"
                               min="0" step="0.01"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                        @error('penalty_per_day')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('app.tontine.penalty_cap') }}
                            <span class="text-gray-400 font-normal">({{ __('app.tontine.penalty_cap_hint') }})</span>
                        </label>
                        <input type="number" name="penalty_cap" value="{{ old('penalty_cap') }}"
                               min="0" step="0.01"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                        @error('penalty_cap')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Calendrier des tours --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('app.schedule.config_title') }}</h2>
            <p class="text-xs text-gray-400 mb-4">Les tours seront générés automatiquement selon ces paramètres.</p>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.schedule.first_round_month') }}</label>
                    <input type="month" name="first_round_month" value="{{ old('first_round_month') }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    @error('first_round_month')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('app.schedule.rounds_count') }}
                        <span class="text-gray-400 font-normal">({{ __('app.schedule.rounds_count_hint') }})</span>
                    </label>
                    <input type="number" name="rounds_count" value="{{ old('rounds_count') }}"
                           min="1" max="200"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    @error('rounds_count')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.schedule.bid_open_day') }}</label>
                    <div class="relative">
                        <input type="number" name="bid_day_open" value="{{ old('bid_day_open', 1) }}"
                               min="1" max="28" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">{{ __('app.schedule.day_hint') }}</p>
                    @error('bid_day_open')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.schedule.bid_close_day') }}</label>
                    <input type="number" name="bid_day_close" value="{{ old('bid_day_close', 5) }}"
                           min="1" max="28" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    @error('bid_day_close')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.schedule.payment_day') }}</label>
                    <input type="number" name="payment_day" value="{{ old('payment_day', 20) }}"
                           min="1" max="28" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    @error('payment_day')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="bg-indigo-50 rounded-lg p-3 text-xs text-indigo-700">
                Exemple : enchères du <strong>1 au 5</strong> de chaque mois, paiement avant le <strong>20</strong>.
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-medium">
                {{ __('app.tontine.create_btn') }}
            </button>
            <a href="{{ route('admin.tontines.index') }}"
               class="border border-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-50">
                {{ __('app.common.cancel') }}
            </a>
        </div>
    </form>
</div>
@endsection
