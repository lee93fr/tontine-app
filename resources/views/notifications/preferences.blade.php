@extends('layouts.app')
@section('title', 'Préférences de notifications')

@section('content')
<div class="max-w-2xl mx-auto space-y-8">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">Préférences de notifications</h1>
        <p class="text-gray-500 mt-1">Choisissez comment vous souhaitez être notifié.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Canaux de notification</h2>
        </div>

        <form method="POST" action="{{ route('notifications.preferences.update') }}" class="px-6 py-6 space-y-6">
            @csrf
            @method('PATCH')

            {{-- Email --}}
            <div class="flex items-start gap-4">
                <div class="flex items-center h-6 mt-0.5">
                    <input type="hidden" name="notify_by_email" value="0">
                    <input
                        type="checkbox"
                        id="notify_by_email"
                        name="notify_by_email"
                        value="1"
                        {{ old('notify_by_email', $user->notify_by_email) ? 'checked' : '' }}
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                    >
                </div>
                <div>
                    <label for="notify_by_email" class="text-sm font-medium text-gray-800 cursor-pointer">
                        Notifications par email
                    </label>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Recevez les alertes importantes (enchères, résultats, invitations) sur <strong>{{ $user->email }}</strong>.
                    </p>
                </div>
            </div>

            {{-- SMS --}}
            <div
                x-data="{ smsEnabled: {{ old('notify_by_sms', $user->notify_by_sms) ? 'true' : 'false' }} }"
                class="border-t border-gray-100 pt-5 space-y-4"
            >
                <div class="flex items-start gap-4">
                    <div class="flex items-center h-6 mt-0.5">
                        <input type="hidden" name="notify_by_sms" value="0">
                        <input
                            type="checkbox"
                            id="notify_by_sms"
                            name="notify_by_sms"
                            value="1"
                            x-model="smsEnabled"
                            {{ old('notify_by_sms', $user->notify_by_sms) ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                        >
                    </div>
                    <div>
                        <label for="notify_by_sms" class="text-sm font-medium text-gray-800 cursor-pointer">
                            Notifications par SMS
                        </label>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Recevez un SMS pour les événements critiques.
                            Le SMS ne sera envoyé que si votre numéro est vérifié.
                        </p>
                        @if($user->notify_by_sms && ! $user->hasVerifiedPhoneNumber())
                            <p class="text-xs text-amber-600 mt-1 font-medium">
                                ⚠ Numéro non vérifié — les SMS ne seront pas envoyés tant que votre numéro n'est pas confirmé.
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Numéro de téléphone (visible si SMS activé) --}}
                <div x-show="smsEnabled" x-transition class="pl-8">
                    <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1">
                        Numéro de téléphone pour SMS
                    </label>
                    <input
                        type="tel"
                        id="phone_number"
                        name="phone_number"
                        value="{{ old('phone_number', $user->phone_number) }}"
                        maxlength="30"
                        placeholder="+33 6 12 34 56 78"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('phone_number') border-red-400 @enderror"
                    >
                    @error('phone_number')
                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-xs text-gray-400 mt-1">
                        Format international requis (ex : +33612345678).
                        @if($user->sms_verified_at)
                            <span class="text-green-600 font-medium">✓ Numéro vérifié le {{ $user->sms_verified_at->format('d/m/Y') }}</span>
                        @else
                            Votre numéro devra être vérifié avant l'envoi du premier SMS.
                        @endif
                    </p>
                </div>

                {{-- Événements pour lesquels recevoir un SMS --}}
                <div x-show="smsEnabled" x-transition class="pl-8 space-y-2 pt-3 border-t border-gray-100">
                    <p class="text-sm font-medium text-gray-800">Pour quels événements ?</p>
                    <p class="text-xs text-gray-500 -mt-1">
                        Cochez les événements pour lesquels vous voulez recevoir un SMS.
                        Par défaut : ouverture de tour + résultat de tour.
                    </p>

                    @php
                        $currentPrefs = old('sms_notification_preferences', $user->sms_notification_preferences ?? \App\Models\User::SMS_EVENTS_DEFAULT);
                    @endphp

                    {{-- hidden pour qu'on reçoive un array vide si tout décoché --}}
                    <input type="hidden" name="sms_notification_preferences[]" value="">

                    @foreach(\App\Models\User::SMS_EVENTS as $key => $label)
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox"
                                   name="sms_notification_preferences[]"
                                   value="{{ $key }}"
                                   @checked(in_array($key, $currentPrefs))
                                   class="h-4 w-4 mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-gray-700">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="border-t border-gray-100 pt-5 flex justify-end">
                <button
                    type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition"
                >
                    Enregistrer les préférences
                </button>
            </div>
        </form>
    </div>

    {{-- Notifications push --}}
    <div
        x-cloak
        x-data="pushNotifications()"
        x-init="init()"
        class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden"
    >
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Notifications push</h2>
            <p class="text-sm text-gray-500 mt-0.5">Recevez des alertes en temps réel dans votre navigateur, même sans email ni SMS.</p>
        </div>

        <div class="px-6 py-6 space-y-4">

            {{-- Chargement --}}
            <div x-show="loading" class="flex items-center gap-3 text-sm text-gray-500">
                <svg class="animate-spin h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Vérification en cours…
            </div>

            {{-- Navigateur non supporté --}}
            <div x-show="!loading && !supported" class="flex items-start gap-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
                <span class="text-xl leading-none mt-0.5">🚫</span>
                <div>
                    <p class="text-sm font-medium text-gray-700">Non disponible</p>
                    <p class="text-xs text-gray-500 mt-0.5">Votre navigateur ne prend pas en charge les notifications push (nécessite Chrome, Firefox ou Edge récent).</p>
                </div>
            </div>

            {{-- Contenu principal (navigateur supporté) --}}
            <div x-show="!loading && supported" class="space-y-4">

                {{-- Permission bloquée --}}
                <div x-show="permissionDenied" class="flex items-start gap-3 p-4 bg-amber-50 rounded-lg border border-amber-200">
                    <span class="text-xl leading-none mt-0.5">⚠️</span>
                    <div>
                        <p class="text-sm font-medium text-amber-800">Notifications bloquées par le navigateur</p>
                        <p class="text-xs text-amber-700 mt-0.5">
                            Vous avez précédemment refusé les notifications pour ce site. Pour les réactiver, modifiez les autorisations dans les paramètres de votre navigateur (icône 🔒 dans la barre d'adresse).
                        </p>
                    </div>
                </div>

                {{-- Statut actuel --}}
                <div class="flex items-center gap-3">
                    <span
                        :class="subscribed ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium"
                    >
                        <span x-text="subscribed ? '🔔 Activées sur cet appareil' : '🔕 Désactivées'"></span>
                    </span>
                </div>

                {{-- Boutons --}}
                <div x-show="!permissionDenied" class="flex flex-wrap gap-3">
                    <button
                        x-show="!subscribed"
                        @click="subscribe()"
                        :disabled="loading"
                        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        Activer les notifications push
                    </button>

                    <button
                        x-show="subscribed"
                        @click="unsubscribe()"
                        :disabled="loading"
                        class="inline-flex items-center gap-2 bg-white hover:bg-gray-50 disabled:opacity-50 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg border border-gray-300 transition"
                    >
                        Désactiver
                    </button>

                    <button
                        x-show="subscribed"
                        @click="sendTest()"
                        :disabled="loading"
                        class="inline-flex items-center gap-2 bg-white hover:bg-gray-50 disabled:opacity-50 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg border border-gray-300 transition"
                    >
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        Envoyer un test
                    </button>
                </div>

                {{-- Message de retour --}}
                <div
                    x-show="message !== null"
                    x-transition
                    :class="{
                        'bg-green-50 border-green-200 text-green-800': messageType === 'success',
                        'bg-red-50 border-red-200 text-red-800': messageType === 'error',
                        'bg-blue-50 border-blue-200 text-blue-800': messageType === 'info',
                    }"
                    class="flex items-center gap-2 px-4 py-3 rounded-lg border text-sm"
                >
                    <span x-text="messageType === 'success' ? '✅' : (messageType === 'error' ? '❌' : 'ℹ️')"></span>
                    <span x-text="message"></span>
                </div>

            </div>
        </div>
    </div>

    {{-- Lien retour vers le profil --}}
    <div class="text-sm text-gray-500">
        @if(auth()->user()->isAdmin())
            <a href="{{ route('admin.profile.edit') }}" class="text-indigo-600 hover:underline">← Retour au profil</a>
        @elseif(auth()->user()->isTresorier() && session()->has('tresorier_active_tontine_id'))
            <a href="{{ route('tresorier.profile.edit') }}" class="text-indigo-600 hover:underline">← Retour au profil</a>
        @elseif(auth()->user()->isTresorier())
            <a href="{{ route('participant.profile.edit') }}" class="text-indigo-600 hover:underline">← Retour au profil</a>
        @else
            <a href="{{ route('participant.profile.edit') }}" class="text-indigo-600 hover:underline">← Retour au profil</a>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
function pushNotifications() {
    return {
        supported: false,
        permissionDenied: false,
        subscribed: false,
        loading: true,
        message: null,
        messageType: 'info',
        publicKey: null,

        async init() {
            this.supported = 'serviceWorker' in navigator && 'PushManager' in window;
            if (!this.supported) {
                this.loading = false;
                return;
            }
            this.permissionDenied = Notification.permission === 'denied';
            await this.fetchStatus();
        },

        async fetchStatus() {
            this.loading = true;
            try {
                const data = await this.request('GET', '/api/push/status');
                this.subscribed = data.subscribed;
                this.publicKey = data.public_key;
            } catch {
                this.message = "Impossible de vérifier l'état de la souscription.";
                this.messageType = 'error';
            } finally {
                this.loading = false;
            }
        },

        async subscribe() {
            this.loading = true;
            this.message = null;
            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    this.permissionDenied = permission === 'denied';
                    this.message = 'Permission refusée. Activez les notifications dans les paramètres de votre navigateur.';
                    this.messageType = 'error';
                    return;
                }
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.vapidKey(this.publicKey),
                });
                const json = sub.toJSON();
                await this.request('POST', '/api/push/subscribe', { endpoint: json.endpoint, keys: json.keys });
                this.subscribed = true;
                this.message = 'Notifications push activées sur cet appareil.';
                this.messageType = 'success';
            } catch (e) {
                this.message = 'Erreur lors de l\'activation : ' + (e.message || 'inconnue');
                this.messageType = 'error';
            } finally {
                this.loading = false;
            }
        },

        async unsubscribe() {
            this.loading = true;
            this.message = null;
            try {
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.getSubscription();
                await this.request('POST', '/api/push/unsubscribe', { endpoint: sub?.endpoint ?? null });
                if (sub) await sub.unsubscribe();
                this.subscribed = false;
                this.message = 'Notifications push désactivées.';
                this.messageType = 'info';
            } catch (e) {
                this.message = 'Erreur lors de la désactivation : ' + (e.message || 'inconnue');
                this.messageType = 'error';
            } finally {
                this.loading = false;
            }
        },

        async sendTest() {
            this.loading = true;
            this.message = null;
            try {
                await this.request('POST', '/api/push/test', {
                    title: 'Test TontineApp',
                    body: 'Vos notifications push fonctionnent correctement !',
                });
                this.message = 'Notification de test envoyée. Vérifiez vos alertes navigateur.';
                this.messageType = 'success';
            } catch (e) {
                this.message = 'Erreur lors du test : ' + (e.message || 'inconnue');
                this.messageType = 'error';
            } finally {
                this.loading = false;
            }
        },

        async request(method, url, body) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const opts = {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
            };
            if (body !== undefined) opts.body = JSON.stringify(body);
            const res = await fetch(url, opts);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        },

        vapidKey(base64) {
            const pad = '='.repeat((4 - (base64.length % 4)) % 4);
            const b64 = (base64 + pad).replace(/-/g, '+').replace(/_/g, '/');
            const raw = atob(b64);
            return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
        },
    };
}
</script>
@endpush
