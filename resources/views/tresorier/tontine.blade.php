@extends('layouts.app')
@section('title', $tontine->name)

@section('content')
<div class="space-y-8">

    <div>
        <div class="text-sm text-gray-400 mb-1">
            <a href="{{ route('tresorier.dashboard') }}" class="hover:text-indigo-600">Tableau de bord</a>
            <span class="mx-1">/</span>
            <span>{{ $tontine->name }}</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">{{ $tontine->name }}</h1>
        <p class="text-gray-500 mt-1">{{ $tontine->description }}</p>
    </div>

    {{-- Infos tontine --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @foreach([
            [__('app.tontine.cotisation'), number_format($tontine->cotisation_amount,2).' €'],
            [__('app.tontine.rounds_count'), $tontine->rounds->count()],
            [$tontine->has_bidding ? __('app.tontine.bid_cap') : __('app.schedule.has_bidding'),
             $tontine->has_bidding ? $tontine->bid_cap.'%' : '🎲 Tirage au sort'],
            [__('app.common.status'), __('app.status.'.$tontine->status)],
        ] as [$label,$val])
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase tracking-wide">{{ $label }}</div>
            <div class="text-lg font-bold text-gray-900 mt-1">{{ $val }}</div>
        </div>
        @endforeach
    </div>

    @if($tontine->first_round_month)
    <div class="bg-indigo-50 border border-indigo-100 rounded-xl px-5 py-3 text-xs text-indigo-700 flex flex-wrap gap-x-6 gap-y-1">
        <span>📅 1er tour : <strong>{{ $tontine->first_round_month->isoFormat('MMMM YYYY') }}</strong></span>
        @if($tontine->payment_day)
        <span>Échéance paiement : le <strong>{{ $tontine->payment_day }}</strong> de chaque mois</span>
        @endif
        <span>{{ $tontine->participants->count() }} participant(s)</span>
    </div>
    @endif

    {{-- Participants (lecture seule) --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">
                {{ __('app.tontine.participants_section') }} ({{ $tontine->participants->count() }})
            </h2>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($tontine->participants as $p)
            <div class="px-6 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-4">
                <div class="min-w-0">
                    <div class="font-medium text-gray-900 text-sm">{{ $p->full_name }}</div>
                    <div class="text-xs text-gray-400">{{ $p->email }}</div>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    @if($p->pivot->wins_count >= $p->pivot->slots)
                        <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full">
                            🏆 {{ $p->pivot->wins_count }}/{{ $p->pivot->slots }} {{ __('app.tontine.rounds_won') }}
                        </span>
                    @elseif($p->pivot->wins_count > 0)
                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">
                            {{ $p->pivot->wins_count }}/{{ $p->pivot->slots }} {{ __('app.tontine.rounds_won') }}
                        </span>
                    @else
                        <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">
                            0/{{ $p->pivot->slots }}
                        </span>
                    @endif
                    <span class="text-xs text-gray-400">{{ $p->pivot->slots }} slot(s)</span>
                </div>
            </div>
            @empty
            <div class="px-6 py-8 text-center text-gray-400 text-sm">{{ __('app.tontine.no_participants') }}</div>
            @endforelse
        </div>
    </div>

    {{-- Tours --}}
    @php $hasOpenRound = $tontine->rounds->contains('status', 'open'); @endphp
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <h2 class="font-semibold text-gray-800">
                {{ __('app.tontine.rounds_section') }} ({{ $tontine->rounds->count() }})
            </h2>
            @if($hasOpenRound)
                <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">
                    🟢 {{ __('app.round.one_open') }}
                </span>
            @endif
        </div>

        @forelse($tontine->rounds->sortBy('round_number') as $round)
        <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-gray-100 last:border-0">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm shrink-0
                    {{ $round->isPreliminary() ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700' }}">
                    #{{ $round->round_number }}
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-900">
                        {{ __('app.round.bid_closes') }} : {{ $round->bid_closes_at->format('d/m/Y H:i') }}
                    </div>
                    <div class="text-xs text-gray-500">
                        @if($round->isPreliminary())
                            Mise par pers. : {{ number_format($round->preliminary_amount, 2) }} €
                            — Total : {{ number_format($round->pot_amount, 2) }} €
                        @else
                            {{ __('app.round.pot') }} : {{ number_format($round->pot_amount, 2) }} €
                            @if($round->winner) — Gagnant : <strong>{{ $round->winner->full_name }}</strong> @endif
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center flex-wrap gap-2 shrink-0">
                @php
                    $colors = ['pending'=>'gray','open'=>'green','closed'=>'yellow','drawn'=>'blue','paid'=>'indigo'];
                    $color = $colors[$round->status] ?? 'gray';
                @endphp
                @if($round->isPreliminary())
                    <span class="bg-amber-100 text-amber-700 text-xs px-2 py-0.5 rounded-full">{{ __('app.round.preliminary_badge') }}</span>
                @endif
                <span class="bg-{{ $color }}-100 text-{{ $color }}-700 text-xs px-2 py-0.5 rounded-full">
                    {{ __('app.status.'.$round->status) }}
                </span>

                {{-- Ouvrir --}}
                @if($round->status === 'pending' && !$hasOpenRound)
                <form method="POST" action="{{ route('tresorier.rounds.open', $round) }}">
                    @csrf @method('PATCH')
                    <button class="text-xs bg-green-600 hover:bg-green-700 text-white px-2.5 py-1 rounded-lg font-medium">
                        {{ __('app.round.open_btn') }}
                    </button>
                </form>
                @endif

                {{-- Fermer --}}
                @if($round->status === 'open')
                <form method="POST" action="{{ route('tresorier.rounds.close', $round) }}"
                      onsubmit="return confirm('{{ __('app.round.close_confirm') }}')">
                    @csrf @method('PATCH')
                    <button class="text-xs bg-orange-500 hover:bg-orange-600 text-white px-2.5 py-1 rounded-lg font-medium">
                        {{ __('app.round.close_btn') }}
                    </button>
                </form>
                @endif

                <a href="{{ route('tresorier.rounds.show', $round) }}"
                   class="text-indigo-600 hover:text-indigo-800 text-sm">→</a>
            </div>
        </div>
        @empty
        <div class="px-6 py-12 text-center text-gray-400">
            <div class="text-4xl mb-3">🗓️</div>
            <p>{{ __('app.tontine.no_rounds') }}</p>
        </div>
        @endforelse
    </div>

    {{-- Moyens de paiement --}}
    @php $pi = $tontine->payment_info ?? []; @endphp
    <div x-data="{ open: {{ count($pi) ? 'false' : 'true' }} }" class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <button type="button" @click="open = !open"
                class="w-full px-6 py-4 border-b border-gray-100 flex items-center justify-between text-left">
            <div class="flex items-center gap-2">
                <h2 class="font-semibold text-gray-800">💳 Moyens de paiement des cotisations</h2>
                @if(count($pi))
                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Configuré</span>
                @else
                    <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">À renseigner</span>
                @endif
            </div>
            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="open" x-cloak class="px-6 py-5">
            <p class="text-xs text-gray-400 mb-4">Ces informations seront incluses dans les emails envoyés aux participants.</p>
            <form method="POST" action="{{ route('tresorier.tontine.payment-info') }}" class="space-y-4">
                @csrf @method('PATCH')

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Prénom</label>
                        <input type="text" name="tresorier_firstname" value="{{ old('tresorier_firstname', $pi['tresorier_firstname'] ?? '') }}"
                               placeholder="Marie"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Nom</label>
                        <input type="text" name="tresorier_name" value="{{ old('tresorier_name', $pi['tresorier_name'] ?? '') }}"
                               placeholder="Dupont"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Téléphone</label>
                    <input type="tel" name="tresorier_phone" value="{{ old('tresorier_phone', $pi['tresorier_phone'] ?? '') }}"
                           placeholder="06 00 00 00 00"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="border-t border-gray-100 pt-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Virement bancaire</p>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">IBAN</label>
                            <input type="text" name="iban" value="{{ old('iban', $pi['iban'] ?? '') }}"
                                   placeholder="FR76 3000 6000 0112 3456 7890 189"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">BIC</label>
                            <input type="text" name="bic" value="{{ old('bic', $pi['bic'] ?? '') }}"
                                   placeholder="BNPAFRPP"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>
                <div class="border-t border-gray-100 pt-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Autres moyens</p>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Lien Revolut</label>
                            <input type="url" name="revolut_link" value="{{ old('revolut_link', $pi['revolut_link'] ?? '') }}"
                                   placeholder="https://revolut.me/..."
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Adresse (espèces)</label>
                            <textarea name="address" rows="2" placeholder="12 rue de la Paix, 75001 Paris"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('address', $pi['address'] ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end pt-1">
                    <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
