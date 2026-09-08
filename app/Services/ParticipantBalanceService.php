<?php

namespace App\Services;

use App\Models\ParticipantBalanceVersion;
use App\Models\Tontine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ParticipantBalanceService
{
    public function update(
        Tontine $tontine,
        User $participant,
        User $actor,
        float $newBalance,
        ?string $reason = null,
    ): bool {
        return DB::transaction(function () use ($tontine, $participant, $actor, $newBalance, $reason) {
            $membership = DB::table('tontine_user')
                ->where('tontine_id', $tontine->id)
                ->where('user_id', $participant->id)
                ->lockForUpdate()
                ->first();

            abort_unless($membership, 404);

            $previousBalance = round((float) ($membership->balance ?? 0), 2);
            $newBalance = round($newBalance, 2);

            if ($previousBalance === $newBalance) {
                return false;
            }

            $version = ((int) ParticipantBalanceVersion::where('tontine_id', $tontine->id)
                ->where('user_id', $participant->id)
                ->max('version')) + 1;

            ParticipantBalanceVersion::create([
                'tontine_id' => $tontine->id,
                'user_id' => $participant->id,
                'version' => $version,
                'previous_balance' => $previousBalance,
                'new_balance' => $newBalance,
                'reason' => filled($reason) ? trim($reason) : null,
                'changed_by' => $actor->id,
            ]);

            DB::table('tontine_user')
                ->where('id', $membership->id)
                ->update([
                    'balance' => $newBalance,
                    'updated_at' => now(),
                ]);

            return true;
        });
    }
}
