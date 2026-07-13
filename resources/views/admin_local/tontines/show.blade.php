@extends('layouts.app')
@section('title', $tontine->name)

@section('content')
<div class="space-y-8">

    {{-- En-tête --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                <a href="{{ route('admin-local.tontines.index') }}" class="hover:text-indigo-600">Mes tontines</a>
                <span>/</span>
                <span>{{ $tontine->name }}</span>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <h1 class="text-2xl font-bold text-gray-900">{{ $tontine->name }}</h1>
                @if($canEdit)
                    <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full font-medium">Ma tontine</span>
                @else
                    <span class="bg-amber-100 text-amber-700 text-xs px-2 py-0.5 rounded-full font-medium">Lecture seule</span>
                @endif
            </div>
            @if($tontine->description)<p class="text-gray-500 mt-1">{{ $tontine->description }}</p>@endif
        </div>
        @if($canEdit)
        <div class="flex gap-2 flex-wrap shrink-0">
            <a href="{{ route('admin-local.tontines.edit', $tontine) }}"
               class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium">
                Modifier
            </a>
        </div>
        @endif
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @foreach([
            ['Cotisation', number_format($tontine->cotisation_amount,2).' €'],
            ['Tours', $tontine->rounds->count()],
            [$tontine->has_bidding ? 'Enchère max' : 'Mode',
             $tontine->has_bidding ? $tontine->bid_cap.'%' : '🎲 Tirage'],
            ['Statut', __('app.status.'.$tontine->status)],
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
        @if($tontine->bid_day_open)
        <span>Enchères : du <strong>{{ $tontine->bid_day_open }}</strong> au <strong>{{ $tontine->bid_day_close }}</strong></span>
        @endif
        @if($tontine->payment_day)
        <span>Paiement le <strong>{{ $tontine->payment_day }}</strong> de chaque mois</span>
        @endif
        <span>{{ $tontine->rounds->count() }} tours générés</span>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        {{-- Participants --}}
        @php $locked = $tontine->participants_locked; $roundsGenerated = $tontine->rounds->isNotEmpty(); @endphp
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h2 class="font-semibold text-gray-800">Participants ({{ $tontine->participants->count() }})
                    @if($locked) <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full ml-1">🔒</span> @endif
                </h2>
                @if($canEdit && $roundsGenerated)
                <form method="POST" action="{{ route('admin-local.tontines.participants.lock', $tontine) }}">
                    @csrf @method('PATCH')
                    <button class="text-xs px-3 py-1.5 rounded-lg border font-medium
                        {{ $locked ? 'border-amber-300 text-amber-700 hover:bg-amber-50' : 'border-gray-300 text-gray-600 hover:bg-gray-50' }}">
                        {{ $locked ? '🔓 Déverrouiller' : '🔒 Verrouiller' }}
                    </button>
                </form>
                @endif
            </div>

            <div class="divide-y divide-gray-100">
                @forelse($tontine->participants as $p)
                <div class="px-6 py-3 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="font-medium text-gray-900 text-sm">{{ $p->full_name }}</div>
                        <div class="text-xs text-gray-400">{{ $p->email }}</div>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        @if($p->pivot->wins_count >= $p->pivot->slots)
                            <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full">🏆 {{ $p->pivot->wins_count }}/{{ $p->pivot->slots }}</span>
                        @elseif($p->pivot->wins_count > 0)
                            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">{{ $p->pivot->wins_count }}/{{ $p->pivot->slots }}</span>
                        @else
                            <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">0/{{ $p->pivot->slots }}</span>
                        @endif

                        @if($canEdit && !$locked)
                        <form method="POST" action="{{ route('admin-local.tontines.participants.remove', [$tontine, $p]) }}"
                              onsubmit="return confirm('Retirer ce participant ?')">
                            @csrf @method('DELETE')
                            <button class="text-red-400 hover:text-red-600 text-xs">Retirer</button>
                        </form>
                        @elseif(!$canEdit)
                            <span class="text-xs text-gray-400">{{ $p->pivot->slots }} slot(s)</span>
                        @endif
                    </div>
                </div>
                @empty
                <div class="px-6 py-8 text-center text-gray-400 text-sm">Aucun participant pour l'instant.</div>
                @endforelse
            </div>

            @if($canEdit && !$locked && $nonMembers->isNotEmpty())
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
                <form method="POST" action="{{ route('admin-local.tontines.participants.add', $tontine) }}" class="flex flex-col sm:flex-row gap-2">
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
                        <button class="flex-1 sm:flex-none bg-indigo-600 text-white px-3 py-2 rounded-lg text-sm">Ajouter</button>
                    </div>
                </form>
            </div>
            @endif

            @if($canEdit && !$locked && $tontine->participants->isNotEmpty() && $tontine->first_round_month && !$roundsGenerated)
            <div class="px-6 py-4 border-t border-indigo-100 bg-indigo-50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-indigo-800">Prêt à générer les tours</p>
                        <p class="text-xs text-indigo-600 mt-0.5">Cette action est irréversible.</p>
                    </div>
                    <form method="POST" action="{{ route('admin-local.tontines.generate', $tontine) }}"
                          onsubmit="return confirm('Générer les tours pour {{ $tontine->participants->count() }} participants ?')">
                        @csrf
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                            🚀 Générer
                        </button>
                    </form>
                </div>
            </div>
            @endif
        </div>

        {{-- Invitations --}}
        @if($canEdit)
        <div x-data="{ inviteOpen: false }" class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800">Invitations</h2>
                <button @click="inviteOpen = true"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
                    + Inviter
                </button>
            </div>

            {{-- Modal invitation --}}
            <div x-show="inviteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 @keydown.escape.window="inviteOpen = false">
                <div class="absolute inset-0 bg-black/40" @click="inviteOpen = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg p-6 max-h-screen overflow-y-auto">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Inviter un participant</h3>
                    <p class="text-xs text-gray-400 mb-4">Le compte sera créé immédiatement. L'invité reçoit un lien pour choisir son mot de passe.</p>
                    <form method="POST" action="{{ route('admin-local.invitations.store', $tontine) }}" class="space-y-3">
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
                                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Créer & Inviter</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Liste des invitations en attente --}}
            @php $pendingInvitations = $tontine->invitations()->whereNull('accepted_at')->get(); @endphp
            @if($pendingInvitations->isNotEmpty())
            <div class="divide-y divide-gray-100">
                @foreach($pendingInvitations as $inv)
                <div class="px-6 py-3 flex items-center justify-between gap-3">
                    <div class="text-sm text-gray-700">{{ $inv->email }}</div>
                    <span class="text-xs text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">En attente</span>
                </div>
                @endforeach
            </div>
            @else
            <div class="px-6 py-4 text-sm text-gray-400">Aucune invitation en attente.</div>
            @endif
        </div>
        @endif

        {{-- Informations --}}
        <div class="space-y-4">
            @php $pi = $tontine->payment_info ?? []; @endphp
            @if(count($pi))
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">💳 Moyens de paiement</h3>
                <div class="text-xs text-gray-600 space-y-1">
                    @if(!empty($pi['tresorier_firstname']) || !empty($pi['tresorier_name']))
                    <div>Trésorier : {{ trim(($pi['tresorier_firstname']??'').' '.($pi['tresorier_name']??'')) }}</div>
                    @endif
                    @if(!empty($pi['iban']))<div>IBAN : {{ $pi['iban'] }}</div>@endif
                    @if(!empty($pi['revolut_link']))<div>Revolut : {{ $pi['revolut_link'] }}</div>@endif
                </div>
            </div>
            @endif

            @php $tresorier = $tontine->tresoriers()->first(); @endphp
            @if($tresorier)
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Trésorier assigné</h3>
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-bold text-sm">
                        {{ strtoupper(mb_substr($tresorier->first_name ?: $tresorier->name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">{{ $tresorier->full_name }}</div>
                        <div class="text-xs text-gray-400">{{ $tresorier->email }}</div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Tours --}}
    @php $hasOpenRound = $tontine->rounds->contains('status', 'open'); @endphp
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-gray-800">Tours ({{ $tontine->rounds->count() }})</h2>
                @if($hasOpenRound)
                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">🟢 Un tour ouvert</span>
                @endif
            </div>
        </div>

        @forelse($tontine->rounds->sortBy('round_number') as $round)
        <div class="px-4 sm:px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-gray-100 last:border-0">
            <div class="flex items-center gap-3 sm:gap-4">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm shrink-0
                    {{ $round->isPreliminary() ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700' }}">
                    #{{ $round->round_number }}
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-medium text-gray-900">
                        Clôture : {{ $round->bid_closes_at->format('d/m/Y H:i') }}
                    </div>
                    <div class="text-xs text-gray-500">
                        @if($round->isPreliminary())
                            {{ number_format($round->preliminary_amount, 2) }} € / personne — Total : {{ number_format($round->pot_amount, 2) }} €
                        @else
                            Cagnotte : {{ number_format($round->pot_amount, 2) }} €
                            — {{ $round->bids->count() }} enchère(s)
                            @if($round->winner) — Vainqueur : <strong>{{ $round->winner->full_name }}</strong> @endif
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center flex-wrap gap-2 shrink-0">
                @php
                    $colors = ['pending'=>'gray','open'=>'green','closed'=>'yellow','drawn'=>'blue','paid'=>'indigo'];
                    $color  = $colors[$round->status] ?? 'gray';
                @endphp
                @if($round->isPreliminary())
                    <span class="bg-amber-100 text-amber-700 text-xs px-2 py-0.5 rounded-full">Préliminaire</span>
                @endif
                <span class="bg-{{ $color }}-100 text-{{ $color }}-700 text-xs px-2 py-0.5 rounded-full">
                    {{ __('app.status.'.$round->status) }}
                </span>

                @if($canEdit)
                    @if($round->status === 'pending' && !$hasOpenRound)
                    <form method="POST" action="{{ route('admin-local.rounds.open', [$tontine, $round]) }}">
                        @csrf @method('PATCH')
                        <button class="text-xs bg-green-600 hover:bg-green-700 text-white px-2.5 py-1 rounded-lg font-medium">Ouvrir</button>
                    </form>
                    @endif

                    @if($round->status === 'open')
                    <form method="POST" action="{{ route('admin-local.rounds.close', [$tontine, $round]) }}"
                          onsubmit="return confirm('Fermer ce tour ?')">
                        @csrf @method('PATCH')
                        <button class="text-xs bg-orange-500 hover:bg-orange-600 text-white px-2.5 py-1 rounded-lg font-medium">Fermer</button>
                    </form>
                    @endif
                @endif

                <a href="{{ route('admin-local.rounds.show', [$tontine, $round]) }}"
                   class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">Détail →</a>
            </div>
        </div>
        @empty
        <div class="px-6 py-12 text-center text-gray-400">
            <div class="text-4xl mb-3">🗓️</div>
            <p>Aucun tour généré.</p>
        </div>
        @endforelse
    </div>

</div>
@endsection
