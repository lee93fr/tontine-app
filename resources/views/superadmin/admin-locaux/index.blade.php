@extends('layouts.app')
@section('title', 'Administrateurs locaux')

@section('content')
<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Administrateurs locaux</h1>
            <p class="text-gray-500 mt-1 text-sm">Gérez les comptes admin_local et leurs tontines attribuées.</p>
        </div>
        <a href="{{ route('superadmin.admin-locaux.create') }}"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium text-sm transition">
            + Nouvel admin local
        </a>
    </div>

    @if($adminLocaux->isEmpty())
    <div class="bg-white border border-gray-200 rounded-xl p-12 text-center text-gray-400">
        <div class="text-4xl mb-3">👤</div>
        <p class="font-medium">Aucun administrateur local créé.</p>
        <a href="{{ route('superadmin.admin-locaux.create') }}" class="text-indigo-600 text-sm mt-2 inline-block">
            Créer le premier →
        </a>
    </div>
    @else
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-6 py-3 text-left">Nom</th>
                        <th class="px-6 py-3 text-left">Email</th>
                        <th class="px-6 py-3 text-left">Tontines attribuées</th>
                        <th class="px-6 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($adminLocaux as $al)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $al->full_name }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $al->email }}</td>
                        <td class="px-6 py-4">
                            @php $owned = $al->adminLocalTontines->where('pivot.can_edit', true); $assigned = $al->adminLocalTontines->where('pivot.can_edit', false); @endphp
                            <div class="flex flex-wrap gap-1">
                                @foreach($owned as $t)
                                    <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full">{{ $t->name }}</span>
                                @endforeach
                                @foreach($assigned as $t)
                                    <span class="bg-amber-100 text-amber-700 text-xs px-2 py-0.5 rounded-full">{{ $t->name }} (lecture)</span>
                                @endforeach
                                @if($al->adminLocalTontines->isEmpty())
                                    <span class="text-gray-400 text-xs italic">Aucune</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('superadmin.admin-locaux.show', $al) }}"
                                   class="text-indigo-600 hover:text-indigo-800 font-medium text-sm">
                                    Gérer →
                                </a>
                                <form method="POST" action="{{ route('superadmin.admin-locaux.destroy', $al) }}"
                                      onsubmit="return confirm('Rétrograder {{ addslashes($al->full_name) }} en participant ?')">
                                    @csrf @method('DELETE')
                                    <button class="text-amber-600 hover:text-amber-800 text-sm">Rétrograder</button>
                                </form>
                                <form method="POST" action="{{ route('superadmin.admin-locaux.force-delete', $al) }}"
                                      onsubmit="return confirm('⚠️ Supprimer définitivement le compte de {{ addslashes($al->full_name) }} ? Cette action est irréversible.')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-500 hover:text-red-700 text-sm">Supprimer</button>
                                </form>
                            </div>
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
