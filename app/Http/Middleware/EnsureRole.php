<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): mixed
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Admin en mode trésorier (session) peut accéder aux routes trésorier
        if ($role === 'tresorier' && $user->isAdmin() && session()->has('tresorier_tontine_id')) {
            return $next($request);
        }

        // Trésorier avec tontine active en session peut accéder aux routes trésorier
        if ($role === 'tresorier' && $user->isTresorier() && session()->has('tresorier_active_tontine_id')) {
            return $next($request);
        }

        // Trésorier peut toujours accéder aux routes participant (mode par défaut)
        if ($role === 'participant' && $user->isTresorier()) {
            return $next($request);
        }

        // Superadmin et Admin accèdent aux routes admin
        if ($role === 'admin' && $user->isAdmin()) {
            return $next($request);
        }

        // Admin_local accède aux routes admin_local
        if ($role === 'admin_local' && $user->isAdminLocal()) {
            return $next($request);
        }

        // Superadmin accède aussi aux routes admin_local (pour superviser)
        if ($role === 'admin_local' && $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($user->role !== $role) {
            if ($role === 'admin') {
                abort(403, 'Accès réservé aux administrateurs.');
            }
            if ($role === 'admin_local') {
                abort(403, 'Accès réservé aux administrateurs locaux.');
            }
            if ($role === 'tresorier') {
                abort(403, 'Accès réservé aux trésoriers.');
            }
            return redirect()->route('login');
        }

        if ($user->role === 'participant' && !$user->active) {
            auth()->logout();
            return redirect()->route('login')->with('error', 'Votre compte a été désactivé.');
        }

        return $next($request);
    }
}
