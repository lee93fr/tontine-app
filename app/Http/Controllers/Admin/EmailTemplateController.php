<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    private const TEMPLATES = [
        'bid_placed' => [
            'vars' => ['{recipient_name}', '{tontine_name}', '{round_number}', '{amount}', '{highest}', '{cap}', '{closes_at}'],
        ],
        'round_opened' => [
            'vars' => ['{recipient_name}', '{tontine_name}', '{round_number}', '{pot_amount}', '{closes_at}', '{cap}'],
        ],
        'round_result' => [
            'vars' => ['{recipient_name}', '{tontine_name}', '{round_number}', '{winner_name}', '{pot_amount}', '{method}'],
        ],
        'round_result_winner' => [
            'vars' => ['{recipient_name}', '{tontine_name}', '{round_number}', '{pot_amount}', '{method}'],
        ],
        'invitation' => [
            'vars' => ['{tontine_name}', '{inviter_name}', '{cotisation_amount}', '{max_participants}', '{expires_at}'],
        ],
        'payment_reminder' => [
            'vars' => ['{recipient_name}', '{tontine_name}', '{round_number}', '{amount}', '{due_date}', '{days_late}', '{payment_info}'],
        ],
        'signature_request' => [
            'vars' => ['{recipient_name}', '{tontine_name}', '{cotisation_amount}', '{preliminary_amount}', '{max_participants}', '{signature_url}', '{inviter_name}'],
        ],
    ];

    public function index()
    {
        $locales = ['fr', 'en'];
        $templates = [];

        foreach (array_keys(self::TEMPLATES) as $key) {
            $templates[$key] = [];
            foreach ($locales as $locale) {
                $templates[$key][$locale] = EmailTemplate::getFor($key, $locale);
            }
        }

        return view('admin.emails.index', compact('templates', 'locales'));
    }

    public function edit(string $key, string $locale)
    {
        abort_unless(array_key_exists($key, self::TEMPLATES), 404);
        abort_unless(in_array($locale, ['fr', 'en']), 404);

        $template = EmailTemplate::getFor($key, $locale);
        $vars     = self::TEMPLATES[$key]['vars'];

        return view('admin.emails.edit', compact('template', 'key', 'locale', 'vars'));
    }

    public function update(Request $request, string $key, string $locale)
    {
        abort_unless(array_key_exists($key, self::TEMPLATES), 404);
        abort_unless(in_array($locale, ['fr', 'en']), 404);

        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'body'    => 'required|string',
        ]);

        EmailTemplate::updateOrCreate(
            ['key' => $key, 'locale' => $locale],
            $data
        );

        return redirect()->route('admin.settings.index', ['tab' => 'emails'])
            ->with('success', __('app.emails.msg_saved'));
    }
}
