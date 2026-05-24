<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tontine_user', function (Blueprint $table) {
            // Pour les binômes, le partenaire signe en parallèle (pas de condition d'ordre).
            // Sa submission DocuSeal est trackée ici en miroir des colonnes signature_* du primaire.
            $table->string('partner_signature_submission_id', 100)->nullable()->after('signature_error')->index();
            $table->string('partner_signature_status', 20)->nullable();
            $table->timestamp('partner_signature_sent_at')->nullable();
            $table->timestamp('partner_signed_at')->nullable();
            $table->text('partner_signed_pdf_url')->nullable();
            $table->text('partner_signature_signer_url')->nullable();
            $table->text('partner_signature_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tontine_user', function (Blueprint $table) {
            $table->dropColumn([
                'partner_signature_submission_id',
                'partner_signature_status',
                'partner_signature_sent_at',
                'partner_signed_at',
                'partner_signed_pdf_url',
                'partner_signature_signer_url',
                'partner_signature_error',
            ]);
        });
    }
};
