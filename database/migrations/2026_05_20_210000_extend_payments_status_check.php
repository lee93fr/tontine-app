<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Étend l'enum 'status' de la table 'payments' pour accepter le statut 'partial'
        // (paiement partiel — voir Payment::deriveStatus()).
        // PostgreSQL ne supporte pas la modification d'enum via Schema::table sans drop,
        // donc on remplace la CHECK constraint à la main.
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('pending', 'paid', 'partial', 'late'))");
    }

    public function down(): void
    {
        // Si on rollback, on remet l'ancienne contrainte (sans 'partial').
        // Attention : si des lignes ont déjà status='partial', le rollback échouera.
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('pending', 'paid', 'late'))");
    }
};
