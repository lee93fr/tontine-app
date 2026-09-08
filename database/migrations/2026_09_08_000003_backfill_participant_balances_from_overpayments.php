<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $overpayments = DB::table('payments')
            ->join('rounds', 'rounds.id', '=', 'payments.round_id')
            ->select('rounds.tontine_id', 'payments.user_id')
            ->selectRaw('SUM(CASE WHEN payments.paid_amount > payments.amount THEN payments.paid_amount - payments.amount ELSE 0 END) AS overpayment')
            ->groupBy('rounds.tontine_id', 'payments.user_id')
            ->get();

        foreach ($overpayments as $overpayment) {
            $amount = round((float) $overpayment->overpayment, 2);
            if ($amount <= 0) {
                continue;
            }

            $membership = DB::table('tontine_user')
                ->where('tontine_id', $overpayment->tontine_id)
                ->where('user_id', $overpayment->user_id)
                ->first();

            if (! $membership) {
                continue;
            }

            $previousBalance = round((float) ($membership->balance ?? 0), 2);
            $newBalance = round($previousBalance + $amount, 2);
            $version = ((int) DB::table('participant_balance_versions')
                ->where('tontine_id', $overpayment->tontine_id)
                ->where('user_id', $overpayment->user_id)
                ->max('version')) + 1;

            DB::table('tontine_user')->where('id', $membership->id)->update([
                'balance' => $newBalance,
                'updated_at' => now(),
            ]);

            DB::table('participant_balance_versions')->insert([
                'tontine_id' => $overpayment->tontine_id,
                'user_id' => $overpayment->user_id,
                'version' => $version,
                'previous_balance' => $previousBalance,
                'new_balance' => $newBalance,
                'reason' => 'Initialisation automatique à partir des trop-perçus existants.',
                'changed_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // La reprise ne peut pas être annulée sans risquer d'effacer des ajustements ultérieurs.
    }
};
