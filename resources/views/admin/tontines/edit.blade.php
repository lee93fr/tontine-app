@extends('layouts.app')
@section('title', __('app.tontine.edit_btn').' — '.$tontine->name)

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('admin.tontines.index') }}" class="hover:text-indigo-600">{{ __('app.tontine.title') }}</a>
        <span>/</span>
        <a href="{{ route('admin.tontines.show', $tontine) }}" class="hover:text-indigo-600">{{ $tontine->name }}</a>
        <span>/</span>
        <span>{{ __('app.tontine.edit_btn') }}</span>
    </div>

    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('app.tontine.edit_btn') }}</h1>

    @if($isLaunched)
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-3 text-sm mb-5 flex items-start gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <span><span class="font-semibold">Tontine en cours.</span> Seuls le nom, la description, le statut, le nombre de participants et le plafond de pénalité sont modifiables.</span>
    </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('admin.tontines.update', $tontine) }}" class="space-y-5"
              x-data="{
                  hasBidding: {{ $tontine->has_bidding ? 'true' : 'false' }},
                  hasPenalties: {{ ($tontine->penalty_per_day > 0) ? 'true' : 'false' }},
              }">
            @csrf @method('PATCH')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.tontine.name_label') }}</label>
                <input type="text" name="name" value="{{ old('name', $tontine->name) }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none">
                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.common.description') }} <span class="text-gray-400 font-normal">({{ __('app.common.optional') }})</span></label>
                <textarea name="description" rows="3"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">{{ old('description', $tontine->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.tontine.cotisation') }}</label>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.tontine.max_participants') }}</label>
                    <input type="number" name="max_participants" value="{{ old('max_participants', $tontine->max_participants) }}"
                           min="2" max="100" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    @error('max_participants')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            @if(!$isLaunched)
            {{-- Enchères --}}
            <div>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="has_bidding" value="1"
                           x-model="hasBidding" {{ $tontine->has_bidding ? 'checked' : '' }}
                           class="w-4 h-4 rounded text-indigo-600 border-gray-300">
                    <span class="text-sm font-medium text-gray-700">{{ __('app.schedule.has_bidding') }}</span>
                </label>
                <div x-show="hasBidding" x-cloak class="mt-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.tontine.bid_cap') }}</label>
                    <input type="number" name="bid_cap" value="{{ old('bid_cap', $tontine->bid_cap) }}"
                           min="1" max="100"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                    @error('bid_cap')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Pénalités --}}
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
                        @error('penalty_per_day')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('app.tontine.penalty_cap') }}
                            <span class="text-gray-400 font-normal">({{ __('app.tontine.penalty_cap_hint') }})</span>
                        </label>
                        <input type="number" name="penalty_cap" value="{{ old('penalty_cap', $tontine->penalty_cap) }}"
                               min="0" step="0.01"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                        @error('penalty_cap')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- Nombre de tours calculé (lecture seule) --}}
            @php
                $calculatedTours = $tontine->participants->isNotEmpty()
                    ? (int) $tontine->participants->sum(fn($p) => (int) ($p->pivot->slots ?? 1))
                    : ($tontine->rounds_count ?: $tontine->max_participants);
            @endphp
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Nombre de tours
                    <span class="text-gray-400 font-normal">(calculé automatiquement — Σ slots des participants)</span>
                </label>
                <div class="w-full border border-gray-200 rounded-lg px-3 py-2 bg-gray-50 text-gray-800 text-sm flex items-center gap-2">
                    <span class="font-semibold text-indigo-700 text-base">{{ $calculatedTours }}</span>
                    <span class="text-gray-400 text-xs">
                        @if($tontine->participants->isNotEmpty())
                            — {{ $tontine->participants->count() }} participant(s),
                            @foreach($tontine->participants->groupBy(fn($p) => (int)($p->pivot->slots ?? 1)) as $slots => $group)
                                {{ $group->count() }}×{{ $slots }}{{ !$loop->last ? ', ' : '' }}
                            @endforeach
                        @else
                            — aucun participant inscrit pour l'instant
                        @endif
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-1">Ce champ se met à jour automatiquement quand des participants sont ajoutés ou modifiés.</p>
            </div>

            {{-- Calendrier (modifiable, ne régénère pas les tours existants) --}}
            @if($tontine->first_round_month)
            <div class="border-t border-gray-100 pt-4">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('app.schedule.config_title') }}</h3>
                <p class="text-xs text-gray-400 mb-3">Ces valeurs s'appliquent aux nouveaux tours créés manuellement. Elles ne régénèrent pas les tours existants.</p>
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
            {{-- Tontine lancée : plafond pénalité uniquement si pénalités configurées --}}
            @if($tontine->penalty_per_day > 0)
            <div class="border-t border-gray-100 pt-4">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Pénalités</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.tontine.penalty_per_day') }}</label>
                        <div class="w-full border border-gray-200 rounded-lg px-3 py-2 bg-gray-50 text-gray-500 text-sm">
                            {{ number_format($tontine->penalty_per_day, 2, ',', ' ') }} € / jour
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('app.tontine.penalty_cap') }}
                            <span class="text-gray-400 font-normal">({{ __('app.tontine.penalty_cap_hint') }})</span>
                        </label>
                        <input type="number" name="penalty_cap" value="{{ old('penalty_cap', $tontine->penalty_cap) }}"
                               min="0" step="0.01"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                        @error('penalty_cap')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
            @endif
            @endif

            {{-- Signature requise pour enchérir (toujours modifiable) --}}
            @if($tontine->has_bidding)
            <div class="border-t border-gray-100 pt-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="bid_requires_signature" value="1"
                           {{ old('bid_requires_signature', $tontine->bid_requires_signature) ? 'checked' : '' }}
                           class="w-4 h-4 rounded text-indigo-600 border-gray-300 focus:ring-indigo-500">
                    <div>
                        <span class="text-sm font-medium text-gray-700">Enchères réservées aux participants ayant signé le règlement</span>
                        <p class="text-xs text-gray-400 mt-0.5">Si activé, un participant ne peut placer une enchère que si son règlement est signé.</p>
                    </div>
                </label>
            </div>
            @endif

            {{-- Date de versement prévisionnelle --}}
            <div class="border-t border-gray-100 pt-4">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Versement de la cagnotte</h3>
                <div class="grid grid-cols-2 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Jour de versement prévisionnel
                            <span class="text-gray-400 font-normal">(du mois, 1–28)</span>
                        </label>
                        <input type="number" name="payout_day" value="{{ old('payout_day', $tontine->payout_day) }}"
                               min="1" max="28"
                               placeholder="Ex : 15"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
                        @error('payout_day')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <p class="text-xs text-gray-400 pb-2">
                        Modifié ici, ce jour se propagera automatiquement à tous les tours <strong>en attente ou ouverts</strong>.
                        Les tours clôturés et tirés ne sont pas affectés.
                    </p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.common.status') }}</label>
                <select name="status" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-indigo-500 bg-white">
                    @foreach(['active', 'paused', 'completed', 'archived'] as $s)
                    <option value="{{ $s }}" {{ old('status', $tontine->status) === $s ? 'selected' : '' }}>
                        {{ __('app.status.'.$s) }}
                    </option>
                    @endforeach
                </select>
                @error('status')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="pt-2 flex gap-3">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-medium">
                    {{ __('app.common.save') }}
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
