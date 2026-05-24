@extends('layouts.app')
@section('title', __('app.round.create_title'))
@section('content')
<div class="max-w-2xl mx-auto" x-data="{ type: '{{ old('type', $type) }}' }">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ __('app.round.create_title') }}</h1>
    <p class="text-gray-500 mb-6">
        {{ __('app.tontine.title') }} : <strong>{{ $tontine->name }}</strong>
        <span x-show="type==='standard'"> — {{ __('app.round.estimated_pot') }} : <strong>{{ number_format($tontine->pot_amount, 2) }} €</strong></span>
    </p>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('admin.rounds.store', $tontine) }}" class="space-y-5">
            @csrf
            <input type="hidden" name="type" :value="type">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('app.round.type_label') }}</label>
                <div class="flex gap-3">
                    <button type="button" @click="type='standard'"
                        :class="type==='standard'
                            ? 'bg-indigo-600 text-white border-indigo-600'
                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                        class="flex-1 border rounded-lg px-4 py-2.5 text-sm font-medium transition-colors">
                        {{ __('app.round.type_standard') }}
                    </button>
                    <button type="button" @click="type='preliminary'"
                        :class="type==='preliminary'
                            ? 'bg-amber-500 text-white border-amber-500'
                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                        class="flex-1 border rounded-lg px-4 py-2.5 text-sm font-medium transition-colors">
                        {{ __('app.round.type_preliminary') }}
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.round.number') }}</label>
                <input type="number" name="round_number" value="{{ old('round_number', $nextNumber) }}" min="1" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
            </div>

            <div x-show="type==='preliminary'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.round.preliminary_amount') }}</label>
                <input type="number" name="preliminary_amount" value="{{ old('preliminary_amount', 800) }}"
                       min="1" step="0.01" :required="type==='preliminary'"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-amber-500">
                @error('preliminary_amount')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                <p class="text-xs text-gray-400 mt-1">{{ __('app.round.preliminary_hint') }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"
                           x-text="type==='preliminary' ? '{{ __('app.round.payment_opens') }}' : '{{ __('app.round.bid_opens') }}'">
                        {{ __('app.round.bid_opens') }}
                    </label>
                    <input type="datetime-local" name="bid_opens_at" value="{{ old('bid_opens_at') }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    @error('bid_opens_at')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"
                           x-text="type==='preliminary' ? '{{ __('app.round.payment_deadline') }}' : '{{ __('app.round.bid_closes') }}'">
                        {{ __('app.round.bid_closes') }}
                    </label>
                    <input type="datetime-local" name="bid_closes_at" value="{{ old('bid_closes_at') }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    @error('bid_closes_at')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.common.notes') }} <span class="text-gray-400 font-normal">({{ __('app.common.optional') }})</span></label>
                <textarea name="notes" rows="2"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">{{ old('notes') }}</textarea>
            </div>

            <div x-show="type==='standard'" x-cloak
                 class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-700">
                ℹ️ {{ __('app.round.standard_info') }}
            </div>
            <div x-show="type==='preliminary'" x-cloak
                 class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-700">
                ℹ️ {{ __('app.round.preliminary_info') }}
            </div>

            <div class="flex gap-3">
                <button type="submit"
                    :class="type==='preliminary' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-indigo-600 hover:bg-indigo-700'"
                    class="text-white px-6 py-2 rounded-lg font-medium">
                    {{ __('app.round.create_btn') }}
                </button>
                <a href="{{ route('admin.tontines.show', $tontine) }}"
                   class="border border-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-50">
                    {{ __('app.common.cancel') }}
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
