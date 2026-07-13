@extends('layouts.app')
@section('title', 'Utilisateurs')

@php
    $roleLabels = [
        'all'         => 'Tous',
        'superadmin'  => 'Super-admin',
        'admin'       => 'Administrateurs',
        'admin_local' => 'Admins locaux',
        'tresorier'   => 'Trésoriers',
        'participant' => 'Participants',
    ];
    $roleBadge = [
        'superadmin'  => 'bg-purple-100 text-purple-700',
        'admin'       => 'bg-indigo-100 text-indigo-700',
        'admin_local' => 'bg-blue-100 text-blue-700',
        'tresorier'   => 'bg-emerald-100 text-emerald-700',
        'participant' => 'bg-gray-100 text-gray-700',
    ];

    $rolesOf = function ($u) {
        $hats = [];
        $primary = $u->role;
        $hats[$primary] = $primary;
        if (($u->managed_tontines_count ?? 0) > 0 && $primary !== 'tresorier') {
            $hats['tresorier'] = 'tresorier';
        }
        if (($u->tontines_count ?? 0) > 0 && $primary !== 'participant') {
            $hats['participant'] = 'participant';
        }
        return array_values($hats);
    };

    $canInvite           = $permissions['can_invite'] ?? false;
    $canCancelInvitation = $permissions['can_cancel_invitation'] ?? false;
    $canPromoteAdminLocal= $permissions['can_promote_admin_local'] ?? false;
    $canDemoteAdminLocal = $permissions['can_demote_admin_local'] ?? false;
    $canDelete           = $permissions['can_delete'] ?? false;
    $canToggle           = $permissions['can_toggle'] ?? false;
@endphp

@section('content')
<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Gestion des utilisateurs</h1>
            <p class="text-gray-500 mt-1 text-sm">
                @if(auth()->user()->isAdminLocal())
                    Utilisateurs rattachés aux tontines que vous gérez.
                @else
                    Tous les comptes, tous rôles confondus.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($canInvite)
            <div x-data="{ open: false, channel: 'email' }">
                <button @click="open = true"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    + Inviter un participant
                </button>

                {{-- Modal invitation --}}
                <div x-show="open" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     @keydown.escape.window="open = false">
                    <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
                    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg p-6 max-h-screen overflow-y-auto">
                        <h3 class="text-lg font-semibold text-gray-900 mb-1">Inviter un participant</h3>
                        <p class="text-xs text-gray-400 mb-4">Le compte sera pré-créé. L'invité reçoit un lien pour choisir son mot de passe.</p>

                        {{-- Sélecteur de canal --}}
                        <div class="flex gap-2 mb-4 p-1 bg-gray-100 rounded-lg">
                            <button type="button" @click="channel = 'email'"
                                    :class="channel === 'email' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                                    class="flex-1 px-3 py-1.5 rounded-md text-sm font-medium transition">
                                ✉️ Par email
                            </button>
                            <button type="button" @click="channel = 'sms'"
                                    :class="channel === 'sms' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                                    class="flex-1 px-3 py-1.5 rounded-md text-sm font-medium transition">
                                📱 Par SMS
                            </button>
                        </div>

                        <form method="POST" action="{{ route('admin.invitations.standalone') }}" class="space-y-3">
                            @csrf
                            <input type="hidden" name="channel" :value="channel">

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Prénom <span class="text-red-500">*</span></label>
                                    <input type="text" name="first_name" required placeholder="Marie"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                                    <input type="text" name="name" required placeholder="Dupont"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>

                            {{-- Email : requis si canal=email, optionnel sinon --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">
                                    Email <span class="text-red-500" x-show="channel === 'email'">*</span>
                                    <span class="text-gray-400" x-show="channel === 'sms'" x-cloak>(optionnel)</span>
                                </label>
                                <input type="email" name="email" placeholder="marie@exemple.com"
                                       :required="channel === 'email'"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>

                            {{-- Téléphone : requis si canal=sms --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">
                                    Téléphone <span class="text-red-500" x-show="channel === 'sms'" x-cloak>*</span>
                                    <span class="text-gray-400" x-show="channel === 'email'">(optionnel)</span>
                                </label>
                                <input type="tel" name="phone" placeholder="06 00 00 00 00"
                                       :required="channel === 'sms'"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                <p class="text-xs text-gray-400 mt-1" x-show="channel === 'sms'" x-cloak>
                                    Les numéros français 06/07 seront automatiquement convertis en +33.
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Adresse</label>
                                <input type="text" name="address" placeholder="12 rue de la Paix"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Code postal</label>
                                    <input type="text" name="postal_code" placeholder="75001"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Ville</label>
                                    <input type="text" name="city" placeholder="Paris"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" @click="open = false"
                                        class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
                                <button type="submit"
                                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                    <span x-show="channel === 'email'">Créer & Envoyer par email</span>
                                    <span x-show="channel === 'sms'" x-cloak>Créer & Envoyer par SMS</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endif

            @if($canPromoteAdminLocal)
            <a href="{{ route('superadmin.admin-locaux.create') }}"
               class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg font-medium text-sm transition">
                + Nouvel admin local
            </a>
            @endif
        </div>
    </div>

    {{-- Filtres par rôle --}}
    <div class="flex flex-wrap gap-2">
        @foreach($roleLabels as $key => $label)
            @php $active = $role === $key; @endphp
            <a href="{{ route('users.index', array_filter(['role' => $key, 'q' => $search])) }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium transition
                      {{ $active ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' }}">
                {{ $label }}
                <span class="text-xs {{ $active ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500' }} px-1.5 py-0.5 rounded-full">
                    {{ $counts[$key] ?? 0 }}
                </span>
            </a>
        @endforeach
    </div>

    {{-- Recherche --}}
    <form method="GET" action="{{ route('users.index') }}" class="flex flex-col sm:flex-row gap-2">
        <input type="hidden" name="role" value="{{ $role }}">
        <input type="text" name="q" value="{{ $search }}" placeholder="Rechercher par nom, prénom ou email…"
               class="flex-1 min-w-0 rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
        <button class="bg-gray-800 hover:bg-gray-900 text-white px-4 py-2 rounded-lg text-sm whitespace-nowrap">Rechercher</button>
        @if($search)
            <a href="{{ route('users.index', ['role' => $role]) }}"
               class="border border-gray-300 text-gray-600 hover:bg-gray-50 px-3 py-2 rounded-lg text-sm whitespace-nowrap text-center">Réinitialiser</a>
        @endif
    </form>

    @if($users->isEmpty())
    <div class="bg-white border border-gray-200 rounded-xl p-12 text-center text-gray-400">
        <div class="text-4xl mb-3">👥</div>
        <p class="font-medium">Aucun utilisateur ne correspond à ces critères.</p>
    </div>
    @else
    {{-- Vue mobile : cartes --}}
    <div class="sm:hidden space-y-3">
        @foreach($users as $u)
            @include('superadmin.users._mobile_card', [
                'u'                    => $u,
                'roleBadge'            => $roleBadge,
                'canPromoteAdminLocal' => $canPromoteAdminLocal,
                'canDemoteAdminLocal'  => $canDemoteAdminLocal,
                'canDelete'            => $canDelete,
                'canToggle'            => $canToggle,
            ])
        @endforeach
    </div>

    {{-- Vue desktop : tableau --}}
    <div class="hidden sm:block bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Utilisateur</th>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">Rôle</th>
                        <th class="px-4 py-3 text-left">Tontines</th>
                        <th class="px-4 py-3 text-left">Statut</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($users as $u)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $u->full_name ?: $u->name }}</div>
                            @if($u->phone)
                                <div class="text-xs text-gray-400">{{ $u->phone }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $u->email }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach($rolesOf($u) as $r)
                                    <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $roleBadge[$r] ?? 'bg-gray-100 text-gray-700' }}"
                                          title="{{ $loop->first ? 'Rôle principal' : 'Rôle additionnel' }}">
                                        {{ $roleLabels[$r] ?? $r }}{{ $loop->first ? '' : ' +' }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1 text-xs">
                                @if(($u->tontines_count ?? 0) > 0)
                                    <span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full" title="Participations">
                                        👤 {{ $u->tontines_count }}
                                    </span>
                                @endif
                                @if(($u->managed_tontines_count ?? 0) > 0)
                                    <span class="bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full" title="Tontines gérées (trésorier)">
                                        💼 {{ $u->managed_tontines_count }}
                                    </span>
                                @endif
                                @if(($u->admin_local_tontines_count ?? 0) > 0)
                                    <span class="bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full" title="Tontines administrées (admin local)">
                                        ⚙ {{ $u->admin_local_tontines_count }}
                                    </span>
                                @endif
                                @if((($u->tontines_count ?? 0) + ($u->managed_tontines_count ?? 0) + ($u->admin_local_tontines_count ?? 0)) === 0)
                                    <span class="text-gray-400">—</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if(! $u->isAdmin() && ! $u->isAdminLocal())
                                <span class="inline-flex items-center gap-1 text-xs">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $u->active ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                    {{ $u->active ? 'Actif' : 'Inactif' }}
                                </span>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2 flex-wrap">
                                @if(! $u->isAdmin() && ! $u->isAdminLocal())
                                    <a href="{{ route('admin.participants.show', $u) }}"
                                       class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Voir</a>
                                @elseif($u->isAdminLocal() && auth()->user()->isSuperAdmin())
                                    <a href="{{ route('superadmin.admin-locaux.show', $u) }}"
                                       class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Gérer</a>
                                @endif

                                @if($canPromoteAdminLocal && ! $u->isAdmin() && ! $u->isAdminLocal())
                                <form method="POST" action="{{ route('users.promote.admin-local', $u) }}"
                                      onsubmit="return confirm('Promouvoir {{ addslashes($u->full_name) }} en admin local ?')">
                                    @csrf
                                    <button class="text-blue-600 hover:text-blue-800 text-xs font-medium">↑ Admin local</button>
                                </form>
                                @endif

                                @if($canDemoteAdminLocal && $u->isAdminLocal())
                                <form method="POST" action="{{ route('users.demote.participant', $u) }}"
                                      onsubmit="return confirm('Rétrograder {{ addslashes($u->full_name) }} en participant ?')">
                                    @csrf
                                    <button class="text-amber-600 hover:text-amber-800 text-xs font-medium">↓ Participant</button>
                                </form>
                                @endif

                                @if($canToggle && ! $u->isAdmin() && ! $u->isAdminLocal())
                                <form method="POST" action="{{ route('users.toggle', $u) }}">
                                    @csrf @method('PATCH')
                                    <button class="text-gray-500 hover:text-gray-700 text-xs font-medium">
                                        {{ $u->active ? 'Désactiver' : 'Activer' }}
                                    </button>
                                </form>
                                @endif

                                @if($canDelete && ! $u->isAdmin() && $u->id !== auth()->id())
                                <form method="POST" action="{{ route('users.destroy', $u) }}"
                                      onsubmit="return confirm('⚠️ Supprimer définitivement {{ addslashes($u->full_name) }} ? Cette action est irréversible.')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-500 hover:text-red-700 text-xs font-medium">Supprimer</button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Invitations en attente --}}
    @if($pendingInvitations->isNotEmpty())
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-800 text-sm">Invitations en attente</h2>
            <span class="text-xs text-gray-400">{{ $pendingInvitations->count() }} invitation(s)</span>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($pendingInvitations as $inv)
            <div class="px-6 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-gray-700 flex items-center gap-2">
                        @if(($inv->channel ?? 'email') === 'sms')
                            <span class="text-xs px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700">SMS</span>
                            {{ $inv->phone ?? $inv->recipient }}
                        @else
                            <span class="text-xs px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700">Email</span>
                            {{ $inv->email }}
                        @endif
                    </div>
                    <div class="text-xs text-gray-400 mb-1">
                        {{ $inv->tontine ? $inv->tontine->name : 'Sans tontine' }}
                        @if($inv->inviter)
                            · Par {{ $inv->inviter->full_name }}
                        @endif
                        · Expire le {{ $inv->expires_at->format('d/m/Y') }}
                    </div>
                    <a href="{{ route('invitation.show', $inv->token) }}" target="_blank"
                       class="text-xs text-indigo-500 hover:text-indigo-700 break-all">
                        {{ route('invitation.show', $inv->token) }}
                    </a>
                </div>
                @if($canCancelInvitation)
                <form method="POST" action="{{ route('admin.invitations.destroy', $inv) }}" class="shrink-0">
                    @csrf @method('DELETE')
                    <button class="text-xs text-red-400 hover:text-red-600">Annuler</button>
                </form>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
