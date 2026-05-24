<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('address')->nullable()->after('phone');
            $table->string('postal_code', 20)->nullable()->after('address');
            $table->string('city')->nullable()->after('postal_code');
            $table->string('iban', 34)->nullable()->after('city');
            $table->string('bic', 11)->nullable()->after('iban');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'address', 'postal_code', 'city', 'iban', 'bic']);
        });
    }
};
