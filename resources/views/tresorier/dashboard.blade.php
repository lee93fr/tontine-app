@extends('layouts.app')
@section('title', 'Tableau de bord — Trésorier')

@section('content')
<div class="space-y-8">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">Tableau de bord</h1>
        <p class="text-gray-500 mt-1">Tontine gérée : <strong>{{ $tontine->name }}</strong></p>
    </div>

    {{-- Statistiques paiements --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase tracking-wide">En attente</div>
            <div class="text-2xl font-bold text-yellow-600 mt-1">{{ $stats['pending'] }}</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase tracking-wide">En retard</div>
            <div class="text-2xl font-bold text-red-600 mt-1">{{ $stats['late'] }}</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Réglés</div>
            <div class="text-2xl font-bold text-green-600 mt-1">{{ $stats['paid'] }}</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Tours</div>
            <div class="text-2xl font-bold text-gray-900 mt-1">{{ $tontine->rounds->count() }}</div>
        </div>
    </div>

    @if($stats['late'] > 0)
    <div class="bg-red-50 border border-red-200 rounded-xl px-5 py-4 flex items-center gap-3">
        <span class="text-2xl">⚠️</span>
        <div>
            <p class="font-semibold text-red-700">{{ $stats['late'] }} paiement(s) en retard</p>
            <a href="{{ route('tresorier.paiements', ['statut' => 'late']) }}" class="text-sm text-red-600 hover:underline">
                Voir les retards →
            </a>
        </div>
    </div>
    @endif

    @if($openRound)
    <div class="bg-green-50 border border-green-200 rounded-xl px-5 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="text-2xl">🟢</span>
            <div>
                <p class="font-semibold text-green-800">Tour #{{ $openRound->round_number }} en cours</p>
                <p class="text-sm text-green-700">Clôture : {{ $openRound->bid_closes_at->format('d/m/Y à H:i') }}</p>
            </div>
        </div>
        <a href="{{ route('tresorier.rounds.show', $openRound) }}"
           class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
            Gérer →
        </a>
    </div>
    @endif

    {{-- Accès rapides --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="{{ route('tresorier.tontine') }}"
           class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm hover:border-indigo-300 hover:shadow-md transition group">
            <div class="text-2xl mb-2">📋</div>
            <div class="font-semibold text-gray-800 group-hover:text-indigo-600">Ma tontine</div>
            <div class="text-xs text-gray-400 mt-1">Participants, tours et enchères</div>
        </a>
        <a href="{{ route('tresorier.paiements') }}"
           class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm hover:border-indigo-300 hover:shadow-md transition group">
            <div class="text-2xl mb-2">💳</div>
            <div class="font-semibold text-gray-800 group-hover:text-indigo-600">Suivi des paiements</div>
            <div class="text-xs text-gray-400 mt-1">Tous les paiements à collecter</div>
        </a>
        <a href="{{ route('tresorier.paiements', ['statut' => 'pending']) }}"
           class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm hover:border-yellow-300 hover:shadow-md transition group">
            <div class="text-2xl mb-2">⏳</div>
            <div class="font-semibold text-gray-800 group-hover:text-yellow-600">Paiements en attente</div>
            <div class="text-xs text-gray-400 mt-1">{{ $stats['pending'] }} paiement(s) à valider</div>
        </a>
    </div>

</div>
@endsection
