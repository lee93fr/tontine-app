<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->enum('type', ['standard', 'preliminary'])->default('standard')->after('tontine_id');
            $table->decimal('preliminary_amount', 10, 2)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn(['type', 'preliminary_amount']);
        });
    }
};
