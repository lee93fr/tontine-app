<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_balance_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tontine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->decimal('previous_balance', 12, 2);
            $table->decimal('new_balance', 12, 2);
            $table->string('reason', 500)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tontine_id', 'user_id', 'version']);
            $table->index(['tontine_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_balance_versions');
    }
};
