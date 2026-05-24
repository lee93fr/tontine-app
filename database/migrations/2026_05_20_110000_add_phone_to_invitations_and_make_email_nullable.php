<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('channel', 10)->default('email')->after('phone');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn(['phone', 'channel']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
