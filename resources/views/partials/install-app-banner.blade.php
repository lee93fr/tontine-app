{{-- Bandeau "Installer l'app sur l'écran d'accueil".
     - Android (Chrome/Edge/etc.) : utilise l'événement beforeinstallprompt pour déclencher l'install natif.
     - iOS Safari : pas d'API d'install, on affiche une mini-modale d'instructions ("Partager → Sur l'écran d'accueil").
     - Masqué si l'app tourne déjà en mode standalone (déjà installée) ou récemment dismissé. --}}
<div
    x-data="installAppBanner()"
    x-init="init()"
    x-cloak
    class="max-w-7xl mx-auto px-4 mt-4">

    {{-- Bandeau --}}
    <div x-show="visible"
         x-transition.opacity
         class="bg-gradient-to-r from-indigo-50 to-violet-50 border border-indigo-200 rounded-lg p-4 flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="flex items-start sm:items-center gap-3 flex-1">
            <div class="shrink-0 w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-indigo-900">Installer TontineApp sur votre téléphone</p>
                <p class="text-xs text-indigo-700 mt-0.5">
                    Ajoutez l'app à votre écran d'accueil pour l'ouvrir d'un toucher, sans passer par le navigateur.
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button @click="install()"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium px-3 py-1.5 rounded-md whitespace-nowrap">
                <span x-show="!isIos">Installer</span>
                <span x-show="isIos" x-cloak>Comment installer ?</span>
            </button>
            <button @click="dismiss()" type="button"
                    class="text-indigo-400 hover:text-indigo-700 p-1"
                    title="Masquer">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Modale iOS : instructions visuelles --}}
    <div x-show="iosModalOpen"
         x-cloak
         x-transition.opacity
         @keydown.escape.window="iosModalOpen = false"
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 px-4 pb-4 sm:pb-0">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6"
             @click.outside="iosModalOpen = false">
            <div class="flex items-start justify-between gap-3 mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Installer sur iPhone / iPad</h3>
                <button @click="iosModalOpen = false" class="text-gray-400 hover:text-gray-700 p-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <ol class="space-y-3 text-sm text-gray-700">
                <li class="flex gap-3">
                    <span class="shrink-0 w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">1</span>
                    <span>Touchez le bouton <strong>Partager</strong>
                        <span class="inline-flex items-center justify-center w-5 h-5 align-middle">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 12v7a2 2 0 002 2h12a2 2 0 002-2v-7M16 6l-4-4m0 0L8 6m4-4v14"/>
                            </svg>
                        </span>
                        en bas de Safari.</span>
                </li>
                <li class="flex gap-3">
                    <span class="shrink-0 w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">2</span>
                    <span>Faites défiler la liste et choisissez <strong>« Sur l'écran d'accueil »</strong>.</span>
                </li>
                <li class="flex gap-3">
                    <span class="shrink-0 w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">3</span>
                    <span>Validez en touchant <strong>Ajouter</strong> en haut à droite.</span>
                </li>
            </ol>
            <div class="mt-5 text-xs text-gray-500">
                L'app TontineApp apparaîtra sur votre écran d'accueil comme une application classique.
            </div>
            <div class="mt-5 flex justify-end">
                <button @click="iosModalOpen = false"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function installAppBanner() {
    return {
        visible: false,
        iosModalOpen: false,
        isIos: false,
        deferredPrompt: null,

        init() {
            // Déjà installée (standalone) → on ne fait rien
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true;
            if (isStandalone) {
                return;
            }

            const ua = window.navigator.userAgent.toLowerCase();

            // Restriction smartphone : on n'affiche le banner que sur téléphone/tablette.
            // Combinaison user-agent mobile + pointeur tactile primaire pour exclure les
            // navigateurs desktop (y compris Chrome desktop, qui supporte aussi l'install PWA).
            const isMobileUa = /android|iphone|ipad|ipod|mobile|silk|kindle|opera mini|iemobile/.test(ua);
            const isCoarsePointer = window.matchMedia('(pointer: coarse)').matches;
            if (! isMobileUa && ! isCoarsePointer) {
                return;
            }

            // Récemment dismissé ?
            const until = parseInt(localStorage.getItem('installBannerDismissedUntil') || '0', 10);
            if (until > Date.now()) {
                return;
            }

            // Détection iOS (pas d'API d'install → on affiche des instructions)
            const isIosDevice = /iphone|ipad|ipod/.test(ua);
            const isSafari = /safari/.test(ua) && !/crios|fxios|edgios/.test(ua);
            this.isIos = isIosDevice && isSafari;

            if (this.isIos) {
                this.visible = true;
                return;
            }

            // Android / Chromium : on attend beforeinstallprompt
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                this.deferredPrompt = e;
                this.visible = true;
            });

            // Si l'app a été installée pendant la session → on masque le bandeau
            window.addEventListener('appinstalled', () => {
                this.visible = false;
                this.deferredPrompt = null;
                localStorage.setItem('installBannerDismissedUntil', String(Date.now() + 365 * 24 * 60 * 60 * 1000));
            });
        },

        async install() {
            if (this.isIos) {
                this.iosModalOpen = true;
                return;
            }

            if (!this.deferredPrompt) {
                return;
            }

            this.deferredPrompt.prompt();
            try {
                const choice = await this.deferredPrompt.userChoice;
                if (choice && choice.outcome === 'dismissed') {
                    // L'utilisateur a refusé : on masque 30 jours
                    this.dismiss(30);
                }
            } catch (_) {
                // ignore
            } finally {
                this.deferredPrompt = null;
                this.visible = false;
            }
        },

        dismiss(days = 30) {
            const ms = days * 24 * 60 * 60 * 1000;
            localStorage.setItem('installBannerDismissedUntil', String(Date.now() + ms));
            this.visible = false;
        },
    };
}
</script>
@endpush
