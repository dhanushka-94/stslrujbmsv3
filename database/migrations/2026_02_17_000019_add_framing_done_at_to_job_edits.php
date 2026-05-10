<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_edits', function (Blueprint $table) {
            if (! Schema::hasColumn('job_edits', 'framing_done_at')) {
                $table->timestamp('framing_done_at')->nullable()->after('edit_done_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_edits', function (Blueprint $table) {
            if (Schema::hasColumn('job_edits', 'framing_done_at')) {
                $table->dropColumn('framing_done_at');
            }
        });
    }
};
