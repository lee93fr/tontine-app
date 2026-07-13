@extends('layouts.app')
@section('title', __('app.tontine.title'))
@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-bold text-gray-900">{{ __('app.tontine.title') }}</h1>
            @if(($totalFavorites ?? 0) > 0 && ! ($showAll ?? false))
                <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">
                    ★ Favoris uniquement ({{ $totalFavorites }}/{{ $totalAll }})
                </span>
            @endif
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if(($totalFavorites ?? 0) > 0 && ! ($showAll ?? false) && $totalAll > $totalFavorites)
                <a href="{{ route('admin.tontines.index', ['all' => 1]) }}"
                   class="border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium">
                    Afficher tout ({{ $totalAll }})
                </a>
            @elseif(($showAll ?? false) && ($totalFavorites ?? 0) > 0)
                <a href="{{ route('admin.tontines.index') }}"
                   class="border border-amber-300 hover:bg-amber-50 text-amber-700 px-3 py-2 rounded-lg text-sm font-medium">
                    ★ Favoris uniquement
                </a>
            @endif
            <a href="{{ route('admin.tontines.create') }}"
               class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium text-sm">
                {{ __('app.tontine.new') }}
            </a>
        </div>
    </div>

    @forelse($tontines as $tontine)
    @php $isFav = ($favoriteIds ?? collect())->contains($tontine->id); @endphp
    <div class="bg-white border {{ $isFav ? 'border-amber-200' : 'border-gray-200' }} rounded-xl shadow-sm p-5 hover:border-indigo-300 transition">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <form method="POST" action="{{ route('admin.tontines.favorite.toggle', $tontine) }}" class="shrink-0">
                        @csrf
                        <button type="submit"
                                title="{{ $isFav ? 'Retirer des favoris' : 'Ajouter aux favoris' }}"
                                class="{{ $isFav ? 'text-amber-400 hover:text-amber-500' : 'text-gray-300 hover:text-amber-400' }} transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="{{ $isFav ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.539 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.539-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                        </button>
                    </form>
                    {{ $tontine->name }}
                </h2>
                @if($tontine->description)
                <p class="text-gray-500 text-sm mt-1">{{ $tontine->description }}</p>
                @endif
                <div class="flex flex-wrap gap-3 mt-3 text-sm text-gray-600">
                    <span>💰 {{ number_format($tontine->cotisation_amount,2) }} {{ __('app.tontine.cotisation_short') }}</span>
                    <span>👥 {{ $tontine->participants_count }}/{{ $tontine->max_participants }}</span>
                    <span>📊 {{ __('app.tontine.bid_cap') }} {{ $tontine->bid_cap }}%</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3 shrink-0">
                @php $sc = ['active'=>'green','paused'=>'yellow','completed'=>'gray','archived'=>'red'][$tontine->status] ?? 'gray'; @endphp
                <span class="text-xs bg-{{ $sc }}-100 text-{{ $sc }}-700 px-2 py-0.5 rounded-full">
                    {{ __('app.status.'.$tontine->status) }}
                </span>
                <a href="{{ route('admin.tontines.show', $tontine) }}"
                   class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium">
                    {{ __('app.tontine.manage') }}
                </a>
                @if($tontine->status !== 'archived')
                <form method="POST" action="{{ route('admin.tontines.archive', $tontine) }}"
                      onsubmit="return confirm('{{ __('app.tontine.archive_confirm', ['name' => addslashes($tontine->name)]) }}')">
                    @csrf @method('PATCH')
                    <button type="submit"
                            class="border border-gray-300 hover:bg-gray-50 text-gray-600 px-3 py-1.5 rounded-lg text-sm font-medium">
                        {{ __('app.tontine.archive_btn') }}
                    </button>
                </form>
                @else
                <form method="POST" action="{{ route('admin.tontines.destroy', $tontine) }}"
                      onsubmit="return confirm('{{ __('app.tontine.delete_confirm1', ['name' => addslashes($tontine->name)]) }}')
                                    && confirm('{{ __('app.tontine.delete_confirm2') }}')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="border border-red-300 hover:bg-red-50 text-red-600 px-3 py-1.5 rounded-lg text-sm font-medium">
                        🗑 {{ __('app.common.delete') }}
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="bg-white border border-gray-200 rounded-xl p-12 text-center text-gray-400">
        <div class="text-4xl mb-3">💰</div>
        <p>{{ __('app.tontine.no_tontines') }}</p>
        <a href="{{ route('admin.tontines.create') }}" class="text-indigo-600 text-sm mt-2 inline-block">{{ __('app.tontine.create_first') }}</a>
    </div>
    @endforelse
</div>
@endsection
