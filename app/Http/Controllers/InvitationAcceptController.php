<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class InvitationAcceptController extends Controller
{
    public function show(string $token)
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if (!$invitation->isValid()) {
            return view('auth.invitation-expired');
        }

        if (auth()->check() && auth()->user()->email !== $invitation->email) {
            Auth::logout();
        }

        $existingUser = User::where('email', $invitation->email)->first();
        // Pré-créé = compte inactif en attente d'activation
        $preCreated = $existingUser && !$existingUser->active;
        // Déjà actif = compte pleinement existant
        $userExists = $existingUser && $existingUser->active;

        return view('auth.accept-invitation', compact('invitation', 'preCreated', 'userExists', 'existingUser'));
    }

    public function accept(Request $request, string $token)
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if (!$invitation->isValid()) {
            return redirect()->route('login')->with('error', 'Cette invitation a expiré ou a déjà été utilisée.');
        }

        $tontine = $invitation->tontine;

        if ($tontine && $tontine->participants_locked) {
            return redirect()->route('login')->with('error', 'Les inscriptions à cette tontine sont verrouillées.');
        }

        if (auth()->check()) {
            if (auth()->user()->email !== $invitation->email) {
                Auth::logout();
                return redirect()->route('invitation.show', $invitation->token)
                                 ->with('info', 'Veuillez utiliser l\'adresse ' . $invitation->email . '.');
            }

            if ($tontine) {
                $tontine->participants()->syncWithoutDetaching([auth()->id()]);
            }
            $invitation->update(['accepted_at' => now()]);
            return $tontine
                ? redirect()->route('participant.tontine', $tontine)->with('success', 'Vous avez rejoint la tontine !')
                : redirect()->route('participant.dashboard')->with('success', 'Invitation acceptée.');
        }

        $existingUser = User::where('email', $invitation->email)->first();

        // Cas 1 : Utilisateur pré-créé (compte inactif) → activer avec le mot de passe choisi
        if ($existingUser && !$existingUser->active) {
            $data = $request->validate([
                'password' => 'required|string|min:8|confirmed',
            ]);
            $existingUser->update([
                'password' => Hash::make($data['password']),
                'active'   => true,
            ]);
            $invitation->update(['accepted_at' => now()]);
            return redirect()->route('login')
                             ->with('success', 'Compte activé ! Connectez-vous avec ' . $invitation->email . '.');
        }

        // Cas 2 : Utilisateur déjà actif → connecter directement
        if ($existingUser && $existingUser->active) {
            Auth::login($existingUser);
            if ($tontine) {
                $tontine->participants()->syncWithoutDetaching([$existingUser->id]);
            }
            $invitation->update(['accepted_at' => now()]);
            return redirect()->route('participant.dashboard')
                             ->with('success', 'Bienvenue !' . ($tontine ? ' Vous avez rejoint la tontine.' : ''));
        }

        // Cas 3 : Aucun compte (fallback) → création complète
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'first_name'  => 'required|string|max:255',
            'phone'       => 'required|string|max:20',
            'address'     => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'city'        => 'required|string|max:255',
            'password'    => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'        => $data['name'],
            'first_name'  => $data['first_name'],
            'phone'       => $data['phone'],
            'address'     => $data['address'],
            'postal_code' => $data['postal_code'],
            'city'        => $data['city'],
            'email'       => $invitation->email,
            'password'    => Hash::make($data['password']),
            'role'        => 'participant',
            'active'      => true,
        ]);

        if ($tontine) {
            $tontine->participants()->syncWithoutDetaching([$user->id]);
        }
        $invitation->update(['accepted_at' => now()]);

        return redirect()->route('login')
                         ->with('success', 'Compte créé ! Connectez-vous avec l\'adresse ' . $invitation->email . '.');
    }
}
