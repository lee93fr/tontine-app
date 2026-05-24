<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('last_reminder_sent_at')->nullable()->after('paid_at');
            $table->unsignedSmallInteger('reminder_count')->default(0)->after('last_reminder_sent_at');
            $table->timestamp('last_sms_reminder_sent_at')->nullable()->after('reminder_count');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['last_reminder_sent_at', 'reminder_count', 'last_sms_reminder_sent_at']);
        });
    }
};
