@extends('layouts.app')
@section('title', ($round->isPreliminary() ? __('app.round.preliminary_label') : __('app.round.col_round')).' #'.$round->round_number)

@section('content')
<div class="space-y-8">

    <div class="flex items-start justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('tresorier.dashboard') }}" class="hover:text-indigo-600">Tableau de bord</a>
                <span>/</span>
                <a href="{{ route('tresorier.tontine') }}" class="hover:text-indigo-600">{{ $tontine->name }}</a>
                <span>/</span>
                <span>{{ $round->isPreliminary() ? __('app.round.preliminary_label') : __('app.round.col_round') }} #{{ $round->round_number }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ $round->isPreliminary() ? __('app.round.preliminary_label') : __('app.round.col_round') }} #{{ $round->round_number }}
                </h1>
                @if($round->isPreliminary())
                    <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">{{ __('app.round.preliminary_badge') }}</span>
                @endif
            </div>
        </div>

        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('tresorier.rounds.recap', $round) }}" target="_blank" rel="noopener"
               class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium text-sm">
                {{ __('app.round.recap_pdf_btn') }}
            </a>

            @if($round->status === 'pending' && !$hasOtherOpenRound)
            <form method="POST" action="{{ route('tresorier.rounds.open', $round) }}">
                @csrf @method('PATCH')
                <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium text-sm">
                    📂 {{ __('app.round.open_btn') }}
                </button>
            </form>
            @endif

            @if($round->status === 'open')
            <form method="POST" action="{{ route('tresorier.rounds.close', $round) }}"
                  onsubmit="return confirm('{{ __('app.round.close_confirm') }}')">
                @csrf @method('PATCH')
                <button class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg font-medium text-sm">
                    🔒 {{ __('app.round.close_btn') }}
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- Statistiques --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @if($round->isPreliminary())
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase">{{ __('app.round.deposit_per_person') }}</div>
            <div class="text-xl font-bold text-amber-600 mt-1">{{ number_format($round->preliminary_amount, 2) }} €</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase">{{ __('app.round.total_collected') }}</div>
            <div class="text-xl font-bold text-gray-900 mt-1">
                {{ number_format($round->payments->sum('paid_amount'), 2) }} €
                <span class="text-xs text-gray-400 font-normal">/ {{ number_format($round->payments->sum('amount'), 2) }} €</span>
            </div>
        </div>
        @else
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase">{{ __('app.round.pot') }}</div>
            <div class="text-xl font-bold text-gray-900 mt-1">{{ number_format($round->pot_amount, 2) }} €</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase">{{ __('app.round.bids_count') }}</div>
            <div class="text-xl font-bold text-gray-900 mt-1">{{ $round->bids->count() }}</div>
        </div>
        @endif
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase">{{ __('app.round.payments_paid') }}</div>
            <div class="text-xl font-bold text-gray-900 mt-1">
                {{ $round->payments->where('status','paid')->count() }}/{{ $round->payments->count() }}
            </div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm"
             x-data="countdown('{{ $round->bid_closes_at->toISOString() }}', '{{ __('app.round.closed_label') }}')">
            <div class="text-xs text-gray-500 uppercase">
                {{ $round->isPreliminary() ? __('app.round.deadline_label') : __('app.round.closing_label') }}
            </div>
            <div class="text-xl font-bold mt-1"
                 :class="isUrgent() ? 'text-red-600 countdown-urgent' : 'text-gray-900'">
                <span x-text="format()">{{ $round->bid_closes_at->format('d/m/Y H:i') }}</span>
            </div>
            <div class="text-xs text-gray-400 mt-1">{{ $round->bid_closes_at->format('d/m/Y à H:i') }}</div>
        </div>
    </div>

    @if(!$round->isPreliminary() && $round->winner)
    <div class="bg-gradient-to-r from-indigo-50 to-purple-50 border border-indigo-200 rounded-xl p-6">
        <div class="flex items-center gap-4">
            <div class="text-4xl">🏆</div>
            <div>
                <div class="text-sm text-indigo-600 font-medium uppercase tracking-wide">{{ __('app.round.winner_label') }}</div>
                <div class="text-2xl font-bold text-gray-900">{{ $round->winner->full_name }}</div>
                <div class="text-sm text-gray-600 mt-1">
                    {{ __('app.round.winning_bid') }} : <strong>{{ $round->winning_bid }}%</strong>
                    — {{ __('app.round.net_pot') }} : <strong>{{ number_format($round->pot_amount, 2) }} €</strong>
                    @if($round->drawn_by_lot)
                        — <span class="text-yellow-600">{{ __('app.round.by_lot') }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    @if(!$round->isPreliminary())
    {{-- Enchères reçues --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">{{ __('app.round.bids_received') }}</h2>
        </div>
        @forelse($round->bids->sortByDesc('amount') as $i => $bid)
        <div class="px-6 py-3 flex items-center justify-between border-b border-gray-50 last:border-0
            {{ $bid->user_id === $round->winner_id ? 'bg-yellow-50' : '' }}">
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-400 w-5">{{ $i+1 }}.</span>
                <div>
                    <div class="text-sm font-medium text-gray-900">{{ $bid->user->full_name }}</div>
                    <div class="text-xs text-gray-400">{{ $bid->bid_at->format('d/m H:i') }}</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-lg font-bold {{ $bid->amount >= $tontine->bid_cap ? 'text-red-600' : 'text-gray-900' }}">
                    {{ $bid->amount }}%
                </span>
                @if($bid->user_id === $round->winner_id)<span class="text-yellow-500">🏆</span>@endif
                @if($bid->amount >= $tontine->bid_cap)
                    <span class="text-xs bg-red-100 text-red-600 px-1.5 py-0.5 rounded">{{ __('app.round.cap_label') }}</span>
                @endif
            </div>
        </div>
        @empty
        <div class="px-6 py-8 text-center text-gray-400 text-sm">{{ __('app.round.no_bids') }}</div>
        @endforelse

        @if($round->isOpen())
        <div class="px-6 py-4 bg-indigo-50/60 border-t border-indigo-100">
            <p class="text-xs font-semibold text-indigo-700 mb-2">🛡️ Enchérir pour un participant</p>
            @error('amount')<p class="text-xs text-red-600 font-medium mb-2">⚠️ {{ $message }}</p>@enderror
            @forelse($eligibleParticipants as $p)
            @php $existingBid = $round->bids->firstWhere('user_id', $p->id); @endphp
            <div class="flex items-center gap-2 mb-1.5 last:mb-0">
                <form method="POST" action="{{ route('tresorier.rounds.bid', [$round, $p]) }}"
                      class="flex items-center gap-2 flex-1">
                    @csrf
                    <span class="text-xs text-gray-700 flex-1 truncate">{{ $p->full_name }}</span>
                    <input type="number" name="amount" min="0" max="{{ $tontine->bid_cap }}" step="1"
                           value="{{ $existingBid?->amount }}" placeholder="%" required
                           class="text-xs border border-gray-300 rounded px-2 py-1 w-16 text-right">
                    <button class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-2.5 py-1 rounded font-medium">
                        {{ $existingBid ? 'Modifier' : 'Enchérir' }}
                    </button>
                </form>
                @if($existingBid)
                <form method="POST" action="{{ route('tresorier.rounds.bid.cancel', [$round, $p]) }}"
                      onsubmit="return confirm('Annuler l\'enchère de {{ addslashes($p->full_name) }} ?')">
                    @csrf @method('DELETE')
                    <button class="text-xs bg-red-100 hover:bg-red-200 text-red-700 px-2 py-1 rounded" title="Annuler l'enchère">🗑️</button>
                </form>
                @endif
            </div>
            @empty
            <p class="text-xs text-gray-400">Aucun participant éligible.</p>
            @endforelse
        </div>
        @endif
    </div>
    @endif

    {{-- Paiements à gérer --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <h2 class="font-semibold text-gray-800">
                {{ $round->isPreliminary() ? __('app.round.deposits') : __('app.round.contributions') }}
            </h2>
            <span class="text-xs text-gray-500">
                {{ $round->payments->where('status','paid')->count() }}/{{ $round->payments->count() }} réglés
            </span>
        </div>

        @if($round->isPreliminary())
        @php
            $paidCount    = $round->payments->where('status','paid')->count();
            $partialCount = $round->payments->where('status','partial')->count();
            $totalPaidAmount = $round->payments->sum('paid_amount');
            $totalDueAmount  = $round->payments->sum('amount');
            $pctCollected = $totalDueAmount > 0 ? round($totalPaidAmount / $totalDueAmount * 100) : 0;
        @endphp
        <div class="px-6 py-3 border-b border-gray-100">
            <div class="flex justify-between text-xs text-gray-500 mb-1 flex-wrap gap-2">
                <span>
                    <strong>{{ number_format($totalPaidAmount, 2) }} €</strong> collectés
                    <span class="text-gray-400">({{ $pctCollected }}%)</span>
                </span>
                <span>
                    {{ $paidCount }} réglé{{ $paidCount > 1 ? 's' : '' }}
                    @if($partialCount > 0)
                        · <span class="text-blue-600">{{ $partialCount }} partiel{{ $partialCount > 1 ? 's' : '' }}</span>
                    @endif
                    · sur {{ number_format($totalDueAmount, 2) }} €
                </span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2">
                <div class="bg-amber-500 h-2 rounded-full transition-all"
                     style="width: {{ $pctCollected }}%"></div>
            </div>
        </div>
        @endif

        @php $paymentsLocked = ! $round->isPreliminary() && ! $round->winner_id; @endphp
        @if($paymentsLocked)
        <div class="mx-6 mt-3 mb-1 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-2 text-xs flex items-center gap-2">
            🔒 Les paiements de cotisations seront saisissables une fois le gagnant désigné.
        </div>
        @endif

        @foreach($round->payments as $payment)
        @php
            $sc = ['pending'=>'yellow','paid'=>'green','late'=>'red'][$payment->status];
            $daysLate = $payment->daysLate();
            $penalty  = $payment->penaltyAmount(
                (float) $tontine->penalty_per_day,
                $tontine->penalty_cap !== null ? (float) $tontine->penalty_cap : null
            );
        @endphp
        <div class="px-6 py-3 border-b border-gray-50 last:border-0">
            <div class="flex items-center justify-between mb-1">
                <div>
                    <div class="text-sm font-medium text-gray-900">{{ $payment->user->full_name }}</div>
                    <div class="text-xs text-gray-400">{{ number_format($payment->amount, 2) }} €</div>
                </div>
                <span class="text-xs bg-{{ $sc }}-100 text-{{ $sc }}-700 px-2 py-0.5 rounded-full">
                    {{ __('app.status.'.$payment->status) }}
                </span>
            </div>

            <div class="flex gap-4 text-xs text-gray-500 mb-2">
                @if($payment->due_date)
                    <span>{{ __('app.round.due_date') }}: <strong>{{ $payment->due_date->format('d/m/Y') }}</strong></span>
                    @if($payment->status === 'paid' && $payment->paid_at)
                        <span>Réglé le : <strong>{{ $payment->paid_at->format('d/m/Y') }}</strong></span>
                    @endif
                    @if($daysLate > 0)
                        <span class="text-red-600 font-medium">
                            {{ __('app.round.days_late') }}: {{ $daysLate }}j
                            @if($penalty > 0)
                                — {{ __('app.round.penalty') }}: {{ number_format($penalty, 2) }} €
                            @endif
                        </span>
                    @endif
                @endif
                @if($payment->reference)
                    <span>Réf : <strong>{{ $payment->reference }}</strong></span>
                @endif
            </div>

            <div class="{{ $paymentsLocked ? 'opacity-40 pointer-events-none select-none' : '' }}">
            <form method="POST"
                  action="{{ route('tresorier.rounds.payments.update', [$round, $payment]) }}"
                  class="flex gap-2">
                @csrf @method('PATCH')
                <select name="status" {{ $paymentsLocked ? 'disabled' : '' }}
                        class="text-xs border border-gray-300 rounded px-2 py-1">
                    <option value="pending" {{ $payment->status==='pending'?'selected':'' }}>{{ __('app.status.pending') }}</option>
                    <option value="paid"    {{ $payment->status==='paid'?'selected':'' }}>{{ __('app.status.paid') }}</option>
                    <option value="late"    {{ $payment->status==='late'?'selected':'' }}>{{ __('app.status.late') }}</option>
                </select>
                <input type="text" name="reference" placeholder="{{ __('app.common.reference') }}"
                       value="{{ $payment->reference }}" {{ $paymentsLocked ? 'disabled' : '' }}
                       class="text-xs border border-gray-300 rounded px-2 py-1 flex-1">
                <button {{ $paymentsLocked ? 'disabled' : '' }}
                        class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded font-medium">✓</button>
            </form>
            </div>
        </div>
        @endforeach
    </div>

</div>
@endsection
