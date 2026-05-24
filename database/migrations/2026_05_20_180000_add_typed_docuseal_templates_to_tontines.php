<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            // Templates DocuSeal spécifiques par type de participation.
            // - individual : Annexe 3 (Reconnaissance individuelle)
            // - binome     : Annexe 4 (Reconnaissance solidaire de binôme)
            // - double     : Annexe 5 (Reconnaissance de participation double)
            // Fallback : docuseal_template_id (par défaut tontine), puis template global Paramètres.
            $table->unsignedInteger('docuseal_template_individual_id')->nullable()->after('docuseal_template_id');
            $table->unsignedInteger('docuseal_template_binome_id')->nullable()->after('docuseal_template_individual_id');
            $table->unsignedInteger('docuseal_template_double_id')->nullable()->after('docuseal_template_binome_id');
        });
    }

    public function down(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            $table->dropColumn([
                'docuseal_template_individual_id',
                'docuseal_template_binome_id',
                'docuseal_template_double_id',
            ]);
        });
    }
};
