<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tontine_user', function (Blueprint $table) {
            // Dernière raison d'échec lors d'un envoi de demande de signature.
            // Null si pas d'échec ou après une tentative réussie.
            $table->text('signature_error')->nullable()->after('signature_signer_url');
        });
    }

    public function down(): void
    {
        Schema::table('tontine_user', function (Blueprint $table) {
            $table->dropColumn('signature_error');
        });
    }
};
