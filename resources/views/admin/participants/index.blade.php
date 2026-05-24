@extends('layouts.app')
@section('title', __('app.participant.title'))
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('app.participant.title') }}</h1>

        <div x-data="{ open: false }">
            <button @click="open = true"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                + Inviter un participant
            </button>

            {{-- Modal invitation standalone --}}
            <div x-show="open" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 @keydown.escape.window="open = false">
                <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg p-6 max-h-screen overflow-y-auto">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Inviter un participant</h3>
                    <p class="text-xs text-gray-400 mb-4">Le compte sera pré-créé. L'invité reçoit un lien pour choisir son mot de passe.</p>
                    <form method="POST" action="{{ route('admin.invitations.standalone') }}" class="space-y-3">
                        @csrf
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
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="email" required placeholder="marie@exemple.com"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Téléphone</label>
                            <input type="tel" name="phone" placeholder="06 00 00 00 00"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
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
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Créer & Inviter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Vue mobile : cartes --}}
    <div class="sm:hidden space-y-3">
        @foreach($participants as $p)
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="min-w-0">
                    <a href="{{ route('admin.participants.show', $p) }}" class="font-medium text-gray-900 hover:text-indigo-600 block">
                        {{ $p->full_name }}
                    </a>
                    <div class="text-xs text-gray-500 mt-0.5 truncate">{{ $p->email }}</div>
                    @if($p->partner_id)
                        <div class="text-xs text-indigo-500 mt-0.5">👥 Binôme de {{ $p->partner->full_name }}</div>
                    @elseif($p->partnerOf)
                        <div class="text-xs text-indigo-500 mt-0.5">👥 Binôme avec {{ $p->partnerOf->full_name }}</div>
                    @endif
                </div>
                <div class="flex flex-col items-end gap-1 shrink-0">
                    @if($p->active)
                        <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">{{ __('app.participant.active') }}</span>
                    @else
                        <span class="bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded-full">{{ __('app.participant.inactive') }}</span>
                    @endif
                    <span class="text-xs text-gray-400">{{ $p->partner_id ? ($p->partner->tontines->count() ?? 0) : $p->tontines->count() }} tontine(s)</span>
                </div>
            </div>
            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <a href="{{ route('admin.participants.show', $p) }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Voir →</a>
                <form method="POST" action="{{ route('admin.participants.toggle', $p) }}">
                    @csrf @method('PATCH')
                    <button class="text-xs {{ $p->active ? 'text-red-500 hover:text-red-700' : 'text-green-600 hover:text-green-800' }}">
                        {{ $p->active ? __('app.participant.deactivate') : __('app.participant.activate') }}
                    </button>
                </form>
                @if($p->tontines->isEmpty())
                <form method="POST" action="{{ route('admin.participants.destroy', $p) }}"
                      onsubmit="return confirm('{{ __('app.participant.delete_confirm', ['name' => addslashes($p->full_name)]) }}')">
                    @csrf @method('DELETE')
                    <button class="text-xs text-red-400 hover:text-red-600">{{ __('app.common.delete') }}</button>
                </form>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- Vue desktop : tableau --}}
    <div class="hidden sm:block bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-6 py-3 text-left">{{ __('app.participant.col_name') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('app.participant.col_email') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('app.participant.col_tontines') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('app.participant.col_status') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('app.participant.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($participants as $p)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <a href="{{ route('admin.participants.show', $p) }}" class="font-medium text-gray-900 hover:text-indigo-600">
                            {{ $p->full_name }}
                        </a>
                        @if($p->partner_id)
                            <div class="text-xs text-indigo-500 mt-0.5">👥 Binôme de {{ $p->partner->full_name }}</div>
                        @elseif($p->partnerOf)
                            <div class="text-xs text-indigo-500 mt-0.5">👥 Binôme avec {{ $p->partnerOf->full_name }}</div>
                        @elseif($p->city)
                            <div class="text-xs text-gray-400">{{ $p->city }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-gray-500">{{ $p->email }}</td>
                    <td class="px-6 py-4 text-gray-500">
                        {{ $p->partner_id ? ($p->partner->tontines->count() ?? 0) : $p->tontines->count() }}
                    </td>
                    <td class="px-6 py-4">
                        @if($p->active)
                            <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">{{ __('app.participant.active') }}</span>
                        @else
                            <span class="bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded-full">{{ __('app.participant.inactive') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-4">
                            <a href="{{ route('admin.participants.show', $p) }}"
                               class="text-xs text-gray-500 hover:text-gray-700">{{ __('app.common.edit') }}</a>
                            <form method="POST" action="{{ route('admin.participants.toggle', $p) }}">
                                @csrf @method('PATCH')
                                <button class="text-xs {{ $p->active ? 'text-red-500 hover:text-red-700' : 'text-green-600 hover:text-green-800' }}">
                                    {{ $p->active ? __('app.participant.deactivate') : __('app.participant.activate') }}
                                </button>
                            </form>
                            @if($p->tontines->isEmpty())
                            <form method="POST" action="{{ route('admin.participants.destroy', $p) }}"
                                  onsubmit="return confirm('{{ __('app.participant.delete_confirm', ['name' => addslashes($p->full_name)]) }}')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-400 hover:text-red-600">{{ __('app.common.delete') }}</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Invitations en attente --}}
    @if($pendingInvitations->isNotEmpty())
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-800 text-sm">Invitations en attente</h2>
            <span class="text-xs text-gray-400">{{ $pendingInvitations->count() }} invitation(s)</span>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($pendingInvitations as $inv)
            <div class="px-6 py-3 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-sm font-medium text-gray-700">{{ $inv->email }}</div>
                    <div class="text-xs text-gray-400 mb-1">
                        {{ $inv->tontine ? $inv->tontine->name : 'Sans tontine' }}
                        · Expire le {{ $inv->expires_at->format('d/m/Y') }}
                    </div>
                    <a href="{{ route('invitation.show', $inv->token) }}" target="_blank"
                       class="text-xs text-indigo-500 hover:text-indigo-700 break-all">
                        {{ route('invitation.show', $inv->token) }}
                    </a>
                </div>
                <form method="POST" action="{{ route('admin.invitations.destroy', $inv) }}" class="shrink-0">
                    @csrf @method('DELETE')
                    <button class="text-xs text-red-400 hover:text-red-600">Annuler</button>
                </form>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>

<x-help-panel title="Participants">
    <p><strong>Inviter</strong> — le compte est pré-créé immédiatement. L'invité reçoit un lien pour choisir son mot de passe et activer son compte.</p>
    <p><strong>Pré-créé</strong> — un participant pré-créé peut déjà être ajouté aux tontines et participer aux tours avant même d'avoir accepté l'invitation.</p>
    <p><strong>Délégation</strong> — depuis la fiche d'un participant, vous pouvez lui assigner un délégué qui enchérira en son nom.</p>
    <p><strong>Invitations en attente</strong> — les liens d'invitation sont visibles ici. Vous pouvez les annuler si nécessaire.</p>
</x-help-panel>

@endsection
