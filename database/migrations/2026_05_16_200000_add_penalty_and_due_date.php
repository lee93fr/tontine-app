<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            $table->decimal('penalty_per_day', 8, 2)->default(1.00)->after('bid_cap');
            $table->decimal('penalty_cap', 8, 2)->nullable()->after('penalty_per_day');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            $table->dropColumn(['penalty_per_day', 'penalty_cap']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
