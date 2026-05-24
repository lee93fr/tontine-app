<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('email', 'hing.lilian@gmail.com')
            ->update(['role' => 'superadmin']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('email', 'hing.lilian@gmail.com')
            ->where('role', 'superadmin')
            ->update(['role' => 'admin']);
    }
};
