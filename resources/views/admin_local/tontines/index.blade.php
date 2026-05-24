@extends('layouts.app')
@section('title', 'Mes tontines')

@section('content')
<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Mes tontines</h1>
            <p class="text-sm text-gray-500 mt-0.5">Tontines que vous gérez et celles attribuées en lecture seule.</p>
        </div>
        <a href="{{ route('admin-local.tontines.create') }}"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium text-sm transition">
            + Nouvelle tontine
        </a>
    </div>

    @forelse($rows as $tontine)
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 hover:border-indigo-300 transition">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-lg font-bold text-gray-900">{{ $tontine->name }}</h2>
                    @if($tontine->is_own)
                        <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full font-medium">Ma tontine</span>
                    @else
                        <span class="bg-amber-100 text-amber-700 text-xs px-2 py-0.5 rounded-full font-medium">Lecture seule</span>
                    @endif
                </div>
                @if($tontine->description)
                <p class="text-gray-500 text-sm mt-1">{{ $tontine->description }}</p>
                @endif
                <div class="flex flex-wrap gap-3 mt-3 text-sm text-gray-600">
                    <span>💰 {{ number_format($tontine->cotisation_amount,2) }} €/mois</span>
                    <span>👥 {{ $tontine->participants_count }}/{{ $tontine->max_participants }}</span>
                    @if($tontine->bid_cap)
                    <span>📊 Enchère max {{ $tontine->bid_cap }}%</span>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3 shrink-0">
                @php $sc = ['active'=>'green','paused'=>'yellow','completed'=>'gray','archived'=>'red'][$tontine->status] ?? 'gray'; @endphp
                <span class="text-xs bg-{{ $sc }}-100 text-{{ $sc }}-700 px-2 py-0.5 rounded-full">
                    {{ __('app.status.'.$tontine->status) }}
                </span>
                <a href="{{ route('admin-local.tontines.show', $tontine) }}"
                   class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium">
                    {{ $tontine->is_own ? 'Gérer' : 'Voir' }} →
                </a>
            </div>
        </div>
    </div>
    @empty
    <div class="bg-white border border-gray-200 rounded-xl p-12 text-center text-gray-400">
        <div class="text-4xl mb-3">💰</div>
        <p>Aucune tontine disponible.</p>
        <a href="{{ route('admin-local.tontines.create') }}" class="text-indigo-600 text-sm mt-2 inline-block">
            Créer votre première tontine →
        </a>
    </div>
    @endforelse

</div>
@endsection
