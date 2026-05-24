<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            $table->unsignedTinyInteger('payout_day')->nullable()->after('payment_day');
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->date('payout_date')->nullable()->after('payment_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            $table->dropColumn('payout_day');
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn('payout_date');
        });
    }
};
