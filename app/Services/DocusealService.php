<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Tontine;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Intégration DocuSeal (instance self-hosted).
 *
 * - Lit la config dans app_settings (groupe « docuseal ») : url, api_key, template_id, webhook_secret
 * - Crée une submission par participant via POST /api/submissions
 * - Pré-remplit les champs du template avec les paramètres de la tontine
 * - Vérifie l'authenticité des webhooks (header X-DocuSeal-Signature)
 *
 * Docs API : https://www.docuseal.com/docs/api
 */
class DocusealService
{
    /**
     * Crée une submission DocuSeal pour un participant donné d'une tontine.
     * Retourne la réponse brute de DocuSeal (un tableau de submitters) ou null.
     */
    /**
     * Dernière raison d'échec d'un appel `createSubmission` (consultable juste après).
     * Permet à l'orchestrateur de stocker la raison dans le pivot pour affichage UI.
     */
    public ?string $lastError = null;

    public function createSubmission(Tontine $tontine, User $user): ?array
    {
        $this->lastError = null;
        $config = $this->config();

        if (! $config['enabled']) {
            $this->lastError = 'DocuSeal désactivé dans Paramètres > Signature.';
            return null;
        }

        if (! $config['url'] || ! $config['api_key']) {
            $this->lastError = 'URL ou clé API DocuSeal manquante.';
            Log::warning('DocuSeal non configuré, signature ignorée.', ['user_id' => $user->id]);
            return null;
        }

        $templateId = $this->templateIdForParticipant($tontine, $user);
        if (! $templateId) {
            $type = $this->participationType($user);
            $this->lastError = "Aucun template DocuSeal configuré pour le type « {$type} ». Configure-le dans Paramètres > Règlement & signature de la tontine.";
            Log::warning('DocuSeal : aucun template_id résolu pour ce participant.', [
                'tontine_id' => $tontine->id,
                'user_id'    => $user->id,
                'type'       => $type,
            ]);
            return null;
        }
        $url = rtrim($config['url'], '/') . '/api/submissions';

        // Pré-remplissage : on envoie à la fois `values` (raccourci) et `fields`
        // (format détaillé) pour maximiser la compatibilité avec les versions DocuSeal.
        $values = $this->buildFieldValues($tontine, $user);
        $fields = [];
        foreach ($values as $name => $value) {
            $str = (string) $value;
            $fields[] = ['name' => $name, 'default_value' => $str, 'readonly' => $str !== '' && $str !== '—'];
        }

        // DocuSeal exige le téléphone en E.164 (+33...). On normalise et on omet le champ
        // si le numéro est absent ou non normalisable.
        $normalizedPhone = SmsNotificationService::normalizePhone($user->phone);

        $submitter = [
            'role'   => $config['submitter_role'],
            'email'  => $user->email,
            'name'   => $user->full_name ?: $user->name,
            'values' => $values,
            'fields' => $fields,
        ];
        if ($normalizedPhone) {
            $submitter['phone'] = $normalizedPhone;
        }

        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $config['api_key'],
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($url, [
                'template_id'    => $templateId,
                // send_email: false → DocuSeal n'envoie PAS son email standard.
                // Notre app envoie son propre email via SignatureRequestNotification
                // pour avoir un branding cohérent avec le reste de l'application.
                'send_email'     => false,
                'submitters'     => [$submitter],
                'metadata' => [
                    'tontine_id' => $tontine->id,
                    'user_id'    => $user->id,
                ],
            ]);

            if (! $response->successful()) {
                $hint = match ($response->status()) {
                    404 => "Template DocuSeal #{$templateId} introuvable. Vérifie l'ID dans Paramètres > Signature et qu'il existe bien dans DocuSeal UI.",
                    401, 403 => 'Clé API DocuSeal invalide ou non autorisée.',
                    422 => 'Données invalides — vérifier les noms de champs et le rôle « Participant » dans le template.',
                    default => 'Erreur DocuSeal HTTP ' . $response->status(),
                };
                // Extrait le message court de l'API DocuSeal si présent (ex: {"error":"..."})
                $body = $response->json();
                $apiError = is_array($body) ? ($body['error'] ?? $body['message'] ?? null) : null;
                $this->lastError = $hint . ($apiError ? ' — ' . $apiError : '');
                Log::error('Échec création submission DocuSeal.', [
                    'url'         => $url,
                    'template_id' => $templateId,
                    'status'      => $response->status(),
                    'body'        => $response->body(),
                    'hint'        => $hint,
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            $this->lastError = 'Exception : ' . $e->getMessage();
            Log::error('Exception création submission DocuSeal.', [
                'url'         => $url,
                'template_id' => $templateId,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Diagnostique la configuration DocuSeal : retourne un tableau d'observations
     * pour aider l'admin à comprendre pourquoi l'envoi échoue.
     */
    public function diagnose(): array
    {
        $config = $this->config();
        $report = [
            'enabled'     => $config['enabled'],
            'url_set'     => ! empty($config['url']),
            'api_key_set' => ! empty($config['api_key']),
            'template_id' => $config['template_id'] ?? null,
            'checks'      => [],
        ];

        if (! $config['enabled']) {
            $report['checks'][] = ['ok' => false, 'msg' => 'DocuSeal est désactivé (case décochée).'];
            return $report;
        }

        if (! $config['url'] || ! $config['api_key']) {
            $report['checks'][] = ['ok' => false, 'msg' => 'URL ou clé API manquante.'];
            return $report;
        }

        if (! $config['template_id']) {
            $report['checks'][] = ['ok' => false, 'msg' => 'Aucun ID de template configuré.'];
            return $report;
        }

        // Tentative GET sur le template pour vérifier qu'il existe et que la clé fonctionne
        try {
            $resp = Http::withHeaders([
                'X-Auth-Token' => $config['api_key'],
                'Accept'       => 'application/json',
            ])->timeout(10)->get(rtrim($config['url'], '/') . '/api/templates/' . $config['template_id']);

            if ($resp->successful()) {
                $tpl = $resp->json();
                $report['checks'][] = ['ok' => true, 'msg' => "Template #{$config['template_id']} trouvé : « " . ($tpl['name'] ?? '?') . " »."];

                $submitters = $tpl['submitters'] ?? [];
                $roles = array_column($submitters, 'name');
                $expectedRole = $config['submitter_role'];
                if (! in_array($expectedRole, $roles, true)) {
                    $report['checks'][] = ['ok' => false, 'msg' => "Le template n'a pas de rôle « {$expectedRole} ». Rôles trouvés : " . (empty($roles) ? '(aucun)' : implode(', ', $roles)) . ". Mets à jour le champ « Nom du rôle signataire » ci-dessus pour matcher l'un des rôles du template."];
                } else {
                    $report['checks'][] = ['ok' => true, 'msg' => "Le rôle « {$expectedRole} » est défini sur le template."];
                }

                $fields = array_column($tpl['fields'] ?? [], 'name');
                $report['checks'][] = ['ok' => true, 'msg' => 'Champs détectés dans le template : ' . (empty($fields) ? '(aucun)' : implode(', ', $fields))];
            } elseif ($resp->status() === 404) {
                $report['checks'][] = ['ok' => false, 'msg' => "Template #{$config['template_id']} introuvable dans DocuSeal. Vérifie l'ID."];
            } elseif (in_array($resp->status(), [401, 403], true)) {
                $report['checks'][] = ['ok' => false, 'msg' => 'Clé API rejetée (' . $resp->status() . '). Vérifie la clé dans DocuSeal > Console > API.'];
            } else {
                $report['checks'][] = ['ok' => false, 'msg' => 'Erreur HTTP ' . $resp->status() . ' : ' . substr($resp->body(), 0, 200)];
            }
        } catch (\Throwable $e) {
            $report['checks'][] = ['ok' => false, 'msg' => 'Exception : ' . $e->getMessage()];
        }

        return $report;
    }

    /**
     * Détermine le type de participation d'un utilisateur dans une tontine :
     *   - 'double'     : slots > 1 uniquement (pas de partenaire)
     *   - 'binome'     : slots = 1 avec un partenaire (partner_id OU partnerOf)
     *   - 'individual' : sinon
     *
     * Cas mixte double+binôme : slots > 1 ET partenaire → 'double' (Annexe 5)
     * car c'est l'annexe la plus engageante ; le partenaire signe via le flux partner_.
     */
    public function participationType(User $user): string
    {
        $slots      = (int) ($user->pivot->slots ?? 1);
        $hasPartner = $user->partner_id || $user->partnerOf;

        if ($slots > 1) {
            return 'double';
        }
        if ($hasPartner) {
            return 'binome';
        }
        return 'individual';
    }

    /**
     * Résout le template DocuSeal à utiliser pour un participant donné.
     * Ordre de priorité :
     *   1. Template spécifique au type sur la tontine (individuel / binôme / double)
     *   2. Template par défaut de la tontine (docuseal_template_id)
     *   3. Template global configuré dans Paramètres > Signature
     *
     * Pour un secondaire de binôme (partner_id défini), on résout selon son type propre
     * (slots > 1 → double, sinon binome), pas en fonction du primaire.
     */
    public function templateIdForParticipant(Tontine $tontine, User $user): ?int
    {
        $type = $this->participationType($user);

        $typeSpecific = match ($type) {
            'individual' => $tontine->docuseal_template_individual_id,
            'binome'     => $tontine->docuseal_template_binome_id,
            'double'     => $tontine->docuseal_template_double_id,
            default      => null,
        };

        $globalTemplateId = $this->config()['template_id'] ?? null;

        return (int) ($typeSpecific ?: $tontine->docuseal_template_id ?: $globalTemplateId) ?: null;
    }

    /**
     * Crée une submission DocuSeal unique co-signée par les deux membres d'un binôme.
     * Le template doit avoir deux rôles : submitter_role (primaire) + submitter_role2 (partenaire).
     * Retourne un tableau de 2 submitters [primary, partner] ou null.
     */
    public function createBinomeSubmission(Tontine $tontine, User $primary, User $partner): ?array
    {
        $this->lastError = null;
        $config = $this->config();

        if (! $config['enabled']) {
            $this->lastError = 'DocuSeal désactivé dans Paramètres > Signature.';
            return null;
        }
        if (! $config['url'] || ! $config['api_key']) {
            $this->lastError = 'URL ou clé API DocuSeal manquante.';
            return null;
        }

        $templateId = $this->templateIdForParticipant($tontine, $primary);
        if (! $templateId) {
            $type = $this->participationType($primary);
            $this->lastError = "Aucun template DocuSeal configuré pour le type « {$type} ».";
            return null;
        }

        $sharedValues  = $this->buildFieldValues($tontine, $primary);
        $partnerValues = $this->buildPartnerFieldValues($partner);
        $allValues     = array_merge($sharedValues, $partnerValues);

        $fieldsArray = array_map(
            function ($name, $value) {
                $str = (string) $value;
                return ['name' => $name, 'default_value' => $str, 'readonly' => $str !== '' && $str !== '—'];
            },
            array_keys($allValues),
            array_values($allValues),
        );

        $phone1 = SmsNotificationService::normalizePhone($primary->phone);
        $phone2 = SmsNotificationService::normalizePhone($partner->phone);

        $submitter1 = [
            'role'   => $config['submitter_role'],
            'email'  => $primary->email,
            'name'   => $primary->full_name ?: $primary->name,
            'values' => $allValues,
            'fields' => $fieldsArray,
        ];
        if ($phone1) $submitter1['phone'] = $phone1;

        $submitter2 = [
            'role'   => $config['submitter_role2'],
            'email'  => $partner->email,
            'name'   => $partner->full_name ?: $partner->name,
            'values' => $allValues,
            'fields' => $fieldsArray,
        ];
        if ($phone2) $submitter2['phone'] = $phone2;

        $url = rtrim($config['url'], '/') . '/api/submissions';

        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $config['api_key'],
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($url, [
                'template_id' => $templateId,
                'send_email'  => false,
                'submitters'  => [$submitter1, $submitter2],
                'metadata'    => [
                    'tontine_id' => $tontine->id,
                    'primary_id' => $primary->id,
                    'partner_id' => $partner->id,
                    'type'       => 'binome',
                ],
            ]);

            if (! $response->successful()) {
                $hint = match ($response->status()) {
                    404 => "Template DocuSeal #{$templateId} introuvable.",
                    401, 403 => 'Clé API DocuSeal invalide ou non autorisée.',
                    422 => 'Données invalides — vérifier les noms de rôles dans le template binôme.',
                    default => 'Erreur DocuSeal HTTP ' . $response->status(),
                };
                $body     = $response->json();
                $apiError = is_array($body) ? ($body['error'] ?? $body['message'] ?? null) : null;
                $this->lastError = $hint . ($apiError ? ' — ' . $apiError : '');
                Log::error('Échec createBinomeSubmission DocuSeal.', [
                    'url'    => $url,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            $this->lastError = 'Exception : ' . $e->getMessage();
            Log::error('Exception createBinomeSubmission DocuSeal.', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Champs participant2_* pour le partenaire d'un binôme (injectés dans la submission co-signée).
     */
    private function buildPartnerFieldValues(User $partner): array
    {
        return [
            'participant2_name'    => $partner->full_name ?: $partner->name,
            'participant2_email'   => $partner->email,
            'participant2_phone'   => $partner->phone ?? '',
            'participant2_address' => trim(($partner->address ?? '') . ' ' . ($partner->postal_code ?? '') . ' ' . ($partner->city ?? '')),
        ];
    }

    /**
     * Mappage des paramètres de tontine vers les champs DocuSeal.
     * Les noms de champs côté template doivent correspondre à ceux-ci.
     */
    private function buildFieldValues(Tontine $tontine, User $user): array
    {
        $payoutDay = $tontine->payment_day ?? null;

        // Champs calculés (Σ slots des participants inscrits)
        $tontine->loadMissing('participants');
        $participationsCount = $tontine->participants->isNotEmpty()
            ? (int) $tontine->participants->sum(fn($p) => (int) ($p->pivot->slots ?? 1))
            : (int) ($tontine->rounds_count ?: $tontine->max_participants);

        $cotisation    = (float) $tontine->cotisation_amount;
        $preliminary   = (float) ($tontine->preliminary_amount ?? 0);
        $potTotal      = $participationsCount * $cotisation;
        $advanceTotal  = $participationsCount * $preliminary;
        $distTotal     = $potTotal + $preliminary;
        $bidMax        = ((float) ($tontine->bid_cap ?? 0) / 100) * $potTotal;

        $fmt  = fn(?float $v) => fmod((float) $v, 1) === 0.0
            ? number_format((float) $v, 0, ',', ' ')
            : number_format((float) $v, 2, ',', ' ');
        $fmtI = fn(?float $v) => number_format((float) $v, 0, ',', ' ');

        // Nombre de slots du participant (1 pour individuel/binôme, 2+ pour double)
        $slots = (int) ($user->pivot->slots ?? 1);

        return [
            // Identité signataire
            'participant_name'       => $user->full_name ?: $user->name,
            'participant_email'      => $user->email,
            'participant_phone'      => $user->phone ?? '',
            'participant_address'    => trim(($user->address ?? '') . ' ' . ($user->postal_code ?? '') . ' ' . ($user->city ?? '')),
            'participant_slots'      => (string) $slots,

            // Tontine — identité
            'tontine_name'           => $tontine->name,
            'tresorier_full_name'    => trim(($tontine->payment_info['tresorier_firstname'] ?? '') . ' ' . ($tontine->payment_info['tresorier_name'] ?? '')) ?: '—',
            'tresorier_phone'        => $tontine->payment_info['tresorier_phone'] ?? '—',
            'tresorier_iban'         => $tontine->payment_info['iban'] ?? '—',
            'tresorier_bic'          => $tontine->payment_info['bic'] ?? '—',

            // Montants de base
            'cotisation_amount'           => $fmt($cotisation),
            'participant_cotisation_total' => $fmt($cotisation * $slots),
            'participant_advance_total'    => $preliminary > 0 ? $fmt($preliminary * $slots) : '—',
            'max_participants'             => (string) $tontine->max_participants,
            'bid_cap'                => (string) ($tontine->bid_cap ?? '—'),
            'penalty_per_day'        => $tontine->penalty_per_day !== null ? $fmt($tontine->penalty_per_day) : '—',
            'penalty_cap'            => $tontine->penalty_cap !== null ? $fmt($tontine->penalty_cap) : '—',

            // Montants calculés
            'participations_count'   => (string) $participationsCount,
            'preliminary_amount'     => $preliminary > 0 ? $fmt($preliminary) : '—',
            'pot_amount_total'       => $fmtI($potTotal),
            'advance_total'          => $fmtI($advanceTotal),
            'distribution_total'     => $fmtI($distTotal),
            'bid_max_amount'         => $fmtI($bidMax),

            // Options (Oui / Non)
            'has_bidding'            => $tontine->has_bidding ? 'Oui' : 'Non',
            'has_preliminary'        => $tontine->has_preliminary ? 'Oui' : 'Non',
            'has_penalties'          => $tontine->has_penalties ? 'Oui' : 'Non',

            // Préliminaire — dates
            'preliminary_period_start' => optional($tontine->preliminary_period_start)->format('d/m/Y') ?? '—',
            'preliminary_period_end'   => optional($tontine->preliminary_period_end)->format('d/m/Y') ?? '—',
            'preliminary_period_month' => $tontine->preliminary_period_start
                ? \Illuminate\Support\Carbon::parse($tontine->preliminary_period_start)->translatedFormat('F Y')
                : '—',

            // Calendrier
            'first_round_month'       => optional($tontine->first_round_month)->format('m/Y') ?? '—',
            'first_round_month_label' => $tontine->first_round_month
                ? '1er ' . $tontine->first_round_month->translatedFormat('F Y')
                : '—',
            'bid_day_open'            => (string) ($tontine->bid_day_open ?? '—'),
            'bid_day_close'           => (string) ($tontine->bid_day_close ?? '—'),
            'payment_day'             => $payoutDay !== null ? (string) $payoutDay : '—',
            'payout_day'              => $tontine->payout_day !== null ? (string) $tontine->payout_day : '—',
            'first_payment_due_date'  => $tontine->first_round_month && $payoutDay
                ? \Illuminate\Support\Carbon::parse($tontine->first_round_month)->setDay(min((int) $payoutDay, 28))->format('d/m/Y')
                : '—',
            'first_bid_date'          => $tontine->first_round_month && $tontine->bid_day_open
                ? \Illuminate\Support\Carbon::parse($tontine->first_round_month)->setDay(min((int) $tontine->bid_day_open, 28))->format('d/m/Y')
                : '—',

            // Date de signature
            'signature_date'         => now()->format('d/m/Y'),
        ];
    }

    /**
     * @deprecated Requiert DocuSeal Pro Edition. Conservé pour usage futur.
     * En Community, créer manuellement le template via l'UI et utiliser createSubmission.
     */
    public function createTemplateFromHtml(string $html, string $name): ?array
    {
        $config = $this->config();

        if (! $config['url'] || ! $config['api_key']) {
            Log::warning('DocuSeal non configuré, createTemplateFromHtml ignoré.');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $config['api_key'],
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(60)->post(rtrim($config['url'], '/') . '/api/templates/html', [
                'name'      => $name,
                'documents' => [[
                    'name' => Str::slug($name) . '.html',
                    'html' => $html,
                ]],
            ]);

            if (! $response->successful()) {
                Log::error('Échec createTemplateFromHtml DocuSeal.', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Exception createTemplateFromHtml DocuSeal.', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Crée une submission DocuSeal pour un participant donné contre un template existant.
     * Retourne la liste de submitters (avec id, embed_src, etc.) ou null.
     */
    public function createSubmissionForTemplate(string $templateId, User $user, Tontine $tontine): ?array
    {
        $config = $this->config();
        if (! $config['url'] || ! $config['api_key']) return null;

        $normalizedPhone = SmsNotificationService::normalizePhone($user->phone);
        $submitterPayload = [
            'role'   => $config['submitter_role'],
            'email'  => $user->email,
            'name'   => $user->full_name ?: $user->name,
        ];
        if ($normalizedPhone) {
            $submitterPayload['phone'] = $normalizedPhone;
        }

        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $config['api_key'],
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(30)->post(rtrim($config['url'], '/') . '/api/submissions', [
                'template_id' => (int) $templateId,
                'send_email'  => true,
                'submitters'  => [$submitterPayload],
                'metadata' => [
                    'tontine_id' => $tontine->id,
                    'user_id'    => $user->id,
                    'context'    => 'regulation',
                ],
            ]);

            if (! $response->successful()) {
                Log::error('Échec createSubmissionForTemplate DocuSeal.', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Exception createSubmissionForTemplate DocuSeal.', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Liste les templates disponibles dans DocuSeal pour alimenter le dropdown
     * de sélection au niveau d'une tontine.
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function listTemplates(int $limit = 100): array
    {
        $config = $this->config();
        if (! $config['enabled'] || ! $config['url'] || ! $config['api_key']) {
            return [];
        }

        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $config['api_key'],
                'Accept'       => 'application/json',
            ])->timeout(15)->get(rtrim($config['url'], '/') . '/api/templates', [
                'limit' => $limit,
            ]);

            if (! $response->successful()) {
                Log::warning('Échec listTemplates DocuSeal.', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 300),
                ]);
                return [];
            }

            $json = $response->json();
            // L'API DocuSeal renvoie soit {data: [...]} soit un tableau direct.
            $items = $json['data'] ?? $json;
            if (! is_array($items)) return [];

            return array_map(
                fn($t) => ['id' => (int) ($t['id'] ?? 0), 'name' => (string) ($t['name'] ?? '(sans nom)')],
                array_filter($items, fn($t) => is_array($t) && ! empty($t['id']))
            );
        } catch (\Throwable $e) {
            Log::error('Exception listTemplates DocuSeal.', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Récupère le détail d'une submission DocuSeal (status, completed_at, documents…).
     * Utilisé pour rafraichir manuellement le statut sans dépendre des webhooks.
     */
    public function getSubmission(string $submissionId): ?array
    {
        $config = $this->config();
        if (! $config['url'] || ! $config['api_key']) return null;

        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $config['api_key'],
                'Accept'       => 'application/json',
            ])->timeout(15)->get(rtrim($config['url'], '/') . '/api/submissions/' . $submissionId);

            if (! $response->successful()) {
                Log::warning('DocuSeal getSubmission a échoué.', [
                    'submission_id' => $submissionId,
                    'status'        => $response->status(),
                    'body'          => substr($response->body(), 0, 200),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Exception getSubmission DocuSeal.', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Récupère le détail d'un submitter (signataire) particulier.
     * C'est l'ID qu'on stocke dans tontine_user.signature_submission_id
     * (la réponse de POST /api/submissions renvoie un array de submitters,
     * dont chacun a son propre `id`).
     */
    public function getSubmitter(string $submitterId): ?array
    {
        $config = $this->config();
        if (! $config['url'] || ! $config['api_key']) return null;

        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $config['api_key'],
                'Accept'       => 'application/json',
            ])->timeout(15)->get(rtrim($config['url'], '/') . '/api/submitters/' . $submitterId);

            if (! $response->successful()) {
                Log::warning('DocuSeal getSubmitter a échoué.', [
                    'submitter_id' => $submitterId,
                    'status'       => $response->status(),
                    'body'         => substr($response->body(), 0, 200),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Exception getSubmitter DocuSeal.', [
                'submitter_id' => $submitterId,
                'error'        => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Récupère les méta d'un template (incluant l'URL du PDF preview).
     */
    public function getTemplate(string $templateId): ?array
    {
        $config = $this->config();
        if (! $config['url'] || ! $config['api_key']) return null;

        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $config['api_key'],
                'Accept'       => 'application/json',
            ])->timeout(15)->get(rtrim($config['url'], '/') . '/api/templates/' . $templateId);

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::error('Exception getTemplate DocuSeal.', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Extrait l'URL du PDF d'un template DocuSeal (1er document attaché).
     */
    public function getTemplatePdfUrl(string $templateId): ?string
    {
        $tpl = $this->getTemplate($templateId);
        if (! $tpl) return null;

        return $tpl['documents'][0]['url']
            ?? $tpl['schema'][0]['url']
            ?? null;
    }

    /**
     * Vérifie l'authenticité du webhook DocuSeal.
     * DocuSeal envoie un header X-DocuSeal-Signature (HMAC SHA-256 du body avec le secret).
     */
    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = $this->config()['webhook_secret'] ?? null;

        // Si pas de secret configuré, on accepte mais on log un avertissement (mode dev)
        if (empty($secret)) {
            Log::warning('Webhook DocuSeal reçu sans secret configuré — accepté en mode permissif.');
            return true;
        }

        if (! $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Configuration effective DocuSeal (DB > env > defaults).
     */
    public function config(): array
    {
        $db = AppSetting::getGroup('docuseal');

        return [
            'enabled'        => (bool) ($db['enabled'] ?? env('DOCUSEAL_ENABLED', false)),
            'url'            => $db['url']            ?? env('DOCUSEAL_URL'),
            'api_key'        => $db['api_key']        ?? env('DOCUSEAL_API_KEY'),
            'template_id'    => $db['template_id']    ?? env('DOCUSEAL_TEMPLATE_ID'),
            'webhook_secret' => $db['webhook_secret'] ?? env('DOCUSEAL_WEBHOOK_SECRET'),
            // Nom du rôle signataire tel que défini dans le template DocuSeal.
            // Par défaut « Première partie » (default FR de DocuSeal).
            'submitter_role'  => ($db['submitter_role'] ?? null) ?: env('DOCUSEAL_SUBMITTER_ROLE', 'Première partie'),
            // Rôle du 2e signataire dans le template binôme (co-signature).
            'submitter_role2' => ($db['submitter_role2'] ?? null) ?: env('DOCUSEAL_SUBMITTER_ROLE2', 'Deuxième partie'),
        ];
    }
}
