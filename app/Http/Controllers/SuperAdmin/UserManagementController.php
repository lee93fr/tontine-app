<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserManagementController extends Controller
{
    /**
     * Page unique listant les comptes accessibles à l'utilisateur connecté.
     *
     * - Superadmin / Admin : voient tous les utilisateurs.
     * - Admin local       : ne voit que les utilisateurs (participants & trésoriers)
     *                        rattachés aux tontines qu'il gère.
     *
     * Les actions sensibles sont restreintes :
     * - Promouvoir en admin_local : superadmin uniquement.
     * - Rétrograder un admin_local : superadmin uniquement.
     * - Supprimer un compte        : superadmin & admin uniquement (admin_local interdit).
     * - Activer / désactiver       : superadmin, admin et admin_local (pour ses utilisateurs).
     */
    public function index(Request $request)
    {
        $current = auth()->user();
        abort_unless($current->isAdmin() || $current->isAdminLocal(), 403);

        $role   = $request->query('role');
        $search = trim((string) $request->query('q', ''));

        // Périmètre des IDs visibles pour l'admin_local : participants de ses tontines + trésoriers de ses tontines
        $scopeIds = null;
        if ($current->isAdminLocal()) {
            $tontineIds = $current->adminLocalTontines()->pluck('tontines.id');

            $participantIds = DB::table('tontine_user')
                ->whereIn('tontine_id', $tontineIds)
                ->pluck('user_id');

            $tresorierIds = DB::table('tresorier_tontines')
                ->whereIn('tontine_id', $tontineIds)
                ->pluck('user_id');

            $scopeIds = $participantIds->merge($tresorierIds)->unique();
        }

        $query = User::query()
            ->with(['adminLocalTontines', 'tontines', 'managedTontines'])
            ->withCount(['tontines', 'managedTontines', 'adminLocalTontines'])
            ->orderBy('name');

        if ($scopeIds !== null) {
            $query->whereIn('id', $scopeIds);
        }

        if ($role && $role !== 'all') {
            $query->where(function ($q) use ($role) {
                match ($role) {
                    'superadmin'  => $q->where('role', 'superadmin'),
                    'admin'       => $q->whereIn('role', ['admin', 'superadmin']),
                    'admin_local' => $q->where('role', 'admin_local'),
                    'tresorier'   => $q->where('role', 'tresorier')
                                       ->orWhereHas('managedTontines'),
                    'participant' => $q->whereHas('tontines'),
                    default       => $q->where('role', $role),
                };
            });
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('first_name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $users = $query->get();

        // Comptages contextuels (filtrés par scope pour admin_local)
        $countQ = User::query();
        if ($scopeIds !== null) {
            $countQ->whereIn('id', $scopeIds);
        }
        $base = $countQ->get();

        $counts = [
            'all'         => $base->count(),
            'superadmin'  => $base->where('role', 'superadmin')->count(),
            'admin'       => $base->whereIn('role', ['admin', 'superadmin'])->count(),
            'admin_local' => $base->where('role', 'admin_local')->count(),
            'tresorier'   => $base->filter(fn($u) => $u->role === 'tresorier' || $u->managedTontines()->exists())->count(),
            'participant' => $base->filter(fn($u) => $u->tontines()->exists())->count(),
        ];

        // Invitations en attente
        $invitationsQ = Invitation::with(['tontine', 'inviter'])
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest();

        if ($current->isAdminLocal()) {
            $tontineIds = $current->adminLocalTontines()->pluck('tontines.id');
            $invitationsQ->whereIn('tontine_id', $tontineIds);
        }

        $pendingInvitations = $invitationsQ->get();

        return view('superadmin.users.index', [
            'users'              => $users,
            'role'               => $role ?: 'all',
            'search'             => $search,
            'counts'             => $counts,
            'pendingInvitations' => $pendingInvitations,
            'permissions'        => $this->permissions($current),
        ]);
    }

    /** Promouvoir un compte (participant / tresorier) en admin_local — superadmin uniquement */
    public function promoteToAdminLocal(User $user)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        abort_if($user->isAdmin(), 403, 'Impossible de modifier le rôle d\'un administrateur.');

        $user->update(['role' => 'admin_local']);

        return back()->with('success', $user->full_name . ' est désormais administrateur local.');
    }

    /** Rétrograder un admin_local en participant — superadmin uniquement */
    public function demoteToParticipant(User $user)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        abort_unless($user->isAdminLocal(), 404);

        DB::table('admin_local_tontines')->where('user_id', $user->id)->delete();
        $user->update(['role' => 'participant']);

        return back()->with('success', $user->full_name . ' est désormais participant.');
    }

    /** Activer / désactiver un compte (admin_local restreint à son périmètre) */
    public function toggleActive(User $user)
    {
        $current = auth()->user();
        abort_unless($current->isAdmin() || $current->isAdminLocal(), 403);
        abort_if($user->isAdmin(), 403, 'Impossible de modifier un administrateur.');

        if ($current->isAdminLocal()) {
            $this->ensureWithinScope($current, $user);
        }

        $user->update(['active' => ! $user->active]);

        return back()->with('success', 'Statut du compte mis à jour.');
    }

    /** Suppression définitive (admin & superadmin uniquement, hors admin/superadmin) */
    public function destroy(User $user)
    {
        $current = auth()->user();
        abort_unless($current->isAdmin(), 403); // exclut admin_local et participant
        abort_if($user->isAdmin(), 403, 'Impossible de supprimer un administrateur depuis cette page.');
        abort_if($user->id === $current->id, 403, 'Vous ne pouvez pas supprimer votre propre compte.');

        if ($user->tontines()->exists()) {
            return back()->with('error', 'Impossible de supprimer ' . $user->full_name . ' : il/elle est inscrit·e à une ou plusieurs tontines.');
        }

        $fullName = $user->full_name;

        DB::transaction(function () use ($user) {
            DB::table('admin_local_tontines')->where('user_id', $user->id)->delete();
            DB::table('tresorier_tontines')->where('user_id', $user->id)->delete();
            User::where('partner_id', $user->id)->update(['partner_id' => null]);
            User::where('delegate_id', $user->id)->update(['delegate_id' => null]);
            $user->delete();
        });

        return redirect()
            ->route('users.index')
            ->with('success', $fullName . ' a été supprimé.');
    }

    /** Capabilities exposées à la vue pour cacher/afficher les boutons. */
    private function permissions(User $current): array
    {
        return [
            'can_promote_admin_local' => $current->isSuperAdmin(),
            'can_demote_admin_local'  => $current->isSuperAdmin(),
            'can_delete'              => $current->isAdmin(),  // admin + superadmin
            'can_toggle'              => $current->isAdmin() || $current->isAdminLocal(),
            'can_invite'              => $current->isAdmin(),  // standalone invitations = admin/superadmin
            'can_cancel_invitation'   => $current->isAdmin(),
        ];
    }

    private function ensureWithinScope(User $current, User $user): void
    {
        $tontineIds = $current->adminLocalTontines()->pluck('tontines.id');

        $isInScope = DB::table('tontine_user')
                ->whereIn('tontine_id', $tontineIds)
                ->where('user_id', $user->id)
                ->exists()
            || DB::table('tresorier_tontines')
                ->whereIn('tontine_id', $tontineIds)
                ->where('user_id', $user->id)
                ->exists();

        abort_unless($isInScope, 403, 'Cet utilisateur ne fait pas partie de vos tontines.');
    }
}
