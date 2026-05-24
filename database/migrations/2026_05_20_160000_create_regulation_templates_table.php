<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulation_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Règlement de tontine');
            $table->string('locale', 5)->default('fr');
            $table->unsignedSmallInteger('version')->default(1);
            // Liste ordonnée de blocs (titre, contenu HTML, condition, order, type).
            // Voir RegulationService pour la structure.
            $table->jsonb('blocks');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['locale', 'is_active'], 'regulation_active_per_locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulation_templates');
    }
};
