@extends('layouts.app')
@section('title', 'Attribuer des tontines — ' . $user->full_name)

@section('content')
<div class="space-y-6">

    <div class="flex items-center gap-4">
        <a href="{{ route('superadmin.admin-locaux.index') }}" class="text-gray-400 hover:text-gray-600 text-sm">← Retour</a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $user->full_name }}</h1>
            <p class="text-gray-500 text-sm mt-0.5">{{ $user->email }} — Admin local</p>
        </div>
    </div>

    {{-- Tontines propres (can_edit = true) --}}
    @php
        $ownedTontines    = $user->adminLocalTontines->where('pivot.can_edit', true);
        $assignedTontines = $user->adminLocalTontines->where('pivot.can_edit', false);
    @endphp

    @if($ownedTontines->isNotEmpty())
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
        <h2 class="text-base font-semibold text-gray-800 mb-3">Tontines gérées par cet admin local</h2>
        <div class="flex flex-wrap gap-2">
            @foreach($ownedTontines as $t)
            <span class="bg-indigo-100 text-indigo-800 text-sm px-3 py-1 rounded-full font-medium">{{ $t->name }}</span>
            @endforeach
        </div>
        <p class="text-xs text-gray-400 mt-2">Ces tontines ont été créées par cet administrateur local — elles ne peuvent pas être retirées ici.</p>
    </div>
    @endif

    {{-- Formulaire d'attribution de tontines (can_edit = false) --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-1">Attribuer des tontines (lecture seule)</h2>
        <p class="text-sm text-gray-500 mb-4">
            Cochez les tontines que <strong>{{ $user->full_name }}</strong> pourra visualiser sans pouvoir les modifier.
            Les tontines déjà gérées par cet admin sont exclues.
        </p>

        <form method="POST" action="{{ route('superadmin.admin-locaux.tontines.assign', $user) }}">
            @csrf

            @if($allTontines->isEmpty())
                <p class="text-gray-400 text-sm italic">Aucune tontine disponible.</p>
            @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
                @foreach($allTontines as $tontine)
                @php
                    $isOwned    = $ownedTontines->contains('id', $tontine->id);
                    $isAssigned = $assignedIds->contains($tontine->id) && !$isOwned;
                @endphp
                <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition
                    {{ $isOwned ? 'bg-gray-50 border-gray-200 opacity-50 cursor-not-allowed' : 'border-gray-200 hover:border-indigo-300 hover:bg-indigo-50' }}">
                    <input type="checkbox"
                           name="tontine_ids[]"
                           value="{{ $tontine->id }}"
                           class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                           {{ $isAssigned ? 'checked' : '' }}
                           {{ $isOwned ? 'disabled' : '' }}>
                    <div class="min-w-0">
                        <div class="font-medium text-gray-900 text-sm">{{ $tontine->name }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">
                            {{ number_format($tontine->cotisation_amount, 2) }} € /mois
                            @if($isOwned)
                                · <span class="text-indigo-600 font-medium">Déjà gérée</span>
                            @endif
                        </div>
                    </div>
                </label>
                @endforeach
            </div>
            @endif

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg font-medium text-sm transition">
                    Enregistrer les attributions
                </button>
                <a href="{{ route('superadmin.admin-locaux.index') }}"
                   class="text-gray-500 hover:text-gray-700 text-sm">
                    Annuler
                </a>
            </div>
        </form>
    </div>

    {{-- Résumé actuel --}}
    @if($assignedTontines->isNotEmpty())
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <h3 class="text-sm font-semibold text-amber-800 mb-2">Tontines actuellement attribuées (lecture seule)</h3>
        <div class="flex flex-wrap gap-2">
            @foreach($assignedTontines as $t)
            <span class="bg-amber-100 text-amber-800 text-xs px-3 py-1 rounded-full">{{ $t->name }}</span>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
