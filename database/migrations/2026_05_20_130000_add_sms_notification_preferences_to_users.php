<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Liste des événements pour lesquels l'utilisateur veut recevoir un SMS.
            // Par défaut : ouverture de tour + résultat de tour.
            $table->jsonb('sms_notification_preferences')
                ->nullable()
                ->after('notify_by_sms');
        });

        DB::table('users')->update([
            'sms_notification_preferences' => json_encode(['round_opened', 'round_result']),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sms_notification_preferences');
        });
    }
};
