<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tresorier_tontines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tontine_id')->constrained('tontines')->cascadeOnDelete();
            $table->unique(['user_id', 'tontine_id']);
            $table->timestamps();
        });

        // Migrer les assignations existantes
        $tresoriers = DB::table('users')
            ->where('role', 'tresorier')
            ->whereNotNull('managed_tontine_id')
            ->get();

        foreach ($tresoriers as $user) {
            DB::table('tresorier_tontines')->insertOrIgnore([
                'user_id'    => $user->id,
                'tontine_id' => $user->managed_tontine_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tresorier_tontines');
    }
};
