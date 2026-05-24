@extends('layouts.app')
@section('title', __('app.dashboard.title'))

@section('content')
<div class="space-y-8">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('app.dashboard.title') }}</h1>
        <p class="text-gray-500 mt-1">{{ __('app.dashboard.subtitle') }}</p>
    </div>

    {{-- Filtre de tontines : préférence persistée par admin. Vide = toutes. --}}
    @if(! $filterTableExists)
        <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl px-4 py-3 text-sm">
            <p class="font-semibold mb-1">⚠️ Migration en attente</p>
            <p>Le filtre de tontines du dashboard nécessite la table <code class="bg-amber-100 px-1 rounded">user_dashboard_tontines</code>. Lancez <code class="bg-amber-100 px-1 rounded">php artisan migrate</code> pour l'activer.</p>
        </div>
    @elseif($allTontines->isNotEmpty())
        @php
            $selectedCount = $selectedTontineIds->count();
            $totalCount    = $allTontines->count();
        @endphp
        <details class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" {{ $hasFilter ? 'open' : '' }}>
            <summary class="px-6 py-4 cursor-pointer flex items-center justify-between gap-3 hover:bg-gray-50 transition">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🔧</span>
                    <span class="font-semibold text-gray-800">Filtrer par tontine</span>
                    @if($hasFilter)
                        <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-medium">
                            {{ $selectedCount }} / {{ $totalCount }} sélectionnée{{ $selectedCount > 1 ? 's' : '' }}
                        </span>
                    @else
                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                            Toutes les tontines
                        </span>
                    @endif
                </div>
                <span class="text-xs text-gray-400">Préférence enregistrée</span>
            </summary>

            <form method="POST" action="{{ route('admin.dashboard.filter.update') }}"
                  x-data="{
                      toggleAll(check) {
                          this.$root.querySelectorAll('input[name=&quot;tontine_ids[]&quot;]').forEach(cb => cb.checked = check);
                      }
                  }"
                  class="px-6 pt-2 pb-5 border-t border-gray-100">
                @csrf @method('PATCH')

                <div class="flex items-center gap-2 mb-3 text-xs">
                    <button type="button" @click="toggleAll(true)"
                            class="text-indigo-600 hover:text-indigo-800 underline">Tout sélectionner</button>
                    <span class="text-gray-300">·</span>
                    <button type="button" @click="toggleAll(false)"
                            class="text-indigo-600 hover:text-indigo-800 underline">Tout désélectionner</button>
                    <span class="text-gray-400 ml-auto">Aucune sélection = toutes les tontines</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-72 overflow-y-auto pr-1">
                    @foreach($allTontines as $t)
                        <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="tontine_ids[]" value="{{ $t->id }}"
                                   {{ $selectedTontineIds->contains($t->id) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-gray-700 truncate">{{ $t->name }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="flex justify-end pt-4">
                    <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                        Enregistrer
                    </button>
                </div>
            </form>
        </details>
    @endif

    {{-- Premières tuiles : montants financiers, puis comptes --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php
            $pct = $stats['total_due'] > 0 ? round($stats['collected'] / $stats['total_due'] * 100) : 0;
        @endphp
        <div class="bg-green-50 rounded-xl border border-green-200 p-5 shadow-sm">
            <div class="text-3xl mb-2">💶</div>
            <div class="text-2xl font-bold text-green-700">{{ number_format($stats['collected'], 0, ',', ' ') }} €</div>
            <div class="text-sm text-green-700 mt-1">Montant collecté</div>
            @if($stats['total_due'] > 0)
                <div class="text-xs text-green-600 mt-2">{{ $pct }}% du dû</div>
                <div class="w-full bg-white rounded-full h-1 mt-1 overflow-hidden">
                    <div class="bg-green-500 h-1" style="width: {{ $pct }}%"></div>
                </div>
            @endif
        </div>

        <div class="bg-amber-50 rounded-xl border border-amber-200 p-5 shadow-sm">
            <div class="text-3xl mb-2">⏳</div>
            <div class="text-2xl font-bold text-amber-700">{{ number_format($stats['to_collect'], 0, ',', ' ') }} €</div>
            <div class="text-sm text-amber-700 mt-1">Reste à collecter</div>
            <div class="text-xs text-amber-600 mt-2">sur {{ number_format($stats['total_due'], 0, ',', ' ') }} € attendus</div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div class="text-3xl mb-2">💰</div>
            <div class="text-2xl font-bold text-gray-900">{{ $stats['tontines'] }}</div>
            <div class="text-sm text-gray-500 mt-1">{{ __('app.dashboard.active_tontines') }}</div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div class="text-3xl mb-2">👥</div>
            <div class="text-2xl font-bold text-gray-900">{{ $stats['participants'] }}</div>
            <div class="text-sm text-gray-500 mt-1">{{ __('app.dashboard.participants') }}</div>
        </div>
    </div>

    {{-- Échéances à venir (dynamique) --}}
    @if($upcomingDeadlines->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800 flex items-center gap-2">
                📅 Échéances à venir
                <span class="text-xs text-gray-400 font-normal">(30 prochains jours)</span>
            </h2>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($upcomingDeadlines as $date => $payments)
                @php
                    $carbonDate = \Illuminate\Support\Carbon::parse($date);
                    // Deadline = fin de la journée (23:59:59) pour le countdown JS
                    $deadlineIso = $carbonDate->copy()->endOfDay()->toIso8601String();
                    $totalDay = $payments->sum(fn($p) => max(0, (float) $p->amount - (float) $p->paid_amount));
                @endphp
                <div class="px-6 py-3 flex items-center justify-between gap-3 flex-wrap"
                     x-data="deadlineCountdown('{{ $deadlineIso }}')" x-init="init()">
                    <div class="flex items-center gap-3 min-w-0">
                        {{-- Compteur dynamique avec couleur contextuelle --}}
                        <span class="text-xs font-mono font-semibold px-2.5 py-1.5 rounded-lg whitespace-nowrap tabular-nums transition-colors"
                              :class="isPast
                                  ? 'bg-red-100 text-red-700 animate-pulse'
                                  : (isToday
                                      ? 'bg-amber-100 text-amber-700 animate-pulse'
                                      : (isUrgent
                                          ? 'bg-yellow-100 text-yellow-700'
                                          : 'bg-gray-100 text-gray-600'))"
                              x-text="format()">
                            …
                        </span>
                        <div>
                            <div class="text-sm font-medium text-gray-900">{{ $carbonDate->isoFormat('dddd D MMMM YYYY') }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $payments->count() }} paiement{{ $payments->count() > 1 ? 's' : '' }} en attente —
                                <strong>{{ number_format($totalDay, 0, ',', ' ') }} €</strong> à recevoir
                            </div>
                        </div>
                    </div>
                    <div class="text-xs text-gray-400">
                        @foreach($payments->groupBy('round.tontine.name') as $tontineName => $tontinePayments)
                            <span class="inline-block">{{ $tontineName }} ({{ $tontinePayments->count() }})</span>
                            @if(!$loop->last) · @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('admin.tontines.create') }}"
           class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium text-sm transition">
            {{ __('app.dashboard.new_tontine') }}
        </a>
        <a href="{{ route('admin.tontines.index') }}"
           class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg font-medium text-sm transition">
            {{ __('app.dashboard.manage_tontines') }}
        </a>
        <a href="{{ route('users.index') }}"
           class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg font-medium text-sm transition">
            {{ __('app.dashboard.manage_participants') }}
        </a>
    </div>

    @if($recentRounds->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">{{ __('app.dashboard.recent_results') }}</h2>
        </div>

        {{-- Mobile : cartes --}}
        <div class="sm:hidden divide-y divide-gray-100">
            @foreach($recentRounds as $round)
            <div class="px-4 py-4 space-y-1">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium text-gray-900 text-sm">{{ $round->tontine->name }}</span>
                    <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full shrink-0">{{ __('app.status.'.$round->status) }}</span>
                </div>
                <div class="text-xs text-gray-500">Tour #{{ $round->round_number }} · {{ number_format($round->pot_amount, 2) }} €</div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-700">{{ $round->winner?->full_name ?? '—' }}</span>
                    @if($round->drawn_by_lot)
                        <span class="bg-yellow-100 text-yellow-700 text-xs px-2 py-0.5 rounded-full">{{ __('app.round.by_lot_badge') }}</span>
                    @else
                        <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">{{ __('app.round.by_bid_badge') }}</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        {{-- Desktop : tableau --}}
        <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-6 py-3 text-left">{{ __('app.round.col_tontine') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('app.round.col_round') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('app.round.col_winner') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('app.round.col_pot') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('app.round.col_mode') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('app.round.col_status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($recentRounds as $round)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium text-gray-900">{{ $round->tontine->name }}</td>
                    <td class="px-6 py-4 text-gray-600">#{{ $round->round_number }}</td>
                    <td class="px-6 py-4">{{ $round->winner?->full_name ?? '—' }}</td>
                    <td class="px-6 py-4">{{ number_format($round->pot_amount, 2) }} €</td>
                    <td class="px-6 py-4">
                        @if($round->drawn_by_lot)
                            <span class="bg-yellow-100 text-yellow-700 text-xs px-2 py-0.5 rounded-full">{{ __('app.round.by_lot_badge') }}</span>
                        @else
                            <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">{{ __('app.round.by_bid_badge') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full">{{ __('app.status.'.$round->status) }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
    @endif
</div>
@endsection
