<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_by_email')->default(true)->after('remember_token');
            $table->boolean('notify_by_sms')->default(false)->after('notify_by_email');
            $table->string('phone_number', 30)->nullable()->after('notify_by_sms');
            $table->timestamp('sms_verified_at')->nullable()->after('phone_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_by_email', 'notify_by_sms', 'phone_number', 'sms_verified_at']);
        });
    }
};
