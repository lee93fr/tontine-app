<?php

namespace App\Http\Controllers;

use App\Services\DocusealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocusealWebhookController extends Controller
{
    public function __construct(private DocusealService $docuseal) {}

    /**
     * Webhook DocuSeal — appelé sur submission.completed / submission.declined / submission.opened.
     * Authentifié par HMAC SHA-256 du body avec le secret partagé.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload   = $request->getContent();
        $signature = $request->header('X-DocuSeal-Signature');

        // Trace systématique de chaque webhook reçu (utile pour diagnostiquer)
        Log::info('DocuSeal webhook reçu.', [
            'ip'              => $request->ip(),
            'has_signature'   => ! empty($signature),
            'payload_preview' => substr($payload, 0, 500),
        ]);

        if (! $this->docuseal->verifyWebhookSignature($payload, $signature)) {
            Log::warning('DocuSeal webhook : signature invalide.', ['signature' => $signature]);
            return response()->json(['error' => 'invalid signature'], 401);
        }

        $data      = $request->json()->all();
        $event     = $data['event_type'] ?? $data['event'] ?? null;
        $submitter = $data['data'] ?? [];

        // `signature_submission_id` stocke l'ID du SUBMITTER (signataire), pas l'ID de la submission.
        // DocuSeal envoie data.id = submitter ID, data.submission_id = submission ID.
        $submitterId = (string) ($submitter['id'] ?? '');

        Log::info('DocuSeal webhook : event détecté.', [
            'event'        => $event,
            'submitter_id' => $submitterId,
            'submission_id'=> $submitter['submission_id'] ?? null,
            'data_keys'    => array_keys($data),
        ]);

        if ($submitterId === '') {
            Log::info('DocuSeal webhook sans submitter id, ignoré.', ['event' => $event, 'data' => $data]);
            return response()->json(['ok' => true, 'note' => 'no submitter id']);
        }

        // Détermine si l'ID correspond à un signataire primaire ou à un partenaire de binôme
        $isPartnerSubmission = DB::table('tontine_user')
            ->where('partner_signature_submission_id', $submitterId)
            ->exists();
        $prefix = $isPartnerSubmission ? 'partner_' : '';

        $update = ['updated_at' => now()];

        switch ($event) {
            case 'submission.completed':
            case 'form.completed':
                $update[$prefix . 'signature_status'] = 'signed';
                $update[$prefix . 'signed_at']        = now();
                $pdfUrl = $submitter['documents'][0]['url']
                    ?? $submitter['combined_document_url']
                    ?? $data['combined_document_url']
                    ?? null;
                if ($pdfUrl) {
                    $update[$prefix . 'signed_pdf_url'] = $pdfUrl;
                }
                break;

            case 'submission.declined':
            case 'form.declined':
                $update[$prefix . 'signature_status'] = 'declined';
                break;

            case 'submission.opened':
            case 'form.viewed':
                $update[$prefix . 'signature_status'] = 'opened';
                break;

            case 'submission.expired':
                $update[$prefix . 'signature_status'] = 'expired';
                break;

            default:
                Log::info('DocuSeal webhook : événement non géré.', ['event' => $event]);
                return response()->json(['ok' => true, 'note' => 'event ignored']);
        }

        $affected = DB::table('tontine_user')
            ->where($prefix . 'signature_submission_id', $submitterId)
            ->update($update);

        if ($affected === 0) {
            Log::warning('DocuSeal webhook : aucune ligne mise à jour.', [
                'submitter_id' => $submitterId,
                'is_partner'   => $isPartnerSubmission,
                'event'        => $event,
            ]);
        }

        return response()->json(['ok' => true, 'rows' => $affected, 'is_partner' => $isPartnerSubmission]);
    }
}
