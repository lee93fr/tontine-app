@extends('layouts.app')
@section('title', 'Modifier — '.$tontine->name)

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('admin-local.tontines.index') }}" class="hover:text-indigo-600">Mes tontines</a>
        <span>/</span>
        <a href="{{ route('admin-local.tontines.show', $tontine) }}" class="hover:text-indigo-600">{{ $tontine->name }}</a>
        <span>/</span>
        <span>Modifier</span>
    </div>

    <h1 class="text-2xl font-bold text-gray-900 mb-6">Modifier la tontine</h1>

    @if($isLaunched)
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-3 text-sm mb-5">
        <span class="font-semibold">Tontine en cours.</span> Seuls le nom, la description, le statut et le nombre de participants sont modifiables.
    </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('admin-local.tontines.update', $tontine) }}" class="space-y-5"
              x-data="{ hasBidding: {{ $tontine->has_bidding ? 'true' : 'false' }}, hasPenalties: {{ ($tontine->penalty_per_day > 0) ? 'true' : 'false' }} }">
            @csrf @method('PATCH')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $tontine->name) }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none">
                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description <span class="text-gray-400 font-normal">(optionnel)</span></label>
                <textarea name="description" rows="3"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">{{ old('description', $tontine->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cotisation</label>
                    @if($isLaunched)
                        <div class="w-full border border-gray-200 rounded-lg px-3 py-2 bg-gray-50 text-gray-500 text-sm">
                            {{ number_format($tontine->cotisation_amount, 2, ',', ' ') }} €
                        </div>
                    @else
                        <input type="number" name="cotisation_amount" value="{{ old('cotisation_amount', $tontine->cotisation_amount) }}"
                               min="1" step="0.01" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                        @error('cotisation_amount')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Participants max</label>
                    <input type="number" name="max_participants" value="{{ old('max_participants', $tontine->max_participants) }}"
                           min="2" max="100" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    @error('max_participants')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            @if(!$isLaunched)
            <div>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="has_bidding" value="1"
                           x-model="hasBidding" {{ $tontine->has_bidding ? 'checked' : '' }}
                           class="w-4 h-4 rounded text-indigo-600 border-gray-300">
                    <span class="text-sm font-medium text-gray-700">{{ __('app.schedule.has_bidding') }}</span>
                </label>
                <div x-show="hasBidding" x-cloak class="mt-3 space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.tontine.bid_cap') }}</label>
                        <input type="number" name="bid_cap" value="{{ old('bid_cap', $tontine->bid_cap) }}"
                               min="1" max="100"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    </div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="bid_requires_signature" value="1"
                               {{ old('bid_requires_signature', $tontine->bid_requires_signature) ? 'checked' : '' }}
                               class="w-4 h-4 rounded text-indigo-600 border-gray-300 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Enchères réservées aux participants ayant signé le règlement</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="has_penalties" value="1"
                           x-model="hasPenalties" {{ $tontine->penalty_per_day > 0 ? 'checked' : '' }}
                           class="w-4 h-4 rounded text-indigo-600 border-gray-300">
                    <span class="text-sm font-medium text-gray-700">{{ __('app.schedule.has_penalties') }}</span>
                </label>
                <div x-show="hasPenalties" x-cloak class="mt-3 grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.tontine.penalty_per_day') }}</label>
                        <input type="number" name="penalty_per_day" value="{{ old('penalty_per_day', $tontine->penalty_per_day) }}"
                               min="0" step="0.01"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.tontine.penalty_cap') }}</label>
                        <input type="number" name="penalty_cap" value="{{ old('penalty_cap', $tontine->penalty_cap) }}"
                               min="0" step="0.01"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('app.schedule.rounds_count') }}
                    <span class="text-gray-400 font-normal">({{ __('app.schedule.rounds_count_hint') }})</span>
                </label>
                <input type="number" name="rounds_count" value="{{ old('rounds_count', $tontine->rounds_count) }}"
                       min="1" max="200" placeholder="{{ $tontine->max_participants }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                @error('rounds_count')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            @if($tontine->first_round_month)
            <div class="border-t border-gray-100 pt-4">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('app.schedule.config_title') }}</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.schedule.bid_open_day') }}</label>
                        <input type="number" name="bid_day_open" value="{{ old('bid_day_open', $tontine->bid_day_open) }}"
                               min="1" max="28"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.schedule.bid_close_day') }}</label>
                        <input type="number" name="bid_day_close" value="{{ old('bid_day_close', $tontine->bid_day_close) }}"
                               min="1" max="28"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.schedule.payment_day') }}</label>
                        <input type="number" name="payment_day" value="{{ old('payment_day', $tontine->payment_day) }}"
                               min="1" max="28"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    </div>
                </div>
            </div>
            @endif

            @else
            @if($tontine->penalty_per_day > 0)
            <div class="border-t border-gray-100 pt-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.tontine.penalty_cap') }}</label>
                    <input type="number" name="penalty_cap" value="{{ old('penalty_cap', $tontine->penalty_cap) }}"
                           min="0" step="0.01"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                </div>
            </div>
            @endif
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                <select name="status" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500 bg-white">
                    @foreach(['active', 'paused', 'completed', 'archived'] as $s)
                    <option value="{{ $s }}" {{ old('status', $tontine->status) === $s ? 'selected' : '' }}>
                        {{ __('app.status.'.$s) }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="pt-2 flex gap-3">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-medium">
                    Enregistrer
                </button>
                <a href="{{ route('admin-local.tontines.show', $tontine) }}"
                   class="border border-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-50">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
