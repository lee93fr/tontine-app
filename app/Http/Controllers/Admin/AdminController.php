<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\Payment;
use App\Models\Tontine;
use App\Models\TontineRegulation;
use App\Models\User;
use App\Models\Round;
use App\Services\DocusealService;
use App\Services\ParticipantSignatureService;
use App\Services\RegulationService;
use App\Services\TontineRegulationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();

        // Préférence persistée : si l'admin a sélectionné un sous-ensemble de tontines,
        // on filtre toutes les agrégations dessus. Sélection vide = toutes les tontines.
        // Tant que la migration user_dashboard_tontines n'est pas appliquée, on dégrade
        // proprement en mode "pas de filtre".
        $allTontines       = Tontine::orderBy('name')->get(['id', 'name']);
        $filterTableExists = \Illuminate\Support\Facades\Schema::hasTable('user_dashboard_tontines');
        $selectedTontineIds = $filterTableExists
            ? $user->dashboardTontines()->pluck('tontines.id')
            : collect();
        $hasFilter        = $selectedTontineIds->isNotEmpty();
        $scopedTontineIds = $hasFilter ? $selectedTontineIds : $allTontines->pluck('id');

        // Périmètre des paiements "actifs" : tours avec gagnant désigné (drawn/paid) + préliminaires
        // Les tours en attente ou en cours d'enchères (pending/open/closed) n'ont pas de montant exigible
        $activeRoundIds = Round::whereIn('tontine_id', $scopedTontineIds)
            ->where(fn($q) =>
                $q->where('type', 'preliminary')
                  ->orWhereIn('status', ['drawn', 'paid'])
            )->pluck('id');

        $payments = \App\Models\Payment::whereIn('round_id', $activeRoundIds)->get();
        $collected = (float) $payments->sum('paid_amount');
        $totalDue  = (float) $payments->sum('amount');
        $remaining = max(0, $totalDue - $collected);

        // Nombre de participants distincts impliqués dans le périmètre filtré
        if ($hasFilter) {
            $participantsCount = DB::table('tontine_user')
                ->join('users', 'users.id', '=', 'tontine_user.user_id')
                ->whereIn('tontine_user.tontine_id', $scopedTontineIds)
                ->where('users.role', 'participant')
                ->where('users.active', true)
                ->distinct('users.id')
                ->count('users.id');
        } else {
            $participantsCount = User::where('role', 'participant')->where('active', true)->count();
        }

        $stats = [
            'collected'        => $collected,
            'to_collect'       => $remaining,
            'total_due'        => $totalDue,
            'tontines'         => $hasFilter ? $scopedTontineIds->count() : Tontine::count(),
            'participants'     => $participantsCount,
            'open_rounds'      => Round::whereIn('tontine_id', $scopedTontineIds)->where('status', 'open')->count(),
            'pending_payments' => $payments->whereIn('status', ['pending', 'partial', 'late'])->count(),
        ];

        // Échéances à venir : paiements non terminés avec date d'échéance dans les 30 prochains jours
        $upcomingDeadlines = \App\Models\Payment::with(['round.tontine', 'user'])
            ->whereIn('round_id', $activeRoundIds)
            ->whereIn('status', ['pending', 'partial', 'late'])
            ->whereNotNull('due_date')
            ->where('due_date', '<=', now()->addDays(30))
            ->orderBy('due_date')
            ->get()
            ->groupBy(fn($p) => $p->due_date->format('Y-m-d'))
            ->take(10);

        $recentRounds = Round::with(['tontine', 'winner'])
            ->whereIn('tontine_id', $scopedTontineIds)
            ->whereIn('status', ['drawn', 'paid'])
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'stats', 'recentRounds', 'upcomingDeadlines',
            'allTontines', 'selectedTontineIds', 'hasFilter', 'filterTableExists'
        ));
    }

    /**
     * Sauvegarde la sélection de tontines visibles au dashboard pour l'admin courant.
     * Sélection vide = toutes les tontines (comportement par défaut).
     */
    public function updateDashboardFilter(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        if (! \Illuminate\Support\Facades\Schema::hasTable('user_dashboard_tontines')) {
            return redirect()->route('admin.dashboard')
                ->with('error', "La table 'user_dashboard_tontines' n'existe pas encore. Lancez : php artisan migrate");
        }

        $data = $request->validate([
            'tontine_ids'   => 'nullable|array',
            'tontine_ids.*' => 'integer|exists:tontines,id',
        ]);

        auth()->user()->dashboardTontines()->sync($data['tontine_ids'] ?? []);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Filtre des tontines du dashboard mis à jour.');
    }

    // ---- Tontines ----

    public function tontines(Request $request)
    {
        $user = auth()->user();
        $showAll = $request->boolean('all');

        $favoriteIds = $user->favoriteTontines()->pluck('tontines.id');
        $totalFavorites = $favoriteIds->count();

        $query = Tontine::withCount('participants')->latest();

        // Par défaut on n'affiche que les favoris s'il y en a, sauf si ?all=1
        if (! $showAll && $totalFavorites > 0) {
            $query->whereIn('id', $favoriteIds);
        }

        $tontines = $query->get();
        $totalAll = Tontine::count();

        return view('admin.tontines.index', [
            'tontines'       => $tontines,
            'favoriteIds'    => $favoriteIds,
            'totalFavorites' => $totalFavorites,
            'totalAll'       => $totalAll,
            'showAll'        => $showAll,
        ]);
    }

    /**
     * Prévisualisation HTML du règlement signé pour cette tontine.
     * Le rendu utilise le template actif (BDD) + les variables/conditions de la tontine.
     */
    public function previewRegulation(Tontine $tontine, RegulationService $regulations)
    {
        $html = $regulations->renderHtml($tontine);
        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Envoie le règlement en signature à tous les participants n'ayant pas encore signé.
     * Utilise le template DocuSeal universel configuré dans Paramètres > Signature,
     * en pré-remplissant les champs avec les variables de la tontine + du participant.
     */
    /** Interroge DocuSeal pour rafraichir le statut de signature de tous les participants. */
    public function refreshSignatureStatuses(Tontine $tontine, TontineRegulationService $service)
    {
        $stats = $service->refreshStatuses($tontine);

        $parts = [];
        if ($stats['updated'] > 0)   $parts[] = "{$stats['updated']} mise(s) à jour";
        if ($stats['unchanged'] > 0) $parts[] = "{$stats['unchanged']} inchangée(s)";
        if ($stats['errors'] > 0)    $parts[] = "{$stats['errors']} erreur(s)";

        $msg = empty($parts) ? 'Aucune submission à vérifier.' : implode(', ', $parts);

        return back()->with($stats['errors'] > 0 ? 'error' : 'success', "Rafraîchissement : {$msg}");
    }

    public function sendRegulationForSignature(Request $request, Tontine $tontine, TontineRegulationService $service)
    {
        $data = $request->validate([
            'user_ids'   => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $userIds = ! empty($data['user_ids']) ? $data['user_ids'] : null;

        if ($userIds !== null && empty($userIds)) {
            return back()->with('error', 'Aucun participant sélectionné.');
        }

        $stats = $service->sendForSignature($tontine, $userIds);

        if (! empty($stats['error'])) {
            $msg = match ($stats['error']) {
                'docuseal_disabled'        => 'DocuSeal est désactivé dans Paramètres > Signature.',
                'docuseal_not_configured'  => 'DocuSeal incomplet : vérifie URL et clé API dans Paramètres > Signature.',
                'no_template'              => 'Aucun template DocuSeal défini (ni au niveau tontine, ni global). Sélectionne un template dans cette page ou dans Paramètres > Signature.',
                default                    => 'Configuration DocuSeal invalide.',
            };
            return back()->with('error', $msg);
        }

        $parts = [];
        if ($stats['sent'] > 0)    $parts[] = "{$stats['sent']} envoi(s)";
        if ($stats['skipped'] > 0) $parts[] = "{$stats['skipped']} déjà signé(s)";
        if ($stats['failed'] > 0)  $parts[] = "{$stats['failed']} échec(s)";
        $msg = 'Envoi terminé : ' . implode(', ', $parts);

        return back()->with($stats['sent'] > 0 || $stats['skipped'] > 0 ? 'success' : 'error', $msg);
    }

    /** Ajouter/retirer une tontine des favoris (admin/superadmin) */
    public function toggleFavoriteTontine(Tontine $tontine)
    {
        $user = auth()->user();
        $user->favoriteTontines()->toggle([$tontine->id]);
        return back();
    }

    public function createTontine()
    {
        return view('admin.tontines.create');
    }

    public function storeTontine(Request $request)
    {
        $hasPreliminary = $request->boolean('has_preliminary');
        $hasBidding     = $request->boolean('has_bidding');
        $hasPenalties   = $request->boolean('has_penalties');

        $data = $request->validate([
            'name'                     => 'required|string|max:255',
            'description'              => 'nullable|string',
            'cotisation_amount'        => 'required|numeric|min:1',
            'max_participants'         => 'required|integer|min:2|max:100',
            'bid_cap'                  => 'nullable|integer|min:0|max:100',
            'penalty_per_day'          => 'nullable|numeric|min:0',
            'penalty_cap'              => 'nullable|numeric|min:0',
            'preliminary_amount'       => 'nullable|numeric|min:1',
            'preliminary_period_start' => 'nullable|date',
            'preliminary_period_end'   => 'nullable|date|after_or_equal:preliminary_period_start',
            'first_round_month'        => 'required|date_format:Y-m',
            'bid_day_open'             => 'required|integer|min:1|max:28',
            'bid_day_close'            => 'required|integer|min:1|max:28|gte:bid_day_open',
            'payment_day'              => 'required|integer|min:1|max:28',
            'rounds_count'             => 'nullable|integer|min:1|max:200',
        ]);

        if ($hasPreliminary) {
            $request->validate([
                'preliminary_amount'       => 'required|numeric|min:1',
                'preliminary_period_start' => 'required|date',
                'preliminary_period_end'   => 'required|date|after:preliminary_period_start',
            ]);
        }

        if ($hasBidding) {
            $request->validate(['bid_cap' => 'required|integer|min:1|max:100']);
        }

        $tontine = Tontine::create(array_merge($data, [
            'has_preliminary'        => $hasPreliminary,
            'has_bidding'            => $hasBidding,
            'bid_cap'                => $hasBidding ? ($data['bid_cap'] ?? 15) : 0,
            'bid_requires_signature' => $hasBidding && $request->boolean('bid_requires_signature'),
            'penalty_per_day'        => $hasPenalties ? ($data['penalty_per_day'] ?? 1) : 0,
            'penalty_cap'            => $hasPenalties ? ($data['penalty_cap'] ?? null) : null,
            'first_round_month'      => $data['first_round_month'] . '-01',
        ]));

        return redirect()->route('admin.tontines.show', $tontine)
                         ->with('success', __('app.tontine.msg_created'));
    }

    /**
     * Crée le tour préliminaire (si pas déjà fait) + les Payment rows associés
     * pour tous les participants déjà inscrits.
     * Si le tour existe déjà, met à jour les dates/montants et synchronise les paiements.
     */
    private function generatePreliminary(Tontine $tontine): void
    {
        if (! $tontine->has_preliminary || ! $tontine->preliminary_period_start) {
            return;
        }

        $existing = $tontine->rounds()->where('type', 'preliminary')->first();
        $payload = [
            'preliminary_amount' => $tontine->preliminary_amount ?? 0,
            'bid_opens_at'       => $tontine->preliminary_period_start->startOfDay(),
            'bid_closes_at'      => $tontine->preliminary_period_end->endOfDay(),
            'payment_due_at'     => $tontine->preliminary_period_end,
        ];

        if ($existing) {
            $existing->update($payload);
            $round = $existing;
        } else {
            $round = $tontine->rounds()->create(array_merge($payload, [
                'type'         => 'preliminary',
                'round_number' => 0,
                'pot_amount'   => 0,
                'status'       => 'pending',
            ]));
        }

        // Synchronise les Payment du préliminaire avec la liste actuelle des participants
        $this->syncRoundPayments($round, $tontine);
    }

    /**
     * Crée les tours standards (round_number 1..N).
     * Lève une exception si déjà générés (refus de doublonner).
     */
    private function generateStandardRounds(Tontine $tontine, ?int $count = null): void
    {
        if ($tontine->rounds()->where('type', 'standard')->exists()) {
            return;
        }

        $count     = $count ?: $tontine->rounds_count ?: $this->defaultRoundsCount($tontine);
        $baseMonth = Carbon::parse($tontine->first_round_month)->startOfMonth();

        for ($i = 1; $i <= $count; $i++) {
            $month     = $baseMonth->copy()->addMonths($i - 1);
            $bidOpens  = $month->copy()->setDay($tontine->bid_day_open)->startOfDay();
            $bidCloses = $month->copy()->setDay($tontine->bid_day_close)->endOfDay();
            $payDue    = $month->copy()->setDay($tontine->payment_day)->toDateString();

            $round = $tontine->rounds()->create([
                'type'           => 'standard',
                'round_number'   => $i,
                'pot_amount'     => 0,
                'bid_opens_at'   => $bidOpens,
                'bid_closes_at'  => $bidCloses,
                'payment_due_at' => $payDue,
                'status'         => 'pending',
            ]);

            $this->syncRoundPayments($round, $tontine);
        }
    }

    /** Crée les lignes Payment pour les participants actuels (idempotent). */
    private function syncRoundPayments(\App\Models\Round $round, Tontine $tontine): void
    {
        $unitAmount = $round->isPreliminary()
            ? ($round->preliminary_amount ?? 0)
            : (float) $tontine->cotisation_amount;

        foreach ($tontine->participants()->get() as $participant) {
            $slots = (int) ($participant->pivot->slots ?? 1);
            Payment::updateOrCreate(
                ['round_id' => $round->id, 'user_id' => $participant->id],
                [
                    'amount'   => $unitAmount * $slots,
                    'due_date' => $round->payment_due_at,
                    'status'   => Payment::where('round_id', $round->id)
                                         ->where('user_id', $participant->id)
                                         ->value('status') ?? 'pending',
                ]
            );
        }
    }

    /**
     * Nombre de tours par défaut = somme des slots (participations).
     * Ex: 12 individuels + 1 double = 13 tours.
     */
    private function defaultRoundsCount(Tontine $tontine): int
    {
        $slotsSum = (int) $tontine->participants()->sum('slots');
        return $slotsSum > 0 ? $slotsSum : (int) $tontine->max_participants;
    }

    private function recalcPotAmounts(Tontine $tontine): void
    {
        $tontine->recalcPotAmounts();
    }

    public function showTontine(Tontine $tontine)
    {
        $tontine->load(['participants', 'rounds.winner', 'rounds.bids']);
        $nonMembers = User::where('role', 'participant')
                          ->where('active', true)
                          ->whereDoesntHave('tontines', fn($q) => $q->where('tontine_id', $tontine->id))
                          ->orderBy('name')
                          ->get();
        $regulation = TontineRegulation::where('tontine_id', $tontine->id)->first();

        // Liste des templates DocuSeal pour le mapping par tontine (vide si DocuSeal pas config)
        $docusealTemplates = app(DocusealService::class)->listTemplates();
        $docusealConfig    = app(DocusealService::class)->config();

        return view('admin.tontines.show', compact(
            'tontine', 'nonMembers', 'regulation', 'docusealTemplates', 'docusealConfig'
        ));
    }

    /** Définit le(s) template(s) DocuSeal à utiliser pour cette tontine. */
    public function updateDocusealTemplate(Request $request, Tontine $tontine)
    {
        $data = $request->validate([
            'docuseal_template_id'            => 'nullable|integer|min:1',
            'docuseal_template_individual_id' => 'nullable|integer|min:1',
            'docuseal_template_binome_id'     => 'nullable|integer|min:1',
            'docuseal_template_double_id'     => 'nullable|integer|min:1',
        ]);

        $tontine->update([
            'docuseal_template_id'            => $data['docuseal_template_id']            ?? null,
            'docuseal_template_individual_id' => $data['docuseal_template_individual_id'] ?? null,
            'docuseal_template_binome_id'     => $data['docuseal_template_binome_id']     ?? null,
            'docuseal_template_double_id'     => $data['docuseal_template_double_id']     ?? null,
        ]);

        return back()->with('success', 'Templates DocuSeal mis à jour pour cette tontine.');
    }

    public function editTontine(Tontine $tontine)
    {
        // Tant que seul le préliminaire est généré, on peut encore modifier la config
        // (dates, montants, etc.). Le verrouillage n'intervient qu'après génération des tours standards.
        $isLaunched = $tontine->rounds()->where('type', 'standard')->exists();
        return view('admin.tontines.edit', compact('tontine', 'isLaunched'));
    }

    public function updateTontine(Request $request, Tontine $tontine)
    {
        $isLaunched = $tontine->rounds()->where('type', 'standard')->exists();

        if ($isLaunched) {
            $data = $request->validate([
                'name'             => 'required|string|max:255',
                'description'      => 'nullable|string',
                'status'           => 'required|in:active,paused,completed,archived',
                'max_participants' => 'required|integer|min:2|max:100',
                'penalty_cap'      => 'nullable|numeric|min:0',
                'payout_day'       => 'nullable|integer|min:1|max:28',
            ]);
            $oldPayoutDay = $tontine->payout_day;
            $tontine->update(array_merge($data, [
                'bid_requires_signature' => $tontine->has_bidding && $request->boolean('bid_requires_signature'),
            ]));
            if (isset($data['payout_day']) && $data['payout_day'] != $oldPayoutDay) {
                $this->propagatePayoutDay($tontine, (int) $data['payout_day']);
            }
        } else {
            $hasBidding   = $request->boolean('has_bidding');
            $hasPenalties = $request->boolean('has_penalties');

            $data = $request->validate([
                'name'              => 'required|string|max:255',
                'description'       => 'nullable|string',
                'cotisation_amount' => 'required|numeric|min:1',
                'max_participants'  => 'required|integer|min:2|max:100',
                'bid_cap'           => 'nullable|integer|min:0|max:100',
                'status'            => 'required|in:active,paused,completed,archived',
                'penalty_per_day'   => 'nullable|numeric|min:0',
                'penalty_cap'       => 'nullable|numeric|min:0',
                'bid_day_open'      => 'nullable|integer|min:1|max:28',
                'bid_day_close'     => 'nullable|integer|min:1|max:28|gte:bid_day_open',
                'payment_day'       => 'nullable|integer|min:1|max:28',
                'payout_day'        => 'nullable|integer|min:1|max:28',
                'rounds_count'      => 'nullable|integer|min:1|max:200',
            ]);

            $tontine->update(array_merge($data, [
                'has_bidding'            => $hasBidding,
                'bid_cap'                => $hasBidding ? ($data['bid_cap'] ?? $tontine->bid_cap) : 0,
                'bid_requires_signature' => $hasBidding && $request->boolean('bid_requires_signature'),
                'penalty_per_day'        => $hasPenalties ? ($data['penalty_per_day'] ?? 0) : 0,
                'penalty_cap'            => $hasPenalties ? ($data['penalty_cap'] ?? null) : null,
            ]));
        }

        return redirect()->route('admin.tontines.show', $tontine)
                         ->with('success', __('app.tontine.msg_updated'));
    }

    public function updatePaymentInfo(Request $request, Tontine $tontine)
    {
        $data = $request->validate([
            'tresorier_name'      => 'nullable|string|max:100',
            'tresorier_firstname' => 'nullable|string|max:100',
            'tresorier_phone'     => 'nullable|string|max:30',
            'iban'                => 'nullable|string|max:34',
            'bic'                 => 'nullable|string|max:11',
            'address'             => 'nullable|string|max:500',
            'revolut_link'        => 'nullable|string|max:255',
        ]);

        $tontine->update(['payment_info' => array_filter($data, fn($v) => $v !== null && $v !== '')]);

        return back()->with('success', 'Coordonnées de paiement mises à jour.');
    }

    private function propagatePayoutDay(Tontine $tontine, int $day): void
    {
        $tontine->rounds()
            ->whereIn('status', ['pending', 'open'])
            ->get()
            ->each(function ($round) use ($day) {
                $base = ($round->bid_closes_at ?? now())->copy();
                // Si le jour de versement est strictement après la clôture dans le même mois → même mois
                // Sinon → mois suivant (le versement a lieu après le tirage)
                if ($day > $base->day) {
                    $date = $base->setDay(min($day, $base->daysInMonth));
                } else {
                    $next = $base->copy()->addMonthNoOverflow()->startOfMonth();
                    $date = $next->setDay(min($day, $next->daysInMonth));
                }
                $round->update(['payout_date' => $date->toDateString()]);
            });
    }

    public function assignTresorier(Request $request, Tontine $tontine)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);

        $user = User::findOrFail($request->user_id);

        // Retire l'éventuel trésorier actuel de cette tontine (un seul trésorier par tontine)
        $previous = User::whereHas('managedTontines', fn($q) => $q->where('tontines.id', $tontine->id))
            ->where('id', '!=', $user->id)
            ->first();

        if ($previous) {
            $previous->managedTontines()->detach($tontine->id);
            // Ne rétrograde pas un admin/superadmin/admin_local — uniquement les participants/tresoriers purs
            if (! $previous->isAdmin() && ! $previous->isAdminLocal()) {
                if ((int) $previous->managed_tontine_id === $tontine->id) {
                    $next = $previous->managedTontines()->first();
                    $previous->update($next
                        ? ['managed_tontine_id' => $next->id]
                        : ['managed_tontine_id' => null, 'role' => 'participant']
                    );
                }
            }
        }

        // Ajoute la tontine à la liste du trésorier (sans écraser ses autres assignations)
        $user->managedTontines()->syncWithoutDetaching([$tontine->id]);

        // Ne change pas le rôle d'un admin/superadmin/admin_local — ils gardent leur rôle principal
        // et accèdent au mode trésorier via la session.
        $updates = [];
        if (! $user->isAdmin() && ! $user->isAdminLocal() && $user->role !== 'tresorier') {
            $updates['role'] = 'tresorier';
        }
        if (! $user->managed_tontine_id) {
            $updates['managed_tontine_id'] = $tontine->id;
        }
        if (! empty($updates)) {
            $user->update($updates);
        }

        return back()->with('success', "Trésorier assigné : {$user->full_name}");
    }

    public function removeTresorier(Tontine $tontine)
    {
        // Tout utilisateur peut être trésorier (admin/superadmin/admin_local inclus),
        // on cherche donc via la relation pivot sans filtrer sur le rôle.
        $tresorier = User::whereHas('managedTontines', fn($q) => $q->where('tontines.id', $tontine->id))->first();

        if (!$tresorier) {
            return back()->with('success', 'Trésorier retiré.');
        }

        $tresorier->managedTontines()->detach($tontine->id);

        // On ne rétrograde que les anciens « tresorier » purs — un admin/superadmin/admin_local
        // conserve son rôle quoi qu'il arrive.
        if ((int) $tresorier->managed_tontine_id === $tontine->id) {
            $next = $tresorier->managedTontines()->first();
            $updates = ['managed_tontine_id' => $next?->id];
            if (! $tresorier->isAdmin() && ! $tresorier->isAdminLocal() && ! $next) {
                $updates['role'] = 'participant';
            }
            $tresorier->update($updates);
        }

        return back()->with('success', 'Trésorier retiré de la tontine.');
    }

    public function archiveTontine(Tontine $tontine)
    {
        $name = $tontine->name;
        $tontine->update(['status' => 'archived']);

        return back()->with('success', __('app.tontine.msg_archived', ['name' => $name]));
    }

    public function destroyTontine(Tontine $tontine)
    {
        abort_if($tontine->status !== 'archived', 403, 'Seules les tontines archivées peuvent être supprimées.');

        // Récupérer les trésoriers avant suppression (cascade supprimera tresorier_tontines)
        $tresoriers = $tontine->tresoriers()->get();

        $name = $tontine->name;
        $tontine->delete();

        // Repasser en participant les trésoriers qui n'ont plus de tontine gérée
        foreach ($tresoriers as $tresorier) {
            $tresorier->refresh();
            $remaining = $tresorier->managedTontines()->count();
            if ($remaining === 0) {
                $tresorier->update(['role' => 'participant', 'managed_tontine_id' => null]);
            } elseif (!$tresorier->managed_tontine_id) {
                $next = $tresorier->managedTontines()->first();
                $tresorier->update(['managed_tontine_id' => $next->id]);
            }
        }

        return redirect()->route('admin.tontines.index')
                         ->with('success', __('app.tontine.msg_deleted', ['name' => $name]));
    }

    /** Génère/met à jour UNIQUEMENT le tour préliminaire (et ses paiements). */
    public function generatePreliminaryAction(Tontine $tontine)
    {
        if (! $tontine->has_preliminary) {
            return back()->with('error', 'Cette tontine n\'a pas de tour préliminaire activé.');
        }
        if (! $tontine->preliminary_period_start || ! $tontine->preliminary_period_end) {
            return back()->with('error', 'Les dates du tour préliminaire ne sont pas renseignées sur la tontine.');
        }
        if ($tontine->participants()->doesntExist()) {
            return back()->with('error', __('app.tontine.msg_no_participants_for_generate'));
        }

        $existed = $tontine->rounds()->where('type', 'preliminary')->exists();
        $this->generatePreliminary($tontine);
        $this->recalcPotAmounts($tontine);

        $msg = $existed
            ? 'Tour préliminaire mis à jour. Les paiements des participants ont été synchronisés.'
            : 'Tour préliminaire généré avec succès.';
        return back()->with('success', $msg);
    }

    /** Génère UNIQUEMENT les tours standards. Idempotent : refuse si déjà générés. */
    public function generateStandardRoundsAction(Request $request, Tontine $tontine)
    {
        if ($tontine->rounds()->where('type', 'standard')->exists()) {
            return back()->with('error', 'Les tours standards ont déjà été générés.');
        }
        if ($tontine->participants()->doesntExist()) {
            return back()->with('error', __('app.tontine.msg_no_participants_for_generate'));
        }
        if (! $tontine->first_round_month) {
            return back()->with('error', 'Le mois du premier tour n\'est pas défini sur la tontine.');
        }

        $data = $request->validate([
            'rounds_count' => 'nullable|integer|min:1|max:200',
        ]);
        $count = $data['rounds_count'] ?? $this->defaultRoundsCount($tontine);

        $tontine->update(['rounds_count' => $count]);

        $this->generateStandardRounds($tontine, $count);
        $this->recalcPotAmounts($tontine);

        $tontine->update(['participants_locked' => true]);

        $count = $tontine->rounds()->count();
        return redirect()->route('admin.tontines.show', $tontine)
                         ->with('success', __('app.tontine.msg_rounds_generated', ['count' => $count]));
    }

    public function toggleParticipantsLock(Tontine $tontine)
    {
        $tontine->update(['participants_locked' => !$tontine->participants_locked]);

        $msg = $tontine->participants_locked
            ? __('app.tontine.msg_participants_relocked')
            : __('app.tontine.msg_participants_unlocked');

        return back()->with('success', $msg);
    }

    public function addParticipant(Request $request, Tontine $tontine, ParticipantSignatureService $signatures)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'slots'   => 'required|integer|min:1|max:10',
        ]);

        if ($tontine->participants_locked) {
            return back()->with('error', __('app.tontine.msg_participants_locked'));
        }

        if ($tontine->participants()->count() >= $tontine->max_participants) {
            return back()->with('error', __('app.tontine.msg_max_reached'));
        }

        $tontine->participants()->syncWithoutDetaching([
            $request->user_id => ['slots' => $request->slots],
        ]);

        $signatures->ensureSubmission($tontine, User::find($request->user_id));

        // Créer les lignes de paiement pour tous les tours existants
        foreach ($tontine->rounds()->get() as $round) {
            $exists = Payment::where('round_id', $round->id)
                             ->where('user_id', $request->user_id)
                             ->exists();
            if (!$exists) {
                $unitAmount = $round->isPreliminary()
                    ? ($round->preliminary_amount ?? 0)
                    : $tontine->cotisation_amount;
                Payment::create([
                    'round_id' => $round->id,
                    'user_id'  => $request->user_id,
                    'amount'   => $unitAmount * $request->slots,
                    'due_date' => $round->payment_due_at,
                    'status'   => 'pending',
                ]);
            }
        }

        $this->recalcPotAmounts($tontine);

        return back()->with('success', __('app.tontine.msg_participant_added'));
    }

    public function updateParticipantSlots(Request $request, Tontine $tontine, User $user)
    {
        $request->validate(['slots' => 'required|integer|min:1|max:10']);

        abort_unless($tontine->participants()->where('user_id', $user->id)->exists(), 404);

        $tontine->participants()->updateExistingPivot($user->id, ['slots' => $request->slots]);

        $this->recalcPotAmounts($tontine);

        return back()->with('success', __('app.tontine.msg_slots_updated', ['name' => $user->name]));
    }

    public function replaceParticipant(Request $request, Tontine $tontine, User $user)
    {
        $oldUser = $user;
        abort_unless($tontine->participants()->where('user_id', $oldUser->id)->exists(), 404);

        $mode = $request->input('mode');

        if ($mode === 'existing') {
            $request->validate(['replacement_user_id' => 'required|exists:users,id']);
            $newUser = User::findOrFail($request->replacement_user_id);
        } else {
            $request->validate([
                'name'       => 'required|string|max:255',
                'first_name' => 'nullable|string|max:255',
                'email'      => 'required|email|unique:users,email',
                'password'   => 'required|string|min:8',
            ]);
            $newUser = User::create([
                'name'       => $request->name,
                'first_name' => $request->first_name,
                'email'      => $request->email,
                'password'   => $request->password,
                'role'       => 'participant',
                'active'     => true,
            ]);
        }

        if ($newUser->id === $oldUser->id) {
            return back()->with('error', 'Le remplaçant est identique au participant actuel.');
        }

        if ($tontine->participants()->where('user_id', $newUser->id)->exists()) {
            return back()->with('error', "{$newUser->full_name} est déjà participant de cette tontine.");
        }

        $pivot     = $tontine->participants()->where('user_id', $oldUser->id)->first()->pivot;
        $slots     = $pivot->slots;
        $winsCount = $pivot->wins_count;

        DB::transaction(function () use ($tontine, $oldUser, $newUser, $slots, $winsCount) {
            $roundIds = $tontine->rounds()->pluck('id');

            $tontine->participants()->detach($oldUser->id);
            $tontine->participants()->attach($newUser->id, ['slots' => $slots, 'wins_count' => $winsCount]);

            Payment::whereIn('round_id', $roundIds)->where('user_id', $oldUser->id)
                   ->update(['user_id' => $newUser->id]);
            Bid::whereIn('round_id', $roundIds)->where('user_id', $oldUser->id)
               ->update(['user_id' => $newUser->id]);
            Round::whereIn('id', $roundIds)->where('winner_id', $oldUser->id)
                 ->update(['winner_id' => $newUser->id]);
        });

        return back()->with('success', "Participant remplacé : {$oldUser->full_name} → {$newUser->full_name}");
    }

    public function removeParticipant(Tontine $tontine, User $user)
    {
        if ($tontine->participants_locked) {
            return back()->with('error', __('app.tontine.msg_participants_locked'));
        }

        // Supprimer les paiements en attente si les tours existent déjà
        if ($tontine->rounds()->exists()) {
            Payment::whereIn('round_id', $tontine->rounds()->pluck('id'))
                   ->where('user_id', $user->id)
                   ->where('status', 'pending')
                   ->delete();
        }

        $tontine->participants()->detach($user->id);

        $this->recalcPotAmounts($tontine);

        return back()->with('success', __('app.tontine.msg_participant_removed'));
    }

    // ---- Participants ----

    public function participants()
    {
        $participants = User::where('role', 'participant')
            ->with(['tontines', 'partner', 'partnerOf'])
            ->latest()
            ->get();

        $pendingInvitations = \App\Models\Invitation::with('tontine')
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get();

        return view('admin.participants.index', compact('participants', 'pendingInvitations'));
    }

    public function toggleParticipant(User $user)
    {
        $user->update(['active' => !$user->active]);
        return back()->with('success', __('app.participant.msg_status_updated'));
    }

    // ---- Mode trésorier ----

    public function enterTresorierMode(Request $request)
    {
        $validated = $request->validate([
            'tontine_id' => 'required|exists:tontines,id',
        ]);

        session(['tresorier_tontine_id' => (int) $validated['tontine_id']]);

        return redirect()->route('tresorier.paiements')
                         ->with('success', 'Mode trésorier activé.');
    }

    public function exitTresorierMode()
    {
        session()->forget('tresorier_tontine_id');

        return redirect()->route('admin.dashboard')
                         ->with('success', 'Retour en mode administrateur.');
    }

    // ---- Profil admin ----

    public function editProfile()
    {
        return view('admin.profile', [
            'user'     => auth()->user(),
            'tontines' => Tontine::orderBy('name')->get(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'phone'      => 'nullable|string|max:20',
            'address'    => 'nullable|string|max:255',
            'postal_code'=> 'nullable|string|max:20',
            'city'       => 'nullable|string|max:255',
            'iban'       => 'nullable|string|max:34',
            'bic'        => 'nullable|string|max:11',
        ]);

        auth()->user()->update($data);

        return back()->with('success', __('app.profile.msg_updated'));
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => __('app.profile.err_wrong_password')])->withInput();
        }

        $user->update(['password' => $request->password]);

        return back()->with('success', __('app.profile.msg_password_changed'));
    }

    // ---- Édition profil participant (admin) ----

    public function destroyParticipant(User $user)
    {
        abort_if($user->isAdmin(), 403);

        if ($user->tontines()->exists()) {
            return back()->with('error', __('app.participant.msg_delete_has_tontines'));
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.participants.index')
                         ->with('success', __('app.participant.msg_deleted', ['name' => $name]));
    }

    public function showParticipant(User $user)
    {
        abort_if($user->isAdmin(), 403);
        $user->load(['tontines', 'partner', 'partnerOf', 'delegate', 'delegatedFor']);
        $primaryUser = $user->primaryUser();

        $availablePartners = User::where('role', 'participant')
            ->where('id', '!=', $user->id)
            ->whereNull('partner_id')
            ->whereDoesntHave('partnerOf')
            ->orderBy('name')
            ->get();

        $availableDelegates = User::whereIn('role', ['participant', 'tresorier'])
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get();

        return view('admin.participants.show', compact('user', 'primaryUser', 'availablePartners', 'availableDelegates'));
    }

    public function editParticipant(User $user)
    {
        abort_if($user->isAdmin(), 403);
        $primaryUser = $user->primaryUser();
        return view('admin.participants.edit', compact('user', 'primaryUser'));
    }

    public function updateParticipant(Request $request, User $user)
    {
        abort_if($user->isAdmin(), 403);

        $request->merge(['iban' => strtoupper(preg_replace('/\s+/', '', $request->input('iban') ?? ''))]);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'first_name'  => 'required|string|max:255',
            'phone'       => 'required|string|max:20',
            'address'     => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'city'        => 'required|string|max:255',
            'iban'        => ['nullable', 'string', 'max:34', function($a, $v, $fail) {
                if (empty($v) || strtoupper(substr($v, 0, 2)) !== 'FR') return;
                if (strlen($v) !== 27) { $fail(__('app.rib.iban_invalid')); return; }
                $r = substr($v, 4).substr($v, 0, 4);
                $n = implode('', array_map(fn($c) => ctype_alpha($c) ? (string)(ord($c)-55) : $c, str_split($r)));
                if (bcmod($n, '97') !== '1') $fail(__('app.rib.iban_invalid'));
            }],
            'bic'         => 'nullable|string|max:11',
        ]);

        // Champs personnels → $user
        $user->update([
            'name'       => $data['name'],
            'first_name' => $data['first_name'],
            'phone'      => $data['phone'],
        ]);

        // Champs partagés → principal du binôme
        $primaryUser = $user->primaryUser();
        $primaryUser->update([
            'address'     => $data['address'],
            'postal_code' => $data['postal_code'],
            'city'        => $data['city'],
            'iban'        => $data['iban'] ?? null,
            'bic'         => $data['bic'] ?? null,
        ]);

        return redirect()->route('admin.participants.show', $user)
                         ->with('success', __('app.participant.msg_updated', ['name' => $user->full_name]));
    }

    public function linkPartner(Request $request, User $user)
    {
        abort_if($user->isAdmin(), 403);

        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ($validated['partner_id'] == $user->id) {
            return back()->with('error', 'Un participant ne peut pas être son propre partenaire.');
        }

        $partner = User::findOrFail($validated['partner_id']);

        if ($user->partnerOf()->exists() || $user->partner_id) {
            return back()->with('error', "{$user->full_name} est déjà dans un binôme.");
        }
        if ($partner->partnerOf()->exists() || $partner->partner_id) {
            return back()->with('error', "{$partner->full_name} est déjà dans un binôme.");
        }
        if ($partner->role !== 'participant') {
            return back()->with('error', 'Le partenaire doit être un participant.');
        }

        $partner->update(['partner_id' => $user->id]);

        return back()->with('success', "Binôme créé : {$user->full_name} & {$partner->full_name}");
    }

    public function unlinkPartner(User $user)
    {
        abort_if($user->isAdmin(), 403);

        if ($user->partnerOf) {
            $user->partnerOf->update(['partner_id' => null]);
        } elseif ($user->partner_id) {
            $user->update(['partner_id' => null]);
        } else {
            return back()->with('error', 'Aucun binôme trouvé.');
        }

        return back()->with('success', 'Binôme dissous.');
    }

    // ---- Délégation ----

    public function setDelegate(Request $request, User $user)
    {
        abort_if($user->isAdmin(), 403);
        $request->validate(['delegate_id' => 'required|exists:users,id']);

        $delegate = User::findOrFail($request->delegate_id);
        abort_if($delegate->id === $user->id, 422);

        $user->update(['delegate_id' => $delegate->id]);

        return back()->with('success', "{$delegate->full_name} peut désormais enchérir pour {$user->full_name}.");
    }

    public function removeDelegate(User $user)
    {
        abort_if($user->isAdmin(), 403);
        $user->update(['delegate_id' => null]);
        return back()->with('success', 'Délégation supprimée.');
    }
}
