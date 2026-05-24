@extends('layouts.app')
@section('title', __('app.history.title'))
@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">{{ __('app.history.title') }}</h1>

    @forelse($rounds as $round)
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5
        {{ $round->winner_id === auth()->id() ? 'border-yellow-300 bg-yellow-50' : '' }}">
        <div class="flex items-start justify-between">
            <div>
                <div class="text-sm text-indigo-600 font-medium">{{ $round->tontine->name }}</div>
                <div class="text-lg font-bold text-gray-900 mt-1">{{ __('app.round.col_round') }} #{{ $round->round_number }}</div>
                <div class="text-sm text-gray-500 mt-1">
                    {{ __('app.history.closing') }} : {{ $round->bid_closes_at->format('d/m/Y') }}
                    — {{ __('app.history.pot') }} : {{ number_format($round->pot_amount,2) }} €
                </div>
                @if($round->bids->first())
                <div class="text-sm text-gray-600 mt-1">
                    {{ __('app.history.my_bid') }} : <strong>{{ $round->bids->first()->amount }}%</strong>
                </div>
                @else
                <div class="text-sm text-gray-400 mt-1">{{ __('app.history.no_bid') }}</div>
                @endif
            </div>
            <div class="text-right">
                @if($round->winner_id === auth()->id())
                    <div class="text-2xl">🏆</div>
                    <div class="text-sm font-bold text-yellow-700">{{ __('app.history.won') }}</div>
                    @if($round->drawn_by_lot)
                        <div class="text-xs text-yellow-600">{{ __('app.history.by_lot') }}</div>
                    @endif
                @elseif($round->winner)
                    <div class="text-sm text-gray-500">{{ __('app.history.winner') }}</div>
                    <div class="text-sm font-medium text-gray-800">{{ $round->winner->full_name }}</div>
                    @if($round->drawn_by_lot)
                        <div class="text-xs text-gray-400">{{ __('app.history.draw') }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="bg-white border border-gray-200 rounded-xl p-12 text-center text-gray-400">
        <div class="text-4xl mb-3">📋</div>
        <p>{{ __('app.history.no_history') }}</p>
    </div>
    @endforelse

    {{ $rounds->links() }}
</div>
@endsection
