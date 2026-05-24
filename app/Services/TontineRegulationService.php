<?php

namespace App\Services;

use App\Models\Tontine;
use App\Models\TontineRegulation;
use App\Notifications\SignatureRequestNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Orchestre l'envoi en signature du règlement d'une tontine.
 *
 * Architecture (DocuSeal Community-compatible) :
 *   • Aperçu de la version personnalisée : HTML rendu en interne par RegulationService.
 *   • Signature électronique : UN template DocuSeal UNIVERSEL créé manuellement
 *     une seule fois dans DocuSeal UI, dont l'ID est configuré dans Paramètres > Signature.
 *     Chaque submission pré-remplit les champs du template avec les variables de la tontine
 *     + du participant.
 *
 * Le template universel DocuSeal DOIT contenir au minimum ces champs (voir l'onglet Paramètres > Signature) :
 *   - participant_name, participant_email, participant_phone
 *   - tontine_name, cotisation_amount, max_participants, bid_cap, ...
 *   - signature (signature-field), mention_lu_approuve (text-field), date_signature (date-field)
 */
class TontineRegulationService
{
    public function __construct(
        private DocusealService $docuseal,
    ) {}

    /**
     * Envoie une submission DocuSeal aux participants sélectionnés (ou à tous si null),
     * en utilisant le template universel configuré dans les paramètres.
     *
     * @param array|null $userIds  Liste optionnelle d'IDs utilisateurs ciblés. Si null, tous les participants.
     * @return array{sent:int, skipped:int, failed:int, error?:string}
     */
    public function sendForSignature(Tontine $tontine, ?array $userIds = null): array
    {
        $config = $this->docuseal->config();

        if (! $config['enabled']) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'error' => 'docuseal_disabled'];
        }
        // Le template_id global n'est pas obligatoire si la tontine en a un propre,
        // mais URL + api_key le sont toujours.
        if (! $config['url'] || ! $config['api_key']) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'error' => 'docuseal_not_configured'];
        }
        // Au moins un template doit être résolvable (par défaut tontine, par type, ou global).
        $hasAnyTemplate = $tontine->docuseal_template_id
            || $tontine->docuseal_template_individual_id
            || $tontine->docuseal_template_binome_id
            || $tontine->docuseal_template_double_id
            || $config['template_id'];
        if (! $hasAnyTemplate) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'error' => 'no_template'];
        }

        $stats = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

        $participants = $tontine->participants;
        if ($userIds !== null) {
            $participants = $participants->whereIn('id', $userIds);
        }

        foreach ($participants as $participant) {
            $participant->load(['partner', 'partnerOf']);

            // Les secondaires d'un binôme (partner_id défini → ils pointent vers leur primaire)
            // sont traités via leur primaire. On les saute ici pour éviter les doublons.
            if ($participant->partner_id !== null) {
                continue;
            }

            $primarySigned = ($participant->pivot->signature_status ?? null) === 'signed';

            // Partenaire : le secondaire du binôme (partnerOf) OU les doubles participations
            // avec un partenaire déclaré. On teste les deux relations.
            $partner = $participant->partnerOf ?? null;

            // Pour les doubles participations : si pas de partnerOf mais slots > 1,
            // on cherche tout de même un éventuel partenaire via partner (cas mixte double+binôme).
            if (! $partner && ($participant->pivot->slots ?? 1) > 1) {
                $partner = $participant->partner ?? null;
            }

            $partnerSigned = $partner && (($participant->pivot->partner_signature_status ?? null) === 'signed');

            // Si tout est déjà signé (primaire + partenaire éventuel) → skip
            if ($primarySigned && (! $partner || $partnerSigned)) {
                $stats['skipped']++;
                continue;
            }

            if ($partner) {
                // ─── Binôme : 1 submission co-signée (2 signataires sur le même document) ──
                $sent = $this->sendBinomeSubmission(
                    tontine:   $tontine,
                    primary:   $participant,
                    partner:   $partner,
                );
                if ($sent) {
                    $stats['sent'] += 2;
                } else {
                    $stats['failed'] += 2;
                }
            } else {
                // ─── Individuel ou double : 1 submission, 1 signataire ──
                if (! $primarySigned) {
                    $sentPrimary = $this->sendOneSubmission(
                        tontine:    $tontine,
                        pivotUserId: $participant->id,
                        signatory:  $participant,
                        isPartner:  false,
                    );
                    if ($sentPrimary) $stats['sent']++;
                    else              $stats['failed']++;
                }
            }
        }

        TontineRegulation::updateOrCreate(
            ['tontine_id' => $tontine->id],
            [
                'status'                 => 'sent_for_signature',
                'sent_for_signature_at'  => now(),
            ]
        );

        return $stats;
    }

    /**
     * Crée une submission DocuSeal co-signée pour un binôme (1 document, 2 signataires).
     * DocuSeal renvoie 2 submitters : [0] = primaire, [1] = partenaire.
     * Les deux IDs + URLs sont persistés dans le pivot du primaire.
     */
    private function sendBinomeSubmission(
        Tontine $tontine,
        \App\Models\User $primary,
        \App\Models\User $partner,
    ): bool {
        $response = $this->docuseal->createBinomeSubmission($tontine, $primary, $partner);

        if (! $response || count($response) < 2) {
            $error = $this->docuseal->lastError ?? 'Réponse DocuSeal invalide (moins de 2 submitters renvoyés).';
            DB::table('tontine_user')
                ->where('tontine_id', $tontine->id)
                ->where('user_id', $primary->id)
                ->update([
                    'signature_status'         => 'failed',
                    'signature_error'           => $error,
                    'partner_signature_status'  => 'failed',
                    'partner_signature_error'   => $error,
                    'updated_at'               => now(),
                ]);
            return false;
        }

        $sub1 = $response[0]; // primary submitter
        $sub2 = $response[1]; // partner submitter

        $url1 = $sub1['embed_src'] ?? $sub1['url'] ?? null;
        $url2 = $sub2['embed_src'] ?? $sub2['url'] ?? null;

        DB::table('tontine_user')
            ->where('tontine_id', $tontine->id)
            ->where('user_id', $primary->id)
            ->update([
                'signature_submission_id'        => (string) ($sub1['id'] ?? $sub1['submission_id'] ?? ''),
                'signature_status'               => 'pending',
                'signature_sent_at'              => now(),
                'signature_signer_url'           => $url1,
                'signed_at'                      => null,
                'signed_pdf_url'                 => null,
                'signature_error'                => null,
                'partner_signature_submission_id'=> (string) ($sub2['id'] ?? $sub2['submission_id'] ?? ''),
                'partner_signature_status'       => 'pending',
                'partner_signature_sent_at'      => now(),
                'partner_signature_signer_url'   => $url2,
                'partner_signed_at'              => null,
                'partner_signed_pdf_url'         => null,
                'partner_signature_error'        => null,
                'updated_at'                     => now(),
            ]);

        // Email au primaire
        if ($url1) {
            try {
                Notification::send($primary, new SignatureRequestNotification($tontine, $url1, auth()->user()));
            } catch (\Throwable $e) {
                Log::warning('Échec email signature binôme (primaire).', ['user_id' => $primary->id, 'error' => $e->getMessage()]);
            }
        }

        // Email au partenaire
        if ($url2) {
            try {
                Notification::send($partner, new SignatureRequestNotification($tontine, $url2, auth()->user()));
            } catch (\Throwable $e) {
                Log::warning('Échec email signature binôme (partenaire).', ['user_id' => $partner->id, 'error' => $e->getMessage()]);
            }
        }

        return true;
    }

    /**
     * Crée une submission DocuSeal pour un signataire donné (primaire ou partenaire d'un binôme)
     * et persiste les colonnes correspondantes (signature_* ou partner_signature_*) du pivot.
     */
    private function sendOneSubmission(
        Tontine $tontine,
        int $pivotUserId,
        \App\Models\User $signatory,
        bool $isPartner,
    ): bool {
        $prefix = $isPartner ? 'partner_' : '';

        $response = $this->docuseal->createSubmission($tontine, $signatory);

        if (! $response) {
            DB::table('tontine_user')
                ->where('tontine_id', $tontine->id)
                ->where('user_id', $pivotUserId)
                ->update([
                    $prefix . 'signature_status' => 'failed',
                    $prefix . 'signature_error'  => $this->docuseal->lastError ?? 'Échec sans détail (voir logs).',
                    'updated_at'                 => now(),
                ]);
            return false;
        }

        $submitter = is_array($response) ? ($response[0] ?? null) : null;
        if (! $submitter) {
            DB::table('tontine_user')
                ->where('tontine_id', $tontine->id)
                ->where('user_id', $pivotUserId)
                ->update([
                    $prefix . 'signature_status' => 'failed',
                    $prefix . 'signature_error'  => 'Réponse DocuSeal invalide (pas de submitter renvoyé).',
                    'updated_at'                 => now(),
                ]);
            return false;
        }

        $signerUrl = $submitter['embed_src'] ?? $submitter['url'] ?? null;

        DB::table('tontine_user')
            ->where('tontine_id', $tontine->id)
            ->where('user_id', $pivotUserId)
            ->update([
                $prefix . 'signature_submission_id' => (string) ($submitter['id'] ?? $submitter['submission_id'] ?? ''),
                $prefix . 'signature_status'        => 'pending',
                $prefix . 'signature_sent_at'       => now(),
                $prefix . 'signature_signer_url'    => $signerUrl,
                $prefix . 'signed_at'               => null,
                $prefix . 'signed_pdf_url'          => null,
                $prefix . 'signature_error'         => null,
                'updated_at'                        => now(),
            ]);

        // Envoi de notre email (template signature_request) avec le lien DocuSeal personnel
        if ($signerUrl) {
            try {
                Notification::send(
                    $signatory,
                    new SignatureRequestNotification($tontine, $signerUrl, auth()->user())
                );
            } catch (\Throwable $e) {
                Log::warning('Échec envoi email signature_request.', [
                    'user_id'    => $signatory->id,
                    'is_partner' => $isPartner,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return true;
    }

    /**
     * Interroge DocuSeal pour rafraichir le statut de signature de chaque participant
     * ayant une submission active. Utile si les webhooks ne fonctionnent pas.
     *
     * Note importante : `signature_submission_id` stocke en réalité l'ID du SUBMITTER
     * (signataire) retourné par `POST /api/submissions`, pas l'ID de la submission
     * parente. On appelle donc `GET /api/submitters/{id}`.
     *
     * @return array{updated:int, unchanged:int, errors:int}
     */
    public function refreshStatuses(Tontine $tontine): array
    {
        $stats = ['updated' => 0, 'unchanged' => 0, 'errors' => 0];

        foreach ($tontine->participants as $participant) {
            // Rafraichit la submission primaire
            if (! empty($participant->pivot->signature_submission_id)) {
                $result = $this->refreshOneSubmitter(
                    tontine: $tontine,
                    pivotUserId: $participant->id,
                    submitterId: (string) $participant->pivot->signature_submission_id,
                    currentStatus: $participant->pivot->signature_status,
                    isPartner: false,
                );
                $stats[$result]++;
            }

            // Rafraichit la submission du partenaire (binôme)
            if (! empty($participant->pivot->partner_signature_submission_id)) {
                $result = $this->refreshOneSubmitter(
                    tontine: $tontine,
                    pivotUserId: $participant->id,
                    submitterId: (string) $participant->pivot->partner_signature_submission_id,
                    currentStatus: $participant->pivot->partner_signature_status,
                    isPartner: true,
                );
                $stats[$result]++;
            }
        }

        return $stats;
    }

    /** @return 'updated'|'unchanged'|'errors' */
    private function refreshOneSubmitter(
        Tontine $tontine,
        int $pivotUserId,
        string $submitterId,
        ?string $currentStatus,
        bool $isPartner,
    ): string {
        $submitter = $this->docuseal->getSubmitter($submitterId);
        if (! $submitter) {
            return 'errors';
        }

        $status      = $submitter['status']       ?? null;
        $completedAt = $submitter['completed_at'] ?? null;
        $declinedAt  = $submitter['declined_at']  ?? null;
        $openedAt    = $submitter['opened_at']    ?? null;
        $documents   = $submitter['documents']    ?? [];
        $pdfUrl      = $documents[0]['url'] ?? null;

        $newStatus = match (true) {
            ! empty($completedAt)                         => 'signed',
            ! empty($declinedAt)                          => 'declined',
            $status === 'completed'                       => 'signed',
            $status === 'declined'                        => 'declined',
            $status === 'expired'                         => 'expired',
            ! empty($openedAt) || $status === 'opened'    => 'opened',
            in_array($status, ['sent', 'awaiting'], true) => 'pending',
            default                                       => null,
        };

        Log::info('DocuSeal refresh : statut récupéré.', [
            'tontine_id'    => $tontine->id,
            'pivot_user_id' => $pivotUserId,
            'submitter_id'  => $submitterId,
            'is_partner'    => $isPartner,
            'remote_status' => $status,
            'completed_at'  => $completedAt,
            'mapped_status' => $newStatus,
            'previous'      => $currentStatus,
        ]);

        if ($newStatus === null || $newStatus === $currentStatus) {
            return 'unchanged';
        }

        $prefix = $isPartner ? 'partner_' : '';
        $update = [
            $prefix . 'signature_status' => $newStatus,
            'updated_at'                 => now(),
        ];
        if ($newStatus === 'signed') {
            $update[$prefix . 'signed_at'] = $completedAt ?: now();
            if ($pdfUrl) {
                $update[$prefix . 'signed_pdf_url'] = $pdfUrl;
            }
        }

        DB::table('tontine_user')
            ->where('tontine_id', $tontine->id)
            ->where('user_id', $pivotUserId)
            ->update($update);

        return 'updated';
    }
}
