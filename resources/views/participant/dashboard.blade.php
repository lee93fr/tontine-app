@extends('layouts.app')
@section('title', __('app.nav.my_space'))

@section('content')
<div class="space-y-8">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('app.space.hello', ['name' => auth()->user()->name]) }}</h1>
        <p class="text-gray-500 mt-1">{{ __('app.space.subtitle') }}</p>
    </div>

    @if($upcomingRounds->where('status','open')->isNotEmpty())
    <div class="space-y-4">
        <h2 class="text-lg font-semibold text-gray-800">{{ __('app.space.active_bids') }}</h2>
        @foreach($upcomingRounds->where('status','open') as $round)
        <div class="bg-white border-2 border-indigo-300 rounded-xl shadow-sm overflow-hidden"
             x-data="countdown('{{ $round->bid_closes_at->toISOString() }}', '{{ __('app.round.closed_label') }}')">
            <div class="px-6 py-5">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="text-sm text-indigo-600 font-medium">{{ $round->tontine->name }}</div>
                        <div class="text-xl font-bold text-gray-900 mt-1">{{ __('app.round.col_round') }} #{{ $round->round_number }}</div>
                        <div class="text-sm text-gray-500 mt-1">
                            {{ __('app.round.pot') }} : <strong>{{ number_format($round->pot_amount,2) }} €</strong>
                            — {{ __('app.round.cap_label') }} : <strong>{{ $round->tontine->bid_cap }}%</strong>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-gray-400 uppercase tracking-wide">{{ __('app.space.closes_in') }}</div>
                        <div class="text-2xl font-bold"
                             :class="isUrgent() ? 'text-red-600 countdown-urgent' : 'text-indigo-700'"
                             x-text="format()">...</div>
                    </div>
                </div>
            </div>
            <div class="px-6 pb-5">
                @php $bidClosed = $round->bid_closes_at->isPast(); @endphp
                <a href="{{ route('participant.tontine', $round->tontine) }}"
                   class="block w-full text-white text-center py-2.5 rounded-lg font-medium transition
                          {{ $bidClosed ? 'bg-gray-500 hover:bg-gray-600' : 'bg-indigo-600 hover:bg-indigo-700' }}">
                    {{ $bidClosed ? __('app.space.see_only') : __('app.space.see_bid') }}
                </a>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <div>
        <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ __('app.space.my_tontines') }}</h2>
        @php $isSecondary = auth()->user()->partner_id !== null; @endphp
        @forelse($tontines as $tontine)
        @php
            $sigStatus = $isSecondary
                ? $tontine->pivot->partner_signature_status
                : $tontine->pivot->signature_status;
            $sigUrl = $isSecondary
                ? $tontine->pivot->partner_signature_signer_url
                : $tontine->pivot->signature_signer_url;
            $sigBadge = match($sigStatus ?: null) {
                'signed'   => ['bg-green-100',  'text-green-700',  '✅ Règlement signé'],
                'pending'  => ['bg-yellow-100', 'text-yellow-700', '⏳ Signature en attente'],
                'opened'   => ['bg-blue-100',   'text-blue-700',   '👁 À signer'],
                'declined' => ['bg-red-100',    'text-red-700',    '✗ Signature refusée'],
                'expired'  => ['bg-orange-100', 'text-orange-700', '⚠ Lien expiré'],
                'failed'   => ['bg-red-100',    'text-red-700',    '✗ Erreur envoi'],
                default    => null,
            };
        @endphp
        <a href="{{ route('participant.tontine', $tontine) }}"
           class="block bg-white border border-gray-200 rounded-xl p-5 shadow-sm hover:border-indigo-300 hover:shadow-md transition mb-3">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-gray-900">{{ $tontine->name }}</div>
                    <div class="text-sm text-gray-500 mt-1">
                        {{ __('app.space.contribution') }} : {{ number_format($tontine->cotisation_amount,2) }} € {{ __('app.common.months') }}
                    </div>
                    @if($sigBadge)
                    <div class="mt-2 flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center text-xs font-medium px-2 py-0.5 rounded-full {{ $sigBadge[0] }} {{ $sigBadge[1] }}">
                            {{ $sigBadge[2] }}
                        </span>
                        @if(in_array($sigStatus, ['pending', 'opened']) && $sigUrl)
                            <span class="text-xs text-indigo-600 font-medium">→ Cliquez pour signer</span>
                        @endif
                    </div>
                    @endif
                </div>
                <div class="shrink-0">
                    @if($tontine->currentRound)
                        <span class="bg-green-100 text-green-700 text-xs px-3 py-1 rounded-full font-medium">
                            {{ __('app.space.round_open') }}
                        </span>
                    @else
                        <span class="bg-gray-100 text-gray-500 text-xs px-3 py-1 rounded-full">
                            {{ __('app.space.waiting') }}
                        </span>
                    @endif
                </div>
            </div>
        </a>
        @empty
        <div class="bg-white border border-gray-200 rounded-xl p-12 text-center text-gray-400">
            <div class="text-4xl mb-3">💰</div>
            <p>{{ __('app.space.no_tontines') }}</p>
            <p class="text-sm mt-1">{{ __('app.space.invite_hint') }}</p>
        </div>
        @endforelse
    </div>

    {{-- Paiements à venir --}}
    @if($upcomingPayments->isNotEmpty())
    <div>
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Paiements à venir</h2>
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-6 py-3 text-left">Tontine</th>
                        <th class="px-6 py-3 text-left">Tour</th>
                        <th class="px-6 py-3 text-left">Montant</th>
                        <th class="px-6 py-3 text-left">Échéance</th>
                        <th class="px-6 py-3 text-left">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($upcomingPayments as $payment)
                    @php
                        $daysLate = $payment->daysLate();
                        $isLate   = $payment->status === 'late' || ($payment->status === 'pending' && $daysLate > 0);
                    @endphp
                    <tr class="{{ $isLate ? 'bg-red-50' : 'hover:bg-gray-50' }}">
                        <td class="px-6 py-3 font-medium text-gray-900">
                            <a href="{{ route('participant.tontine', $payment->round->tontine) }}"
                               class="hover:text-indigo-600">
                                {{ $payment->round->tontine->name }}
                            </a>
                        </td>
                        <td class="px-6 py-3 text-gray-600">
                            @if($payment->round->isPreliminary())
                                Préliminaire
                            @else
                                Tour #{{ $payment->round->round_number }}
                            @endif
                        </td>
                        <td class="px-6 py-3 font-semibold text-gray-900">
                            {{ number_format($payment->amount, 2) }} €
                        </td>
                        <td class="px-6 py-3 {{ $isLate ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                            {{ $payment->due_date ? $payment->due_date->format('d/m/Y') : '—' }}
                            @if($daysLate > 0)
                                <span class="text-xs ml-1">({{ $daysLate }}j de retard)</span>
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            @if($isLate)
                                <span class="inline-flex items-center gap-1 bg-red-100 text-red-700 text-xs font-medium px-2 py-0.5 rounded-full">
                                    ⚠ En retard
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 bg-yellow-100 text-yellow-700 text-xs font-medium px-2 py-0.5 rounded-full">
                                    À régler
                                </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Tontines pour lesquelles je suis délégué --}}
    @if($delegatedTontines->isNotEmpty())
    <div class="space-y-3">
        <h2 class="text-lg font-semibold text-gray-800">🔁 Je gère les enchères pour d'autres</h2>
        @foreach($delegatedTontines as $dt)
        @php
            $dtParticipants = $dt->participants;
        @endphp
        <a href="{{ route('participant.tontine', $dt) }}"
           class="block bg-white border-2 border-amber-200 rounded-xl p-5 shadow-sm hover:border-amber-400 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-semibold text-gray-900">{{ $dt->name }}</div>
                    <div class="text-sm text-amber-600 mt-1">
                        Pour :
                        @foreach($dtParticipants as $dtP)
                            <span class="font-medium">{{ $dtP->full_name }}</span>{{ !$loop->last ? ', ' : '' }}
                        @endforeach
                    </div>
                </div>
                @if($dt->currentRound)
                    <span class="bg-amber-100 text-amber-700 text-xs px-3 py-1 rounded-full font-medium shrink-0">
                        Tour ouvert
                    </span>
                @else
                    <span class="bg-gray-100 text-gray-500 text-xs px-3 py-1 rounded-full shrink-0">
                        En attente
                    </span>
                @endif
            </div>
        </a>
        @endforeach
    </div>
    @endif

    @if($myBids->isNotEmpty())
    <div>
        <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ __('app.bid.my_bids') }}</h2>
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-6 py-3 text-left">{{ __('app.bid.col_tontine') }}</th>
                        <th class="px-6 py-3 text-left">{{ __('app.bid.col_round') }}</th>
                        <th class="px-6 py-3 text-left">{{ __('app.bid.col_amount') }}</th>
                        <th class="px-6 py-3 text-left">{{ __('app.bid.col_date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($myBids as $bid)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-900">{{ $bid->round->tontine->name }}</td>
                        <td class="px-6 py-3 text-gray-600">#{{ $bid->round->round_number }}</td>
                        <td class="px-6 py-3 font-bold text-indigo-700">{{ $bid->amount }}%</td>
                        <td class="px-6 py-3 text-gray-400">{{ $bid->bid_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    </div>
    @endif
</div>

<x-help-panel title="Mon espace">
    <p><strong>Mes tontines</strong> — accédez à chaque tontine pour voir les enchères en cours et votre historique.</p>
    <p><strong>Enchères actives</strong> — quand un tour est ouvert, vous pouvez placer ou modifier votre enchère jusqu'à la clôture.</p>
    <p><strong>Délégation</strong> — si vous avez un délégué, il peut enchérir en votre nom. Gérez cela dans votre profil.</p>
    <p><strong>Paiements</strong> — les cotisations dues apparaissent ici avec leur échéance.</p>
</x-help-panel>

@endsection
