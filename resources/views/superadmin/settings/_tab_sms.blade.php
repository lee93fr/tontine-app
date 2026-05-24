<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">SMS — Brevo</h2>
            <p class="text-sm text-gray-500 mt-0.5">API transactionnelle Brevo.</p>
        </div>
        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full whitespace-nowrap
            {{ $sms['enabled'] && $sms['api_key_set'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
            <span class="w-2 h-2 rounded-full {{ $sms['enabled'] && $sms['api_key_set'] ? 'bg-green-500' : 'bg-gray-400' }}"></span>
            {{ $sms['enabled'] && $sms['api_key_set'] ? 'Actif' : 'Inactif' }}
        </span>
    </div>

    <form method="POST" action="{{ route('admin.settings.sms.update') }}" class="px-6 py-6 space-y-5">
        @csrf @method('PATCH')

        <div class="flex items-start gap-4">
            <div class="flex items-center h-6 mt-0.5">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" id="sms_enabled" name="enabled" value="1"
                       {{ $sms['enabled'] ? 'checked' : '' }}
                       class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
            </div>
            <div>
                <label for="sms_enabled" class="text-sm font-medium text-gray-800 cursor-pointer">
                    Activer l'envoi de SMS
                </label>
                <p class="text-xs text-gray-500 mt-0.5">
                    Les notifications SMS ne seront envoyées que si cette case est cochée.
                </p>
            </div>
        </div>

        <div>
            <label for="sms_api_key" class="block text-sm font-medium text-gray-700 mb-1">Clé API Brevo</label>
            <input type="password" id="sms_api_key" name="api_key" autocomplete="off"
                   placeholder="{{ $sms['api_key_set'] ? '•••••••• (laisser vide pour conserver)' : 'xkeysib-...' }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <p class="text-xs text-gray-400 mt-1">Stockée chiffrée en base.</p>
        </div>

        <div>
            <label for="sms_sender" class="block text-sm font-medium text-gray-700 mb-1">
                Expéditeur <span class="text-gray-400 font-normal">(11 caractères max)</span>
            </label>
            <input type="text" id="sms_sender" name="sender" value="{{ $sms['sender'] }}" maxlength="11"
                   placeholder="Tontine"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <div>
            <label for="sms_type" class="block text-sm font-medium text-gray-700 mb-1">Type de SMS</label>
            <select id="sms_type" name="type"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="transactional" {{ $sms['type'] === 'transactional' ? 'selected' : '' }}>Transactionnel</option>
                <option value="marketing"     {{ $sms['type'] === 'marketing' ? 'selected' : '' }}>Marketing</option>
            </select>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                Enregistrer
            </button>
        </div>
    </form>

    {{-- Test SMS --}}
    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
        <p class="text-sm font-medium text-gray-700 mb-2">Tester l'envoi</p>
        <form method="POST" action="{{ route('admin.settings.sms.test') }}"
              class="flex flex-col sm:flex-row gap-2">
            @csrf
            <input type="text" name="to" required placeholder="+33612345678"
                   class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <input type="text" name="message" maxlength="160" placeholder="Message (optionnel)"
                   class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <button class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition whitespace-nowrap">
                Envoyer test
            </button>
        </form>
    </div>
</div>
