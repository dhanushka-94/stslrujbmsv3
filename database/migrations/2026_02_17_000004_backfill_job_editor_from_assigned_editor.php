<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('studio_jobs')
            ->whereNotNull('assigned_editor_id')
            ->select('id', 'assigned_editor_id')
            ->get();
        foreach ($rows as $row) {
            DB::table('job_editor')->insertOrIgnore([
                'studio_job_id' => $row->id,
                'user_id' => $row->assigned_editor_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No-op: we don't remove backfilled rows on rollback
    }
};
