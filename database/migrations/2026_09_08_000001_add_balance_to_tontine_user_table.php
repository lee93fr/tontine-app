<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tontine_user', function (Blueprint $table) {
            $table->decimal('balance', 12, 2)->default(0)->after('wins_count');
        });
    }

    public function down(): void
    {
        Schema::table('tontine_user', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
