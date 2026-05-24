<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsNotificationService
{
    /**
     * Envoie un SMS via l'API transactionnelle Brevo.
     * Le numéro destinataire est normalisé en E.164 (FR : 06/07 → +33).
     */
    public function send(string $to, string $message): bool
    {
        $config = $this->config();

        if (! $config['enabled']) {
            Log::info('SMS désactivé dans les paramètres.', ['to' => $to]);
            return false;
        }

        if (! $config['api_key'] || ! $config['sender']) {
            Log::warning('SMS non envoyé : configuration Brevo incomplète.', ['to' => $to]);
            return false;
        }

        $normalized = self::normalizePhone($to);
        if (! $normalized) {
            Log::warning('SMS non envoyé : numéro invalide.', ['to' => $to]);
            return false;
        }

        try {
            $response = Http::withHeaders([
                'api-key'      => $config['api_key'],
                'accept'       => 'application/json',
                'content-type' => 'application/json',
            ])->timeout(15)->post('https://api.brevo.com/v3/transactionalSMS/sms', [
                'sender'    => $config['sender'],
                'recipient' => $normalized,
                'content'   => $message,
                'type'      => $config['type'] ?: 'transactional',
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('Échec envoi SMS Brevo.', [
                'to'     => $to,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('Exception envoi SMS Brevo.', ['to' => $to, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Normalise un numéro vers le format E.164.
     * - "06 12 34 56 78", "06-12-34-56-78", "0612345678" → "+33612345678"
     * - "07 ..."                                          → "+337..." (mobiles FR commencent par 06 ou 07)
     * - "+33 6 12 34 56 78", "33612345678"               → "+33612345678"
     * - Tout autre numéro déjà préfixé "+XX" est conservé tel quel (chiffres + "+").
     */
    public static function normalizePhone(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Indicateur de format international déjà présent
        $hasPlus = str_starts_with($raw, '+');
        $digits  = preg_replace('/\D/', '', $raw);

        if ($digits === '' || strlen($digits) < 8) {
            return null;
        }

        if ($hasPlus) {
            return '+' . $digits;
        }

        // Numéro FR mobile commençant par 06 ou 07 → +33...
        if (strlen($digits) === 10 && (str_starts_with($digits, '06') || str_starts_with($digits, '07'))) {
            return '+33' . substr($digits, 1);
        }

        // Numéro déjà en 33XXXXXXXXX (sans +) → +33XXXXXXXXX
        if (str_starts_with($digits, '33') && strlen($digits) === 11) {
            return '+' . $digits;
        }

        // Autres formats FR (fixe en 01-05 ou 09) — on ajoute aussi +33
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '+33' . substr($digits, 1);
        }

        // Sinon on retourne le numéro avec un + (le serveur Brevo refusera si invalide)
        return '+' . $digits;
    }

    /**
     * Configuration effective : DB > env > defaults.
     */
    public function config(): array
    {
        $db = AppSetting::getGroup('sms');

        return [
            'enabled' => (bool) ($db['enabled'] ?? env('SMS_ENABLED', false)),
            'api_key' => $db['api_key'] ?? env('BREVO_SMS_API_KEY'),
            'sender'  => $db['sender'] ?? env('BREVO_SMS_SENDER'),
            'type'    => $db['type'] ?? env('BREVO_SMS_TYPE', 'transactional'),
        ];
    }
}
