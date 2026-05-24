<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropForeign(['tontine_id']);
            $table->foreignId('tontine_id')->nullable()->change();
            $table->foreign('tontine_id')->references('id')->on('tontines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropForeign(['tontine_id']);
            $table->foreignId('tontine_id')->nullable(false)->change();
            $table->foreign('tontine_id')->references('id')->on('tontines')->cascadeOnDelete();
        });
    }
};
