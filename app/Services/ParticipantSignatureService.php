<?php

namespace App\Services;

use App\Models\Tontine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Orchestre le déclenchement DocuSeal lors de la pré-inscription d'un participant.
 * Idempotent : si une submission existe déjà pour ce couple (tontine, user), ne refait rien.
 */
class ParticipantSignatureService
{
    public function __construct(private DocusealService $docuseal) {}

    public function ensureSubmission(Tontine $tontine, User $user): void
    {
        $config = $this->docuseal->config();
        if (! $config['enabled']) {
            return;
        }

        // Vérifie qu'une submission n'existe pas déjà
        $existing = DB::table('tontine_user')
            ->where('tontine_id', $tontine->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $existing || $existing->signature_submission_id) {
            return;
        }

        $response = $this->docuseal->createSubmission($tontine, $user);
        if (! $response) {
            return;
        }

        // DocuSeal renvoie un tableau de submitters. On récupère le premier (notre Participant).
        $submitter = is_array($response) ? ($response[0] ?? null) : null;
        if (! $submitter) {
            return;
        }

        DB::table('tontine_user')
            ->where('tontine_id', $tontine->id)
            ->where('user_id', $user->id)
            ->update([
                'signature_submission_id' => (string) ($submitter['id'] ?? $submitter['submission_id'] ?? ''),
                'signature_status'        => 'pending',
                'signature_sent_at'       => now(),
                'signature_signer_url'    => $submitter['embed_src'] ?? $submitter['url'] ?? null,
                'updated_at'              => now(),
            ]);
    }
}
