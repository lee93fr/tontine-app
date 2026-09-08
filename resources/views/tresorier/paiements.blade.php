@extends('layouts.app')
@section('title', 'Suivi des paiements')

@section('content')
<div class="space-y-8">

    <div>
        <div class="text-sm text-gray-400 mb-1">
            <a href="{{ route('tresorier.dashboard') }}" class="hover:text-indigo-600">Tableau de bord</a>
            <span class="mx-1">/</span>
            <span>Suivi des paiements</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">Suivi des paiements</h1>
        <p class="text-gray-500 mt-1">{{ $tontine->name }}</p>
    </div>

    {{-- Résumé --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
            <div class="text-xs text-yellow-700 uppercase tracking-wide">En attente</div>
            <div class="text-2xl font-bold text-yellow-700 mt-1">{{ $summary['pending'] }}</div>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <div class="text-xs text-blue-700 uppercase tracking-wide">Partiels</div>
            <div class="text-2xl font-bold text-blue-700 mt-1">{{ $summary['partial'] }}</div>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4">
            <div class="text-xs text-red-700 uppercase tracking-wide">En retard</div>
            <div class="text-2xl font-bold text-red-700 mt-1">{{ $summary['late'] }}</div>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-xl p-4">
            <div class="text-xs text-green-700 uppercase tracking-wide">Réglés</div>
            <div class="text-2xl font-bold text-green-700 mt-1">{{ $summary['paid'] }}</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Reste à recevoir</div>
            <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($summary['total_due'], 2) }} €</div>
            <div class="text-xs text-gray-400 mt-0.5">{{ number_format($summary['total_received'] ?? 0, 2) }} € encaissés</div>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" action="{{ route('tresorier.paiements') }}" class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Statut</label>
            <select name="statut" class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">Tous</option>
                <option value="pending" {{ request('statut')==='pending' ? 'selected' : '' }}>En attente</option>
                <option value="partial" {{ request('statut')==='partial' ? 'selected' : '' }}>Partiel</option>
                <option value="paid"    {{ request('statut')==='paid'    ? 'selected' : '' }}>Réglé</option>
                <option value="late"    {{ request('statut')==='late'    ? 'selected' : '' }}>En retard</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Participant</label>
            <select name="participant" class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">Tous</option>
                @foreach($participants as $p)
                <option value="{{ $p->id }}" {{ request('participant') == $p->id ? 'selected' : '' }}>
                    {{ $p->full_name }}
                </option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                Filtrer
            </button>
            <a href="{{ route('tresorier.paiements') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm">
                Réinitialiser
            </a>
        </div>
    </form>

    {{-- Tableau des paiements --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center gap-3 flex-wrap">
            <h2 class="font-semibold text-gray-800">Paiements ({{ $payments->count() }})</h2>
            @php $unpaidCount = $payments->whereIn('status', ['pending', 'late'])->count(); @endphp
            @if($unpaidCount > 0)
                <form method="POST" action="{{ route('tresorier.paiements.remind.all') }}"
                      onsubmit="return confirm('Envoyer une relance email + push à tous les {{ $unpaidCount }} paiement(s) en attente/retard ?')">
                    @csrf
                    <button class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
                        🔔 Tout relancer ({{ $unpaidCount }})
                    </button>
                </form>
            @endif
        </div>

        @if($payments->isEmpty())
        <div class="px-6 py-12 text-center text-gray-400">
            <div class="text-4xl mb-3">✅</div>
            <p>Aucun paiement trouvé avec ces filtres.</p>
        </div>
        @else

        {{-- Groupement par tour --}}
        @foreach($payments->groupBy(fn($p) => $p->round_id) as $roundId => $roundPayments)
        @php
            $round = $roundPayments->first()->round;
            $paymentsLocked = ! $round->isPreliminary() && ! $round->winner_id;
        @endphp
        <div>
            <div class="px-6 py-2 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    @if($round->isPreliminary())
                        <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">Préliminaire</span>
                    @else
                        <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-medium">Tour #{{ $round->round_number }}</span>
                    @endif
                    @if($round->waive_penalties)
                        <span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-medium">Sans pénalités</span>
                    @endif
                    <span class="text-xs text-gray-500">
                        Échéance : {{ $round->payment_due_at ? \Carbon\Carbon::parse($round->payment_due_at)->format('d/m/Y') : '—' }}
                    </span>
                </div>
                <a href="{{ route('tresorier.rounds.show', $round) }}"
                   class="text-xs text-indigo-600 hover:text-indigo-800">Gérer ce tour →</a>
            </div>

            @foreach($roundPayments as $payment)
            @php
                $sc = ['pending'=>'yellow','paid'=>'green','late'=>'red','partial'=>'blue'][$payment->status] ?? 'gray';
                $daysLate = $payment->daysLate();
                $penalty  = $payment->penaltyAmount(
                    (float) $tontine->penalty_per_day,
                    $tontine->penalty_cap !== null ? (float) $tontine->penalty_cap : null,
                    $round->waive_penalties
                );
                $remaining = $payment->remainingAmount();
                $progress  = $payment->amount > 0 ? min(100, ($payment->paid_amount / $payment->amount) * 100) : 0;
            @endphp
            <div class="px-6 py-3 border-b border-gray-50 last:border-0 flex items-center gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-medium text-gray-900">{{ $payment->user->full_name }}</span>
                        <span class="text-xs bg-{{ $sc }}-100 text-{{ $sc }}-700 px-2 py-0.5 rounded-full">
                            {{ __('app.status.'.$payment->status) }}
                        </span>
                        @if($daysLate > 0 && $payment->status !== 'paid')
                            <span class="text-xs text-red-600 font-medium">{{ $daysLate }}j de retard</span>
                        @endif
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5 flex gap-3 flex-wrap">
                        <span>
                            <strong>{{ number_format($payment->paid_amount, 2) }} €</strong>
                            / {{ number_format($payment->amount, 2) }} €
                            @if($remaining > 0.01)
                                <span class="text-amber-600 font-medium">(reste {{ number_format($remaining, 2) }} €)</span>
                            @endif
                        </span>
                        @if($payment->due_date)
                            <span>Dû le {{ $payment->due_date->format('d/m/Y') }}</span>
                        @endif
                        @if($payment->paid_at)
                            <span>Réglé le {{ $payment->paid_at->format('d/m/Y') }}</span>
                        @endif
                        @if($payment->reference)
                            <span>Réf : {{ $payment->reference }}</span>
                        @endif
                        @if($penalty > 0 && $payment->status !== 'paid')
                            <span class="text-red-500">Pénalité : {{ number_format($penalty, 2) }} €</span>
                        @endif
                    </div>
                    @if($payment->status === 'partial')
                        <div class="w-full bg-gray-200 rounded-full h-1 mt-1.5 overflow-hidden">
                            <div class="bg-blue-500 h-1 transition-all" style="width: {{ $progress }}%"></div>
                        </div>
                    @endif
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    @if($payment->status !== 'paid')
                        @php
                            $lastReminder = $payment->last_reminder_sent_at;
                            $coolDownActive = $lastReminder && $lastReminder->diffInHours(now()) < 24;
                        @endphp
                        <form method="POST" action="{{ route('tresorier.paiements.remind', $payment) }}"
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
                        <form method="POST" action="{{ route('tresorier.paiements.remind.sms', $payment) }}"
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

                    <div class="{{ $paymentsLocked ? 'opacity-40 pointer-events-none select-none' : '' }}"
                         title="{{ $paymentsLocked ? 'Gagnant non encore désigné' : '' }}">
                    <form method="POST"
                          action="{{ route('tresorier.rounds.payments.update', [$round, $payment]) }}"
                          class="flex gap-1.5 items-center">
                        @csrf @method('PATCH')
                        <input type="number" name="paid_amount" step="0.01" min="0" max="999999"
                               value="{{ number_format((float) $payment->paid_amount, 2, '.', '') }}"
                               title="Montant cumulé reçu (auto-statut : pending → partial → paid)"
                               {{ $paymentsLocked ? 'disabled' : '' }}
                               class="text-xs border border-gray-300 rounded px-2 py-1 w-24 text-right">
                        <span class="text-xs text-gray-400">/ {{ number_format($payment->amount, 0) }}€</span>
                        <input type="text" name="reference" placeholder="Réf."
                               value="{{ $payment->reference }}" {{ $paymentsLocked ? 'disabled' : '' }}
                               class="text-xs border border-gray-300 rounded px-2 py-1 w-24">
                        <button title="Enregistrer" {{ $paymentsLocked ? 'disabled' : '' }}
                                class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded font-medium">✓</button>
                    </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endforeach

        @endif
    </div>

</div>
@endsection
