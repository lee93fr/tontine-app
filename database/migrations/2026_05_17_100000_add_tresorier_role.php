<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL : drop et recréer la contrainte CHECK de l'enum
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin','participant','tresorier'))");

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('managed_tontine_id')
                  ->nullable()
                  ->after('role')
                  ->constrained('tontines')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::statement("UPDATE users SET role='participant' WHERE role='tresorier'");
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin','participant'))");

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['managed_tontine_id']);
            $table->dropColumn('managed_tontine_id');
        });
    }
};
