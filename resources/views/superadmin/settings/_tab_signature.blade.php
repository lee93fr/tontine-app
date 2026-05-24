<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">Signature électronique — DocuSeal</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Signature du règlement déclenchée à la pré-inscription d'un participant.
            </p>
        </div>
        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full whitespace-nowrap
            {{ $docuseal['enabled'] && $docuseal['api_key_set'] && $docuseal['template_id'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
            <span class="w-2 h-2 rounded-full {{ $docuseal['enabled'] && $docuseal['api_key_set'] && $docuseal['template_id'] ? 'bg-green-500' : 'bg-gray-400' }}"></span>
            {{ $docuseal['enabled'] && $docuseal['api_key_set'] && $docuseal['template_id'] ? 'Actif' : 'Inactif' }}
        </span>
    </div>

    <form method="POST" action="{{ route('admin.settings.docuseal.update') }}" class="px-6 py-6 space-y-5">
        @csrf @method('PATCH')

        <div class="flex items-start gap-4">
            <div class="flex items-center h-6 mt-0.5">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" id="docuseal_enabled" name="enabled" value="1"
                       {{ $docuseal['enabled'] ? 'checked' : '' }}
                       class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
            </div>
            <div>
                <label for="docuseal_enabled" class="text-sm font-medium text-gray-800 cursor-pointer">
                    Activer la signature DocuSeal
                </label>
                <p class="text-xs text-gray-500 mt-0.5">
                    Une demande de signature sera créée à chaque pré-inscription. Si désactivé, le flow continue normalement sans signature.
                </p>
            </div>
        </div>

        <div>
            <label for="ds_url" class="block text-sm font-medium text-gray-700 mb-1">URL DocuSeal</label>
            <input type="url" id="ds_url" name="url" value="{{ $docuseal['url'] }}" placeholder="https://docuseal.exemple.com"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <p class="text-xs text-gray-400 mt-1">Sans slash final. Ex : <code class="bg-gray-100 px-1 rounded">https://docuseal.leelyan.ovh</code></p>
        </div>

        <div>
            <label for="ds_api_key" class="block text-sm font-medium text-gray-700 mb-1">Clé API</label>
            <input type="password" id="ds_api_key" name="api_key" autocomplete="off"
                   placeholder="{{ $docuseal['api_key_set'] ? '•••••••• (laisser vide pour conserver)' : 'X-Auth-Token DocuSeal' }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <p class="text-xs text-gray-400 mt-1">Récupérable dans DocuSeal → Console → API. Stockée chiffrée.</p>
        </div>

        <div>
            <label for="ds_template_id" class="block text-sm font-medium text-gray-700 mb-1">ID du template global</label>
            <input type="number" id="ds_template_id" name="template_id"
                   value="{{ $docuseal['template_id'] }}"
                   placeholder="12345"
                   min="1"
                   inputmode="numeric"
                   autocomplete="off"
                   data-form-type="other"
                   data-lpignore="true"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('template_id') border-red-400 @enderror">
            <p class="text-xs text-gray-400 mt-1">L'ID <strong>numérique</strong> du template (visible dans l'URL DocuSeal : <code class="bg-gray-100 px-1 rounded">/templates/<strong>12345</strong></code>).</p>
            @error('template_id')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="ds_submitter_role" class="block text-sm font-medium text-gray-700 mb-1">
                Nom du rôle signataire
            </label>
            <input type="text" id="ds_submitter_role" name="submitter_role"
                   value="{{ $docuseal['submitter_role'] ?: 'Première partie' }}"
                   placeholder="Première partie"
                   autocomplete="off"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <p class="text-xs text-gray-400 mt-1">
                Doit matcher exactement le rôle défini dans ton template DocuSeal.
                Par défaut DocuSeal nomme le premier rôle <strong>« Première partie »</strong> (FR) ou <strong>« First Party »</strong> (EN).
                Visible dans DocuSeal UI dans la liste déroulante des rôles à droite du template.
            </p>
        </div>

        <div>
            <label for="ds_submitter_role2" class="block text-sm font-medium text-gray-700 mb-1">
                Nom du rôle signataire 2 <span class="text-gray-400 font-normal">(2e membre du binôme)</span>
            </label>
            <input type="text" id="ds_submitter_role2" name="submitter_role2"
                   value="{{ $docuseal['submitter_role2'] ?: 'Deuxième partie' }}"
                   placeholder="Deuxième partie"
                   autocomplete="off"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <p class="text-xs text-gray-400 mt-1">
                Rôle du 2e signataire dans le template <strong>binôme</strong>. Doit matcher le 2e rôle défini dans DocuSeal.
                Par défaut DocuSeal nomme le 2e rôle <strong>« Deuxième partie »</strong> (FR) ou <strong>« Second Party »</strong> (EN).
            </p>
        </div>

        <div>
            <label for="ds_webhook_secret" class="block text-sm font-medium text-gray-700 mb-1">Secret webhook</label>
            <input type="password" id="ds_webhook_secret" name="webhook_secret" autocomplete="off"
                   placeholder="{{ $docuseal['webhook_secret_set'] ? '•••••••• (laisser vide pour conserver)' : 'Secret partagé pour valider les webhooks' }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <p class="text-xs text-gray-400 mt-1">
                À configurer côté DocuSeal (Console → Webhooks → Secret). Sans secret, les webhooks sont acceptés en mode permissif.
            </p>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                Enregistrer
            </button>
        </div>
    </form>

    {{-- Test de connexion DocuSeal --}}
    <div class="px-6 py-4 border-t border-gray-100 bg-amber-50">
        <p class="text-sm font-medium text-gray-700 mb-2">Tester la connexion DocuSeal</p>
        <form method="POST" action="{{ route('admin.settings.docuseal.test') }}">
            @csrf
            <button type="submit"
                    class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                🔌 Lancer le diagnostic
            </button>
        </form>

        @if(session('docuseal_diagnostic'))
            @php $report = session('docuseal_diagnostic'); @endphp
            <div class="mt-3 bg-white border border-gray-200 rounded-lg p-3 text-xs space-y-1">
                <div><strong>Activé :</strong> {{ $report['enabled'] ? '✓ Oui' : '✗ Non' }}</div>
                <div><strong>URL configurée :</strong> {{ $report['url_set'] ? '✓ Oui' : '✗ Non' }}</div>
                <div><strong>Clé API configurée :</strong> {{ $report['api_key_set'] ? '✓ Oui' : '✗ Non' }}</div>
                <div><strong>Template ID :</strong> {{ $report['template_id'] ?: '(non défini)' }}</div>
                <div class="border-t border-gray-100 pt-2 mt-2 space-y-1">
                    @foreach($report['checks'] as $check)
                        <div class="{{ $check['ok'] ? 'text-green-700' : 'text-red-700' }}">
                            {{ $check['ok'] ? '✓' : '✗' }} {{ $check['msg'] }}
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Aide : URL webhook + champs attendus dans le template --}}
    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 space-y-4" x-data="{ copied: null }">
        {{-- URL webhook --}}
        <div>
            <p class="text-sm font-medium text-gray-700 mb-1">URL du webhook à déclarer dans DocuSeal</p>
            <div class="flex items-center gap-2 flex-wrap">
                <code id="webhook-url" class="text-xs bg-white border border-gray-200 px-2 py-1 rounded font-mono select-all">{{ url('/api/docuseal/webhook') }}</code>
                <button type="button"
                        @click="navigator.clipboard.writeText('{{ url('/api/docuseal/webhook') }}'); copied = 'webhook'; setTimeout(() => copied = null, 2000)"
                        class="text-xs px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-50 text-gray-600 transition-colors"
                        :class="copied === 'webhook' ? 'border-green-400 text-green-700 bg-green-50' : ''">
                    <span x-show="copied !== 'webhook'">📋 Copier</span>
                    <span x-show="copied === 'webhook'" x-cloak>✓ Copié !</span>
                </button>
            </div>
            <p class="text-xs text-gray-500 mt-1">Événements à activer : <code class="bg-gray-200 px-1 rounded">submission.completed</code>, <code class="bg-gray-200 px-1 rounded">submission.declined</code>.</p>
        </div>

        {{-- Champs de fusion --}}
        <details class="text-sm" open>
            <summary class="cursor-pointer font-medium text-gray-700 hover:text-gray-900">
                Champs de fusion disponibles pour les templates DocuSeal
            </summary>

            @php
            $fieldGroups = [
                'Participant 1 (injecté automatiquement)' => [
                    'participant_name'    => 'Nom et prénom complet',
                    'participant_email'   => 'Adresse email',
                    'participant_phone'   => 'Téléphone',
                    'participant_address' => 'Adresse postale complète',
                ],
                'Participant 2 — binôme uniquement (injecté automatiquement)' => [
                    'participant2_name'    => 'Nom et prénom du 2e membre du binôme',
                    'participant2_email'   => 'Adresse email du 2e membre',
                    'participant2_phone'   => 'Téléphone du 2e membre',
                    'participant2_address' => 'Adresse postale complète du 2e membre',
                ],
                'Identité tontine' => [
                    'tontine_name'        => 'Nom de la tontine',
                    'tresorier_full_name' => 'Nom complet du trésorier',
                    'tresorier_phone'     => 'Téléphone du trésorier',
                    'tresorier_iban'      => 'IBAN du compte de collecte',
                    'tresorier_bic'       => 'BIC du compte de collecte',
                ],
                'Montants' => [
                    'cotisation_amount'            => 'Cotisation mensuelle par participation (ex : 100,00 €)',
                    'participant_slots'            => 'Nombre de participations du signataire (1, 2…)',
                    'participant_cotisation_total' => 'Cotisation totale du signataire (cotisation × slots, ex : 200,00 € pour 2 participations)',
                    'participant_advance_total'    => 'Avance totale du signataire (avance × slots, ex : 1 600,00 € pour 2 participations)',
                    'preliminary_amount'           => 'Avance préliminaire unitaire par participation (ex : 800,00 €)',
                    'participations_count'         => 'Nombre total de tours de la tontine — calculé automatiquement (Σ slots de tous les participants)',
                    'max_participants'             => 'Capacité max déclarée dans les paramètres (peut différer du calculé)',
                    'pot_amount_total'     => 'Cagnotte mensuelle théorique (cotisation × participations_count)',
                    'advance_total'        => 'Total des avances collectées (avance × participations_count)',
                    'distribution_total'   => 'Montant brut distribué par tour (avance + cagnotte mensuelle)',
                    'bid_cap'              => 'Plafond du taux d\'enchère en % (ex : 20 %)',
                    'bid_max_amount'       => 'Plafond enchère en € (bid_cap % × cagnotte mensuelle)',
                    'penalty_per_day'      => 'Pénalité de retard par jour (ex : 1,00 €)',
                    'penalty_cap'          => 'Plafond de pénalité mensuelle (ex : 15,00 €)',
                ],
                'Flags (Oui / Non)' => [
                    'has_bidding'     => 'Enchères activées (Oui / Non)',
                    'has_preliminary' => 'Avance préliminaire activée (Oui / Non)',
                    'has_penalties'   => 'Pénalités de retard activées (Oui / Non)',
                ],
                'Dates et calendrier' => [
                    'first_round_month'        => 'Mois du 1er tour (ex : 07/2026)',
                    'first_round_month_label'  => 'Libellé du 1er tour (ex : 1er juillet 2026)',
                    'first_payment_due_date'   => 'Date de la 1ère cotisation due (ex : 5 juillet 2026)',
                    'first_bid_date'           => 'Date d\'ouverture de la 1ère enchère (ex : 1 juillet 2026)',
                    'preliminary_period_start' => 'Début de la période d\'avance (ex : 01/06/2026)',
                    'preliminary_period_end'   => 'Fin de la période d\'avance (ex : 30/06/2026)',
                    'preliminary_period_month' => 'Mois de la période d\'avance en toutes lettres (ex : juin 2026)',
                    'payment_day'              => 'Jour limite de paiement de la cotisation (ex : 5)',
                    'payout_day'               => 'Jour de versement de la cagnotte au bénéficiaire (ex : 15)',
                    'bid_day_open'             => 'Jour d\'ouverture des enchères dans le mois (ex : 1)',
                    'bid_day_close'            => 'Jour de clôture des enchères dans le mois (ex : 10)',
                ],
                'Signature (date injectée — champs à créer dans le template)' => [
                    'signature_date'        => 'Date de signature — injectée automatiquement (date-field en lecture seule)',
                    'participant_signature' => 'Signature du participant 1 — champ à créer dans DocuSeal (signature-field, rôle 1)',
                    'mention_lu_approuve'   => 'Mention « Lu et approuvé » participant 1 — champ à créer (text-field, rôle 1)',
                    'participant2_signature'=> 'Signature du participant 2 (binôme) — champ à créer dans DocuSeal (signature-field, rôle 2)',
                    'mention_lu_approuve2'  => 'Mention « Lu et approuvé » participant 2 (binôme) — champ à créer (text-field, rôle 2)',
                ],
            ];
            @endphp

            <div class="mt-3 space-y-4">
                @foreach($fieldGroups as $groupName => $fields)
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">{{ $groupName }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1">
                            @foreach($fields as $fieldName => $description)
                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            @click="navigator.clipboard.writeText('{{ $fieldName }}'); copied = '{{ $fieldName }}'; setTimeout(() => copied = null, 2000)"
                                            class="shrink-0 text-xs px-1 py-0.5 rounded border border-gray-200 bg-white hover:bg-indigo-50 hover:border-indigo-300 text-gray-500 transition-colors"
                                            :class="copied === '{{ $fieldName }}' ? 'border-green-400 text-green-700 bg-green-50' : ''"
                                            title="Copier le nom du champ">
                                        <span x-show="copied !== '{{ $fieldName }}'">📋</span>
                                        <span x-show="copied === '{{ $fieldName }}'" x-cloak>✓</span>
                                    </button>
                                    <code class="bg-white border border-gray-200 px-1.5 py-0.5 rounded font-mono text-xs text-indigo-700">{{ $fieldName }}</code>
                                    <span class="text-xs text-gray-500 truncate" title="{{ $description }}">{{ $description }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-gray-500 mt-3">
                💡 Cliquez sur 📋 pour copier le nom du champ, puis collez-le dans DocuSeal lors de la création du template.
            </p>
        </details>
    </div>
</div>
