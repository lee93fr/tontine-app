<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_local_tontines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tontine_id')->constrained()->cascadeOnDelete();
            // true = tontine créée/gérée par cet admin_local, false = attribuée par superadmin (lecture seule)
            $table->boolean('can_edit')->default(true);
            $table->unique(['user_id', 'tontine_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_local_tontines');
    }
};
