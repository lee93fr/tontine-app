@extends('layouts.app')
@section('title', 'Mon espace admin')

@section('content')
<div class="space-y-8">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">Tableau de bord</h1>
        <p class="text-gray-500 mt-1 text-sm">Bienvenue, {{ auth()->user()->full_name }}</p>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        @foreach([
            ['label' => 'Total tontines',       'value' => $stats['tontines'],         'icon' => '💰', 'color' => 'indigo'],
            ['label' => 'Mes tontines',          'value' => $stats['own_tontines'],     'icon' => '✏️', 'color' => 'blue'],
            ['label' => 'Tours ouverts',         'value' => $stats['open_rounds'],      'icon' => '🎯', 'color' => 'green'],
            ['label' => 'Paiements en attente',  'value' => $stats['pending_payments'], 'icon' => '⏳', 'color' => 'amber'],
        ] as $stat)
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div class="text-3xl mb-2">{{ $stat['icon'] }}</div>
            <div class="text-2xl font-bold text-gray-900">{{ $stat['value'] }}</div>
            <div class="text-sm text-gray-500 mt-1">{{ $stat['label'] }}</div>
        </div>
        @endforeach
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('admin-local.tontines.create') }}"
           class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium text-sm transition">
            + Nouvelle tontine
        </a>
        <a href="{{ route('admin-local.tontines.index') }}"
           class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg font-medium text-sm transition">
            Voir mes tontines
        </a>
    </div>

    @if($recentRounds->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Derniers tirages</h2>
        </div>

        {{-- Mobile --}}
        <div class="sm:hidden divide-y divide-gray-100">
            @foreach($recentRounds as $round)
            <div class="px-4 py-4 space-y-1">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium text-gray-900 text-sm">{{ $round->tontine->name }}</span>
                    <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full">{{ __('app.status.'.$round->status) }}</span>
                </div>
                <div class="text-xs text-gray-500">Tour #{{ $round->round_number }} · {{ number_format($round->pot_amount, 2) }} €</div>
                <div class="text-xs text-gray-700">{{ $round->winner?->full_name ?? '—' }}</div>
            </div>
            @endforeach
        </div>

        {{-- Desktop --}}
        <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-6 py-3 text-left">Tontine</th>
                    <th class="px-6 py-3 text-left">Tour</th>
                    <th class="px-6 py-3 text-left">Vainqueur</th>
                    <th class="px-6 py-3 text-left">Cagnotte</th>
                    <th class="px-6 py-3 text-left">Mode</th>
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
                            <span class="bg-yellow-100 text-yellow-700 text-xs px-2 py-0.5 rounded-full">Tirage</span>
                        @else
                            <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">Enchère</span>
                        @endif
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
