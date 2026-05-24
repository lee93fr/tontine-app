<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Montant déjà versé. Permet les paiements partiels (ex: Théo SIV qui doit
            // 1600 € d'avance pour 2 participations et veut payer en plusieurs fois).
            $table->decimal('paid_amount', 10, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};
