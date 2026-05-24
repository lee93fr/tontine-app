<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Tontine;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Services\ParticipantSignatureService;
use App\Services\SmsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function store(Request $request, Tontine $tontine, ParticipantSignatureService $signatures)
    {
        $data = $request->validate([
            'email'       => 'required|email',
            'name'        => 'required|string|max:255',
            'first_name'  => 'required|string|max:255',
            'phone'       => 'nullable|string|max:20',
            'address'     => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'city'        => 'nullable|string|max:255',
        ]);

        if ($tontine->participants()->where('email', $data['email'])->exists()) {
            return back()->with('error', 'Cette adresse est déjà membre de la tontine.');
        }

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            $user = User::create([
                'email'       => $data['email'],
                'name'        => $data['name'],
                'first_name'  => $data['first_name'],
                'phone'       => $data['phone'] ?? null,
                'address'     => $data['address'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'city'        => $data['city'] ?? null,
                'password'    => Hash::make(Str::random(32)),
                'role'        => 'participant',
                'active'      => false,
            ]);
        }

        $tontine->participants()->syncWithoutDetaching([$user->id]);
        if ($tontine->rounds()->exists()) {
            $tontine->recalcPotAmounts();
        }

        $signatures->ensureSubmission($tontine, $user);

        Invitation::where('tontine_id', $tontine->id)
                  ->where('email', $data['email'])
                  ->whereNull('accepted_at')
                  ->delete();

        $invitation = Invitation::generate($tontine->id, auth()->id(), $data['email']);

        Notification::route('mail', $data['email'])
            ->notify(new InvitationNotification($invitation));

        return back()->with('success', "Invitation envoyée à {$data['email']}. Le compte a été pré-créé.");
    }

    /**
     * Invitation autonome (sans tontine). Permet d'inviter par email OU par SMS.
     */
    public function storeStandalone(Request $request, SmsNotificationService $sms)
    {
        $channel = $request->input('channel', 'email') === 'sms' ? 'sms' : 'email';

        $rules = [
            'channel'     => 'nullable|in:email,sms',
            'name'        => 'required|string|max:255',
            'first_name'  => 'required|string|max:255',
            'address'     => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'city'        => 'nullable|string|max:255',
        ];

        if ($channel === 'sms') {
            $rules['phone'] = 'required|string|max:20';
            $rules['email'] = 'nullable|email';
        } else {
            $rules['email'] = 'required|email';
            $rules['phone'] = 'nullable|string|max:20';
        }

        $data = $request->validate($rules);

        // Trouver l'utilisateur par email OU par téléphone normalisé
        $user = null;
        if (! empty($data['email'])) {
            $user = User::where('email', $data['email'])->first();
        }
        if (! $user && ! empty($data['phone'])) {
            $normalized = SmsNotificationService::normalizePhone($data['phone']);
            if ($normalized) {
                $digits = preg_replace('/\D/', '', $normalized);
                $user = User::whereRaw("regexp_replace(coalesce(phone, ''), '\\D', '', 'g') = ?", [$digits])
                    ->orWhereRaw("regexp_replace(coalesce(phone_number, ''), '\\D', '', 'g') = ?", [$digits])
                    ->first();
            }
        }

        if (! $user) {
            // En SMS sans email, on génère un email synthétique pour respecter la contrainte unique
            $emailToSet = $data['email'] ?? null;
            if (! $emailToSet) {
                $digits = preg_replace('/\D/', '', SmsNotificationService::normalizePhone($data['phone']) ?? $data['phone']);
                $emailToSet = "sms-{$digits}@tontine.local";
            }

            $user = User::create([
                'email'       => $emailToSet,
                'name'        => $data['name'],
                'first_name'  => $data['first_name'],
                'phone'       => $data['phone'] ?? null,
                'address'     => $data['address'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'city'        => $data['city'] ?? null,
                'password'    => Hash::make(Str::random(32)),
                'role'        => 'participant',
                'active'      => false,
            ]);
        }

        if ($channel === 'sms') {
            // Annuler les anciennes invitations SMS pour ce numéro
            $normalized = SmsNotificationService::normalizePhone($data['phone']);
            Invitation::whereNull('tontine_id')
                ->where('channel', 'sms')
                ->where('phone', $normalized)
                ->whereNull('accepted_at')
                ->delete();

            $invitation = Invitation::generate(
                tontineId: null,
                inviterId: auth()->id(),
                email: $user->email,
                phone: $normalized,
                channel: 'sms',
            );

            $url     = route('invitation.accept', $invitation->token);
            $message = "Vous êtes invité·e sur " . config('app.name') . ". Acceptez l'invitation : {$url}";

            $ok = $sms->send($normalized, $message);

            if (! $ok) {
                return back()->with('error', "Échec de l'envoi du SMS à {$data['phone']}. Vérifiez la configuration Brevo dans les paramètres.");
            }

            return back()->with('success', "Invitation SMS envoyée au {$normalized}. Le compte a été pré-créé.");
        }

        // Canal email (par défaut)
        Invitation::whereNull('tontine_id')
            ->where('channel', 'email')
            ->where('email', $data['email'])
            ->whereNull('accepted_at')
            ->delete();

        $invitation = Invitation::generate(null, auth()->id(), $data['email']);

        Notification::route('mail', $data['email'])
            ->notify(new InvitationNotification($invitation));

        return back()->with('success', "Invitation envoyée à {$data['email']}. Le compte a été pré-créé.");
    }

    public function destroy(Invitation $invitation)
    {
        $invitation->delete();
        return back()->with('success', 'Invitation annulée.');
    }
}
