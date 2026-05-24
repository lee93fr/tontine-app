{{-- Bandeau dismissible pour activer les notifications push.
     S'affiche seulement si l'utilisateur n'a aucune souscription active ET ne l'a pas masqué récemment.
     L'utilisateur peut activer en un clic ou fermer (mémorisation 7 jours via localStorage). --}}
@auth
@php
    $hasPush = auth()->user()->pushSubscriptions()->where('enabled', true)->exists();
@endphp
@if(! $hasPush)
<div
    x-data="pushBanner()"
    x-init="init()"
    x-show="visible"
    x-cloak
    x-transition.opacity
    class="max-w-7xl mx-auto px-4 mt-4">
    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="flex items-start sm:items-center gap-3 flex-1">
            <div class="shrink-0 w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-indigo-900">Activez les notifications push</p>
                <p class="text-xs text-indigo-700 mt-0.5">
                    Recevez en temps réel les ouvertures de tour, résultats d'enchères et rappels de paiement, même quand l'app est fermée.
                </p>
                <p x-show="error" x-text="error" class="text-xs text-red-600 mt-1"></p>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button @click="activate()" :disabled="busy"
                    class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-medium px-3 py-1.5 rounded-md whitespace-nowrap">
                <span x-show="!busy">Activer</span>
                <span x-show="busy" x-cloak>Activation…</span>
            </button>
            <a href="{{ route('notifications.preferences.edit') }}"
               class="text-xs text-indigo-700 hover:text-indigo-900 underline">
                Préférences
            </a>
            <button @click="dismiss()" type="button"
                    class="text-indigo-400 hover:text-indigo-700 p-1"
                    title="Masquer pour 7 jours">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function pushBanner() {
    return {
        visible: false,
        busy: false,
        error: null,

        async init() {
            // Pas de support → on cache
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                return;
            }
            // L'utilisateur a refusé au niveau navigateur → on cache (on ne peut rien faire)
            if (Notification.permission === 'denied') {
                return;
            }
            // Masqué récemment ?
            const until = parseInt(localStorage.getItem('pushBannerDismissedUntil') || '0', 10);
            if (until > Date.now()) {
                return;
            }
            // Si déjà souscrit côté navigateur, on cache
            try {
                const reg = await navigator.serviceWorker.ready;
                const existing = await reg.pushManager.getSubscription();
                if (existing) { return; }
            } catch (e) { /* ignore */ }

            this.visible = true;
        },

        dismiss() {
            const sevenDaysMs = 7 * 24 * 60 * 60 * 1000;
            localStorage.setItem('pushBannerDismissedUntil', String(Date.now() + sevenDaysMs));
            this.visible = false;
        },

        async activate() {
            this.busy = true;
            this.error = null;
            try {
                const perm = await Notification.requestPermission();
                if (perm !== 'granted') {
                    this.error = "Permission refusée. Vous pouvez l'activer plus tard depuis vos préférences.";
                    return;
                }

                const statusRes = await fetch('/api/push/status', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!statusRes.ok) throw new Error('status ' + statusRes.status);
                const { public_key } = await statusRes.json();

                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.vapidKey(public_key),
                });

                const json = sub.toJSON();
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const subRes = await fetch('/api/push/subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ endpoint: json.endpoint, keys: json.keys }),
                });
                if (!subRes.ok) throw new Error('subscribe ' + subRes.status);

                this.visible = false;
            } catch (e) {
                this.error = "Activation impossible : " + (e.message || 'erreur inconnue');
            } finally {
                this.busy = false;
            }
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
@endif
@endauth
