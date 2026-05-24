<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'TontineApp') — {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.svg') }}">
    <meta name="theme-color" content="#3730a3">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Tontine">
    <meta name="mobile-web-app-capable" content="yes">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .countdown-urgent { animation: pulse 1s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    @auth
    @php
        $homeRoute = auth()->user()->isAdmin()
            ? route('admin.dashboard')
            : (auth()->user()->isAdminLocal()
                ? route('admin-local.dashboard')
                : (auth()->user()->isTresorier() && session()->has('tresorier_active_tontine_id')
                    ? route('tresorier.dashboard')
                    : route('participant.dashboard')));
        $managedTontines = auth()->user()->isTresorier()
            ? auth()->user()->managedTontines()->orderBy('name')->get()
            : collect();
        $adminTontines = auth()->user()->isAdmin() && !session()->has('tresorier_tontine_id')
            ? \App\Models\Tontine::where('status', '!=', 'archived')->orderBy('name')->get()
            : collect();
        $profileRoute = match(true) {
            auth()->user()->isAdmin()   => route('admin.profile.edit'),
            auth()->user()->isAdminLocal() => route('admin-local.profile.edit'),
            auth()->user()->isTresorier() && session()->has('tresorier_active_tontine_id') => route('tresorier.profile.edit'),
            default => route('participant.profile.edit'),
        };
    @endphp
    @endauth

    <nav class="bg-indigo-700 text-white shadow-lg relative z-40" x-data="{ mobileOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">

                {{-- Logo --}}
                <div class="flex items-center gap-2 shrink-0">
                    <img src="{{ asset('favicon.svg') }}" alt="TontineApp" class="w-8 h-8 rounded-md shadow-sm">
                    <a href="{{ $homeRoute ?? route('login') }}" class="font-bold text-xl tracking-tight">TontineApp</a>
                </div>

                @auth

                {{-- ===== DESKTOP NAV (md+) ===== --}}
                <div class="hidden md:flex items-center gap-1">

                    {{-- Liens de navigation principaux --}}
                    @if(auth()->user()->isAdmin() && session()->has('tresorier_tontine_id'))
                        <a href="{{ route('tresorier.dashboard') }}"
                           class="px-3 py-1.5 rounded text-sm font-medium transition {{ request()->routeIs('tresorier.dashboard') ? 'bg-white/20 text-white' : 'text-indigo-200 hover:text-white hover:bg-white/10' }}">
                            {{ __('app.nav.dashboard') }}
                        </a>
                        <a href="{{ route('tresorier.tontine') }}"
                           class="px-3 py-1.5 rounded text-sm font-medium transition {{ request()->routeIs('tresorier.tontine') ? 'bg-white/20 text-white' : 'text-indigo-200 hover:text-white hover:bg-white/10' }}">
                            Ma tontine
                        </a>
                        <a href="{{ route('tresorier.paiements') }}"
                           class="px-3 py-1.5 rounded text-sm font-medium transition {{ request()->routeIs('tresorier.paiements') ? 'bg-white/20 text-white' : 'text-indigo-200 hover:text-white hover:bg-white/10' }}">
                            Paiements
                        </a>

                    @elseif(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}"
                           class="px-3 py-1.5 rounded text-sm font-medium transition {{ request()->routeIs('admin.dashboard') ? 'bg-white/20 text-white' : 'text-indigo-200 hover:text-white hover:bg-white/10' }}">
                            {{ __('app.nav.dashboard') }}
                        </a>
                        <a href="{{ route('admin.tontines.index') }}"
                           class="px-3 py-1.5 rounded text-sm font-medium transition {{ request()->routeIs('admin.tontines.*') ? 'bg-white/20 text-white' : 'text-indigo-200 hover:text-white hover:bg-white/10' }}">
                            {{ __('app.nav.tontines') }}
                        </a>
                        <a href="{{ route('users.index') }}"
                           class="px-3 py-1.5 rounded text-sm font-medium transition {{ request()->routeIs('users.*', 'superadmin.admin-locaux.*') ? 'bg-white/20 text-white' : 'text-indigo-200 hover:text-white hover:bg-white/10' }}">
                            Utilisateurs
                        </a>
                        <a href="{{ route('admin.settings.index') }}"
                           class="px-3 py-1.5 rounded text-sm font-medium transition {{ request()->routeIs('admin.settings.*', 'admin.emails.*') ? 'bg-white/20 text-white' : 'text-indigo-200 hover:text-white hover:bg-white/10' }}">
                            Paramètres
                        </a>

                    @elseif(auth()->user()->isAdminLocal())
                        <a href="{{ route('admin-local.dashboard') }}"
                           class="px-3 py-1.5 rounded text-sm font-medium transition {{ request()->routeIs('admin-local.dashboard') ? 'bg-white/20 text-white' : 'text-indigo-200 hover:text-white hover:bg-white/10' }}">
                            {{ __('app.nav.dashboard') }}
                        </a>
                        <a href="{{ route('admin-local.tontines.index') }}"
                           class="px-3 py-1.5 rounded text-sm font-medium transition {{ request()->routeIs('admin-local.tontines.*') ? 'bg-white/20 text-white' : 'text-indigo-200 hover:text-white hover:bg-white/10' }}">
                            Mes tontines
                        </a>
                        <a href="{{ route('users.index') }}"
                           class="px-3 py-1.5 rounded text-sm font-medium transition {{ request()->routeIs('users.*') ? 'bg-white/20 text-white' : 'text-indigo-200 hover:text-white hover:bg-white/10' }}">
                            Utilisateurs
                        </a>

                    @elseif(auth()->user()->isTresorier() && session()->has('tresorier_active_tontine_id'))
                        <a href="{{ route('tresorier.dashboard') }}"
                           class="px-3 py-1.5 rounded text-sm font-medium transition {{ request()->routeIs('tresorier.dashboard') ? 'bg-white/20 text-white' : 'text-indigo-200 hover:text-white hover:bg-white/10' }}">
                            {{ __('app.nav.dashboard') }}
                        </a>
                        <a href="{{ route('tresorier.tontine') }}"
                           class="px-3 py-1.5 rounded text-sm font-medium transition {{ request()->routeIs('tresorier.tontine') ? 'bg-white/20 text-white' : 'text-indigo-200 hover:text-white hover:bg-white/10' }}">
                            Ma tontine
                        </a>
                        <a href="{{ route('tresorier.paiements') }}"
                           class="px-3 py-1.5 rounded text-sm font-medium transition {{ request()->routeIs('tresorier.paiements') ? 'bg-white/20 text-white' : 'text-indigo-200 hover:text-white hover:bg-white/10' }}">
                            Paiements
                        </a>

                    @else
                        <a href="{{ route('participant.dashboard') }}"
                           class="px-3 py-1.5 rounded text-sm font-medium transition {{ request()->routeIs('participant.dashboard') ? 'bg-white/20 text-white' : 'text-indigo-200 hover:text-white hover:bg-white/10' }}">
                            {{ __('app.nav.my_space') }}
                        </a>
                        <a href="{{ route('participant.history') }}"
                           class="px-3 py-1.5 rounded text-sm font-medium transition {{ request()->routeIs('participant.history') ? 'bg-white/20 text-white' : 'text-indigo-200 hover:text-white hover:bg-white/10' }}">
                            {{ __('app.nav.history') }}
                        </a>
                    @endif

                    {{-- Bouton de changement de mode --}}
                    @if(auth()->user()->isAdmin() && session()->has('tresorier_tontine_id'))
                        <div class="flex items-center ml-2 pl-3 border-l border-indigo-500">
                            <form method="POST" action="{{ route('admin.tresorier_mode.exit') }}">
                                @csrf @method('DELETE')
                                <button class="text-xs bg-amber-400 hover:bg-amber-300 text-amber-900 font-semibold px-3 py-1 rounded-full transition">← Admin</button>
                            </form>
                        </div>
                    @elseif(auth()->user()->isAdmin() && $adminTontines->isNotEmpty())
                        <div class="flex items-center ml-2 pl-3 border-l border-indigo-500">
                            @if($adminTontines->count() === 1)
                                <form method="POST" action="{{ route('admin.tresorier_mode.enter') }}">
                                    @csrf
                                    <input type="hidden" name="tontine_id" value="{{ $adminTontines->first()->id }}">
                                    <button class="text-xs bg-indigo-500 hover:bg-indigo-400 text-white font-semibold px-3 py-1 rounded-full border border-indigo-400 transition">Trésorier →</button>
                                </form>
                            @else
                                <div x-data="{ open: false }" class="relative">
                                    <button @click="open = !open" class="text-xs bg-indigo-500 hover:bg-indigo-400 text-white font-semibold px-3 py-1 rounded-full border border-indigo-400 transition">Trésorier ▾</button>
                                    <div x-show="open" @click.outside="open = false" x-cloak
                                         class="absolute right-0 top-8 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50 min-w-[200px]">
                                        @foreach($adminTontines as $at)
                                        <form method="POST" action="{{ route('admin.tresorier_mode.enter') }}">
                                            @csrf
                                            <input type="hidden" name="tontine_id" value="{{ $at->id }}">
                                            <button class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700">{{ $at->name }}</button>
                                        </form>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @elseif(auth()->user()->isTresorier() && session()->has('tresorier_active_tontine_id'))
                        <div class="flex items-center ml-2 pl-3 border-l border-indigo-500">
                            <form method="POST" action="{{ route('tresorier.mode.exit') }}">
                                @csrf @method('DELETE')
                                <button class="text-xs bg-green-400 hover:bg-green-300 text-green-900 font-semibold px-3 py-1 rounded-full transition">← Participant</button>
                            </form>
                        </div>
                    @elseif(auth()->user()->isTresorier() && $managedTontines->isNotEmpty())
                        <div class="flex items-center ml-2 pl-3 border-l border-indigo-500">
                            @if($managedTontines->count() === 1)
                                <form method="POST" action="{{ route('tresorier.mode.enter') }}">
                                    @csrf
                                    <input type="hidden" name="tontine_id" value="{{ $managedTontines->first()->id }}">
                                    <button class="text-xs bg-indigo-500 hover:bg-indigo-400 text-white font-semibold px-3 py-1 rounded-full border border-indigo-400 transition">Trésorier →</button>
                                </form>
                            @else
                                <div x-data="{ open: false }" class="relative">
                                    <button @click="open = !open" class="text-xs bg-indigo-500 hover:bg-indigo-400 text-white font-semibold px-3 py-1 rounded-full border border-indigo-400 transition">Trésorier ▾</button>
                                    <div x-show="open" @click.outside="open = false" x-cloak
                                         class="absolute right-0 top-8 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50 min-w-[200px]">
                                        @foreach($managedTontines as $mt)
                                        <form method="POST" action="{{ route('tresorier.mode.enter') }}">
                                            @csrf
                                            <input type="hidden" name="tontine_id" value="{{ $mt->id }}">
                                            <button class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700">{{ $mt->name }}</button>
                                        </form>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Langue --}}
                    <div class="flex items-center gap-1 ml-2 pl-3 border-l border-indigo-500">
                        <a href="{{ route('lang.switch', 'fr') }}"
                           class="text-xs px-2 py-0.5 rounded-full font-semibold transition {{ app()->getLocale() === 'fr' ? 'bg-white text-indigo-700' : 'text-indigo-300 hover:text-white' }}">FR</a>
                        <a href="{{ route('lang.switch', 'en') }}"
                           class="text-xs px-2 py-0.5 rounded-full font-semibold transition {{ app()->getLocale() === 'en' ? 'bg-white text-indigo-700' : 'text-indigo-300 hover:text-white' }}">EN</a>
                    </div>

                    {{-- User dropdown --}}
                    <div x-data="{ open: false }" class="relative flex items-center ml-2 pl-4 border-l border-indigo-500">
                        <button @click="open = !open" class="flex items-center gap-2 hover:text-indigo-200 transition">
                            <span class="text-sm text-indigo-200 max-w-[120px] truncate">{{ auth()->user()->name }}</span>
                            <span class="text-xs bg-indigo-900 px-2 py-0.5 rounded-full whitespace-nowrap">
                                @if(auth()->user()->isSuperAdmin()) Super Admin ★
                                @elseif(auth()->user()->isAdmin() && session()->has('tresorier_tontine_id')) Trésorier ✦
                                @elseif(auth()->user()->isAdmin()) {{ __('app.nav.admin_badge') }}
                                @elseif(auth()->user()->isAdminLocal()) Admin Local
                                @elseif(auth()->user()->isTresorier() && session()->has('tresorier_active_tontine_id')) Trésorier ✦
                                @elseif(auth()->user()->isTresorier()) Trésorier
                                @else {{ __('app.nav.participant_badge') }}
                                @endif
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-indigo-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             class="absolute right-0 top-9 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50 min-w-[190px]">
                            <a href="{{ $profileRoute }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Modifier mon profil
                            </a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    {{ __('app.nav.logout') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- ===== MOBILE : badge + hamburger (< md) ===== --}}
                <div class="md:hidden flex items-center gap-3">
                    <span class="text-xs bg-indigo-900 px-2 py-0.5 rounded-full whitespace-nowrap">
                        @if(auth()->user()->isSuperAdmin()) Super Admin ★
                        @elseif(auth()->user()->isAdmin() && session()->has('tresorier_tontine_id')) Trésorier ✦
                        @elseif(auth()->user()->isAdmin()) {{ __('app.nav.admin_badge') }}
                        @elseif(auth()->user()->isAdminLocal()) Admin Local
                        @elseif(auth()->user()->isTresorier() && session()->has('tresorier_active_tontine_id')) Trésorier ✦
                        @elseif(auth()->user()->isTresorier()) Trésorier
                        @else {{ __('app.nav.participant_badge') }}
                        @endif
                    </span>
                    <button @click="mobileOpen = !mobileOpen" class="p-1 text-indigo-200 hover:text-white transition">
                        <svg x-show="!mobileOpen" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        <svg x-show="mobileOpen" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                @endauth
            </div>
        </div>

        {{-- ===== MOBILE MENU PANEL ===== --}}
        @auth
        <div x-show="mobileOpen" @click.outside="mobileOpen = false" x-cloak
             class="md:hidden border-t border-indigo-600 bg-indigo-800">
            <div class="px-4 py-2 space-y-0">

                {{-- Bouton de sortie de mode (en haut, mis en avant) --}}
                @if(auth()->user()->isAdmin() && session()->has('tresorier_tontine_id'))
                    <form method="POST" action="{{ route('admin.tresorier_mode.exit') }}" class="py-2.5 border-b border-indigo-600 mb-1">
                        @csrf @method('DELETE')
                        <button class="flex items-center gap-2 text-sm font-semibold text-amber-300 hover:text-amber-200">
                            ← Retour en mode Admin
                        </button>
                    </form>
                @elseif(auth()->user()->isTresorier() && session()->has('tresorier_active_tontine_id'))
                    <form method="POST" action="{{ route('tresorier.mode.exit') }}" class="py-2.5 border-b border-indigo-600 mb-1">
                        @csrf @method('DELETE')
                        <button class="flex items-center gap-2 text-sm font-semibold text-green-300 hover:text-green-200">
                            ← Retour en mode Participant
                        </button>
                    </form>
                @endif

                {{-- Liens principaux --}}
                @if(auth()->user()->isAdmin() && session()->has('tresorier_tontine_id'))
                    <a href="{{ route('tresorier.dashboard') }}" class="flex items-center py-3 text-sm border-b border-indigo-700 {{ request()->routeIs('tresorier.dashboard') ? 'text-white font-medium' : 'text-indigo-100 hover:text-white' }}">{{ __('app.nav.dashboard') }}</a>
                    <a href="{{ route('tresorier.tontine') }}" class="flex items-center py-3 text-sm border-b border-indigo-700 {{ request()->routeIs('tresorier.tontine') ? 'text-white font-medium' : 'text-indigo-100 hover:text-white' }}">Ma tontine</a>
                    <a href="{{ route('tresorier.paiements') }}" class="flex items-center py-3 text-sm border-b border-indigo-700 {{ request()->routeIs('tresorier.paiements') ? 'text-white font-medium' : 'text-indigo-100 hover:text-white' }}">Paiements</a>

                @elseif(auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center py-3 text-sm border-b border-indigo-700 {{ request()->routeIs('admin.dashboard') ? 'text-white font-medium' : 'text-indigo-100 hover:text-white' }}">{{ __('app.nav.dashboard') }}</a>
                    <a href="{{ route('admin.tontines.index') }}" class="flex items-center py-3 text-sm border-b border-indigo-700 {{ request()->routeIs('admin.tontines.*') ? 'text-white font-medium' : 'text-indigo-100 hover:text-white' }}">{{ __('app.nav.tontines') }}</a>
                    <a href="{{ route('users.index') }}" class="flex items-center py-3 text-sm border-b border-indigo-700 {{ request()->routeIs('users.*', 'superadmin.admin-locaux.*') ? 'text-white font-medium' : 'text-indigo-100 hover:text-white' }}">Utilisateurs</a>
                    <a href="{{ route('admin.settings.index') }}" class="flex items-center py-3 text-sm border-b border-indigo-700 {{ request()->routeIs('admin.settings.*', 'admin.emails.*') ? 'text-white font-medium' : 'text-indigo-100 hover:text-white' }}">Paramètres</a>

                @elseif(auth()->user()->isAdminLocal())
                    <a href="{{ route('admin-local.dashboard') }}" class="flex items-center py-3 text-sm border-b border-indigo-700 {{ request()->routeIs('admin-local.dashboard') ? 'text-white font-medium' : 'text-indigo-100 hover:text-white' }}">{{ __('app.nav.dashboard') }}</a>
                    <a href="{{ route('admin-local.tontines.index') }}" class="flex items-center py-3 text-sm border-b border-indigo-700 {{ request()->routeIs('admin-local.tontines.*') ? 'text-white font-medium' : 'text-indigo-100 hover:text-white' }}">Mes tontines</a>
                    <a href="{{ route('users.index') }}" class="flex items-center py-3 text-sm border-b border-indigo-700 {{ request()->routeIs('users.*') ? 'text-white font-medium' : 'text-indigo-100 hover:text-white' }}">Utilisateurs</a>
                    @if($adminTontines->count() === 1)
                        <form method="POST" action="{{ route('admin.tresorier_mode.enter') }}" class="py-3 border-b border-indigo-700">
                            @csrf
                            <input type="hidden" name="tontine_id" value="{{ $adminTontines->first()->id }}">
                            <button class="text-sm font-semibold text-indigo-300 hover:text-white">Passer en mode Trésorier →</button>
                        </form>
                    @elseif($adminTontines->count() > 1)
                        @foreach($adminTontines as $at)
                        <form method="POST" action="{{ route('admin.tresorier_mode.enter') }}" class="py-3 border-b border-indigo-700">
                            @csrf
                            <input type="hidden" name="tontine_id" value="{{ $at->id }}">
                            <button class="text-sm font-semibold text-indigo-300 hover:text-white">{{ $at->name }} (Trésorier) →</button>
                        </form>
                        @endforeach
                    @endif

                @elseif(auth()->user()->isTresorier() && session()->has('tresorier_active_tontine_id'))
                    <a href="{{ route('tresorier.dashboard') }}" class="flex items-center py-3 text-sm border-b border-indigo-700 {{ request()->routeIs('tresorier.dashboard') ? 'text-white font-medium' : 'text-indigo-100 hover:text-white' }}">{{ __('app.nav.dashboard') }}</a>
                    <a href="{{ route('tresorier.tontine') }}" class="flex items-center py-3 text-sm border-b border-indigo-700 {{ request()->routeIs('tresorier.tontine') ? 'text-white font-medium' : 'text-indigo-100 hover:text-white' }}">Ma tontine</a>
                    <a href="{{ route('tresorier.paiements') }}" class="flex items-center py-3 text-sm border-b border-indigo-700 {{ request()->routeIs('tresorier.paiements') ? 'text-white font-medium' : 'text-indigo-100 hover:text-white' }}">Paiements</a>

                @elseif(auth()->user()->isTresorier())
                    <a href="{{ route('participant.dashboard') }}" class="flex items-center py-3 text-sm border-b border-indigo-700 {{ request()->routeIs('participant.dashboard') ? 'text-white font-medium' : 'text-indigo-100 hover:text-white' }}">{{ __('app.nav.my_space') }}</a>
                    <a href="{{ route('participant.history') }}" class="flex items-center py-3 text-sm border-b border-indigo-700 {{ request()->routeIs('participant.history') ? 'text-white font-medium' : 'text-indigo-100 hover:text-white' }}">{{ __('app.nav.history') }}</a>
                    @if($managedTontines->count() === 1)
                        <form method="POST" action="{{ route('tresorier.mode.enter') }}" class="py-3 border-b border-indigo-700">
                            @csrf
                            <input type="hidden" name="tontine_id" value="{{ $managedTontines->first()->id }}">
                            <button class="text-sm font-semibold text-indigo-300 hover:text-white">Passer en mode Trésorier →</button>
                        </form>
                    @elseif($managedTontines->count() > 1)
                        @foreach($managedTontines as $mt)
                        <form method="POST" action="{{ route('tresorier.mode.enter') }}" class="py-3 border-b border-indigo-700">
                            @csrf
                            <input type="hidden" name="tontine_id" value="{{ $mt->id }}">
                            <button class="text-sm font-semibold text-indigo-300 hover:text-white">{{ $mt->name }} (Trésorier) →</button>
                        </form>
                        @endforeach
                    @endif

                @else
                    <a href="{{ route('participant.dashboard') }}" class="flex items-center py-3 text-sm border-b border-indigo-700 {{ request()->routeIs('participant.dashboard') ? 'text-white font-medium' : 'text-indigo-100 hover:text-white' }}">{{ __('app.nav.my_space') }}</a>
                    <a href="{{ route('participant.history') }}" class="flex items-center py-3 text-sm border-b border-indigo-700 {{ request()->routeIs('participant.history') ? 'text-white font-medium' : 'text-indigo-100 hover:text-white' }}">{{ __('app.nav.history') }}</a>
                @endif

                {{-- Langue --}}
                <div class="flex items-center gap-2 py-3 border-b border-indigo-700">
                    <span class="text-xs text-indigo-400">Langue :</span>
                    <a href="{{ route('lang.switch', 'fr') }}"
                       class="text-xs px-3 py-1 rounded-full font-semibold transition {{ app()->getLocale() === 'fr' ? 'bg-white text-indigo-700' : 'text-indigo-300 hover:text-white border border-indigo-500' }}">FR</a>
                    <a href="{{ route('lang.switch', 'en') }}"
                       class="text-xs px-3 py-1 rounded-full font-semibold transition {{ app()->getLocale() === 'en' ? 'bg-white text-indigo-700' : 'text-indigo-300 hover:text-white border border-indigo-500' }}">EN</a>
                </div>

                {{-- Profil + déconnexion --}}
                <a href="{{ $profileRoute }}" class="flex items-center gap-2 py-3 text-sm text-indigo-100 hover:text-white border-b border-indigo-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Modifier mon profil
                </a>
                <form method="POST" action="{{ route('logout') }}" class="py-3">
                    @csrf
                    <button class="flex items-center gap-2 text-sm text-red-300 hover:text-red-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        {{ __('app.nav.logout') }}
                    </button>
                </form>
            </div>
        </div>
        @endauth
    </nav>
    @include('partials.push-banner')
    @include('partials.install-app-banner')

    @if(session('success'))
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 flex items-center gap-2">
                <span>✅</span> {{ session('success') }}
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 flex items-center gap-2">
                <span>❌</span> {{ session('error') }}
            </div>
        </div>
    @endif
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @yield('content')
    </main>
    <footer class="mt-16 py-6 border-t border-gray-200 text-center text-gray-400 text-sm">
        TontineApp &copy; {{ date('Y') }}
    </footer>
    <script>
    function formatIban(el) {
        const raw = el.value.replace(/\s/g,'').toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,34);
        el.value = raw.replace(/(.{4})(?=.)/g,'$1 ');
        const errEl = el.parentElement.querySelector('.iban-err');
        if (raw.startsWith('FR') && raw.length === 27) {
            const s = raw.slice(4) + raw.slice(0,4);
            const n = s.split('').map(c => /[A-Z]/.test(c) ? String(c.charCodeAt(0)-55) : c).join('');
            let m = 0; for (const d of n) m = (m*10+parseInt(d)) % 97;
            const msg = m === 1 ? '' : (el.dataset.ibanError || 'IBAN invalide');
            el.setCustomValidity(msg);
            if (errEl) { errEl.textContent = msg; errEl.classList.toggle('hidden', !msg); }
        } else {
            el.setCustomValidity('');
            if (errEl) errEl.classList.add('hidden');
        }
    }
    function countdown(closesAt, closedLabel) {
        return {
            remaining: 0,
            interval: null,
            init() { this.update(); this.interval = setInterval(() => this.update(), 1000); },
            update() { this.remaining = Math.max(0, Math.floor((new Date(closesAt) - Date.now()) / 1000)); },
            format() {
                if (this.remaining <= 0) return closedLabel || '{{ __('app.round.closed_label') }}';
                const d = Math.floor(this.remaining / 86400);
                const h = Math.floor((this.remaining % 86400) / 3600);
                const m = Math.floor((this.remaining % 3600) / 60);
                const s = this.remaining % 60;
                if (d > 0) return d+'j '+h+'h '+m+'m';
                if (h > 0) return h+'h '+m+'m '+s+'s';
                return m+'m '+s+'s';
            },
            isUrgent() { return this.remaining > 0 && this.remaining < 3600; }
        }
    }

    /**
     * Compte à rebours étendu : gère les dates passées (retard) et futures.
     * Utilisable pour des dates limites (deadline) qui peuvent être dépassées.
     * Tic tac toutes les secondes.
     */
    function deadlineCountdown(dueAt) {
        return {
            diff: 0,            // secondes restantes (négatif si retard)
            interval: null,
            init() { this.update(); this.interval = setInterval(() => this.update(), 1000); },
            update() { this.diff = Math.floor((new Date(dueAt) - Date.now()) / 1000); },
            get isPast()   { return this.diff < 0; },
            get isToday()  { return this.diff >= 0 && this.diff < 86400; },
            get isUrgent() { return this.diff > 0 && this.diff < 3 * 86400; },
            format() {
                const abs = Math.abs(this.diff);
                const d = Math.floor(abs / 86400);
                const h = Math.floor((abs % 86400) / 3600);
                const m = Math.floor((abs % 3600) / 60);
                const s = abs % 60;
                let str;
                if (d > 0)      str = d + 'j ' + h.toString().padStart(2, '0') + 'h ' + m.toString().padStart(2, '0') + 'm';
                else if (h > 0) str = h + 'h ' + m.toString().padStart(2, '0') + 'm ' + s.toString().padStart(2, '0') + 's';
                else if (m > 0) str = m + 'm ' + s.toString().padStart(2, '0') + 's';
                else            str = s + 's';
                return this.isPast ? '⏰ ' + str + ' de retard' : str;
            }
        }
    }
    </script>
    @auth
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
        }
    </script>
    @endauth
    @stack('scripts')
</body>
</html>
