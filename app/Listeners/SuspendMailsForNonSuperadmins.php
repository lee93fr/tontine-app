<?php

namespace App\Listeners;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;

/**
 * Court-circuite les envois de mail lorsque la suspension globale est active
 * (AppSetting mail.suspended = 1), sauf si TOUS les destinataires "to" sont
 * des comptes superadmin.
 *
 * Retourner false depuis ce handler annule l'envoi (cf. Illuminate\Mail\Mailer).
 */
class SuspendMailsForNonSuperadmins
{
    private static ?array $superadminEmailsCache = null;

    public function handle(MessageSending $event): ?bool
    {
        try {
            $suspended = AppSetting::get('mail', 'suspended');
        } catch (\Throwable $e) {
            return null;
        }

        if (! filter_var($suspended, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $recipients = [];
        foreach ((array) ($event->message->getTo() ?? []) as $addr) {
            $recipients[] = strtolower($addr->getAddress());
        }

        if (empty($recipients)) {
            return null;
        }

        $superadminEmails = $this->superadminEmails();

        foreach ($recipients as $email) {
            if (! in_array($email, $superadminEmails, true)) {
                Log::info('Mail bloqué (suspension globale active).', [
                    'subject'    => $event->message->getSubject(),
                    'recipients' => $recipients,
                ]);
                return false;
            }
        }

        return null;
    }

    private function superadminEmails(): array
    {
        if (self::$superadminEmailsCache !== null) {
            return self::$superadminEmailsCache;
        }

        try {
            return self::$superadminEmailsCache = User::where('role', 'superadmin')
                ->pluck('email')
                ->map(fn ($e) => strtolower((string) $e))
                ->all();
        } catch (\Throwable $e) {
            return self::$superadminEmailsCache = [];
        }
    }
}
