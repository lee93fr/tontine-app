<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tontine_regulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tontine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('regulation_template_id')->nullable()->constrained()->nullOnDelete();

            // Override des blocs pour cette tontine (null = utilise le template tel quel).
            $table->jsonb('blocks_override')->nullable();

            // HTML rendu final (caché lors de la génération pour signature).
            $table->longText('generated_html')->nullable();

            // Identifiant DocuSeal du template créé pour cette tontine.
            $table->string('docuseal_template_id', 100)->nullable();

            // draft | preview | sent_for_signature | completed
            $table->string('status', 30)->default('draft');

            $table->timestamp('generated_at')->nullable();
            $table->timestamp('sent_for_signature_at')->nullable();

            $table->timestamps();
            $table->unique('tontine_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tontine_regulations');
    }
};
