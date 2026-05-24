{{-- Préférences : notifications --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-800">Préférences</h2>
    </div>
    <div class="px-6 py-6 space-y-5">

        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-700">Notifications push</p>
                <p class="text-xs text-gray-400 mt-0.5">Gérez vos préférences de notifications</p>
            </div>
            <a href="{{ route('notifications.preferences.edit') }}"
               class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                Configurer →
            </a>
        </div>

    </div>
</div>
