@extends('layouts.app')
@section('title', $tontine->name)

@section('content')
<div class="space-y-6">

    {{-- En-tête --}}
    <div>
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
            <a href="{{ route('participant.dashboard') }}" class="hover:text-indigo-600">{{ __('app.nav.my_space') }}</a>
            <span>/</span>
            <span>{{ $tontine->name }}</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">{{ $tontine->name }}</h1>
        @if($tontine->description)
            <p class="text-gray-500 mt-1">{{ $tontine->description }}</p>
        @endif
    </div>

    {{-- Stats rapides --}}
    <div class="grid grid-cols-3 gap-3">
        <div class="bg-white border border-gray-200 rounded-xl p-3 sm:p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase leading-tight">{{ __('app.space.cotisation') }}</div>
            <div class="text-lg sm:text-xl font-bold text-gray-900 mt-1">{{ number_format($tontine->cotisation_amount,2) }} €</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-3 sm:p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase leading-tight">{{ __('app.space.rounds_count') }}</div>
            <div class="text-lg sm:text-xl font-bold text-gray-900 mt-1">{{ $tontine->rounds->count() }}</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-3 sm:p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase leading-tight">{{ __('app.space.bid_cap') }}</div>
            <div class="text-lg sm:text-xl font-bold text-gray-900 mt-1">{{ $tontine->bid_cap }}%</div>
        </div>
    </div>

    @if($isMember)
    @php
        $me            = auth()->user();
        $myParticipant = $tontine->participants->firstWhere('id', $me->primaryUser()->id);
        $myPivot       = $myParticipant?->pivot ?? null;

        if ($me->partner_id !== null) {
            $primary   = $tontine->participants->firstWhere('id', $me->partner->id ?? null);
            $sigStatus = $primary?->pivot->partner_signature_status;
            $sigUrl    = $primary?->pivot->partner_signature_signer_url;
            $signedAt  = $primary?->pivot->partner_signed_at;
            $signedPdf = $primary?->pivot->partner_signed_pdf_url;
            $sigError  = $primary?->pivot->partner_signature_error;
        } else {
            $sigStatus = $myPivot?->signature_status;
            $sigUrl    = $myPivot?->signature_signer_url;
            $signedAt  = $myPivot?->signed_at;
            $signedPdf = $myPivot?->signed_pdf_url;
            $sigError  = $myPivot?->signature_error;
        }

        $sigBadge = match($sigStatus) {
            'signed'   => ['bg-green-100',  'text-green-700',  '✅ Signé'],
            'pending'  => ['bg-yellow-100', 'text-yellow-700', '⏳ En attente de signature'],
            'opened'   => ['bg-blue-100',   'text-blue-700',   '👁 Document ouvert'],
            'declined' => ['bg-red-100',    'text-red-700',    '✗ Refusé'],
            'expired'  => ['bg-orange-100', 'text-orange-700', '⚠ Lien expiré'],
            'failed'   => ['bg-red-100',    'text-red-700',    '✗ Échec d\'envoi'],
            default    => ['bg-gray-100',   'text-gray-500',   '— Non envoyé'],
        };

        $pendingPaymentsCount = $myPayments->filter(fn($p) => $p->status !== 'paid')->count();
        $latePaymentsCount    = $myPayments->filter(fn($p) => $p->status === 'late' || ($p->status === 'pending' && $p->daysLate() > 0))->count();
        $meId = $me->primaryUser()->id;

        // Trésoriers de la tontine pour le sélecteur d'aide
        $tontineAdmins = collect();
        $adminLocalIds = \Illuminate\Support\Facades\DB::table('admin_local_tontines')
            ->where('tontine_id', $tontine->id)->where('can_edit', true)->pluck('user_id');
        foreach (\App\Models\User::whereIn('id', $adminLocalIds)->get() as $adm) {
            $tontineAdmins->push(['id' => $adm->id, 'name' => $adm->full_name, 'role' => 'Administrateur']);
        }
        foreach ($tontine->tresoriers as $tr) {
            if (!$tontineAdmins->firstWhere('id', $tr->id)) {
                $tontineAdmins->push(['id' => $tr->id, 'name' => $tr->full_name, 'role' => 'Trésorier']);
            }
        }
    @endphp

    {{-- ONGLETS --}}
    <div x-data="{ tab: '{{ session('success') || session('error') ? 'aide' : 'tour' }}' }">

        {{-- Barre d'onglets --}}
        <div class="flex gap-0 border-b border-gray-200 overflow-x-auto">
            <button @click="tab='tour'"
                    :class="tab==='tour' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-gray-500 hover:text-gray-700'"
                    class="inline-flex items-center px-5 py-3 text-sm font-medium transition whitespace-nowrap">
                Tour
                @if($currentRound && $currentRound->isOpen())
                <span class="ml-1.5 text-xs bg-indigo-100 text-indigo-600 px-1.5 py-0.5 rounded-full">Live</span>
                @endif
            </button>
            <button @click="tab='paiements'"
                    :class="tab==='paiements' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-gray-500 hover:text-gray-700'"
                    class="inline-flex items-center px-5 py-3 text-sm font-medium transition whitespace-nowrap">
                Paiements
                @if($latePaymentsCount > 0)
                <span class="ml-1.5 text-xs bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full">{{ $latePaymentsCount }}</span>
                @elseif($pendingPaymentsCount > 0)
                <span class="ml-1.5 text-xs bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded-full">{{ $pendingPaymentsCount }}</span>
                @endif
            </button>
            <button @click="tab='annuaire'"
                    :class="tab==='annuaire' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm font-medium transition">
                Annuaire
            </button>
            <button @click="tab='aide'"
                    :class="tab==='aide' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm font-medium transition">
                Aide
            </button>
        </div>

        {{-- ══ TOUR ══════════════════════════════════════════════════════════ --}}
        <div x-show="tab==='tour'" class="space-y-6 pt-5">

            {{-- Signature --}}
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-base">✍️</span>
                            <h3 class="text-sm font-semibold text-gray-800">Signature électronique du règlement</h3>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center text-xs font-medium px-2.5 py-1 rounded-full {{ $sigBadge[0] }} {{ $sigBadge[1] }}">
                                {{ $sigBadge[2] }}
                            </span>
                            @if($sigStatus === 'signed' && $signedAt)
                                <span class="text-xs text-gray-400">le {{ \Carbon\Carbon::parse($signedAt)->format('d/m/Y') }}</span>
                            @endif
                        </div>
                        @if($sigStatus === null || $sigStatus === '')
                            <p class="text-xs text-gray-400 mt-1.5">Le document n'a pas encore été envoyé par l'administrateur.</p>
                        @elseif($sigStatus === 'pending')
                            <p class="text-xs text-yellow-600 mt-1.5">Veuillez cliquer sur le bouton ci-contre pour signer le règlement.</p>
                        @elseif($sigStatus === 'expired')
                            <p class="text-xs text-orange-600 mt-1.5">Le lien de signature a expiré. Contactez l'administrateur.</p>
                        @elseif($sigStatus === 'declined')
                            <p class="text-xs text-red-600 mt-1.5">Vous avez refusé de signer. Contactez l'administrateur si c'est une erreur.</p>
                        @elseif($sigStatus === 'failed' && $sigError)
                            <p class="text-xs text-red-500 mt-1.5">{{ $sigError }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 flex-wrap shrink-0">
                        @if(in_array($sigStatus, ['pending', 'opened']) && $sigUrl)
                            <a href="{{ $sigUrl }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                                ✍️ Signer le règlement
                            </a>
                        @endif
                        @if($sigStatus === 'signed' && $signedPdf)
                            <a href="{{ $signedPdf }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 font-medium border border-indigo-200 px-3 py-1.5 rounded-lg hover:bg-indigo-50 transition">
                                📄 Télécharger le PDF signé
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Tour courant --}}
            @if($currentRound && $currentRound->isOpen())
            @php
                $myWinsCount      = $myParticipant?->pivot->wins_count ?? 0;
                $mySlots          = $myParticipant?->pivot->slots ?? 1;
                $canBid           = $myWinsCount < $mySlots;
                $bidIsClosed      = $currentRound->bid_closes_at->isPast();
                $signatureBlocked = $tontine->bid_requires_signature && $sigStatus !== 'signed';
                $totalSlots       = $tontine->participants->sum(fn($p) => $p->pivot->slots);
                $stdCount         = max(1, $tontine->rounds->where('type', 'standard')->count());
                $advancePerRound  = ($tontine->has_preliminary && $tontine->preliminary_amount)
                    ? round($totalSlots * (float) $tontine->preliminary_amount / $stdCount, 2)
                    : 0;
            @endphp
            <div class="{{ $canBid ? 'bg-gradient-to-br from-indigo-50 to-purple-50 border-2 border-indigo-300' : 'bg-gray-50 border border-gray-200' }} rounded-2xl overflow-hidden"
                 x-data="countdown('{{ $currentRound->bid_closes_at->toISOString() }}', '{{ __('app.round.closed_label') }}')">
                <div class="px-6 py-6">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-6 gap-4">
                        <div>
                            <div class="text-xs {{ $canBid ? 'text-indigo-600' : 'text-gray-500' }} uppercase tracking-widest font-semibold mb-1">{{ __('app.space.bid_open_label') }}</div>
                            <h2 class="text-2xl font-bold text-gray-900">{{ __('app.round.col_round') }} #{{ $currentRound->round_number }}</h2>
                            <div class="text-gray-600 mt-1">
                                {{ __('app.round.pot') }} : <strong class="{{ $canBid ? 'text-indigo-700' : 'text-gray-700' }}">{{ number_format($currentRound->pot_amount,2) }} €</strong>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2 shrink-0">
                            <div class="bg-white rounded-xl px-5 py-3 shadow-sm border {{ $canBid ? 'border-indigo-200' : 'border-gray-200' }} text-center sm:text-right">
                                <div class="text-xs text-gray-400 uppercase">{{ __('app.space.closes_in') }}</div>
                                <div class="text-3xl font-bold"
                                     :class="isUrgent() ? 'text-red-600 countdown-urgent' : '{{ $canBid ? 'text-indigo-700' : 'text-gray-600' }}'"
                                     x-text="format()">...</div>
                                <div class="text-xs text-gray-400 mt-1">{{ $currentRound->bid_closes_at->format('d/m/Y H:i') }}</div>
                            </div>
                            @if($currentRound->payout_date)
                            <div class="bg-indigo-50 rounded-xl px-5 py-3 border border-indigo-200 text-center sm:text-right">
                                <div class="text-xs text-indigo-400 uppercase">Versement prévu</div>
                                <div class="text-lg font-bold text-indigo-700">{{ $currentRound->payout_date->format('d/m/Y') }}</div>
                            </div>
                            @endif
                        </div>
                    </div>

                    @if($tontine->has_bidding)
                    @php
                        $allBids    = $currentRound->bids;
                        $highestBid = $allBids->max('amount');
                        $bidsCount  = $allBids->count();
                        $iAmLeading = $myBidOnCurrent && $highestBid !== null && (float) $myBidOnCurrent->amount === (float) $highestBid;
                        $iAmTied    = $iAmLeading && $allBids->where('amount', $highestBid)->count() > 1;
                    @endphp
                    <div class="bg-white rounded-xl border border-gray-200 px-4 py-4 mb-5 flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">{{ __('app.bid.highest') }}</div>
                            @if($highestBid !== null)
                                <div class="text-3xl font-bold text-indigo-700">{{ $highestBid }}%</div>
                                <div class="text-xs text-gray-400 mt-0.5">{{ __('app.bid.bids_placed', ['count' => $bidsCount]) }}</div>
                            @else
                                <div class="text-lg font-semibold text-gray-400">{{ __('app.bid.no_bids_yet') }}</div>
                            @endif
                        </div>
                        @if($iAmLeading)
                            <span class="inline-flex items-center gap-1.5 bg-green-100 text-green-700 text-sm font-medium px-3 py-1.5 rounded-full">
                                ✅ {{ $iAmTied ? __('app.bid.you_tied') : __('app.bid.you_lead') }}
                            </span>
                        @endif
                    </div>
                    @endif

                    @if($canBid && $signatureBlocked)
                    <div class="bg-amber-50 border border-amber-300 rounded-xl p-5 text-center space-y-3">
                        <div class="text-3xl">✍️</div>
                        <p class="font-semibold text-amber-800">Signature du règlement requise</p>
                        <p class="text-sm text-amber-700">Vous devez signer le règlement avant de pouvoir placer une enchère.</p>
                        @if(in_array($sigStatus, ['pending', 'opened']) && $sigUrl)
                            <a href="{{ $sigUrl }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition">
                                ✍️ Signer le règlement
                            </a>
                        @endif
                    </div>
                    @elseif($canBid)
                    <div class="bg-white/70 rounded-xl p-4 mb-6 text-sm text-gray-600 space-y-1">
                        <p>{{ __('app.space.rules_title') }} {!! __('app.space.rule1', ['cap' => $tontine->bid_cap]) !!}</p>
                        <p>🎲 {!! __('app.space.rule2') !!}</p>
                        <p>🎲 {!! __('app.space.rule3') !!}</p>
                        <p>💡 {{ __('app.space.rule4') }}</p>
                    </div>

                    @if($myBidOnCurrent)
                    <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-4 flex items-center justify-between">
                        <div>
                            <div class="text-sm text-green-700 font-medium">{{ __('app.bid.current') }}</div>
                            <div class="text-2xl font-bold text-green-800">{{ $myBidOnCurrent->amount }}%</div>
                            <div class="text-xs text-green-600">{{ __('app.bid.placed_on') }} {{ $myBidOnCurrent->bid_at->format('d/m/Y à H:i') }}</div>
                        </div>
                        <div class="text-4xl">{{ __('app.bid.target') }}</div>
                    </div>
                    @endif

                    @if($bidIsClosed)
                    <div class="bg-gray-100 border border-gray-200 rounded-xl p-4 text-center text-gray-500 text-sm">
                        🔒 {{ __('app.bid.closed_label') }}
                    </div>
                    @else
                    <form method="POST" action="{{ route('participant.bid', $currentRound) }}" class="space-y-4"
                          x-data="{ bid: {{ (float) ($myBidOnCurrent?->amount ?? 0) }}, pot: {{ (float) $currentRound->pot_amount }}, advance: {{ (float) $advancePerRound }} }">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ $myBidOnCurrent ? __('app.bid.update') : __('app.bid.place') }}
                                {{ __('app.bid.range', ['cap' => $tontine->bid_cap]) }}
                            </label>
                            <div class="flex gap-3">
                                <input type="number" name="amount" min="0" max="{{ $tontine->bid_cap }}" step="1"
                                       x-model.number="bid" value="{{ $myBidOnCurrent?->amount ?? '' }}"
                                       placeholder="Ex: {{ $tontine->bid_cap }}" required
                                       class="flex-1 border-2 border-indigo-300 focus:border-indigo-500 rounded-xl px-4 py-3 text-lg font-bold text-center outline-none transition">
                                <span class="flex items-center text-gray-500 font-bold text-xl">%</span>
                            </div>
                            @error('amount')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div x-show="bid >= 0 && bid <= {{ (int) $tontine->bid_cap }}"
                             class="bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3 space-y-2">
                            <div class="text-xs font-semibold text-indigo-500 uppercase tracking-wide">
                                Simulation — si vous remportez ce tour à <span x-text="bid"></span>%
                            </div>
                            <div class="flex flex-col gap-1 text-sm font-mono">
                                <div class="flex justify-between text-gray-600">
                                    <span>Cotisations</span>
                                    <span x-text="'+ ' + new Intl.NumberFormat('fr-FR',{minimumFractionDigits:0,maximumFractionDigits:2}).format(pot-advance) + ' €'"></span>
                                </div>
                                <div class="flex justify-between text-gray-400" x-show="advance > 0">
                                    <span>Avance (non soumise)</span>
                                    <span x-text="'+ ' + new Intl.NumberFormat('fr-FR',{minimumFractionDigits:0,maximumFractionDigits:2}).format(advance) + ' €'"></span>
                                </div>
                                <div class="flex justify-between text-red-500" x-show="bid > 0">
                                    <span>Abattement (<span x-text="bid"></span>% sur cotisations)</span>
                                    <span x-text="'− ' + new Intl.NumberFormat('fr-FR',{minimumFractionDigits:0,maximumFractionDigits:2}).format((pot-advance)*bid/100) + ' €'"></span>
                                </div>
                                <div class="border-t border-indigo-200 pt-1 flex justify-between font-bold text-indigo-700 text-base">
                                    <span>Vous percevrez</span>
                                    <span x-text="new Intl.NumberFormat('fr-FR',{minimumFractionDigits:0,maximumFractionDigits:2}).format(advance+(pot-advance)*(1-bid/100)) + ' €'"></span>
                                </div>
                            </div>
                        </div>
                        <button type="submit"
                                class="w-full bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white py-3 rounded-xl font-bold text-lg transition shadow-md">
                            {{ $myBidOnCurrent ? __('app.bid.update_btn') : __('app.bid.submit_btn') }}
                        </button>
                    </form>
                    @endif

                    @else
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-center space-y-2">
                        <div class="text-3xl">🏆</div>
                        <p class="font-semibold text-amber-800">{{ __('app.space.cannot_bid') }}</p>
                        <p class="text-sm text-amber-700">{{ __('app.space.already_won_hint') }}</p>
                    </div>
                    @endif
                </div>
            </div>

            @elseif($currentRound && $currentRound->status === 'pending')
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 text-center">
                <div class="text-3xl mb-2">⏳</div>
                <h2 class="font-semibold text-gray-800">{{ __('app.space.not_open', ['number' => $currentRound->round_number]) }}</h2>
                <p class="text-gray-500 text-sm mt-1">{{ __('app.space.opens_at') }} {{ $currentRound->bid_opens_at->format('d/m/Y à H:i') }}</p>
            </div>
            @else
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 text-center">
                <div class="text-3xl mb-2">🔒</div>
                <h2 class="font-semibold text-gray-700">{{ __('app.space.no_round') }}</h2>
                <p class="text-gray-500 text-sm mt-1">{{ __('app.space.email_notif') }}</p>
            </div>
            @endif

            {{-- Délégués --}}
            @if($delegatees->isNotEmpty() && $currentRound && $currentRound->isOpen() && $tontine->has_bidding)
            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-gray-800">🔁 Enchérir pour mes délégués</h2>
                @foreach($delegatees as $del)
                @php $delCanBid = $del['canBid']; $delBid = $del['myBid']; @endphp
                <div class="{{ $delCanBid ? 'bg-amber-50 border-amber-300 border-2' : 'bg-gray-50 border-gray-200 border' }} rounded-2xl overflow-hidden">
                    <div class="px-6 py-5">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-9 h-9 rounded-full bg-amber-100 flex items-center justify-center text-amber-700 font-bold text-sm">
                                {{ strtoupper(mb_substr($del['user']->first_name ?: $del['user']->name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">{{ $del['user']->full_name }}</p>
                                <p class="text-xs text-gray-500">vous enchérissez en son nom</p>
                            </div>
                        </div>
                        @if($delBid)
                        <div class="bg-green-50 border border-green-200 rounded-xl p-3 mb-4 flex items-center justify-between">
                            <div>
                                <div class="text-sm text-green-700 font-medium">Enchère en cours</div>
                                <div class="text-xl font-bold text-green-800">{{ $delBid->amount }}%</div>
                                <div class="text-xs text-green-600">{{ $delBid->bid_at->format('d/m/Y à H:i') }}</div>
                            </div>
                            <div class="text-3xl">🎯</div>
                        </div>
                        @endif
                        @if($delCanBid)
                        <form method="POST" action="{{ route('participant.bid', $currentRound) }}" class="flex gap-3 items-end">
                            @csrf
                            <input type="hidden" name="bid_as_user_id" value="{{ $del['user']->id }}">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ $delBid ? 'Modifier l\'enchère' : 'Placer une enchère' }} (0–{{ $tontine->bid_cap }}%)
                                </label>
                                <div class="flex gap-2">
                                    <input type="number" name="amount" min="0" max="{{ $tontine->bid_cap }}" step="1"
                                           value="{{ $delBid?->amount ?? '' }}" placeholder="{{ $tontine->bid_cap }}" required
                                           class="flex-1 border-2 border-amber-300 focus:border-amber-500 rounded-xl px-4 py-3 text-lg font-bold text-center outline-none transition">
                                    <span class="flex items-center text-gray-500 font-bold text-xl">%</span>
                                </div>
                            </div>
                            <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white px-5 py-3 rounded-xl font-bold transition shadow-sm whitespace-nowrap">
                                {{ $delBid ? 'Modifier' : 'Enchérir' }}
                            </button>
                        </form>
                        @else
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-center text-sm text-amber-700">
                            🏆 A déjà remporté tous ses tours dans cette tontine.
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Historique des tours --}}
            @if($tontine->rounds->whereIn('status',['drawn','paid'])->isNotEmpty())
            <div>
                <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ __('app.space.round_history') }}</h2>
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                            <tr>
                                <th class="px-6 py-3 text-left">{{ __('app.space.col_round') }}</th>
                                <th class="px-6 py-3 text-left">{{ __('app.space.col_winner') }}</th>
                                <th class="px-6 py-3 text-left">{{ __('app.space.col_bid') }}</th>
                                <th class="px-6 py-3 text-left">{{ __('app.space.col_pot') }}</th>
                                <th class="px-6 py-3 text-left">{{ __('app.space.col_mode') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($tontine->rounds->whereIn('status',['drawn','paid'])->sortByDesc('round_number') as $round)
                            <tr class="{{ $round->winner_id === auth()->id() ? 'bg-yellow-50' : 'hover:bg-gray-50' }}">
                                <td class="px-6 py-3 font-medium">#{{ $round->round_number }}</td>
                                <td class="px-6 py-3">
                                    {{ $round->winner?->full_name ?? '—' }}
                                    @if($round->winner_id === auth()->id())
                                        <span class="text-yellow-500 ml-1">{{ __('app.space.you') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3">{{ $round->winning_bid }}%</td>
                                <td class="px-6 py-3">{{ number_format($round->pot_amount,2) }} €</td>
                                <td class="px-6 py-3">
                                    @if($round->drawn_by_lot)
                                        <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full">{{ __('app.space.by_lot') }}</span>
                                    @else
                                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">{{ __('app.space.by_bid') }}</span>
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

        </div>{{-- /tab tour --}}

        {{-- ══ PAIEMENTS ═════════════════════════════════════════════════════ --}}
        <div x-show="tab==='paiements'" x-cloak class="pt-5">
            @if($myPayments->isNotEmpty())
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                        <tr>
                            <th class="px-6 py-3 text-left">Tour</th>
                            <th class="px-6 py-3 text-left">Montant</th>
                            <th class="px-6 py-3 text-left">Échéance</th>
                            <th class="px-6 py-3 text-left">Statut</th>
                            <th class="px-6 py-3 text-left">Payé le</th>
                            @if($tontine->penalty_per_day > 0)
                            <th class="px-6 py-3 text-left">Pénalité</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($myPayments as $payment)
                        @php
                            $daysLate = $payment->daysLate();
                            $isLate   = $payment->status === 'late' || ($payment->status === 'pending' && $daysLate > 0);
                            $penalty  = $tontine->penalty_per_day > 0
                                ? $payment->penaltyAmount((float) $tontine->penalty_per_day, $tontine->penalty_cap !== null ? (float) $tontine->penalty_cap : null)
                                : 0;
                        @endphp
                        <tr class="{{ $isLate && $payment->status !== 'paid' ? 'bg-red-50' : ($payment->status === 'paid' ? 'bg-green-50/40' : 'hover:bg-gray-50') }}">
                            <td class="px-6 py-3 font-medium text-gray-900">
                                @if($payment->round->isPreliminary())
                                    <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Préliminaire</span>
                                @else
                                    Tour #{{ $payment->round->round_number }}
                                @endif
                            </td>
                            <td class="px-6 py-3 font-semibold text-gray-900">{{ number_format($payment->amount, 2) }} €</td>
                            <td class="px-6 py-3 {{ $isLate && $payment->status !== 'paid' ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                                {{ $payment->due_date ? $payment->due_date->format('d/m/Y') : '—' }}
                                @if($daysLate > 0 && $payment->status !== 'paid')
                                    <span class="text-xs ml-1">({{ $daysLate }}j)</span>
                                @endif
                            </td>
                            <td class="px-6 py-3">
                                @if($payment->status === 'paid')
                                    <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-xs font-medium px-2 py-0.5 rounded-full">✓ Réglé</span>
                                @elseif($isLate)
                                    <span class="inline-flex items-center gap-1 bg-red-100 text-red-700 text-xs font-medium px-2 py-0.5 rounded-full">⚠ En retard</span>
                                @else
                                    <span class="inline-flex items-center gap-1 bg-yellow-100 text-yellow-700 text-xs font-medium px-2 py-0.5 rounded-full">À régler</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-gray-500">{{ $payment->paid_at ? $payment->paid_at->format('d/m/Y') : '—' }}</td>
                            @if($tontine->penalty_per_day > 0)
                            <td class="px-6 py-3 {{ $penalty > 0 ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                                {{ $penalty > 0 ? number_format($penalty, 2).' €' : '—' }}
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
            @else
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-8 text-center text-gray-400">
                Aucun paiement enregistré pour cette tontine.
            </div>
            @endif
        </div>{{-- /tab paiements --}}

        {{-- ══ ANNUAIRE ══════════════════════════════════════════════════════ --}}
        <div x-show="tab==='annuaire'" x-cloak class="pt-5">
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm divide-y divide-gray-100">
                @foreach($tontine->participants->where('partner_id', null)->sortBy('name') as $member)
                @php
                    $partner = $member->partnerOf ?? null;
                    $isMe    = $member->id === $meId || ($partner && $partner->id === $meId);
                @endphp
                <div class="px-5 py-4 flex items-start gap-4 {{ $isMe ? 'bg-indigo-50/50' : '' }}">
                    <div class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center font-bold text-sm
                        {{ $isMe ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ strtoupper(mb_substr($member->first_name ?: $member->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-gray-900">{{ $member->full_name }}</span>
                            @if($isMe)<span class="text-xs bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full">Vous</span>@endif
                            @if($partner)
                                <span class="text-xs text-gray-400">+</span>
                                <span class="font-medium text-gray-900">{{ $partner->full_name }}</span>
                                <span class="text-xs bg-purple-100 text-purple-600 px-2 py-0.5 rounded-full">Binôme</span>
                            @endif
                        </div>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-sm text-gray-500">
                            @if($member->phone)<a href="tel:{{ $member->phone }}" class="hover:text-indigo-600">{{ $member->phone }}</a>@endif
                            <a href="mailto:{{ $member->email }}" class="hover:text-indigo-600 truncate">{{ $member->email }}</a>
                        </div>
                        @if($partner)
                        <div class="mt-0.5 flex flex-wrap gap-x-4 gap-y-0.5 text-sm text-gray-500">
                            @if($partner->phone)<a href="tel:{{ $partner->phone }}" class="hover:text-indigo-600">{{ $partner->phone }}</a>@endif
                            <a href="mailto:{{ $partner->email }}" class="hover:text-indigo-600 truncate">{{ $partner->email }}</a>
                        </div>
                        @endif
                    </div>
                    @if($member->pivot->wins_count > 0)
                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full self-center flex-shrink-0">Tour reçu</span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>{{-- /tab annuaire --}}

        {{-- ══ AIDE ══════════════════════════════════════════════════════════ --}}
        <div x-show="tab==='aide'" x-cloak class="pt-5">
            @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm mb-4">
                {{ session('success') }}
            </div>
            @endif
            @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm mb-4">
                {{ session('error') }}
            </div>
            @endif

            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-semibold text-gray-800 mb-1">Contacter un responsable</h2>
                <p class="text-sm text-gray-500 mb-5">Envoyez un message à l'administrateur ou au trésorier de cette tontine. Il vous répondra directement par email.</p>

                <form method="POST" action="{{ route('participant.contact', $tontine) }}" class="space-y-4">
                    @csrf

                    @if($tontineAdmins->count() > 1)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Destinataire <span class="text-red-500">*</span></label>
                        <select name="recipient_id" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none bg-white">
                            @foreach($tontineAdmins as $admin)
                            <option value="{{ $admin['id'] }}" {{ old('recipient_id') == $admin['id'] ? 'selected' : '' }}>
                                {{ $admin['name'] }} — {{ $admin['role'] }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    @elseif($tontineAdmins->count() === 1)
                    <input type="hidden" name="recipient_id" value="{{ $tontineAdmins->first()['id'] }}">
                    <p class="text-sm text-gray-500">
                        Votre message sera envoyé à <strong>{{ $tontineAdmins->first()['name'] }}</strong>
                        ({{ $tontineAdmins->first()['role'] }}).
                    </p>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Objet <span class="text-red-500">*</span></label>
                        <input type="text" name="subject" value="{{ old('subject') }}" required maxlength="150"
                               placeholder="Ex : Question sur mon paiement"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none">
                        @error('subject')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Message <span class="text-red-500">*</span></label>
                        <textarea name="body" rows="6" required maxlength="2000"
                                  placeholder="Décrivez votre demande..."
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none resize-none">{{ old('body') }}</textarea>
                        @error('body')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-medium">
                            Envoyer le message
                        </button>
                        <span class="text-xs text-gray-400">Votre email ({{ auth()->user()->email }}) sera visible du destinataire.</span>
                    </div>
                </form>
            </div>
        </div>{{-- /tab aide --}}

    </div>{{-- /x-data tabs --}}
    @endif

</div>

<x-help-panel title="Cette tontine">
    <p><strong>Tour</strong> — enchérissez sur le tour en cours. L'enchère la plus haute gagne la cagnotte, déduction faite du taux proposé.</p>
    <p><strong>0%</strong> — enchérir à 0% signifie que vous acceptez de recevoir la cagnotte intégrale si personne n'enchérit plus.</p>
    <p><strong>Paiements</strong> — consultez l'état de vos cotisations. Un badge rouge indique un retard.</p>
    <p><strong>Annuaire</strong> — coordonnées des membres de la tontine.</p>
    <p><strong>Aide</strong> — envoyez un message à l'administrateur ou au trésorier de la tontine.</p>
</x-help-panel>

@endsection
