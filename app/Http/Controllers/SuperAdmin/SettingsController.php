<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\EmailTemplate;
use App\Services\DocusealService;
use App\Services\SmsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SettingsController extends Controller
{
    /**
     * Page Paramètres unifiée — 3 onglets (SMS, SMTP, Modèles email).
     * Accessible à tout admin ; les onglets SMS et SMTP sont réservés au superadmin
     * (gated côté vue et côté actions update/test).
     */
    public function index(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $current = auth()->user();
        $isSuper = $current->isSuperAdmin();

        // Onglet par défaut : le premier auquel l'utilisateur a accès
        $tab = $request->query('tab');
        if (! in_array($tab, ['sms', 'smtp', 'emails', 'signature'], true)) {
            $tab = $isSuper ? 'sms' : 'emails';
        }
        if (! $isSuper && in_array($tab, ['sms', 'smtp', 'signature'], true)) {
            $tab = 'emails';
        }

        return view('superadmin.settings.index', [
            'tab'         => $tab,
            'isSuper'     => $isSuper,
            'tableReady'  => AppSetting::tableExists(),
            'sms'         => $this->resolveSms(),
            'smtp'        => $this->resolveSmtp(),
            'docuseal'    => $this->resolveDocuseal(),
            'mail'        => $this->resolveMail(),
            'emailGroups' => $this->resolveEmailTemplates(),
        ]);
    }

    /**
     * Suspend ou réactive l'envoi de mail pour tous les utilisateurs sauf les superadmins.
     * Utile pour éviter de spammer les comptes réels pendant une phase de tests.
     */
    public function updateMailSuspended(Request $request)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $request->validate(['suspended' => 'nullable|boolean']);
        $suspended = $request->boolean('suspended');

        AppSetting::setGroup('mail', ['suspended' => $suspended ? '1' : '0']);

        return redirect()
            ->route('admin.settings.index', ['tab' => 'smtp'])
            ->with('success', $suspended
                ? 'Envois de mail suspendus (les superadmins continuent de recevoir leurs mails).'
                : 'Envois de mail réactivés pour tous les utilisateurs.');
    }

    public function updateSms(Request $request)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'api_key' => 'nullable|string|max:255',
            'sender'  => 'nullable|string|max:11',
            'type'    => 'nullable|in:transactional,marketing',
        ]);

        AppSetting::setGroup('sms', [
            'enabled' => $request->boolean('enabled') ? '1' : '0',
            'api_key' => $data['api_key'] ?? null,
            'sender'  => $data['sender'] ?? null,
            'type'    => $data['type'] ?? 'transactional',
        ], encryptedKeys: ['api_key']);

        return redirect()
            ->route('admin.settings.index', ['tab' => 'sms'])
            ->with('success', 'Paramètres SMS Brevo enregistrés.');
    }

    public function updateSmtp(Request $request)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'host'        => 'nullable|string|max:255',
            'port'        => 'nullable|integer|min:1|max:65535',
            'username'    => 'nullable|string|max:255',
            'password'    => 'nullable|string|max:255',
            'encryption'  => 'nullable|in:tls,ssl,none',
            'from_address'=> 'nullable|email|max:255',
            'from_name'   => 'nullable|string|max:255',
        ]);

        $values = [
            'host'         => $data['host'] ?? null,
            'port'         => isset($data['port']) ? (string) $data['port'] : null,
            'username'     => $data['username'] ?? null,
            'encryption'   => $data['encryption'] ?? 'tls',
            'from_address' => $data['from_address'] ?? null,
            'from_name'    => $data['from_name'] ?? null,
        ];

        if (! empty($data['password'])) {
            $values['password'] = $data['password'];
        }

        AppSetting::setGroup('smtp', $values, encryptedKeys: ['password']);

        return redirect()
            ->route('admin.settings.index', ['tab' => 'smtp'])
            ->with('success', 'Paramètres SMTP enregistrés.');
    }

    public function testSms(Request $request, SmsNotificationService $sms)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'to'      => 'required|string|max:20',
            'message' => 'nullable|string|max:160',
        ]);

        $message = $data['message'] ?: 'Test SMS depuis ' . config('app.name');
        $ok = $sms->send($data['to'], $message);

        return redirect()
            ->route('admin.settings.index', ['tab' => 'sms'])
            ->with($ok ? 'success' : 'error',
                $ok ? "SMS test envoyé à {$data['to']}." : "Échec de l'envoi du SMS test (voir les logs).");
    }

    public function testDocuseal(DocusealService $docuseal)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $report = $docuseal->diagnose();
        session()->flash('docuseal_diagnostic', $report);

        return redirect()->route('admin.settings.index', ['tab' => 'signature']);
    }

    public function updateDocuseal(Request $request)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'enabled'         => 'nullable|boolean',
            'url'             => 'nullable|url|max:255',
            'api_key'         => 'nullable|string|max:255',
            'template_id'     => 'nullable|integer|min:1',
            'webhook_secret'  => 'nullable|string|max:255',
            'submitter_role'  => 'nullable|string|max:100',
            'submitter_role2' => 'nullable|string|max:100',
        ], [
            'template_id.integer' => 'L\'ID du template DocuSeal doit être un nombre entier (ex: 12345). Vérifie qu\'un autofill du navigateur n\'a pas rempli ce champ avec un email.',
        ]);

        $values = [
            'enabled'         => $request->boolean('enabled') ? '1' : '0',
            'url'             => $data['url']            ?? null,
            'template_id'     => $data['template_id']    ?? null,
            'submitter_role'  => $data['submitter_role']  ?: 'Première partie',
            'submitter_role2' => $data['submitter_role2'] ?: 'Deuxième partie',
        ];

        if (! empty($data['api_key'])) {
            $values['api_key'] = $data['api_key'];
        }
        if (! empty($data['webhook_secret'])) {
            $values['webhook_secret'] = $data['webhook_secret'];
        }

        AppSetting::setGroup('docuseal', $values, encryptedKeys: ['api_key', 'webhook_secret']);

        return redirect()
            ->route('admin.settings.index', ['tab' => 'signature'])
            ->with('success', 'Paramètres DocuSeal enregistrés.');
    }

    public function testSmtp(Request $request)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'to' => 'required|email|max:255',
        ]);

        try {
            Mail::raw(
                "Ceci est un email de test envoyé depuis " . config('app.name') . ".\n\nSi vous lisez ce message, la configuration SMTP fonctionne.",
                function ($m) use ($data) {
                    $m->to($data['to'])->subject('Test SMTP — ' . config('app.name'));
                }
            );
            return redirect()->route('admin.settings.index', ['tab' => 'smtp'])
                ->with('success', "Email test envoyé à {$data['to']}.");
        } catch (\Throwable $e) {
            Log::error('Échec test SMTP.', ['error' => $e->getMessage()]);
            return redirect()->route('admin.settings.index', ['tab' => 'smtp'])
                ->with('error', "Échec de l'envoi de l'email test : " . $e->getMessage());
        }
    }

    private function resolveSms(): array
    {
        $db = AppSetting::getGroup('sms');
        return [
            'enabled'      => (bool) ($db['enabled'] ?? false),
            'api_key'      => $db['api_key'] ?? null,
            'sender'       => $db['sender'] ?? null,
            'type'         => $db['type'] ?? 'transactional',
            'api_key_set'  => ! empty($db['api_key']),
        ];
    }

    private function resolveSmtp(): array
    {
        $db = AppSetting::getGroup('smtp');
        return [
            'host'         => $db['host']         ?? config('mail.mailers.smtp.host'),
            'port'         => $db['port']         ?? config('mail.mailers.smtp.port'),
            'username'     => $db['username']     ?? config('mail.mailers.smtp.username'),
            'encryption'   => $db['encryption']   ?? config('mail.mailers.smtp.encryption', 'tls'),
            'from_address' => $db['from_address'] ?? config('mail.from.address'),
            'from_name'    => $db['from_name']    ?? config('mail.from.name'),
            'password_set' => ! empty($db['password']),
        ];
    }

    private function resolveMail(): array
    {
        $db = AppSetting::getGroup('mail');
        return [
            'suspended' => (bool) ($db['suspended'] ?? false),
        ];
    }

    private function resolveDocuseal(): array
    {
        $db = AppSetting::getGroup('docuseal');
        return [
            'enabled'             => (bool) ($db['enabled'] ?? false),
            'url'                 => $db['url']            ?? null,
            'template_id'         => $db['template_id']    ?? null,
            'submitter_role'      => ($db['submitter_role']  ?? null) ?: 'Première partie',
            'submitter_role2'     => ($db['submitter_role2'] ?? null) ?: 'Deuxième partie',
            'api_key_set'         => ! empty($db['api_key']),
            'webhook_secret_set'  => ! empty($db['webhook_secret']),
        ];
    }

    /**
     * Liste des modèles email connus + indicateur de présence par locale.
     * On reste sur les mêmes clés que EmailTemplateController pour ne pas
     * dupliquer la source de vérité — il garde la logique d'édition.
     */
    private function resolveEmailTemplates(): array
    {
        $keys = ['bid_placed', 'round_opened', 'round_result', 'round_result_winner', 'invitation', 'payment_reminder', 'signature_request'];
        $locales = ['fr', 'en'];

        $result = [];
        foreach ($keys as $key) {
            $byLocale = [];
            foreach ($locales as $locale) {
                $byLocale[$locale] = EmailTemplate::getFor($key, $locale);
            }
            $result[$key] = $byLocale;
        }

        return ['templates' => $result, 'locales' => $locales];
    }
}
