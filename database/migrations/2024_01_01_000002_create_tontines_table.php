<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tontines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('cotisation_amount', 10, 2);
            $table->integer('max_participants')->default(20);
            $table->integer('bid_cap')->default(15); // plafond d'enchère en %
            $table->enum('status', ['active', 'paused', 'completed'])->default('active');
            $table->timestamps();
        });

        // Table pivot tontine_user
        Schema::create('tontine_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tontine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('has_won')->default(false); // a déjà remporté un tour
            $table->timestamps();
            $table->unique(['tontine_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tontine_user');
        Schema::dropIfExists('tontines');
    }
};
