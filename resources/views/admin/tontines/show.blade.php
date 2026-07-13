@extends('layouts.app')
@section('title', $tontine->name)

@php
    $locked          = $tontine->participants_locked;
    $roundsGenerated = $tontine->rounds->isNotEmpty();
    $tresorier       = $tontine->tresoriers()->first();
    $hasOpenRound    = $tontine->rounds->contains('status', 'open');
    $pi              = $tontine->payment_info ?? [];
    $pendingInvitations = $tontine->invitations->whereNull('accepted_at')->where('expires_at', '>', now());
    // Compteurs de signature : on tient compte des partenaires de binôme s'ils existent
    $signedCount = 0;
    $totalSignatures = 0;
    foreach ($tontine->participants as $_p) {
        $hasPartner = ($_p->partnerOf || $_p->partner);
        $totalSignatures += $hasPartner ? 2 : 1;
        if (($_p->pivot->signature_status ?? null) === 'signed') $signedCount++;
        if ($hasPartner && ($_p->pivot->partner_signature_status ?? null) === 'signed') $signedCount++;
    }
    $totalCount = $tontine->participants->count();
@endphp

@section('content')
<div class="space-y-6"
     x-data="{
        tab: new URL(window.location.href).searchParams.get('tab')
             || localStorage.getItem('tontine_{{ $tontine->id }}_tab')
             || 'tours',
        subtab: new URL(window.location.href).searchParams.get('subtab')
                || localStorage.getItem('tontine_{{ $tontine->id }}_subtab')
                || 'reglement',
        replaceModal: false, replaceUserId: null, replaceUserName: '', replaceMode: 'existing',
        inviteOpen: false,
        editDateRound: null,
     }"
     x-init="
        $watch('tab',    v => localStorage.setItem('tontine_{{ $tontine->id }}_tab', v));
        $watch('subtab', v => localStorage.setItem('tontine_{{ $tontine->id }}_subtab', v));
     ">

    {{-- ════════════════════════ HEADER ════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('admin.tontines.index') }}" class="hover:text-indigo-600">{{ __('app.tontine.title') }}</a>
                <span>/</span>
                <span>{{ $tontine->name }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $tontine->name }}</h1>
            <p class="text-gray-500 mt-1">{{ $tontine->description }}</p>
        </div>
        <div class="flex gap-2 flex-wrap shrink-0">
            @if($tontine->status !== 'archived')
                <form method="POST" action="{{ route('admin.tontines.archive', $tontine) }}"
                      onsubmit="return confirm('{{ __('app.tontine.archive_confirm', ['name' => addslashes($tontine->name)]) }}')">
                    @csrf @method('PATCH')
                    <button class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 px-3 py-2 rounded-lg text-sm font-medium">
                        {{ __('app.tontine.archive_btn') }}
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.tontines.destroy', $tontine) }}"
                      onsubmit="return confirm('{{ __('app.tontine.delete_confirm1', ['name' => addslashes($tontine->name)]) }}') && confirm('{{ __('app.tontine.delete_confirm2') }}')">
                    @csrf @method('DELETE')
                    <button class="bg-white border border-red-300 hover:bg-red-50 text-red-600 px-3 py-2 rounded-lg text-sm font-medium">
                        🗑 {{ __('app.common.delete') }}
                    </button>
                </form>
            @endif
            <a href="{{ route('admin.tontines.regulation.preview', $tontine) }}" target="_blank" rel="noopener"
               class="bg-white border border-indigo-300 hover:bg-indigo-50 text-indigo-700 px-3 py-2 rounded-lg text-sm font-medium"
               title="Visualiser le règlement personnalisé de la tontine dans un nouvel onglet">
                📄 Règlement
            </a>
            <a href="{{ route('admin.tontines.edit', $tontine) }}"
               class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium">
                {{ __('app.tontine.edit_btn') }}
            </a>
            @if($tontine->rounds->isNotEmpty())
                <a href="{{ route('admin.rounds.create', $tontine) }}?type=preliminary"
                   class="bg-amber-500 hover:bg-amber-600 text-white px-3 py-2 rounded-lg text-sm font-medium">
                    {{ __('app.tontine.preliminary_btn') }}
                </a>
                <a href="{{ route('admin.rounds.create', $tontine) }}"
                   class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                    {{ __('app.tontine.standard_btn') }}
                </a>
            @endif
        </div>
    </div>

    {{-- ════════════════════════ STATS ════════════════════════ --}}
    @php
        $calculatedTours = $tontine->participants->isNotEmpty()
            ? (int) $tontine->participants->sum(fn($p) => (int) ($p->pivot->slots ?? 1))
            : ($tontine->rounds_count ?: $tontine->max_participants);
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase tracking-wide">{{ __('app.tontine.cotisation') }}</div>
            <div class="text-lg font-bold text-gray-900 mt-1">{{ number_format($tontine->cotisation_amount,2) }} €</div>
        </div>

        <div class="bg-white border border-indigo-100 rounded-xl p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Nombre de tours</div>
            <div class="text-lg font-bold text-indigo-700 mt-1">{{ $calculatedTours }}</div>
            <div class="text-xs text-gray-400 mt-0.5">
                calculé · {{ $tontine->rounds->count() }} généré(s)
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase tracking-wide">
                {{ $tontine->has_bidding ? __('app.tontine.bid_cap') : __('app.schedule.has_bidding') }}
            </div>
            <div class="text-lg font-bold text-gray-900 mt-1">
                {{ $tontine->has_bidding ? $tontine->bid_cap.'%' : '🎲 Tirage au sort' }}
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase tracking-wide">{{ __('app.common.status') }}</div>
            <div class="text-lg font-bold text-gray-900 mt-1">{{ __('app.status.'.$tontine->status) }}</div>
        </div>
    </div>

    @if($tontine->first_round_month)
    <div class="bg-indigo-50 border border-indigo-100 rounded-xl px-5 py-3 text-xs text-indigo-700 flex flex-wrap gap-x-6 gap-y-1">
        <span>📅 1er tour : <strong>{{ $tontine->first_round_month->isoFormat('MMMM YYYY') }}</strong></span>
        @if($tontine->bid_day_open)
            <span>Enchères : du <strong>{{ $tontine->bid_day_open }}</strong> au <strong>{{ $tontine->bid_day_close }}</strong> de chaque mois</span>
        @endif
        @if($tontine->payment_day)
            <span>Échéance paiement : le <strong>{{ $tontine->payment_day }}</strong> de chaque mois</span>
        @endif
        <span>{{ $tontine->rounds->count() }} {{ Str::plural('tour', $tontine->rounds->count()) }} générés</span>
    </div>
    @endif

    {{-- ════════════════════════ TABS NAV ════════════════════════ --}}
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-1 overflow-x-auto">
            <button type="button" @click="tab = 'tours'"
                    :class="tab === 'tours' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap inline-flex items-center gap-1 sm:gap-2 py-3 px-2.5 sm:px-4 border-b-2 text-sm font-medium transition">
                🎲 Tours <span class="text-xs bg-gray-100 px-1.5 py-0.5 rounded-full">{{ $tontine->rounds->count() }}</span>
                @if($hasOpenRound)<span class="w-2 h-2 rounded-full bg-green-500" title="Tour en cours"></span>@endif
            </button>
            <button type="button" @click="tab = 'membres'"
                    :class="tab === 'membres' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap inline-flex items-center gap-1 sm:gap-2 py-3 px-2.5 sm:px-4 border-b-2 text-sm font-medium transition">
                👥 Membres <span class="text-xs bg-gray-100 px-1.5 py-0.5 rounded-full">{{ $totalCount }}</span>
            </button>
            <button type="button" @click="tab = 'parametres'"
                    :class="tab === 'parametres' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap inline-flex items-center gap-1 sm:gap-2 py-3 px-2.5 sm:px-4 border-b-2 text-sm font-medium transition">
                ⚙️ Paramètres
            </button>
        </nav>
    </div>

    {{-- ═════════════════════════════════════════════════════════════════ --}}
    {{-- ONGLET « MEMBRES » (full width)                                  --}}
    {{-- ═════════════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'membres'" x-cloak class="space-y-6">

        @if($tresorier && ! $tontine->participants->contains('id', $tresorier->id))
            <div class="bg-purple-50 border border-purple-200 rounded-xl px-4 py-3 text-sm flex items-center justify-between gap-3">
                <div>
                    💼 <strong class="text-purple-900">{{ $tresorier->full_name }}</strong>
                    <span class="text-purple-700">est trésorier de cette tontine (non-membre).</span>
                </div>
                <form method="POST" action="{{ route('admin.tontines.tresorier.remove', $tontine) }}"
                      onsubmit="return confirm('Retirer le rôle de trésorier ?')">
                    @csrf @method('DELETE')
                    <button class="text-xs text-red-600 hover:text-red-800 font-medium">Retirer</button>
                </form>
            </div>
        @endif

        {{-- Désigner un trésorier non-membre (admin / superadmin / autre utilisateur) --}}
        @php
            $memberIds = $tontine->participants->pluck('id');
            $externalCandidates = \App\Models\User::where('active', true)
                ->whereNotIn('id', $memberIds)
                ->when($tresorier, fn($q) => $q->where('id', '!=', $tresorier->id))
                ->orderByRaw("CASE role
                    WHEN 'superadmin' THEN 1
                    WHEN 'admin' THEN 2
                    WHEN 'admin_local' THEN 3
                    WHEN 'tresorier' THEN 4
                    ELSE 5 END")
                ->orderBy('name')
                ->get();
            $roleLabels = [
                'superadmin'  => 'Super-admin',
                'admin'       => 'Admin',
                'admin_local' => 'Admin local',
                'tresorier'   => 'Trésorier',
                'participant' => 'Participant',
            ];
        @endphp
        @if($externalCandidates->isNotEmpty())
            <details class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden"
                     @if(! $tresorier) open @endif>
                <summary class="px-6 py-3 cursor-pointer text-sm font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                    💼 Désigner un trésorier non-membre (admin, superadmin, autre utilisateur)
                </summary>
                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
                    <form method="POST" action="{{ route('admin.tontines.tresorier.assign', $tontine) }}" class="flex flex-col sm:flex-row gap-2">
                        @csrf
                        <select name="user_id" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                            @foreach($externalCandidates as $u)
                                <option value="{{ $u->id }}">
                                    {{ $u->full_name }} ({{ $roleLabels[$u->role] ?? $u->role }}) — {{ $u->email }}
                                </option>
                            @endforeach
                        </select>
                        <button class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                            {{ $tresorier ? 'Changer le trésorier' : 'Assigner trésorier' }}
                        </button>
                    </form>
                    <p class="text-xs text-gray-500 mt-2">
                        Pour désigner un trésorier qui est <strong>membre</strong> de cette tontine, utilise le bouton « Désigner trésorier » dans la liste ci-dessous.
                    </p>
                </div>
            </details>
        @endif

        {{-- Carte Membres pleine largeur --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">

            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-3">
                    <h2 class="font-semibold text-gray-800">Membres ({{ $totalCount }})</h2>
                    @if($locked)
                        <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">
                            🔒 {{ __('app.tontine.participants_locked_badge') }}
                        </span>
                    @endif
                    @if($totalCount > 0)
                        <span class="text-xs text-gray-500">— {{ $signedCount }}/{{ $totalCount }} ont signé</span>
                    @endif
                </div>
                <div class="flex gap-2 flex-wrap">
                    @if(!$locked)
                    <button type="button" @click="inviteOpen = true"
                            class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-3 py-2 rounded-lg">
                        + Inviter
                    </button>
                    @endif
                    @if($roundsGenerated)
                        <form method="POST" action="{{ route('admin.tontines.participants.lock', $tontine) }}">
                            @csrf @method('PATCH')
                            <button class="text-xs px-3 py-2 rounded-lg border font-medium
                                {{ $locked ? 'border-amber-300 text-amber-700 hover:bg-amber-50' : 'border-gray-300 text-gray-600 hover:bg-gray-50' }}">
                                {{ $locked ? '🔓 '.__('app.tontine.unlock_participants_btn') : '🔒 '.__('app.tontine.lock_participants_btn') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Form unique pour la batch-signature --}}
            <form method="POST" action="{{ route('admin.tontines.regulation.send', $tontine) }}"
                  onsubmit="return confirm('Envoyer le règlement en signature aux membres sélectionnés ?')">
                @csrf

                {{-- Barre actions globales batch --}}
                <div class="px-6 py-2 border-b border-gray-100 bg-gray-50 flex items-center justify-between gap-3 flex-wrap text-xs">
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox"
                                   @change="document.querySelectorAll('input[name=&quot;user_ids[]&quot;]:not(:disabled)').forEach(cb => cb.checked = $event.target.checked)"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-gray-600 font-medium">Tout cocher</span>
                        </label>
                        <button type="button"
                                @click="document.querySelectorAll('input[name=&quot;user_ids[]&quot;]:not(:disabled)').forEach(cb => cb.checked = cb.dataset.status !== 'signed')"
                                class="text-indigo-600 hover:text-indigo-800 underline">
                            Cocher les non-signés
                        </button>
                        <button type="button"
                                @click="document.querySelectorAll('input[name=&quot;user_ids[]&quot;]:not(:disabled)').forEach(cb => cb.checked = ['failed', 'none', null].includes(cb.dataset.status || 'none'))"
                                class="text-red-600 hover:text-red-800 underline">
                            Cocher les échecs / jamais envoyés
                        </button>
                    </div>
                    <div class="flex gap-2 items-center">
                        <button type="submit" form="refresh-statuses-form"
                                title="Interroger DocuSeal pour mettre à jour le statut des signatures"
                                class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3 py-1.5 rounded-lg">
                            🔄 Rafraîchir
                        </button>
                        <button type="submit"
                                class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium px-4 py-1.5 rounded-lg">
                            📨 Envoyer en signature aux sélectionnés
                        </button>
                    </div>
                </div>

                {{-- Liste des membres --}}
                <div class="divide-y divide-gray-100">
                    @forelse($tontine->participants as $p)
                        @php
                            $isTresorier = $tresorier && $tresorier->id === $p->id;

                            // Secondaire d'un binôme : partner_id défini → pointe vers son primaire.
                            // Sa signature est trackée dans le pivot du primaire (partner_signature_*).
                            // On l'affiche différemment : il n'a pas de checkbox propre.
                            $isSecondary    = $p->partner_id !== null;
                            $primaryOfThis  = $isSecondary ? ($p->partner ?? null) : null;

                            $sigStatus = $p->pivot->signature_status ?? null;
                            $isSigned  = $sigStatus === 'signed';
                            $sigError  = $p->pivot->signature_error ?? null;

                            // Pour le PRIMAIRE : partnerOtherUser = son secondaire (partnerOf)
                            // ou, pour un double+binôme, son partner (si slots > 1 et pas de partnerOf)
                            if (! $isSecondary) {
                                $partnerOtherUser = $p->partnerOf ?? null;
                                if (! $partnerOtherUser && ($p->pivot->slots ?? 1) > 1) {
                                    $partnerOtherUser = $p->partner ?? null;
                                }
                            } else {
                                // Secondaire : son "partner" affiché est le primaire, mais son statut
                                // est dans le pivot du primaire → pas de $partnerSigStatus ici
                                $partnerOtherUser = null;
                            }

                            $partnerSigStatus = $partnerOtherUser ? ($p->pivot->partner_signature_status ?? null) : null;
                            $partnerSigError  = $p->pivot->partner_signature_error ?? null;
                            $partnerSigned    = $partnerSigStatus === 'signed';
                            $isFullySigned    = $isSigned && (! $partnerOtherUser || $partnerSigned);

                            $statusMap = fn($s) => match($s) {
                                'signed'   => ['✓', 'bg-green-100 text-green-700', 'Signé'],
                                'declined' => ['✗', 'bg-red-100 text-red-700', 'Refusé'],
                                'expired'  => ['⌛', 'bg-gray-200 text-gray-700', 'Expiré'],
                                'failed'   => ['⚠', 'bg-red-100 text-red-700', 'Échec d\'envoi'],
                                'pending', 'sent', 'opened' => ['⏳', 'bg-amber-100 text-amber-700', 'En attente'],
                                default    => [null, '', ''],
                            };
                            [$sigIcon, $sigColor, $sigLabel] = $statusMap($sigStatus);
                            [$partnerSigIcon, $partnerSigColor, $partnerSigLabel] = $statusMap($partnerSigStatus);
                        @endphp
                        {{-- Layout 2 colonnes : identité+actions | signature --}}
                        <div class="px-6 py-3 grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-3
                            {{ $isTresorier ? 'bg-purple-50/50' : '' }}
                            {{ $isSecondary ? 'bg-gray-50/60 border-l-4 border-indigo-200 pl-5' : '' }}">

                            {{-- Colonne gauche : checkbox + identité + actions --}}
                            <div class="flex items-start gap-3 min-w-0">
                                {{-- Checkbox : désactivée pour les secondaires (gérés via leur primaire) --}}
                                @if(! $isSecondary)
                                <input type="checkbox" name="user_ids[]" value="{{ $p->id }}"
                                       data-status="{{ $sigStatus ?? 'none' }}"
                                       @disabled($isFullySigned)
                                       class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-40 shrink-0">
                                @else
                                <span class="mt-1 w-4 h-4 shrink-0" title="Secondaire — signature gérée via la ligne du primaire"></span>
                                @endif

                                <div class="min-w-0 flex-1 space-y-1">
                                    {{-- Nom + badges type --}}
                                    <div class="font-medium text-gray-900 text-sm flex items-center gap-2 flex-wrap">
                                        @if($isTresorier)
                                            <span title="Trésorier de cette tontine" class="text-lg leading-none">💼</span>
                                        @endif
                                        <span class="{{ !$isFullySigned && $sigStatus !== null ? 'text-red-600 font-medium' : '' }}">{{ $p->full_name }}</span>

                                        @if($isTresorier)
                                            <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-medium">Trésorier</span>
                                        @endif

                                        @php
                                            $partnerOther = $p->partnerOf ?? $p->partner ?? null;
                                            $slots = (int) ($p->pivot->slots ?? 1);
                                        @endphp
                                        @if($partnerOther)
                                            <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-medium whitespace-nowrap"
                                                  title="Binôme solidaire avec {{ $partnerOther->full_name }}">
                                                👥 Binôme avec {{ $partnerOther->full_name }}
                                            </span>
                                        @endif
                                        @if($slots > 1)
                                            <span class="text-xs bg-fuchsia-100 text-fuchsia-700 px-2 py-0.5 rounded-full font-medium whitespace-nowrap"
                                                  title="Participation double ({{ $slots }} parts)">
                                                ✕{{ $slots }} Double
                                            </span>
                                        @endif

                                        {{-- Gains / slots --}}
                                        @if($p->pivot->wins_count >= $p->pivot->slots)
                                            <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full whitespace-nowrap">
                                                🏆 {{ $p->pivot->wins_count }}/{{ $p->pivot->slots }} {{ __('app.tontine.rounds_won') }}
                                            </span>
                                        @elseif($p->pivot->wins_count > 0)
                                            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full whitespace-nowrap">
                                                {{ $p->pivot->wins_count }}/{{ $p->pivot->slots }}
                                            </span>
                                        @else
                                            <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full whitespace-nowrap">
                                                0/{{ $p->pivot->slots }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="text-xs text-gray-400">{{ $p->email }}</div>

                                    {{-- Actions --}}
                                    <div class="flex items-center gap-2 flex-wrap pt-0.5">
                                        @if($isTresorier)
                                            <button type="submit" form="tresorier-remove-{{ $p->id }}"
                                                    onclick="return confirm('Retirer le rôle de trésorier de {{ addslashes($p->full_name) }} ?')"
                                                    class="text-xs text-purple-700 hover:text-purple-900 underline">Retirer trésorier</button>
                                        @endif

                                        @if(!$locked)
                                        <button type="button"
                                                @click="replaceUserId = {{ $p->id }}; replaceUserName = '{{ addslashes($p->full_name) }}'; replaceModal = true; replaceMode = 'existing'"
                                                class="text-xs text-amber-600 hover:text-amber-800 font-medium">
                                            ⇄ Remplacer
                                        </button>
                                        @endif

                                        @if(!$locked)
                                            <select form="slots-{{ $p->id }}" name="slots"
                                                    class="text-xs border border-gray-300 rounded px-1.5 py-1 bg-white">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <option value="{{ $i }}" {{ $p->pivot->slots == $i ? 'selected' : '' }}>{{ $i }}</option>
                                                @endfor
                                            </select>
                                            <button type="submit" form="slots-{{ $p->id }}"
                                                    class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-2 py-1 rounded">✓</button>

                                            <button type="submit" form="remove-{{ $p->id }}"
                                                    onclick="return confirm('{{ __('app.common.confirm') }} ?')"
                                                    class="text-red-400 hover:text-red-600 text-xs">{{ __('app.common.remove') }}</button>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Colonne droite : statuts de signature --}}
                            <div class="flex flex-col justify-center gap-1.5 border-l border-gray-100 pl-4 min-w-[180px] lg:text-right">

                                @if($isSecondary && $primaryOfThis)
                                    {{-- Secondaire : son statut est dans le pivot du primaire --}}
                                    @php
                                        $primaryPivot = $primaryOfThis->pivot ?? null;
                                        $secStatus    = $primaryPivot?->partner_signature_status ?? null;
                                        $secSignedAt  = $primaryPivot?->partner_signed_at ?? null;
                                        $secPdfUrl    = $primaryPivot?->partner_signed_pdf_url ?? null;
                                        $secSignerUrl = $primaryPivot?->partner_signature_signer_url ?? null;
                                        [$secIcon, $secColor, $secLabel] = $statusMap($secStatus);
                                    @endphp
                                    <span class="text-xs text-indigo-500 italic">
                                        Partenaire de {{ $primaryOfThis->first_name ?: $primaryOfThis->name }}
                                    </span>
                                    @if($secIcon)
                                        <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full {{ $secColor }}"
                                              title="{{ $secSignedAt ? 'Signé le ' . \Illuminate\Support\Carbon::parse($secSignedAt)->format('d/m/Y') : $secLabel }}">
                                            {{ $secIcon }} {{ $secLabel }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400 italic">En attente d'envoi</span>
                                    @endif
                                    @if($secPdfUrl)
                                        <a href="{{ $secPdfUrl }}" target="_blank" rel="noopener"
                                           class="text-xs text-indigo-600 hover:text-indigo-800 underline">📄 PDF signé</a>
                                    @elseif($secSignerUrl && in_array($secStatus, ['pending', 'opened', 'sent'], true))
                                        <a href="{{ $secSignerUrl }}" target="_blank" rel="noopener"
                                           class="text-xs text-indigo-600 hover:text-indigo-800 underline">🔗 Lien signataire</a>
                                    @endif

                                @else
                                    {{-- Primaire ou individuel --}}
                                    @if($sigIcon)
                                        <div>
                                            <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full {{ $sigColor }}"
                                                  title="{{ $sigStatus === 'failed' && $sigError ? $sigError : ($p->pivot->signed_at ? 'Signé le ' . \Illuminate\Support\Carbon::parse($p->pivot->signed_at)->format('d/m/Y') : $sigLabel) }}">
                                                {{ $partnerOtherUser ? ($p->first_name ?: 'Moi') . ' : ' : '' }}{{ $sigIcon }} {{ $sigLabel }}
                                            </span>
                                            @if($sigStatus === 'failed' && $sigError)
                                                <details class="inline-block mt-0.5">
                                                    <summary class="text-xs text-red-600 hover:text-red-800 cursor-pointer underline list-none">détails</summary>
                                                    <div class="absolute z-10 bg-white border border-red-300 rounded-lg p-2 shadow-lg max-w-xs text-xs text-red-700 mt-1 text-left">
                                                        {{ $sigError }}
                                                    </div>
                                                </details>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400 italic">Non envoyé</span>
                                    @endif

                                    {{-- Signature du partenaire (binôme ou double+binôme) --}}
                                    @if($partnerOtherUser)
                                        @if($partnerSigIcon)
                                            <div>
                                                <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full {{ $partnerSigColor }}"
                                                      title="{{ $partnerSigStatus === 'failed' && $partnerSigError ? $partnerSigError : ($p->pivot->partner_signed_at ? 'Signé le ' . \Illuminate\Support\Carbon::parse($p->pivot->partner_signed_at)->format('d/m/Y') : $partnerSigLabel) }}">
                                                    {{ $partnerOtherUser->first_name ?: 'Partenaire' }} : {{ $partnerSigIcon }} {{ $partnerSigLabel }}
                                                </span>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400 italic">{{ $partnerOtherUser->first_name ?: 'Partenaire' }} : non envoyé</span>
                                        @endif
                                    @endif

                                    {{-- Liens PDF / signataire --}}
                                    @if($partnerOtherUser)
                                        @php
                                            $partnerSignerUrl = $p->pivot->partner_signature_signer_url ?? null;
                                            $partnerPdfUrl    = $p->pivot->partner_signed_pdf_url ?? null;
                                            $primaryLabel     = $p->first_name ?: 'Primaire';
                                            $partnerLabel     = $partnerOtherUser->first_name ?: 'Partenaire';
                                        @endphp

                                        {{-- Lien / PDF du primaire --}}
                                        @if($p->pivot->signed_pdf_url)
                                            <a href="{{ $p->pivot->signed_pdf_url }}" target="_blank" rel="noopener"
                                               class="text-xs text-indigo-600 hover:text-indigo-800 underline">📄 PDF {{ $primaryLabel }}</a>
                                        @elseif($p->pivot->signature_signer_url && in_array($sigStatus, ['pending', 'opened', 'sent'], true))
                                            <a href="{{ $p->pivot->signature_signer_url }}" target="_blank" rel="noopener"
                                               class="text-xs text-indigo-600 hover:text-indigo-800 underline">🔗 Lien {{ $primaryLabel }}</a>
                                        @endif

                                        {{-- Lien / PDF du partenaire --}}
                                        @if($partnerPdfUrl)
                                            <a href="{{ $partnerPdfUrl }}" target="_blank" rel="noopener"
                                               class="text-xs text-indigo-600 hover:text-indigo-800 underline">📄 PDF {{ $partnerLabel }}</a>
                                        @elseif($partnerSignerUrl && in_array($partnerSigStatus, ['pending', 'opened', 'sent'], true))
                                            <a href="{{ $partnerSignerUrl }}" target="_blank" rel="noopener"
                                               class="text-xs text-indigo-600 hover:text-indigo-800 underline">🔗 Lien {{ $partnerLabel }}</a>
                                        @endif
                                    @else
                                        @if($p->pivot->signed_pdf_url)
                                            <a href="{{ $p->pivot->signed_pdf_url }}" target="_blank" rel="noopener"
                                               class="text-xs text-indigo-600 hover:text-indigo-800 underline">📄 PDF signé</a>
                                        @elseif($p->pivot->signature_signer_url && in_array($sigStatus, ['pending', 'opened', 'sent'], true))
                                            <a href="{{ $p->pivot->signature_signer_url }}" target="_blank" rel="noopener"
                                               class="text-xs text-indigo-600 hover:text-indigo-800 underline">🔗 Lien signataire</a>
                                        @endif
                                    @endif
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-8 text-center text-gray-400 text-sm">{{ __('app.tontine.no_participants') }}</div>
                    @endforelse
                </div>
            </form>

            {{-- Form refresh statuses (hors form batch) --}}
            <form id="refresh-statuses-form" method="POST"
                  action="{{ route('admin.tontines.regulation.refresh', $tontine) }}" class="hidden">
                @csrf
            </form>

            {{-- Forms cachées pour les actions par ligne (hors du form batch) --}}
            @foreach($tontine->participants as $p)
                @php $isTresorier = $tresorier && $tresorier->id === $p->id; @endphp
                @if($isTresorier)
                    <form id="tresorier-remove-{{ $p->id }}" method="POST"
                          action="{{ route('admin.tontines.tresorier.remove', $tontine) }}" class="hidden">
                        @csrf @method('DELETE')
                    </form>
                @else
                    <form id="tresorier-assign-{{ $p->id }}" method="POST"
                          action="{{ route('admin.tontines.tresorier.assign', $tontine) }}" class="hidden">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $p->id }}">
                    </form>
                @endif

                @if(!$locked)
                    <form id="slots-{{ $p->id }}" method="POST"
                          action="{{ route('admin.tontines.participants.slots', [$tontine, $p]) }}" class="hidden">
                        @csrf @method('PATCH')
                    </form>
                    <form id="remove-{{ $p->id }}" method="POST"
                          action="{{ route('admin.tontines.participants.remove', [$tontine, $p]) }}" class="hidden">
                        @csrf @method('DELETE')
                    </form>
                @endif
            @endforeach

            {{-- Ajouter un membre existant --}}
            @if(!$locked && $nonMembers->isNotEmpty())
                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
                    <form method="POST" action="{{ route('admin.tontines.participants.add', $tontine) }}" class="flex flex-col sm:flex-row gap-2">
                        @csrf
                        <select name="user_id" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-0">
                            @foreach($nonMembers as $u)
                                <option value="{{ $u->id }}">{{ $u->full_name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                        <div class="flex gap-2">
                            <select name="slots" class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                            </select>
                            <button class="flex-1 sm:flex-none bg-indigo-600 text-white px-3 py-2 rounded-lg text-sm">{{ __('app.common.add') }}</button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- Génération des tours en 2 étapes --}}
            @if($tontine->participants->isNotEmpty())
                @php
                    $preliminaryRound  = $tontine->rounds->firstWhere('type', 'preliminary');
                    $hasStandardRounds = $tontine->rounds->where('type', 'standard')->isNotEmpty();
                    $totalSlots        = (int) $tontine->participants->sum(fn($p) => $p->pivot->slots ?? 1);
                @endphp

                <div class="border-t border-gray-200">
                    {{-- Étape 1 : Tour préliminaire (modifiable tant que les standards ne sont pas générés) --}}
                    @if($tontine->has_preliminary)
                    <div class="px-6 py-4 bg-amber-50 border-b border-amber-100 flex items-center justify-between flex-wrap gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-amber-900">
                                Étape 1 — Tour préliminaire
                                @if($preliminaryRound)
                                    <span class="text-xs bg-amber-200 text-amber-800 px-2 py-0.5 rounded-full ml-1">✓ Généré</span>
                                @else
                                    <span class="text-xs bg-white text-amber-700 border border-amber-300 px-2 py-0.5 rounded-full ml-1">À générer</span>
                                @endif
                            </p>
                            <p class="text-xs text-amber-700 mt-0.5">
                                @if($preliminaryRound)
                                    Cliquez sur « Mettre à jour » après tout ajout/retrait de participant pour synchroniser les paiements.
                                @else
                                    Crée un tour préliminaire pour suivre l'avance initiale ({{ number_format($tontine->preliminary_amount ?? 0, 2) }} € × participations).
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('admin.tontines.generate.preliminary', $tontine) }}"
                              onsubmit="return confirm('{{ $preliminaryRound ? 'Mettre à jour le tour préliminaire : synchronisation des paiements avec la liste actuelle des participants. Continuer ?' : 'Générer le tour préliminaire pour ' . $tontine->participants->count() . ' participant(s) ?' }}')">
                            @csrf
                            <button type="submit"
                                    class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                                {{ $preliminaryRound ? '🔄 Mettre à jour le préliminaire' : '⚙️ Générer le tour préliminaire' }}
                            </button>
                        </form>
                    </div>
                    @endif

                    {{-- Étape 2 : Tours standards --}}
                    @if(! $hasStandardRounds)
                        @if($tontine->first_round_month)
                            <div class="px-6 py-4 bg-indigo-50">
                                <form method="POST" action="{{ route('admin.tontines.generate.standards', $tontine) }}"
                                      onsubmit="return confirm('Générer ' + this.elements['rounds_count'].value + ' tours standards à partir du {{ $tontine->first_round_month->isoFormat('MMMM YYYY') }} ?\nCette action verrouille la liste des participants.')"
                                      class="flex items-center justify-between flex-wrap gap-3">
                                    @csrf
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-indigo-900">
                                            Étape 2 — Tours standards
                                            <span class="text-xs bg-white text-indigo-700 border border-indigo-300 px-2 py-0.5 rounded-full ml-1">À générer</span>
                                        </p>
                                        <p class="text-xs text-indigo-700 mt-0.5">
                                            Crée les tours mensuels. Une fois générés, la liste des participants est verrouillée.
                                        </p>
                                    </div>
                                    <div class="flex items-end gap-2 flex-wrap">
                                        <div>
                                            <label class="block text-xs font-medium text-indigo-900 mb-1">Nombre de tours</label>
                                            <input type="number" name="rounds_count" min="1" max="200"
                                                   value="{{ $tontine->rounds_count ?: $totalSlots }}"
                                                   class="w-24 border border-indigo-300 rounded-lg px-3 py-2 text-sm bg-white">
                                            <p class="text-xs text-indigo-500 mt-0.5">Suggéré : {{ $totalSlots }} (somme des slots)</p>
                                        </div>
                                        <button type="submit"
                                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                                            🚀 Générer les tours
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @else
                            <div class="px-6 py-4 bg-gray-50 text-xs text-gray-500">
                                ℹ️ Pour générer les tours standards, renseigne d'abord le <strong>mois du premier tour</strong> dans la fiche tontine.
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        </div>

        {{-- Invitations en attente --}}
        @if($pendingInvitations->isNotEmpty())
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-3 border-b border-gray-100 bg-gray-50">
                    <h3 class="text-sm font-semibold text-gray-700">{{ __('app.tontine.pending_invitations') }} ({{ $pendingInvitations->count() }})</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($pendingInvitations as $inv)
                        <div class="px-6 py-3 flex items-center justify-between">
                            <div>
                                <div class="text-sm text-gray-700">{{ $inv->email }}</div>
                                <div class="text-xs text-gray-400">{{ __('app.tontine.expires') }} {{ $inv->expires_at->format('d/m/Y') }}</div>
                            </div>
                            <form method="POST" action="{{ route('admin.invitations.destroy', $inv) }}">
                                @csrf @method('DELETE')
                                <button class="text-red-400 hover:text-red-600 text-xs">{{ __('app.tontine.cancel_inv') }}</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- ═════════════════════════════════════════════════════════════════ --}}
    {{-- ONGLET « TOURS »                                                 --}}
    {{-- ═════════════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'tours'" x-cloak class="space-y-6">
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <h2 class="font-semibold text-gray-800">{{ __('app.tontine.rounds_section') }} ({{ $tontine->rounds->count() }})</h2>
                    @if($hasOpenRound)
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">🟢 {{ __('app.round.one_open') }}</span>
                    @endif
                </div>
                @if($roundsGenerated)
                    <a href="{{ route('admin.rounds.create', $tontine) }}"
                       class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">{{ __('app.tontine.new_round') }}</a>
                @endif
            </div>
            @forelse($tontine->rounds->sortBy('round_number') as $round)
                <div class="px-4 sm:px-6 py-4 flex flex-col gap-2 border-b border-gray-100 last:border-0">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-3 sm:gap-4">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm shrink-0
                                {{ $round->isPreliminary() ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700' }}">
                                #{{ $round->round_number }}
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 flex items-center gap-2 flex-wrap">
                                    <span>{{ __('app.round.bid_closes') }} : {{ $round->bid_closes_at->format('d/m/Y H:i') }}</span>
                                    <button type="button"
                                            @click="editDateRound = editDateRound === {{ $round->id }} ? null : {{ $round->id }}"
                                            class="text-xs text-indigo-500 hover:text-indigo-700 underline">
                                        ✏️ Modifier
                                    </button>
                                </div>
                                <div class="text-xs text-gray-500">
                                    @if($round->isPreliminary())
                                        {{ __('app.round.deposit_per_person') }} : {{ number_format($round->preliminary_amount, 2) }} €
                                        — {{ __('app.round.total_collected') }} : {{ number_format($round->pot_amount, 2) }} €
                                    @else
                                        {{ __('app.round.pot') }} : {{ number_format($round->pot_amount, 2) }} € —
                                        {{ $round->bids->count() }} {{ __('app.round.bids_count') }}
                                        @if($round->winner) — {{ __('app.round.col_winner') }} : <strong>{{ $round->winner->full_name }}</strong> @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center flex-wrap gap-2 shrink-0 pl-13 sm:pl-0">
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

                            @if($round->status === 'pending' && !$hasOpenRound)
                                <form method="POST" action="{{ route('admin.rounds.open', [$tontine, $round]) }}">
                                    @csrf @method('PATCH')
                                    <button class="text-xs bg-green-600 hover:bg-green-700 text-white px-2.5 py-1 rounded-lg font-medium">
                                        {{ __('app.round.open_btn') }}
                                    </button>
                                </form>
                            @endif

                            @if($round->status === 'open')
                                <form method="POST" action="{{ route('admin.rounds.close', [$tontine, $round]) }}"
                                      onsubmit="return confirm('{{ __('app.round.close_confirm') }}')">
                                    @csrf @method('PATCH')
                                    <button class="text-xs bg-orange-500 hover:bg-orange-600 text-white px-2.5 py-1 rounded-lg font-medium">
                                        {{ __('app.round.close_btn') }}
                                    </button>
                                </form>
                            @endif

                            <a href="{{ route('admin.rounds.show', [$tontine, $round]) }}"
                               class="text-indigo-600 hover:text-indigo-800 text-sm">→</a>
                        </div>
                    </div>

                    {{-- Formulaire inline édition date de clôture --}}
                    <div x-show="editDateRound === {{ $round->id }}" x-cloak
                         class="ml-13 pl-1 sm:ml-14">
                        <form method="POST" action="{{ route('admin.rounds.dates.update', [$tontine, $round]) }}"
                              class="flex items-center gap-2 flex-wrap">
                            @csrf @method('PATCH')
                            <label class="text-xs font-medium text-gray-600">Nouvelle date de clôture :</label>
                            <input type="datetime-local" name="bid_closes_at"
                                   value="{{ $round->bid_closes_at->format('Y-m-d\TH:i') }}"
                                   class="border border-indigo-300 rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <button type="submit"
                                    class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded-lg font-medium">
                                Enregistrer
                            </button>
                            <button type="button" @click="editDateRound = null"
                                    class="text-xs text-gray-500 hover:text-gray-700">
                                Annuler
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-6 py-12 text-center text-gray-400">
                    <div class="text-4xl mb-3">🗓️</div>
                    <p>{{ __('app.tontine.no_rounds') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ═════════════════════════════════════════════════════════════════ --}}
    {{-- ONGLET « PARAMÈTRES » avec sous-onglets                           --}}
    {{-- ═════════════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'parametres'" x-cloak class="space-y-6">

        {{-- Sous-onglets --}}
        <div class="flex flex-wrap gap-1 bg-gray-100 p-1 rounded-lg text-sm w-fit">
            <button type="button" @click="subtab = 'reglement'"
                    :class="subtab === 'reglement' ? 'bg-white shadow text-indigo-700 font-medium' : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-1.5 rounded-md transition">📄 Règlement &amp; signature</button>
            <button type="button" @click="subtab = 'paiement'"
                    :class="subtab === 'paiement' ? 'bg-white shadow text-indigo-700 font-medium' : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-1.5 rounded-md transition">💳 Moyens de paiement</button>
        </div>

        {{-- Sous-onglet RÈGLEMENT --}}
        <div x-show="subtab === 'reglement'" x-cloak>
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <h2 class="font-semibold text-gray-800">Règlement &amp; signature électronique</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Aperçu personnalisé en HTML + envoi en signature via DocuSeal.
                        </p>
                    </div>
                    @php
                        $rStatus = $regulation?->status ?? 'draft';
                        $statusLabel = [
                            'draft'              => ['À envoyer', 'bg-gray-100 text-gray-600'],
                            'sent_for_signature' => ['En signature', 'bg-amber-100 text-amber-700'],
                            'completed'          => ['Complet', 'bg-green-100 text-green-700'],
                        ][$rStatus] ?? ['—', 'bg-gray-100 text-gray-600'];
                    @endphp
                    <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $statusLabel[1] }}">
                        {{ $statusLabel[0] }}
                    </span>
                </div>

                <div class="px-6 py-5 space-y-4">
                    <a href="{{ route('admin.tontines.regulation.preview', $tontine) }}" target="_blank" rel="noopener"
                       class="inline-block bg-white border border-indigo-300 hover:bg-indigo-50 text-indigo-700 px-3 py-2 rounded-lg text-sm font-medium">
                        👁 Prévisualiser le règlement personnalisé
                    </a>

                    {{-- Mapping des templates DocuSeal pour cette tontine (par type de participation) --}}
                    @if($docusealConfig['enabled'] ?? false)
                        <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 space-y-3">
                            <div>
                                <h3 class="text-sm font-semibold text-indigo-900">Templates DocuSeal de signature</h3>
                                <p class="text-xs text-indigo-700 mt-0.5">
                                    Chaque participant reçoit la fiche correspondant à son type de participation.
                                    L'ordre de fallback est : <strong>type</strong> → <strong>défaut tontine</strong> → <strong>global</strong>.
                                </p>
                            </div>

                            <form method="POST" action="{{ route('admin.tontines.docuseal-template.update', $tontine) }}"
                                  class="space-y-3">
                                @csrf @method('PATCH')

                                @php
                                    // Helper local pour générer un select template
                                    $renderTemplateSelect = function ($name, $currentValue, $emptyLabel) use ($docusealTemplates) {
                                        return view('admin.tontines._template_select', [
                                            'name'         => $name,
                                            'currentValue' => $currentValue,
                                            'emptyLabel'   => $emptyLabel,
                                            'templates'    => $docusealTemplates,
                                        ])->render();
                                    };
                                @endphp

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    {{-- Défaut tontine --}}
                                    <div>
                                        <label class="block text-xs font-medium text-indigo-900 mb-1">
                                            🔵 Défaut tontine
                                            <span class="text-indigo-500 font-normal">(fallback principal)</span>
                                        </label>
                                        {!! $renderTemplateSelect('docuseal_template_id', $tontine->docuseal_template_id,
                                            '— Utiliser le template global (#' . ($docusealConfig['template_id'] ?: 'non défini') . ') —') !!}
                                    </div>

                                    {{-- Individuel --}}
                                    <div>
                                        <label class="block text-xs font-medium text-indigo-900 mb-1">
                                            👤 Participation individuelle
                                            <span class="text-indigo-500 font-normal">(Annexe 3)</span>
                                        </label>
                                        {!! $renderTemplateSelect('docuseal_template_individual_id', $tontine->docuseal_template_individual_id,
                                            '— Utiliser le défaut tontine —') !!}
                                    </div>

                                    {{-- Binôme --}}
                                    <div>
                                        <label class="block text-xs font-medium text-indigo-900 mb-1">
                                            👥 Binôme solidaire
                                            <span class="text-indigo-500 font-normal">(Annexe 4)</span>
                                        </label>
                                        {!! $renderTemplateSelect('docuseal_template_binome_id', $tontine->docuseal_template_binome_id,
                                            '— Utiliser le défaut tontine —') !!}
                                    </div>

                                    {{-- Participation double --}}
                                    <div>
                                        <label class="block text-xs font-medium text-indigo-900 mb-1">
                                            ✕2 Participation double
                                            <span class="text-indigo-500 font-normal">(Annexe 5)</span>
                                        </label>
                                        {!! $renderTemplateSelect('docuseal_template_double_id', $tontine->docuseal_template_double_id,
                                            '— Utiliser le défaut tontine —') !!}
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit"
                                            class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
                                        Enregistrer les templates
                                    </button>
                                </div>
                            </form>

                            @if(empty($docusealTemplates))
                                <p class="text-xs text-red-700">
                                    ⚠️ Impossible de lister les templates depuis DocuSeal. Vérifie la
                                    <a href="{{ route('admin.settings.index', ['tab' => 'signature']) }}" class="underline">configuration</a>.
                                </p>
                            @endif
                        </div>
                    @endif

                    <div class="text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                        💡 L'envoi en signature se fait depuis l'onglet <strong>Membres</strong> : coche les destinataires puis clic
                        <strong>« Envoyer en signature aux sélectionnés »</strong>.
                    </div>
                </div>
            </div>
        </div>

        {{-- Sous-onglet MOYENS DE PAIEMENT --}}
        <div x-show="subtab === 'paiement'" x-cloak>
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <h2 class="font-semibold text-gray-800">💳 Moyens de paiement des cotisations</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Ces informations seront incluses dans les emails de notification.</p>
                    </div>
                    @if(count($pi))
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Configuré</span>
                    @else
                        <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">À renseigner</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.tontines.payment-info', $tontine) }}" class="px-6 py-5 space-y-5">
                    @csrf @method('PATCH')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prénom du trésorier</label>
                            <input type="text" name="tresorier_firstname" value="{{ old('tresorier_firstname', $pi['tresorier_firstname'] ?? '') }}"
                                   placeholder="Marie"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nom du trésorier</label>
                            <input type="text" name="tresorier_name" value="{{ old('tresorier_name', $pi['tresorier_name'] ?? '') }}"
                                   placeholder="Dupont"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone du trésorier</label>
                        <input type="tel" name="tresorier_phone" value="{{ old('tresorier_phone', $pi['tresorier_phone'] ?? '') }}"
                               placeholder="06 00 00 00 00"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Virement bancaire</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">IBAN</label>
                                <input type="text" name="iban" value="{{ old('iban', $pi['iban'] ?? '') }}"
                                       placeholder="FR76 3000 6000 0112 3456 7890 189"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">BIC</label>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">Lien Revolut</label>
                                <input type="url" name="revolut_link" value="{{ old('revolut_link', $pi['revolut_link'] ?? '') }}"
                                       placeholder="https://revolut.me/..."
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Adresse (versement en espèces)</label>
                                <textarea name="address" rows="2" placeholder="12 rue de la Paix, 75001 Paris"
                                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('address', $pi['address'] ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>

            {{-- Trésorier "externe" (non-participant) — sélection avancée --}}
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden mt-6">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-800">Trésorier hors membres (avancé)</h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Désigner un utilisateur qui n'est pas membre de cette tontine (admin, autre participant, etc.).
                        Pour les membres, utilise le bouton « Désigner trésorier » dans l'onglet Membres.
                    </p>
                </div>
                <div class="px-6 py-5">
                    @php
                        $memberIds = $tontine->participants->pluck('id');
                        $candidats = \App\Models\User::where('active', true)
                            ->whereNotIn('id', $memberIds)
                            ->when($tresorier, fn($q) => $q->where('id', '!=', $tresorier->id))
                            ->orderByRaw("CASE role WHEN 'superadmin' THEN 1 WHEN 'admin' THEN 2 WHEN 'admin_local' THEN 3 WHEN 'tresorier' THEN 4 ELSE 5 END")
                            ->orderBy('name')
                            ->get();
                        $roleLabels = ['superadmin'=>'Super-admin','admin'=>'Admin','admin_local'=>'Admin local','tresorier'=>'Trésorier','participant'=>'Participant'];
                    @endphp
                    @if($candidats->isNotEmpty())
                        <form method="POST" action="{{ route('admin.tontines.tresorier.assign', $tontine) }}" class="flex flex-col sm:flex-row gap-2">
                            @csrf
                            <select name="user_id" class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                @foreach($candidats as $u)
                                    <option value="{{ $u->id }}">{{ $u->full_name }} ({{ $roleLabels[$u->role] ?? $u->role }}) — {{ $u->email }}</option>
                                @endforeach
                            </select>
                            <button class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded-lg text-sm font-medium">
                                {{ $tresorier ? 'Changer' : 'Assigner' }}
                            </button>
                        </form>
                    @else
                        <p class="text-xs text-gray-400">Aucun utilisateur externe disponible.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════ MODAUX (partagés) ════════════════════════ --}}

    {{-- Modal Remplacement --}}
    <div x-show="replaceModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="replaceModal = false">
        <div class="absolute inset-0 bg-black/40" @click="replaceModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
            <h3 class="text-lg font-semibold text-gray-900">Remplacer <span x-text="replaceUserName" class="text-indigo-600"></span></h3>
            <div class="flex gap-1 bg-gray-100 p-1 rounded-lg text-sm">
                <button type="button" @click="replaceMode = 'existing'"
                        :class="replaceMode === 'existing' ? 'bg-white shadow text-gray-900 font-medium' : 'text-gray-500 hover:text-gray-700'"
                        class="flex-1 py-1.5 rounded-md transition">Utilisateur existant</button>
                <button type="button" @click="replaceMode = 'new'"
                        :class="replaceMode === 'new' ? 'bg-white shadow text-gray-900 font-medium' : 'text-gray-500 hover:text-gray-700'"
                        class="flex-1 py-1.5 rounded-md transition">Nouvel utilisateur</button>
            </div>

            <form x-show="replaceMode === 'existing'" method="POST"
                  :action="'{{ url('admin/tontines/'.$tontine->id.'/participants') }}/' + replaceUserId + '/replace'">
                @csrf
                <input type="hidden" name="mode" value="existing">
                <div class="space-y-3">
                    @if($nonMembers->isNotEmpty())
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Choisir le remplaçant</label>
                            <select name="replacement_user_id" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500">
                                @foreach($nonMembers as $u)
                                    <option value="{{ $u->id }}">{{ $u->full_name }} — {{ $u->email }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <p class="text-sm text-gray-400 italic">Aucun participant disponible. Créez un nouvel utilisateur.</p>
                    @endif
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="replaceModal = false"
                                class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
                        @if($nonMembers->isNotEmpty())
                            <button type="submit"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Remplacer</button>
                        @endif
                    </div>
                </div>
            </form>

            <form x-show="replaceMode === 'new'" method="POST"
                  :action="'{{ url('admin/tontines/'.$tontine->id.'/participants') }}/' + replaceUserId + '/replace'">
                @csrf
                <input type="hidden" name="mode" value="new">
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Prénom</label>
                            <input type="text" name="first_name" placeholder="Marie"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                            <input type="text" name="name" required placeholder="Dupont"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" required placeholder="marie@exemple.com"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Mot de passe <span class="text-red-500">*</span></label>
                        <input type="password" name="password" required minlength="8"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="replaceModal = false"
                                class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
                        <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Créer &amp; remplacer</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Invitation --}}
    <div x-show="inviteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="inviteOpen = false">
        <div class="absolute inset-0 bg-black/40" @click="inviteOpen = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg p-6 max-h-screen overflow-y-auto">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Inviter un participant</h3>
            <p class="text-xs text-gray-400 mb-4">Le compte sera créé immédiatement. L'invité reçoit un lien pour choisir son mot de passe.</p>
            <form method="POST" action="{{ route('admin.invitations.store', $tontine) }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Prénom <span class="text-red-500">*</span></label>
                        <input type="text" name="first_name" required placeholder="Marie"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required placeholder="Dupont"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" required placeholder="marie@exemple.com"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Téléphone</label>
                    <input type="tel" name="phone" placeholder="06 00 00 00 00"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Adresse</label>
                    <input type="text" name="address" placeholder="12 rue de la Paix"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Code postal</label>
                        <input type="text" name="postal_code" placeholder="75001"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Ville</label>
                        <input type="text" name="city" placeholder="Paris"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="inviteOpen = false"
                            class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
                    <button type="submit"
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Créer &amp; Inviter</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
