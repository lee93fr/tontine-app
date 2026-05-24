<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tontine;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SuperAdminController extends Controller
{
    /** Liste des administrateurs locaux */
    public function adminLocaux()
    {
        $adminLocaux = User::where('role', 'admin_local')
            ->with('adminLocalTontines')
            ->orderBy('name')
            ->get();

        return view('superadmin.admin-locaux.index', compact('adminLocaux'));
    }

    /** Page d'assignation de tontines à un admin_local */
    public function showAdminLocal(User $user)
    {
        abort_unless($user->isAdminLocal(), 404);

        $user->load('adminLocalTontines');

        $assignedIds = $user->adminLocalTontines->pluck('id');

        $allTontines = Tontine::whereNotIn('status', ['archived'])
            ->orderBy('name')
            ->get();

        return view('superadmin.admin-locaux.show', compact('user', 'allTontines', 'assignedIds'));
    }

    /** Assigner des tontines à un admin_local (vue) */
    public function assignTontines(Request $request, User $user)
    {
        abort_unless($user->isAdminLocal(), 404);

        $request->validate([
            'tontine_ids'   => 'nullable|array',
            'tontine_ids.*' => 'exists:tontines,id',
        ]);

        $tontineIds = $request->input('tontine_ids', []);

        // Tontines possédées par l'admin_local (can_edit = true) → ne pas toucher
        $ownedIds = DB::table('admin_local_tontines')
            ->where('user_id', $user->id)
            ->where('can_edit', true)
            ->pluck('tontine_id')
            ->toArray();

        // Supprimer les anciens attributions superadmin (can_edit = false)
        DB::table('admin_local_tontines')
            ->where('user_id', $user->id)
            ->where('can_edit', false)
            ->delete();

        // Ré-insérer seulement les tontines non possédées
        $toAssign = array_diff($tontineIds, $ownedIds);

        foreach ($toAssign as $tontineId) {
            DB::table('admin_local_tontines')->insert([
                'user_id'    => $user->id,
                'tontine_id' => $tontineId,
                'can_edit'   => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()
            ->route('superadmin.admin-locaux.show', $user)
            ->with('success', 'Tontines attribuées à ' . $user->full_name . ' avec succès.');
    }

    /** Créer un nouvel admin_local */
    public function createAdminLocal()
    {
        return view('superadmin.admin-locaux.create');
    }

    /** Enregistrer un nouvel admin_local */
    public function storeAdminLocal(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'       => $data['name'],
            'first_name' => $data['first_name'] ?? null,
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
            'role'       => 'admin_local',
        ]);

        return redirect()
            ->route('superadmin.admin-locaux.show', $user)
            ->with('success', 'Administrateur local ' . $user->full_name . ' créé avec succès.');
    }

    /** Rétrograder un admin_local en participant (conserve le compte) */
    public function destroyAdminLocal(User $user)
    {
        abort_unless($user->isAdminLocal(), 404);

        $user->update(['role' => 'participant']);

        DB::table('admin_local_tontines')->where('user_id', $user->id)->delete();

        return redirect()
            ->route('superadmin.admin-locaux.index')
            ->with('success', $user->full_name . ' a été rétrogradé en participant.');
    }

    /** Supprimer définitivement un admin_local (compte + attributions) */
    public function forceDeleteAdminLocal(User $user)
    {
        abort_unless($user->isAdminLocal(), 404);
        abort_if($user->id === auth()->id(), 403, 'Vous ne pouvez pas supprimer votre propre compte.');

        $fullName = $user->full_name;

        DB::transaction(function () use ($user) {
            DB::table('admin_local_tontines')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        return redirect()
            ->route('superadmin.admin-locaux.index')
            ->with('success', $fullName . ' a été supprimé définitivement.');
    }
}
