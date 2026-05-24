<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">SMTP — Email</h2>
            <p class="text-sm text-gray-500 mt-0.5">Compatible Brevo, Mailgun, SendGrid, OVH, etc.</p>
        </div>
        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full whitespace-nowrap
            {{ $smtp['host'] && $smtp['password_set'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
            <span class="w-2 h-2 rounded-full {{ $smtp['host'] && $smtp['password_set'] ? 'bg-green-500' : 'bg-amber-500' }}"></span>
            {{ $smtp['host'] && $smtp['password_set'] ? 'Configuré' : 'Incomplet' }}
        </span>
    </div>

    <form method="POST" action="{{ route('admin.settings.smtp.update') }}" class="px-6 py-6 space-y-5">
        @csrf @method('PATCH')

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label for="smtp_host" class="block text-sm font-medium text-gray-700 mb-1">Hôte</label>
                <input type="text" id="smtp_host" name="host" value="{{ $smtp['host'] }}" placeholder="smtp-relay.brevo.com"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="smtp_port" class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                <input type="number" id="smtp_port" name="port" value="{{ $smtp['port'] }}" placeholder="587"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label for="smtp_username" class="block text-sm font-medium text-gray-700 mb-1">Identifiant</label>
                <input type="text" id="smtp_username" name="username" value="{{ $smtp['username'] }}" autocomplete="off"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="smtp_encryption" class="block text-sm font-medium text-gray-700 mb-1">Chiffrement</label>
                <select id="smtp_encryption" name="encryption"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="tls"  {{ $smtp['encryption'] === 'tls'  ? 'selected' : '' }}>TLS</option>
                    <option value="ssl"  {{ $smtp['encryption'] === 'ssl'  ? 'selected' : '' }}>SSL</option>
                    <option value="none" {{ $smtp['encryption'] === 'none' ? 'selected' : '' }}>Aucun</option>
                </select>
            </div>
        </div>

        <div>
            <label for="smtp_password" class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
            <input type="password" id="smtp_password" name="password" autocomplete="new-password"
                   placeholder="{{ $smtp['password_set'] ? '•••••••• (laisser vide pour conserver)' : '' }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <p class="text-xs text-gray-400 mt-1">Stocké chiffré en base.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label for="smtp_from_address" class="block text-sm font-medium text-gray-700 mb-1">Adresse d'expédition</label>
                <input type="email" id="smtp_from_address" name="from_address" value="{{ $smtp['from_address'] }}" placeholder="tontine@exemple.com"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label for="smtp_from_name" class="block text-sm font-medium text-gray-700 mb-1">Nom d'expédition</label>
                <input type="text" id="smtp_from_name" name="from_name" value="{{ $smtp['from_name'] }}" placeholder="Tontine App"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                Enregistrer
            </button>
        </div>
    </form>

    {{-- Test SMTP --}}
    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
        <p class="text-sm font-medium text-gray-700 mb-2">Tester l'envoi</p>
        <form method="POST" action="{{ route('admin.settings.smtp.test') }}"
              class="flex flex-col sm:flex-row gap-2">
            @csrf
            <input type="email" name="to" required value="{{ auth()->user()->email }}"
                   class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <button class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition whitespace-nowrap">
                Envoyer test
            </button>
        </form>
    </div>
</div>

{{-- Suspension temporaire des envois (sauf superadmins) --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mt-6">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">Suspension temporaire</h2>
            <p class="text-sm text-gray-500 mt-0.5">Bloque tous les envois de mail aux utilisateurs réels. Les superadmins continuent de recevoir leurs mails.</p>
        </div>
        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full whitespace-nowrap
            {{ $mail['suspended'] ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
            <span class="w-2 h-2 rounded-full {{ $mail['suspended'] ? 'bg-red-500' : 'bg-green-500' }}"></span>
            {{ $mail['suspended'] ? 'Suspendus' : 'Actifs' }}
        </span>
    </div>

    <form method="POST" action="{{ route('admin.settings.mail.suspended.update') }}" class="px-6 py-6">
        @csrf @method('PATCH')
        <input type="hidden" name="suspended" value="0">
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="suspended" value="1" {{ $mail['suspended'] ? 'checked' : '' }}
                   class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm text-gray-700">
                <span class="font-medium">Suspendre l'envoi de mail aux utilisateurs</span><br>
                <span class="text-gray-500">Pendant la suspension, seuls les comptes superadmin reçoivent les mails déclenchés par l'application. Les autres destinataires sont ignorés silencieusement (un log est tracé).</span>
            </span>
        </label>
        <div class="flex justify-end pt-4">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                Enregistrer
            </button>
        </div>
    </form>
</div>
