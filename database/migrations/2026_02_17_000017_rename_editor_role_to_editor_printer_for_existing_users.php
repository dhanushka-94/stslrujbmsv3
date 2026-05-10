<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Existing users with role 'editor' had both edit + print permission.
     * Set them to 'editor_printer' (Editor + Printer). New role 'editor' is Editor only.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'editor')->update(['role' => 'editor_printer']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'editor_printer')->update(['role' => 'editor']);
    }
};
