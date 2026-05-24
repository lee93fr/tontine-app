<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            $table->boolean('has_preliminary')->default(false)->after('bid_cap');
            $table->decimal('preliminary_amount', 10, 2)->nullable()->after('has_preliminary');
            $table->date('preliminary_period_start')->nullable()->after('preliminary_amount');
            $table->date('preliminary_period_end')->nullable()->after('preliminary_period_start');
            $table->boolean('has_bidding')->default(true)->after('preliminary_period_end');
            $table->date('first_round_month')->nullable()->after('has_bidding');
            $table->unsignedTinyInteger('bid_day_open')->nullable()->after('first_round_month');
            $table->unsignedTinyInteger('bid_day_close')->nullable()->after('bid_day_open');
            $table->unsignedTinyInteger('payment_day')->nullable()->after('bid_day_close');
            $table->unsignedSmallInteger('rounds_count')->nullable()->after('payment_day');
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->date('payment_due_at')->nullable()->after('bid_closes_at');
        });
    }

    public function down(): void
    {
        Schema::table('tontines', function (Blueprint $table) {
            $table->dropColumn([
                'has_preliminary', 'preliminary_amount',
                'preliminary_period_start', 'preliminary_period_end',
                'has_bidding', 'first_round_month',
                'bid_day_open', 'bid_day_close', 'payment_day', 'rounds_count',
            ]);
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn('payment_due_at');
        });
    }
};
