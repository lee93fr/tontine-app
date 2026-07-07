<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\DocusealWebhookController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\RoundController;
use App\Http\Controllers\AdminLocal\AdminLocalController;
use App\Http\Controllers\InvitationAcceptController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationPreferencesController;
use App\Http\Controllers\Participant\ParticipantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SuperAdmin\SettingsController as SuperAdminSettingsController;
use App\Http\Controllers\SuperAdmin\SuperAdminController;
use App\Http\Controllers\SuperAdmin\UserManagementController;
use App\Http\Controllers\Tresorier\TresorierController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::get('/lang/{locale}', [LocaleController::class, 'switch'])->name('lang.switch');

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    $user = auth()->user();
    if ($user->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->isAdminLocal()) {
        return redirect()->route('admin-local.dashboard');
    }

    if ($user->isTresorier()) {
        if (session()->has('tresorier_active_tontine_id')) {
            return redirect()->route('tresorier.dashboard');
        }
        return redirect()->route('participant.dashboard');
    }

    return redirect()->route('participant.dashboard');
})->middleware('auth');

Route::get('/dashboard', function () {
    $user = auth()->user();
    if ($user->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->isAdminLocal()) {
        return redirect()->route('admin-local.dashboard');
    }

    if ($user->isTresorier()) {
        if (session()->has('tresorier_active_tontine_id')) {
            return redirect()->route('tresorier.dashboard');
        }
        return redirect()->route('participant.dashboard');
    }

    return redirect()->route('participant.dashboard');
})->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ADMIN
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::patch('/dashboard/filter', [AdminController::class, 'updateDashboardFilter'])->name('dashboard.filter.update');
    Route::get('/tontines', [AdminController::class, 'tontines'])->name('tontines.index');
    Route::post('/tontines/{tontine}/favorite', [AdminController::class, 'toggleFavoriteTontine'])->name('tontines.favorite.toggle');
    Route::get('/tontines/create', [AdminController::class, 'createTontine'])->name('tontines.create');
    Route::post('/tontines', [AdminController::class, 'storeTontine'])->name('tontines.store');
    Route::get('/tontines/{tontine}', [AdminController::class, 'showTontine'])->name('tontines.show');
    Route::get('/tontines/{tontine}/reglement/preview', [AdminController::class, 'previewRegulation'])->name('tontines.regulation.preview');
    Route::post('/tontines/{tontine}/reglement/envoyer', [AdminController::class, 'sendRegulationForSignature'])->name('tontines.regulation.send');
    Route::post('/tontines/{tontine}/reglement/refresh', [AdminController::class, 'refreshSignatureStatuses'])->name('tontines.regulation.refresh');
    Route::patch('/tontines/{tontine}/docuseal-template', [AdminController::class, 'updateDocusealTemplate'])->name('tontines.docuseal-template.update');
    // Génération en 2 étapes : préliminaire (modifiable) puis tours standards (figé)
    Route::post('/tontines/{tontine}/rounds/preliminary', [AdminController::class, 'generatePreliminaryAction'])->name('tontines.generate.preliminary');
    Route::post('/tontines/{tontine}/rounds/standards', [AdminController::class, 'generateStandardRoundsAction'])->name('tontines.generate.standards');
    // Alias legacy — redirige vers le générateur de tours standards
    Route::post('/tontines/{tontine}/generate', [AdminController::class, 'generateStandardRoundsAction'])->name('tontines.generate');
    Route::patch('/tontines/{tontine}/participants-lock', [AdminController::class, 'toggleParticipantsLock'])->name('tontines.participants.lock');
    Route::get('/tontines/{tontine}/edit', [AdminController::class, 'editTontine'])->name('tontines.edit');
    Route::patch('/tontines/{tontine}', [AdminController::class, 'updateTontine'])->name('tontines.update');
    Route::patch('/tontines/{tontine}/archive', [AdminController::class, 'archiveTontine'])->name('tontines.archive');
    Route::delete('/tontines/{tontine}', [AdminController::class, 'destroyTontine'])->name('tontines.destroy');
    Route::post('/tontines/{tontine}/participants', [AdminController::class, 'addParticipant'])->name('tontines.participants.add');
    Route::delete('/tontines/{tontine}/participants/{user}', [AdminController::class, 'removeParticipant'])->name('tontines.participants.remove');
    Route::patch('/tontines/{tontine}/participants/{user}/slots', [AdminController::class, 'updateParticipantSlots'])->name('tontines.participants.slots');
    Route::post('/tontines/{tontine}/participants/{user}/replace', [AdminController::class, 'replaceParticipant'])->name('tontines.participants.replace');
    Route::get('/tontines/{tontine}/rounds/create', [RoundController::class, 'create'])->name('rounds.create');
    Route::post('/tontines/{tontine}/rounds', [RoundController::class, 'store'])->name('rounds.store');
    Route::get('/tontines/{tontine}/rounds/{round}', [RoundController::class, 'show'])->name('rounds.show');
    Route::patch('/tontines/{tontine}/rounds/{round}/dates', [RoundController::class, 'updateDates'])->name('rounds.dates.update');
    Route::patch('/tontines/{tontine}/rounds/{round}/open', [RoundController::class, 'openRound'])->name('rounds.open');
    Route::patch('/tontines/{tontine}/rounds/{round}/close', [RoundController::class, 'closeRound'])->name('rounds.close');
    Route::patch('/tontines/{tontine}/rounds/{round}/reopen', [RoundController::class, 'reopenRound'])->name('rounds.reopen');
    Route::post('/tontines/{tontine}/rounds/{round}/draw', [RoundController::class, 'draw'])->name('rounds.draw');
    Route::delete('/tontines/{tontine}/rounds/{round}/draw', [RoundController::class, 'cancelDraw'])->name('rounds.cancel-draw');
    Route::post('/tontines/{tontine}/rounds/{round}/participants/{user}/enchere', [RoundController::class, 'placeBidFor'])->name('rounds.bid');
    Route::delete('/tontines/{tontine}/rounds/{round}/participants/{user}/enchere', [RoundController::class, 'cancelBidFor'])->name('rounds.bid.cancel');
    Route::patch('/tontines/{tontine}/rounds/{round}/payments/{payment}', [RoundController::class, 'updatePayment'])->name('rounds.payments.update');
    Route::post('/tontines/{tontine}/rounds/{round}/payments/{payment}/relancer', [RoundController::class, 'remindPayment'])->name('rounds.payments.remind');
    Route::post('/tontines/{tontine}/rounds/{round}/payments/{payment}/relancer-sms', [RoundController::class, 'remindPaymentSms'])->name('rounds.payments.remind.sms');
    Route::post('/tontines/{tontine}/invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::post('/invitations', [InvitationController::class, 'storeStandalone'])->name('invitations.standalone');
    Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
    Route::patch('/tontines/{tontine}/payment-info', [AdminController::class, 'updatePaymentInfo'])->name('tontines.payment-info');
    Route::post('/tontines/{tontine}/tresorier', [AdminController::class, 'assignTresorier'])->name('tontines.tresorier.assign');
    Route::delete('/tontines/{tontine}/tresorier', [AdminController::class, 'removeTresorier'])->name('tontines.tresorier.remove');
    Route::get('/participants', fn() => redirect()->route('users.index'))->name('participants.index');
    Route::patch('/participants/{user}/toggle', [AdminController::class, 'toggleParticipant'])->name('participants.toggle');
    Route::get('/participants/{user}', [AdminController::class, 'showParticipant'])->name('participants.show');
    Route::get('/participants/{user}/edit', [AdminController::class, 'editParticipant'])->name('participants.edit');
    Route::patch('/participants/{user}', [AdminController::class, 'updateParticipant'])->name('participants.update');
    Route::delete('/participants/{user}', [AdminController::class, 'destroyParticipant'])->name('participants.destroy');
    Route::post('/participants/{user}/binome', [AdminController::class, 'linkPartner'])->name('participants.partner.link');
    Route::delete('/participants/{user}/binome', [AdminController::class, 'unlinkPartner'])->name('participants.partner.unlink');
    Route::patch('/participants/{user}/delegue', [AdminController::class, 'setDelegate'])->name('participants.delegate.set');
    Route::delete('/participants/{user}/delegue', [AdminController::class, 'removeDelegate'])->name('participants.delegate.remove');
    Route::get('/profil', [AdminController::class, 'editProfile'])->name('profile.edit');
    Route::patch('/profil', [AdminController::class, 'updateProfile'])->name('profile.update');
    Route::patch('/profil/mot-de-passe', [AdminController::class, 'updatePassword'])->name('profile.password');
    Route::post('/mode-tresorier', [AdminController::class, 'enterTresorierMode'])->name('tresorier_mode.enter');
    Route::delete('/mode-tresorier', [AdminController::class, 'exitTresorierMode'])->name('tresorier_mode.exit');
    // Redirections legacy → désormais dans la page Paramètres (onglet Modèles email)
    Route::get('/emails', fn() => redirect()->route('admin.settings.index', ['tab' => 'emails']))->name('emails.index');
    Route::get('/emails/{key}/{locale}/edit', [EmailTemplateController::class, 'edit'])->name('emails.edit');
    Route::patch('/emails/{key}/{locale}', [EmailTemplateController::class, 'update'])->name('emails.update');

    // Paramètres applicatifs unifiés (3 onglets : SMS, SMTP, Modèles email)
    Route::get('/parametres', [SuperAdminSettingsController::class, 'index'])->name('settings.index');
    Route::patch('/parametres/sms',         [SuperAdminSettingsController::class, 'updateSms'])->name('settings.sms.update');
    Route::patch('/parametres/smtp',        [SuperAdminSettingsController::class, 'updateSmtp'])->name('settings.smtp.update');
    Route::patch('/parametres/mail-suspendu',[SuperAdminSettingsController::class, 'updateMailSuspended'])->name('settings.mail.suspended.update');
    Route::post('/parametres/sms/test',     [SuperAdminSettingsController::class, 'testSms'])->name('settings.sms.test');
    Route::post('/parametres/smtp/test',    [SuperAdminSettingsController::class, 'testSmtp'])->name('settings.smtp.test');
    Route::patch('/parametres/signature',     [SuperAdminSettingsController::class, 'updateDocuseal'])->name('settings.docuseal.update');
    Route::post('/parametres/signature/test', [SuperAdminSettingsController::class, 'testDocuseal'])->name('settings.docuseal.test');
});

// PARTICIPANT
Route::prefix('mon-espace')->name('participant.')->middleware(['auth', 'role:participant'])->group(function () {
    Route::get('/dashboard', [ParticipantController::class, 'dashboard'])->name('dashboard');
    Route::get('/historique', [ParticipantController::class, 'history'])->name('history');
    Route::get('/tontine/{tontine}', [ParticipantController::class, 'showTontine'])->name('tontine');
    Route::post('/encheres/{round}', [ParticipantController::class, 'placeBid'])->name('bid');
    Route::get('/profil', [ParticipantController::class, 'editProfile'])->name('profile.edit');
    Route::patch('/profil', [ParticipantController::class, 'updateProfile'])->name('profile.update');
    Route::patch('/profil/mot-de-passe', [ParticipantController::class, 'updatePassword'])->name('profile.password');
    Route::patch('/profil/delegue', [ParticipantController::class, 'updateDelegate'])->name('profile.delegate');
    Route::post('/tontine/{tontine}/contacter', [ParticipantController::class, 'contactAdmin'])->name('contact');
});

// TRÉSORIER - Entrée en mode trésorier (accessible sans session active)
Route::middleware('auth')->group(function () {
    Route::post('/tresorier/entrer', [TresorierController::class, 'enterTresorierMode'])->name('tresorier.mode.enter');
});

// TRÉSORIER - Routes nécessitant une session trésorier active
Route::prefix('tresorier')->name('tresorier.')->middleware(['auth', 'role:tresorier'])->group(function () {
    Route::get('/dashboard', [TresorierController::class, 'dashboard'])->name('dashboard');
    Route::get('/tontine', [TresorierController::class, 'showTontine'])->name('tontine');
    Route::get('/tontine/tours/{round}', [TresorierController::class, 'showRound'])->name('rounds.show');
    Route::patch('/tontine/tours/{round}/ouvrir', [TresorierController::class, 'openRound'])->name('rounds.open');
    Route::patch('/tontine/tours/{round}/fermer', [TresorierController::class, 'closeRound'])->name('rounds.close');
    Route::post('/tontine/tours/{round}/participants/{user}/enchere', [TresorierController::class, 'placeBidFor'])->name('rounds.bid');
    Route::delete('/tontine/tours/{round}/participants/{user}/enchere', [TresorierController::class, 'cancelBidFor'])->name('rounds.bid.cancel');
    Route::patch('/tontine/tours/{round}/paiements/{payment}', [TresorierController::class, 'updatePayment'])->name('rounds.payments.update');
    Route::get('/paiements', [TresorierController::class, 'paiements'])->name('paiements');
    Route::post('/paiements/{payment}/relancer', [TresorierController::class, 'remindPayment'])->name('paiements.remind');
    Route::post('/paiements/{payment}/relancer-sms', [TresorierController::class, 'remindPaymentSms'])->name('paiements.remind.sms');
    Route::post('/paiements/relancer-tout', [TresorierController::class, 'remindAllPayments'])->name('paiements.remind.all');
    Route::patch('/tontine/payment-info', [TresorierController::class, 'updatePaymentInfo'])->name('tontine.payment-info');
    Route::get('/profil', [TresorierController::class, 'editProfile'])->name('profile.edit');
    Route::patch('/profil', [TresorierController::class, 'updateProfile'])->name('profile.update');
    Route::patch('/profil/mot-de-passe', [TresorierController::class, 'updatePassword'])->name('profile.password');
    Route::post('/switch-tontine', [TresorierController::class, 'switchTontine'])->name('switch_tontine');
    Route::delete('/quitter', [TresorierController::class, 'exitTresorierMode'])->name('mode.exit');
});

// PRÉFÉRENCES DE NOTIFICATIONS (tous les rôles authentifiés)
Route::middleware('auth')->group(function () {
    Route::get('/notifications/preferences', [NotificationPreferencesController::class, 'edit'])
        ->name('notifications.preferences.edit');
    Route::patch('/notifications/preferences', [NotificationPreferencesController::class, 'update'])
        ->name('notifications.preferences.update');

    Route::prefix('api/push')->name('api.push.')->group(function () {
        Route::post('/subscribe', [PushSubscriptionController::class, 'subscribe'])->name('subscribe');
        Route::post('/unsubscribe', [PushSubscriptionController::class, 'unsubscribe'])->name('unsubscribe');
        Route::get('/status', [PushSubscriptionController::class, 'status'])->name('status');
        Route::post('/test', [PushSubscriptionController::class, 'test'])->name('test');
    });
});

// WEBHOOK DOCUSEAL (sans CSRF, authentifié par HMAC dans le controller)
Route::post('/api/docuseal/webhook', [DocusealWebhookController::class, 'handle'])->name('docuseal.webhook');

// INVITATIONS
Route::get('/invitation/{token}', [InvitationAcceptController::class, 'show'])->name('invitation.show');
Route::post('/invitation/{token}', [InvitationAcceptController::class, 'accept'])->name('invitation.accept');

// SUPERADMIN — gestion des admin_locaux (accessible au superadmin uniquement via isSuperAdmin check)
// Utilise le middleware admin car isSuperAdmin() → isAdmin() = true
Route::prefix('admin/superadmin')->name('superadmin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin-locaux', [SuperAdminController::class, 'adminLocaux'])->name('admin-locaux.index');
    Route::get('/admin-locaux/create', [SuperAdminController::class, 'createAdminLocal'])->name('admin-locaux.create');
    Route::post('/admin-locaux', [SuperAdminController::class, 'storeAdminLocal'])->name('admin-locaux.store');
    Route::get('/admin-locaux/{user}', [SuperAdminController::class, 'showAdminLocal'])->name('admin-locaux.show');
    Route::post('/admin-locaux/{user}/tontines', [SuperAdminController::class, 'assignTontines'])->name('admin-locaux.tontines.assign');
    Route::delete('/admin-locaux/{user}', [SuperAdminController::class, 'destroyAdminLocal'])->name('admin-locaux.destroy');
    Route::delete('/admin-locaux/{user}/force', [SuperAdminController::class, 'forceDeleteAdminLocal'])->name('admin-locaux.force-delete');
});

// Redirection legacy : ancien chemin Paramètres superadmin
Route::get('/admin/superadmin/parametres', fn() => redirect()->route('admin.settings.index'))
    ->middleware('auth')
    ->name('superadmin.settings.edit');

// GESTION DES UTILISATEURS — page unifiée accessible à admin, superadmin et admin_local
// Le contrôleur applique le scope (admin_local ne voit que les utilisateurs de ses tontines)
// et restreint les actions sensibles selon le rôle.
Route::middleware('auth')->prefix('admin/utilisateurs')->name('users.')->group(function () {
    Route::get('/', [UserManagementController::class, 'index'])->name('index');
    Route::post('/{user}/promote-admin-local', [UserManagementController::class, 'promoteToAdminLocal'])->name('promote.admin-local');
    Route::post('/{user}/demote-participant', [UserManagementController::class, 'demoteToParticipant'])->name('demote.participant');
    Route::patch('/{user}/toggle-active', [UserManagementController::class, 'toggleActive'])->name('toggle');
    Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
});


// ADMIN LOCAL — espace de gestion propre
Route::prefix('espace-admin')->name('admin-local.')->middleware(['auth', 'role:admin_local'])->group(function () {
    Route::get('/dashboard', [AdminLocalController::class, 'dashboard'])->name('dashboard');

    // Tontines
    Route::get('/tontines', [AdminLocalController::class, 'tontines'])->name('tontines.index');
    Route::get('/tontines/create', [AdminLocalController::class, 'createTontine'])->name('tontines.create');
    Route::post('/tontines', [AdminLocalController::class, 'storeTontine'])->name('tontines.store');
    Route::get('/tontines/{tontine}', [AdminLocalController::class, 'showTontine'])->name('tontines.show');
    Route::get('/tontines/{tontine}/edit', [AdminLocalController::class, 'editTontine'])->name('tontines.edit');
    Route::patch('/tontines/{tontine}', [AdminLocalController::class, 'updateTontine'])->name('tontines.update');
    Route::post('/tontines/{tontine}/generate', [AdminLocalController::class, 'generateRoundsAction'])->name('tontines.generate');

    // Invitations
    Route::post('/tontines/{tontine}/invitations', [AdminLocalController::class, 'storeInvitation'])->name('invitations.store');

    // Participants
    Route::post('/tontines/{tontine}/participants', [AdminLocalController::class, 'addParticipant'])->name('tontines.participants.add');
    Route::delete('/tontines/{tontine}/participants/{user}', [AdminLocalController::class, 'removeParticipant'])->name('tontines.participants.remove');
    Route::patch('/tontines/{tontine}/participants-lock', [AdminLocalController::class, 'toggleParticipantsLock'])->name('tontines.participants.lock');

    // Tours
    Route::get('/tontines/{tontine}/rounds/{round}', [AdminLocalController::class, 'showRound'])->name('rounds.show');
    Route::patch('/tontines/{tontine}/rounds/{round}/open', [AdminLocalController::class, 'openRound'])->name('rounds.open');
    Route::patch('/tontines/{tontine}/rounds/{round}/close', [AdminLocalController::class, 'closeRound'])->name('rounds.close');
    Route::patch('/tontines/{tontine}/rounds/{round}/reopen', [AdminLocalController::class, 'reopenRound'])->name('rounds.reopen');
    Route::post('/tontines/{tontine}/rounds/{round}/draw', [AdminLocalController::class, 'draw'])->name('rounds.draw');
    Route::delete('/tontines/{tontine}/rounds/{round}/draw', [AdminLocalController::class, 'cancelDraw'])->name('rounds.cancel-draw');
    Route::patch('/tontines/{tontine}/rounds/{round}/payments/{payment}', [AdminLocalController::class, 'updatePayment'])->name('rounds.payments.update');

    // Profil
    Route::get('/profil', [AdminLocalController::class, 'editProfile'])->name('profile.edit');
    Route::patch('/profil', [AdminLocalController::class, 'updateProfile'])->name('profile.update');
    Route::patch('/profil/mot-de-passe', [AdminLocalController::class, 'updatePassword'])->name('profile.password');
});
