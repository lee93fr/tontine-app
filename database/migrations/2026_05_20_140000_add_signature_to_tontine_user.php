<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tontine_user', function (Blueprint $table) {
            // Identifiant DocuSeal de la submission (un par participant/tontine)
            $table->string('signature_submission_id', 100)->nullable()->index();
            // Statut côté DocuSeal : pending | sent | opened | completed | declined | expired
            $table->string('signature_status', 20)->nullable();
            $table->timestamp('signature_sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            // URL du PDF signé (servie par DocuSeal)
            $table->text('signed_pdf_url')->nullable();
            // URL signataire (envoyée par DocuSeal pour le participant)
            $table->text('signature_signer_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tontine_user', function (Blueprint $table) {
            $table->dropColumn([
                'signature_submission_id',
                'signature_status',
                'signature_sent_at',
                'signed_at',
                'signed_pdf_url',
                'signature_signer_url',
            ]);
        });
    }
};
