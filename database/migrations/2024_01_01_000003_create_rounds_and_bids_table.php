<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tours de tontine
        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tontine_id')->constrained()->cascadeOnDelete();
            $table->integer('round_number');
            $table->decimal('pot_amount', 10, 2); // cagnotte totale du tour
            $table->timestamp('bid_opens_at');
            $table->timestamp('bid_closes_at');
            $table->enum('status', ['pending', 'open', 'closed', 'drawn', 'paid'])->default('pending');
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('winning_bid', 10, 2)->nullable();
            $table->boolean('drawn_by_lot')->default(false); // tirage au sort
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Enchères
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2); // montant de la commission en %
            $table->timestamp('bid_at')->useCurrent();
            $table->timestamps();
            $table->unique(['round_id', 'user_id']); // une enchère par participant par tour
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bids');
        Schema::dropIfExists('rounds');
    }
};
