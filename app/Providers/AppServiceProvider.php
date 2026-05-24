<?php

namespace App\Providers;

use App\Listeners\SuspendMailsForNonSuperadmins;
use App\Models\AppSetting;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (env('FORCE_HTTPS', false)) {
            URL::forceScheme('https');
        }

        $this->overrideMailConfigFromDatabase();

        Event::listen(MessageSending::class, SuspendMailsForNonSuperadmins::class);
    }

    /**
     * Si des paramètres SMTP sont enregistrés en base, ils prennent
     * le pas sur le .env. Sans table (avant migration) ou sans valeurs,
     * on garde la config .env intacte.
     */
    private function overrideMailConfigFromDatabase(): void
    {
        try {
            if (! Schema::hasTable('app_settings')) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $smtp = AppSetting::getGroup('smtp');
        if (empty($smtp)) {
            return;
        }

        $encryption = $smtp['encryption'] ?? null;
        if ($encryption === 'none') {
            $encryption = null;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport'  => 'smtp',
            'mail.mailers.smtp.host'       => $smtp['host']       ?? config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port'       => isset($smtp['port']) ? (int) $smtp['port'] : config('mail.mailers.smtp.port'),
            'mail.mailers.smtp.username'   => $smtp['username']   ?? config('mail.mailers.smtp.username'),
            'mail.mailers.smtp.password'   => $smtp['password']   ?? config('mail.mailers.smtp.password'),
            'mail.mailers.smtp.encryption' => $encryption ?? config('mail.mailers.smtp.encryption'),
            'mail.from.address'            => $smtp['from_address'] ?? config('mail.from.address'),
            'mail.from.name'               => $smtp['from_name']    ?? config('mail.from.name'),
        ]);
    }
}
