@extends('layouts.app')
@section('title', ($round->isPreliminary() ? __('app.round.preliminary_label') : __('app.round.col_round')).' #'.$round->round_number)

@section('content')
<div class="space-y-8">

    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('admin.tontines.index') }}" class="hover:text-indigo-600">{{ __('app.tontine.title') }}</a>
                <span>/</span>
                <a href="{{ route('admin.tontines.show', $tontine) }}" class="hover:text-indigo-600">{{ $tontine->full_name }}</a>
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
                @if($round->waive_penalties)
                    <span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-medium">Pénalités désactivées</span>
                @endif
            </div>
        </div>

        <div class="flex gap-2 flex-wrap">
            @if((float) $tontine->penalty_per_day > 0 || $round->waive_penalties)
            <form method="POST" action="{{ route('admin.rounds.penalties.update', [$tontine, $round]) }}"
                  onsubmit="return confirm('{{ $round->waive_penalties ? 'Réactiver les pénalités pour tout ce tour ?' : 'Désactiver les pénalités pour tous les membres de ce tour ?' }}')">
                @csrf @method('PATCH')
                <input type="hidden" name="waive_penalties" value="{{ $round->waive_penalties ? 0 : 1 }}">
                <button class="px-4 py-2 rounded-lg font-medium text-sm {{ $round->waive_penalties ? 'bg-emerald-100 hover:bg-emerald-200 text-emerald-800' : 'bg-gray-100 hover:bg-gray-200 text-gray-700' }}">
                    {{ $round->waive_penalties ? '↩ Réactiver les pénalités' : '⊘ Désactiver les pénalités' }}
                </button>
            </form>
            @endif

            <a href="{{ route('admin.rounds.recap', [$tontine, $round]) }}" target="_blank" rel="noopener"
               class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium text-sm">
                {{ __('app.round.recap_pdf_btn') }}
            </a>

            {{-- Ouvrir les enchères --}}
            @if($round->status === 'pending' && !$hasOtherOpenRound)
            <form method="POST" action="{{ route('admin.rounds.open', [$tontine, $round]) }}">
                @csrf @method('PATCH')
                <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium text-sm">
                    📂 {{ __('app.round.open_btn') }}
                </button>
            </form>
            @endif

            {{-- Fermer les enchères --}}
            @if($round->status === 'open')
            <form method="POST" action="{{ route('admin.rounds.close', [$tontine, $round]) }}"
                  onsubmit="return confirm('{{ __('app.round.close_confirm') }}')">
                @csrf @method('PATCH')
                <button class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg font-medium text-sm">
                    🔒 {{ __('app.round.close_btn') }}
                </button>
            </form>
            @endif

            {{-- Décloturor --}}
            @if($round->status === 'closed' && !$round->winner_id)
            <form method="POST" action="{{ route('admin.rounds.reopen', [$tontine, $round]) }}"
                  onsubmit="return confirm('{{ __('app.round.reopen_confirm') }}')">
                @csrf @method('PATCH')
                <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium text-sm">
                    📂 {{ __('app.round.reopen_btn') }}
                </button>
            </form>
            @endif

            {{-- Désigner le gagnant --}}
            @if(!$round->isPreliminary() && in_array($round->status, ['open', 'closed']))
            <form method="POST" action="{{ route('admin.rounds.draw', [$tontine, $round]) }}"
                  onsubmit="return confirm('{{ __('app.round.draw_confirm') }}')">
                @csrf
                <button class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg font-medium text-sm">
                    {{ __('app.round.draw_btn') }}
                </button>
            </form>
            @endif

            {{-- Annuler la désignation du gagnant --}}
            @if($round->status === 'drawn')
            <form method="POST" action="{{ route('admin.rounds.cancel-draw', [$tontine, $round]) }}"
                  onsubmit="return confirm('{{ __('app.round.cancel_draw_confirm') }}')">
                @csrf @method('DELETE')
                <button class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium text-sm">
                    ↩️ {{ __('app.round.cancel_draw_btn') }}
                </button>
            </form>
            @endif
        </div>
    </div>

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
        @if($round->payout_date)
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 shadow-sm">
            <div class="text-xs text-indigo-500 uppercase">Versement prévu</div>
            <div class="text-xl font-bold text-indigo-700 mt-1">{{ $round->payout_date->format('d/m/Y') }}</div>
        </div>
        @endif
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
                    @if($round->payout_date)
                        — Versement le <strong>{{ $round->payout_date->format('d/m/Y') }}</strong>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="{{ $round->isPreliminary() ? 'max-w-2xl' : 'grid grid-cols-1 lg:grid-cols-2 gap-8' }}">

        @if(!$round->isPreliminary())
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
                    <form method="POST" action="{{ route('admin.rounds.bid', [$tontine, $round, $p]) }}"
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
                    <form method="POST" action="{{ route('admin.rounds.bid.cancel', [$tontine, $round, $p]) }}"
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

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h2 class="font-semibold text-gray-800">
                    {{ $round->isPreliminary() ? __('app.round.deposits') : __('app.round.contributions') }}
                </h2>
                <span class="text-xs text-gray-500">
                    {{ $round->payments->where('status','paid')->count() }}/{{ $round->payments->count() }} {{ __('app.round.payments_paid') }}
                </span>
            </div>

            @if($round->isPreliminary())
            @php
                $paidCount    = $round->payments->where('status','paid')->count();
                $partialCount = $round->payments->where('status','partial')->count();
                $total        = $round->payments->count();
                $totalPaidAmount = $round->payments->sum('paid_amount');
                $totalDueAmount  = $round->payments->sum('amount');
                $pctCollected = $totalDueAmount > 0 ? round($totalPaidAmount / $totalDueAmount * 100) : 0;
            @endphp
            <div class="px-6 py-3 border-b border-gray-100">
                <div class="flex justify-between text-xs text-gray-500 mb-1 flex-wrap gap-2">
                    <span>
                        <strong>{{ number_format($totalPaidAmount, 2) }} €</strong> {{ __('app.round.collected') }}
                        <span class="text-gray-400">({{ $pctCollected }}%)</span>
                    </span>
                    <span>
                        {{ $paidCount }} réglé{{ $paidCount > 1 ? 's' : '' }}
                        @if($partialCount > 0)
                            · <span class="text-blue-600">{{ $partialCount }} partiel{{ $partialCount > 1 ? 's' : '' }}</span>
                        @endif
                        · {{ __('app.round.on') }} {{ number_format($totalDueAmount, 2) }} €
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
                $sc = ['pending'=>'yellow','paid'=>'green','late'=>'red','partial'=>'blue'][$payment->status] ?? 'gray';
                $daysLate = $payment->daysLate();
                $penalty  = $payment->penaltyAmount(
                    (float) $tontine->penalty_per_day,
                    $tontine->penalty_cap !== null ? (float) $tontine->penalty_cap : null,
                    $round->waive_penalties
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

                <div class="flex gap-4 text-xs text-gray-500 mb-2 flex-wrap">
                    <span>
                        <strong>{{ number_format((float) $payment->paid_amount, 2) }} €</strong>
                        / {{ number_format($payment->amount, 2) }} €
                        @if($payment->remainingAmount() > 0.01)
                            <span class="text-amber-600 font-medium">(reste {{ number_format($payment->remainingAmount(), 2) }} €)</span>
                        @endif
                    </span>
                    @if($payment->due_date)
                        <span>{{ __('app.round.due_date') }}: <strong>{{ $payment->due_date->format('d/m/Y') }}</strong></span>
                        @if($payment->status === 'paid' && $payment->paid_at)
                            <span>{{ __('app.round.paid_on') }}: <strong>{{ $payment->paid_at->format('d/m/Y') }}</strong></span>
                        @endif
                        @if($payment->waive_penalty)
                            <span class="text-gray-400 italic">pénalité dispensée</span>
                        @elseif($daysLate > 0)
                            <span class="text-red-600 font-medium">
                                {{ __('app.round.days_late') }}: {{ $daysLate }}j
                                @if($penalty > 0)
                                    — {{ __('app.round.penalty') }}: {{ number_format($penalty, 2) }} €
                                @endif
                            </span>
                        @endif
                    @endif
                </div>

                @if($payment->isPartiallyPaid())
                    @php $progress = $payment->amount > 0 ? min(100, ($payment->paid_amount / $payment->amount) * 100) : 0; @endphp
                    <div class="w-full bg-gray-200 rounded-full h-1 mb-2 overflow-hidden">
                        <div class="bg-blue-500 h-1 transition-all" style="width: {{ $progress }}%"></div>
                    </div>
                @endif

                <div class="flex gap-1.5 items-center flex-wrap {{ $paymentsLocked ? 'opacity-40 pointer-events-none select-none' : '' }}">
                    <form method="POST" action="{{ route('admin.rounds.payments.update', [$tontine, $round, $payment]) }}"
                          class="flex gap-1.5 items-center flex-wrap flex-1">
                        @csrf @method('PATCH')
                        <input type="number" name="paid_amount" step="0.01" min="0" max="999999"
                               value="{{ number_format((float) $payment->paid_amount, 2, '.', '') }}"
                               title="Montant cumulé reçu" {{ $paymentsLocked ? 'disabled' : '' }}
                               class="text-xs border border-gray-300 rounded px-2 py-1 w-24 text-right">
                        <span class="text-xs text-gray-400">/ {{ number_format($payment->amount, 0) }}€</span>
                        <input type="text" name="reference" placeholder="{{ __('app.common.reference') }}" value="{{ $payment->reference }}"
                               {{ $paymentsLocked ? 'disabled' : '' }}
                               class="text-xs border border-gray-300 rounded px-2 py-1 flex-1 min-w-[120px]">
                        <input type="date" name="due_date"
                               value="{{ $payment->due_date?->format('Y-m-d') }}"
                               title="Date de règlement" {{ $paymentsLocked ? 'disabled' : '' }}
                               class="text-xs border border-gray-300 rounded px-2 py-1">
                        @if($round->waive_penalties)
                        <span class="text-xs text-emerald-700 font-medium">Dispense appliquée à tout le tour</span>
                        @else
                        <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer" title="Dispenser de pénalité pour ce paiement">
                            <input type="hidden" name="waive_penalty" value="0">
                            <input type="checkbox" name="waive_penalty" value="1"
                                   {{ $payment->waive_penalty ? 'checked' : '' }}
                                   {{ $paymentsLocked ? 'disabled' : '' }}
                                   class="rounded text-indigo-600">
                            Pas de pénalité
                        </label>
                        @endif
                        <button {{ $paymentsLocked ? 'disabled' : '' }}
                                class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded">✓</button>
                    </form>

                    {{-- Boutons relance (uniquement si non réglé) --}}
                    @if($payment->status !== 'paid')
                        @php
                            $lastReminder   = $payment->last_reminder_sent_at;
                            $coolDownActive = $lastReminder && $lastReminder->diffInHours(now()) < 24;
                        @endphp
                        <form method="POST" action="{{ route('admin.rounds.payments.remind', [$tontine, $round, $payment]) }}"
                              onsubmit="return confirm('Envoyer une relance email + push à {{ addslashes($payment->user->full_name) }} ?')">
                            @csrf
                            <button type="submit"
                                    @disabled($coolDownActive)
                                    title="{{ $coolDownActive ? 'Déjà relancé il y a moins de 24h' : 'Relance email + push' }}"
                                    class="text-xs px-2 py-1 rounded font-medium transition
                                        {{ $coolDownActive
                                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                            : 'bg-amber-100 hover:bg-amber-200 text-amber-700' }}">
                                🔔 @if($payment->reminder_count > 0) {{ $payment->reminder_count }} @endif
                            </button>
                        </form>

                        @if($payment->user->phone_number || $payment->user->phone)
                            <form method="POST" action="{{ route('admin.rounds.payments.remind.sms', [$tontine, $round, $payment]) }}"
                                  onsubmit="return confirm('Envoyer un SMS de relance à {{ addslashes($payment->user->full_name) }} ? Consomme du quota Brevo.')">
                                @csrf
                                <button type="submit"
                                        title="Relance SMS (Brevo)"
                                        class="text-xs bg-emerald-100 hover:bg-emerald-200 text-emerald-700 px-2 py-1 rounded font-medium">
                                    📱
                                </button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
