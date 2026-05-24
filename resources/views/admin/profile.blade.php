@extends('layouts.app')
@section('title', __('app.profile.title'))

@section('content')
<div class="max-w-2xl mx-auto space-y-8">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('app.profile.title') }}</h1>
        <p class="text-gray-500 mt-1">{{ __('app.profile.subtitle') }}</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">{{ __('app.profile.personal_info') }}</h2>
        </div>
        <form method="POST" action="{{ route('admin.profile.update') }}" class="px-6 py-6 space-y-5">
            @csrf @method('PATCH')

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.profile.full_name') }}</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror">
                @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('app.profile.phone') }} <span class="text-gray-400 font-normal">({{ __('app.profile.phone_optional') }})</span>
                </label>
                <input type="text" id="phone" name="phone" inputmode="tel"
                       value="{{ old('phone', trim(preg_replace('/(\d{2})(?=\d)/', '$1 ', preg_replace('/\D/', '', $user->phone ?? '')))) }}"
                       oninput="const d=this.value.replace(/\D/g,'').slice(0,10);this.value=d.replace(/(\d{2})(?=\d)/g,'$1 ').trim()"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('phone') border-red-400 @enderror"
                       placeholder="06 12 34 56 78">
                @error('phone')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.profile.email') }}</label>
                <input type="text" value="{{ $user->email }}" disabled
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-400 cursor-not-allowed">
                <p class="text-xs text-gray-400 mt-1">{{ __('app.profile.email_readonly') }}</p>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                    {{ __('app.common.save') }}
                </button>
            </div>
        </form>
    </div>

    {{-- Mode trésorier --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Mode trésorier</h2>
            <p class="text-sm text-gray-500 mt-0.5">Basculez sur l'interface trésorier d'une tontine pour suivre les paiements.</p>
        </div>
        <div class="px-6 py-6">
            @if(session()->has('tresorier_tontine_id'))
                @php $activeTontine = $tontines->firstWhere('id', session('tresorier_tontine_id')); @endphp
                <div class="flex items-center gap-4">
                    <div class="flex-1 flex items-center gap-3 p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
                        <span class="text-2xl">🏦</span>
                        <div>
                            <p class="text-sm font-medium text-indigo-900">Mode trésorier actif</p>
                            <p class="text-xs text-indigo-700 mt-0.5">{{ $activeTontine?->name ?? 'Tontine inconnue' }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.tresorier_mode.exit') }}">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition">
                            Quitter le mode
                        </button>
                    </form>
                </div>
                <div class="mt-4 flex gap-3">
                    <a href="{{ route('tresorier.paiements') }}"
                       class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                        Voir les paiements →
                    </a>
                    <a href="{{ route('tresorier.dashboard') }}"
                       class="inline-flex items-center gap-2 border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-4 py-2 rounded-lg transition">
                        Tableau de bord trésorier
                    </a>
                </div>
            @else
                @if($tontines->isEmpty())
                    <p class="text-sm text-gray-500">Aucune tontine disponible.</p>
                @else
                    <form method="POST" action="{{ route('admin.tresorier_mode.enter') }}" class="flex items-end gap-4">
                        @csrf
                        <div class="flex-1">
                            <label for="tontine_id" class="block text-sm font-medium text-gray-700 mb-1">Sélectionner une tontine</label>
                            <select id="tontine_id" name="tontine_id"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                @foreach($tontines as $tontine)
                                    <option value="{{ $tontine->id }}">{{ $tontine->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition shrink-0">
                            Passer en mode trésorier
                        </button>
                    </form>
                    @error('tontine_id')<p class="text-red-600 text-xs mt-2">{{ $message }}</p>@enderror
                @endif
            @endif
        </div>
    </div>

    @include('partials.profile-preferences')

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">{{ __('app.profile.change_password') }}</h2>
        </div>
        <form method="POST" action="{{ route('admin.profile.password') }}" class="px-6 py-6 space-y-5">
            @csrf @method('PATCH')

            <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.profile.current_password') }}</label>
                <input type="password" id="current_password" name="current_password" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('current_password') border-red-400 @enderror">
                @error('current_password')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.profile.new_password') }}</label>
                <input type="password" id="password" name="password" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('password') border-red-400 @enderror">
                @error('password')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.profile.confirm_password') }}</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <p class="text-xs text-gray-400">{{ __('app.profile.password_hint') }}</p>

            <div class="flex justify-end pt-2">
                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                    {{ __('app.profile.change_btn') }}
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
