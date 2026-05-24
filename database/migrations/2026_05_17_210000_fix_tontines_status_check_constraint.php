<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tontines DROP CONSTRAINT IF EXISTS tontines_status_check');
        DB::statement("ALTER TABLE tontines ADD CONSTRAINT tontines_status_check CHECK (status IN ('active', 'paused', 'completed', 'archived'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tontines DROP CONSTRAINT IF EXISTS tontines_status_check');
        DB::statement("ALTER TABLE tontines ADD CONSTRAINT tontines_status_check CHECK (status IN ('active', 'paused', 'completed'))");
    }
};
