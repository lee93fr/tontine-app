@props(['title' => 'Aide'])

<div x-data="{ open: false }" class="fixed bottom-6 right-6 z-50">
    {{-- Bouton flottant --}}
    <button @click="open = !open"
            class="w-12 h-12 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg flex items-center justify-center text-xl font-bold transition-transform hover:scale-110"
            title="Aide">
        <span x-show="!open">?</span>
        <span x-show="open" x-cloak>✕</span>
    </button>

    {{-- Panneau d'aide --}}
    <div x-show="open" x-cloak
         @click.outside="open = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="absolute bottom-16 right-0 w-80 bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
        <div class="bg-indigo-600 px-5 py-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-white text-lg">💡</span>
                <h3 class="text-white font-semibold text-sm">{{ $title }}</h3>
            </div>
            <button @click="open = false" class="text-indigo-200 hover:text-white text-lg leading-none">✕</button>
        </div>
        <div class="px-5 py-4 text-sm text-gray-600 space-y-3 max-h-96 overflow-y-auto">
            {{ $slot }}
        </div>
    </div>
</div>
