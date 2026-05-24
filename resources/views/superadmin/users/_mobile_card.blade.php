@php
    $roleLabels = [
        'superadmin'  => 'Super-admin',
        'admin'       => 'Admin',
        'admin_local' => 'Admin local',
        'tresorier'   => 'Trésorier',
        'participant' => 'Participant',
    ];

    $hats = [];
    $primary = $u->role;
    $hats[$primary] = $primary;
    if (($u->managed_tontines_count ?? 0) > 0 && $primary !== 'tresorier') {
        $hats['tresorier'] = 'tresorier';
    }
    if (($u->tontines_count ?? 0) > 0 && $primary !== 'participant') {
        $hats['participant'] = 'participant';
    }
@endphp
<div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
    <div class="flex items-start justify-between gap-3 mb-3">
        <div class="min-w-0 flex-1">
            <div class="font-medium text-gray-900 truncate">{{ $u->full_name ?: $u->name }}</div>
            <div class="text-xs text-gray-500 truncate">{{ $u->email }}</div>
            @if($u->phone)
                <div class="text-xs text-gray-400">{{ $u->phone }}</div>
            @endif
        </div>
        <div class="flex flex-col items-end gap-1 shrink-0">
            @foreach($hats as $r)
                <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $roleBadge[$r] ?? 'bg-gray-100 text-gray-700' }}">
                    {{ $roleLabels[$r] ?? $r }}{{ $loop->first ? '' : ' +' }}
                </span>
            @endforeach
        </div>
    </div>

    <div class="flex flex-wrap gap-1 text-xs mb-2">
        @if(($u->tontines_count ?? 0) > 0)
            <span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full">👤 {{ $u->tontines_count }}</span>
        @endif
        @if(($u->managed_tontines_count ?? 0) > 0)
            <span class="bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full">💼 {{ $u->managed_tontines_count }}</span>
        @endif
        @if(($u->admin_local_tontines_count ?? 0) > 0)
            <span class="bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full">⚙ {{ $u->admin_local_tontines_count }}</span>
        @endif
    </div>

    <div class="flex flex-wrap gap-2 pt-2 border-t border-gray-100">
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
              onsubmit="return confirm('⚠️ Supprimer définitivement {{ addslashes($u->full_name) }} ?')">
            @csrf @method('DELETE')
            <button class="text-red-500 hover:text-red-700 text-xs font-medium">Supprimer</button>
        </form>
        @endif
    </div>
</div>
