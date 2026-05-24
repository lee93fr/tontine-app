@extends('layouts.app')
@section('title', $user->full_name)

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <a href="{{ route('users.index') }}" class="text-sm text-gray-400 hover:text-gray-600">
            {{ __('app.participant.back') }}
        </a>
        <a href="{{ route('admin.participants.edit', $user) }}"
           class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
            {{ __('app.common.edit') }}
        </a>
    </div>

    {{-- En-tête --}}
    <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 text-xl font-bold">
            {{ strtoupper(mb_substr($user->first_name ?: $user->name, 0, 1)) }}
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $user->full_name }}</h1>
            <div class="flex items-center gap-2 mt-1">
                <span class="{{ $user->active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }} text-xs px-2 py-0.5 rounded-full">
                    {{ $user->active ? __('app.participant.active') : __('app.participant.inactive') }}
                </span>
                <span class="text-sm text-gray-400">{{ $user->tontines->count() }} tontine(s)</span>
            </div>
        </div>
    </div>

    {{-- Informations personnelles --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">{{ __('app.participant.personal_info') }}</h2>
        </div>
        <dl class="divide-y divide-gray-100">
            <div class="grid grid-cols-3 px-6 py-3">
                <dt class="text-sm text-gray-500">{{ __('app.common.first_name') }}</dt>
                <dd class="col-span-2 text-sm text-gray-900">{{ $user->first_name ?: '—' }}</dd>
            </div>
            <div class="grid grid-cols-3 px-6 py-3">
                <dt class="text-sm text-gray-500">{{ __('app.common.name') }}</dt>
                <dd class="col-span-2 text-sm text-gray-900">{{ $user->name }}</dd>
            </div>
            <div class="grid grid-cols-3 px-6 py-3">
                <dt class="text-sm text-gray-500">{{ __('app.common.email') }}</dt>
                <dd class="col-span-2 text-sm text-gray-900">{{ $user->email }}</dd>
            </div>
            <div class="grid grid-cols-3 px-6 py-3">
                <dt class="text-sm text-gray-500">{{ __('app.common.phone') }}</dt>
                <dd class="col-span-2 text-sm text-gray-900">{{ $user->phone ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Adresse postale --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">{{ __('app.address.section') }}</h2>
        </div>
        @if($user->address || $user->postal_code || $user->city)
        <dl class="divide-y divide-gray-100">
            @if($user->address)
            <div class="grid grid-cols-3 px-6 py-3">
                <dt class="text-sm text-gray-500">{{ __('app.address.address') }}</dt>
                <dd class="col-span-2 text-sm text-gray-900">{{ $user->address }}</dd>
            </div>
            @endif
            @if($user->postal_code || $user->city)
            <div class="grid grid-cols-3 px-6 py-3">
                <dt class="text-sm text-gray-500">{{ __('app.address.postal_code') }} / {{ __('app.address.city') }}</dt>
                <dd class="col-span-2 text-sm text-gray-900">
                    {{ trim($user->postal_code . ' ' . $user->city) }}
                </dd>
            </div>
            @endif
        </dl>
        @else
        <p class="px-6 py-4 text-sm text-gray-400 italic">—</p>
        @endif
    </div>

    {{-- RIB --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">{{ __('app.rib.section') }}</h2>
        </div>
        @if($user->iban || $user->bic)
        <dl class="divide-y divide-gray-100">
            @if($user->iban)
            <div class="grid grid-cols-3 px-6 py-3">
                <dt class="text-sm text-gray-500">{{ __('app.rib.iban') }}</dt>
                <dd class="col-span-2 text-sm font-mono text-gray-900 tracking-wide">{{ $user->iban }}</dd>
            </div>
            @endif
            @if($user->bic)
            <div class="grid grid-cols-3 px-6 py-3">
                <dt class="text-sm text-gray-500">{{ __('app.rib.bic') }}</dt>
                <dd class="col-span-2 text-sm font-mono text-gray-900">{{ $user->bic }}</dd>
            </div>
            @endif
        </dl>
        @else
        <p class="px-6 py-4 text-sm text-gray-400 italic">—</p>
        @endif
    </div>

    {{-- Tontines --}}
    @if($user->tontines->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">{{ __('app.tontine.title') }}</h2>
        </div>
        <ul class="divide-y divide-gray-100">
            @foreach($user->tontines as $t)
            <li class="px-6 py-3 flex items-center justify-between">
                <span class="text-sm text-gray-900">{{ $t->name }}</span>
                <a href="{{ route('admin.tontines.show', $t) }}" class="text-xs text-indigo-600 hover:text-indigo-800">
                    {{ __('app.tontine.manage') }}
                </a>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Binôme --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">👥 Binôme</h2>
            @if($user->partnerOf || $user->partner_id)
                <form method="POST" action="{{ route('admin.participants.partner.unlink', $user) }}"
                      onsubmit="return confirm('Dissoudre le binôme ?')">
                    @csrf @method('DELETE')
                    <button class="text-xs text-red-500 hover:text-red-700">Dissoudre</button>
                </form>
            @endif
        </div>
        <div class="px-6 py-5">
            @if($user->partner_id)
                {{-- Utilisateur secondaire --}}
                <div class="flex items-center gap-3 p-3 bg-indigo-50 border border-indigo-200 rounded-lg">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-sm">
                        {{ strtoupper(mb_substr($user->partner->first_name ?: $user->partner->name, 0, 1)) }}
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-indigo-900">{{ $user->partner->full_name }}</p>
                        <p class="text-xs text-indigo-600">{{ $user->partner->email }} · Membre principal</p>
                    </div>
                    <a href="{{ route('admin.participants.show', $user->partner) }}"
                       class="text-xs text-indigo-600 hover:text-indigo-800">Voir →</a>
                </div>
                <p class="text-xs text-gray-400 mt-2">Cet utilisateur est le membre secondaire du binôme. Les tontines, enchères et paiements sont gérés par le membre principal.</p>
            @elseif($user->partnerOf)
                {{-- Utilisateur principal --}}
                <div class="flex items-center gap-3 p-3 bg-indigo-50 border border-indigo-200 rounded-lg">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-sm">
                        {{ strtoupper(mb_substr($user->partnerOf->first_name ?: $user->partnerOf->name, 0, 1)) }}
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-indigo-900">{{ $user->partnerOf->full_name }}</p>
                        <p class="text-xs text-indigo-600">{{ $user->partnerOf->email }} · Membre secondaire</p>
                    </div>
                    <a href="{{ route('admin.participants.show', $user->partnerOf) }}"
                       class="text-xs text-indigo-600 hover:text-indigo-800">Voir →</a>
                </div>
                <p class="text-xs text-gray-400 mt-2">Cet utilisateur est le membre principal. Les deux partagent les mêmes tontines, enchères et paiements.</p>
            @else
                {{-- Pas de binôme : formulaire de liaison --}}
                @if($availablePartners->isNotEmpty())
                <form method="POST" action="{{ route('admin.participants.partner.link', $user) }}" class="flex gap-2">
                    @csrf
                    <select name="partner_id"
                            class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">— Sélectionner un partenaire —</option>
                        @foreach($availablePartners as $candidate)
                            <option value="{{ $candidate->id }}">{{ $candidate->full_name }} ({{ $candidate->email }})</option>
                        @endforeach
                    </select>
                    <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition whitespace-nowrap">
                        Créer le binôme
                    </button>
                </form>
                <p class="text-xs text-gray-400 mt-2">Le participant sélectionné deviendra le membre secondaire et partagera le slot de ce participant.</p>
                @else
                <p class="text-sm text-gray-400 italic">Aucun participant disponible pour former un binôme.</p>
                @endif
            @endif
        </div>
    </div>

    {{-- Délégation --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">🔁 Délégation</h2>
            @if($user->delegate_id)
                <form method="POST" action="{{ route('admin.participants.delegate.remove', $user) }}">
                    @csrf @method('DELETE')
                    <button class="text-xs text-red-500 hover:text-red-700">Retirer le délégué</button>
                </form>
            @endif
        </div>
        <div class="px-6 py-5 space-y-4">

            {{-- Délégué actuel --}}
            @if($user->delegate)
                <div class="flex items-center gap-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                    <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 font-bold text-sm">
                        {{ strtoupper(mb_substr($user->delegate->first_name ?: $user->delegate->name, 0, 1)) }}
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-amber-900">{{ $user->delegate->full_name }}</p>
                        <p class="text-xs text-amber-600">{{ $user->delegate->email }} · peut enchérir au nom de {{ $user->first_name }}</p>
                    </div>
                    <a href="{{ route('admin.participants.show', $user->delegate) }}"
                       class="text-xs text-amber-600 hover:text-amber-800">Voir →</a>
                </div>
            @else
                {{-- Formulaire de choix --}}
                @if($availableDelegates->isNotEmpty())
                <form method="POST" action="{{ route('admin.participants.delegate.set', $user) }}" class="flex gap-2">
                    @csrf @method('PATCH')
                    <select name="delegate_id"
                            class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">— Choisir un délégué —</option>
                        @foreach($availableDelegates as $d)
                            <option value="{{ $d->id }}">{{ $d->full_name }} ({{ $d->email }})</option>
                        @endforeach
                    </select>
                    <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition whitespace-nowrap">
                        Assigner
                    </button>
                </form>
                @else
                <p class="text-sm text-gray-400 italic">Aucun participant disponible pour délégation.</p>
                @endif
            @endif

            {{-- Qui délègue à cet utilisateur --}}
            @if($user->delegatedFor->isNotEmpty())
            <div class="pt-3 border-t border-gray-100">
                <p class="text-xs font-medium text-gray-500 mb-2">Enchérit également au nom de :</p>
                <ul class="space-y-1">
                    @foreach($user->delegatedFor as $delegatee)
                    <li class="flex items-center justify-between text-sm">
                        <span class="text-gray-700">{{ $delegatee->full_name }}</span>
                        <a href="{{ route('admin.participants.show', $delegatee) }}" class="text-xs text-indigo-500 hover:text-indigo-700">Voir →</a>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

        </div>
    </div>

    <x-help-panel title="Fiche participant">
        <p><strong>Délégation</strong> — le délégué assigné peut enchérir au nom de ce participant depuis son propre espace.</p>
        <p><strong>Binôme</strong> — deux participants peuvent partager un seul slot de tontine (cotisation partagée, gains partagés).</p>
        <p><strong>Désactiver</strong> — un participant désactivé ne peut plus se connecter mais ses données sont conservées.</p>
        <p><strong>Supprimer</strong> — la suppression n'est possible que si le participant n'est dans aucune tontine.</p>
    </x-help-panel>

    {{-- Actions --}}
    <div class="flex items-center gap-3">
        <form method="POST" action="{{ route('admin.participants.toggle', $user) }}">
            @csrf @method('PATCH')
            <button class="text-sm px-4 py-2 rounded-lg border {{ $user->active ? 'border-red-300 text-red-600 hover:bg-red-50' : 'border-green-300 text-green-600 hover:bg-green-50' }}">
                {{ $user->active ? __('app.participant.deactivate') : __('app.participant.activate') }}
            </button>
        </form>

        @if($user->tontines->isEmpty())
        <form method="POST" action="{{ route('admin.participants.destroy', $user) }}"
              onsubmit="return confirm('{{ __('app.participant.delete_confirm', ['name' => addslashes($user->full_name)]) }}')">
            @csrf @method('DELETE')
            <button class="text-sm px-4 py-2 rounded-lg border border-red-300 text-red-600 hover:bg-red-50">
                {{ __('app.common.delete') }}
            </button>
        </form>
        @endif
    </div>

</div>
@endsection
