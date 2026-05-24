@extends('layouts.app')
@section('title', 'Nouvel administrateur local')

@section('content')
<div class="max-w-lg mx-auto space-y-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('superadmin.admin-locaux.index') }}" class="text-gray-400 hover:text-gray-600 text-sm">← Retour</a>
    </div>

    <div>
        <h1 class="text-2xl font-bold text-gray-900">Nouvel administrateur local</h1>
        <p class="text-gray-500 mt-1 text-sm">Le compte pourra se connecter immédiatement et gérer ses propres tontines.</p>
    </div>

    <form method="POST" action="{{ route('superadmin.admin-locaux.store') }}" class="space-y-6">
        @csrf

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-lg font-semibold text-gray-800">Informations personnelles</h2>
            </div>
            <div class="px-6 py-6 space-y-4">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">Prénom</label>
                        <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('first_name') border-red-400 @enderror"
                               placeholder="Marie">
                        @error('first_name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror"
                               placeholder="Dupont">
                        @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('email') border-red-400 @enderror"
                           placeholder="marie@exemple.com">
                    @error('email')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-lg font-semibold text-gray-800">Mot de passe</h2>
            </div>
            <div class="px-6 py-6 space-y-4">

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Mot de passe <span class="text-red-500">*</span></label>
                    <input type="password" id="password" name="password" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('password') border-red-400 @enderror">
                    @error('password')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirmer le mot de passe <span class="text-red-500">*</span></label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

            </div>
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('superadmin.admin-locaux.index') }}"
               class="text-sm text-gray-500 hover:text-gray-700">Annuler</a>
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                Créer le compte
            </button>
        </div>
    </form>

</div>
@endsection
