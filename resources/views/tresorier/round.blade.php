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
