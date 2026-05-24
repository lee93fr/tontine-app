<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            // ID DocuSeal du template à utiliser pour cette tontine.
            // null = utiliser le template global défini dans Paramètres > Signature.
            $table->unsignedInteger('docuseal_template_id')->nullable()->after('payment_info');
        });
    }

    public function down(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            $table->dropColumn('docuseal_template_id');
        });
    }
};
