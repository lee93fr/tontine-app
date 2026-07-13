<?php

namespace App\Services;

use App\Models\Bid;
use App\Models\Round;
use App\Models\User;
use App\Notifications\BidPlacedNotification;

class BidService
{
    /**
     * Place ou met à jour l'enchère d'un participant pour un tour donné.
     * $placedBy identifie l'auteur réel de l'action lorsqu'il diffère du bénéficiaire
     * (délégué, trésorier ou admin enchérissant pour un participant).
     *
     * @return array{success: bool, type?: string, message?: string, bid?: Bid}
     */
    public function placeBid(Round $round, User $primaryUser, int $amount, ?User $placedBy = null): array
    {
        $pivotParticipant = $round->tontine->participants()->where('user_id', $primaryUser->id)->first();
        if (!$pivotParticipant) {
            return ['success' => false, 'type' => 'abort', 'message' => "Ce participant ne fait pas partie de cette tontine."];
        }

        if (!$round->tontine->has_bidding) {
            return ['success' => false, 'type' => 'error', 'message' => 'Cette tontine fonctionne par tirage au sort, les enchères ne sont pas activées.'];
        }

        if ($round->tontine->bid_requires_signature) {
            $isSecondary = $primaryUser->partner_id !== null;
            $sigStatus   = $isSecondary
                ? ($pivotParticipant->pivot->partner_signature_status ?? null)
                : ($pivotParticipant->pivot->signature_status ?? null);
            if ($sigStatus !== 'signed') {
                return ['success' => false, 'type' => 'error', 'message' => 'Le règlement de la tontine doit être signé avant de pouvoir placer une enchère.'];
            }
        }

        if ($pivotParticipant->pivot->wins_count >= $pivotParticipant->pivot->slots) {
            return ['success' => false, 'type' => 'error', 'message' => 'Ce participant a déjà remporté tous ses tours dans cette tontine.'];
        }

        if (!$round->isOpen()) {
            return ['success' => false, 'type' => 'error', 'message' => 'Les enchères sont fermées pour ce tour.'];
        }

        // Une enchère à 0% est une inscription volontaire au tirage au sort (pas une
        // offre compétitive) : plusieurs participants peuvent s'inscrire à 0% sans
        // se bloquer mutuellement, le tirage au sort les départagera.
        $cap = (int) $round->tontine->bid_cap;
        if ($amount > 0 && $amount < $cap) {
            $currentHighest = (int) $round->bids()->max('amount');
            if ($amount <= $currentHighest) {
                return ['success' => false, 'type' => 'validation', 'message' => __('app.bid.too_low')];
            }
        }

        $bid = Bid::updateOrCreate(
            ['round_id' => $round->id, 'user_id' => $primaryUser->id],
            ['amount' => $amount, 'bid_at' => now(), 'placed_by_id' => $placedBy?->id]
        );

        $round->tontine->participants()
            ->where('user_id', '!=', $primaryUser->id)
            ->with('partnerOf')
            ->get()
            ->flatMap(fn($p) => $p->partnerOf ? collect([$p, $p->partnerOf]) : collect([$p]))
            ->each(fn($recipient) => $recipient->notify(new BidPlacedNotification($round, $bid)));

        return ['success' => true, 'bid' => $bid];
    }

    /**
     * Annule l'enchère d'un participant pour un tour donné (admin/trésorier uniquement,
     * tant que le tour est encore ouvert).
     *
     * @return array{success: bool, message?: string}
     */
    public function cancelBid(Round $round, User $primaryUser): array
    {
        if (!$round->isOpen()) {
            return ['success' => false, 'message' => 'Les enchères sont fermées pour ce tour, impossible d\'annuler.'];
        }

        $bid = Bid::where('round_id', $round->id)->where('user_id', $primaryUser->id)->first();
        if (!$bid) {
            return ['success' => false, 'message' => "Ce participant n'a pas d'enchère sur ce tour."];
        }

        $bid->delete();

        return ['success' => true];
    }
}
