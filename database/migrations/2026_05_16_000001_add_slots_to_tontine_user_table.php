<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tontine_user', function (Blueprint $table) {
            $table->unsignedTinyInteger('slots')->default(1)->after('user_id');
            $table->unsignedTinyInteger('wins_count')->default(0)->after('slots');
        });

        // Migrer les données existantes
        DB::statement('UPDATE tontine_user SET wins_count = 1 WHERE has_won = true');

        Schema::table('tontine_user', function (Blueprint $table) {
            $table->dropColumn('has_won');
        });
    }

    public function down(): void
    {
        Schema::table('tontine_user', function (Blueprint $table) {
            $table->boolean('has_won')->default(false)->after('user_id');
        });

        DB::statement('UPDATE tontine_user SET has_won = true WHERE wins_count >= 1');

        Schema::table('tontine_user', function (Blueprint $table) {
            $table->dropColumn(['slots', 'wins_count']);
        });
    }
};
