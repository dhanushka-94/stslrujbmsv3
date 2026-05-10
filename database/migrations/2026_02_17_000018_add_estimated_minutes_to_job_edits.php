<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_edits', function (Blueprint $table) {
            if (! Schema::hasColumn('job_edits', 'estimated_minutes')) {
                $table->unsignedSmallInteger('estimated_minutes')->nullable()->after('edit_done_at');
            }
            if (! Schema::hasColumn('job_edits', 'estimated_minutes_at')) {
                $table->timestamp('estimated_minutes_at')->nullable()->after('estimated_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_edits', function (Blueprint $table) {
            if (Schema::hasColumn('job_edits', 'estimated_minutes')) {
                $table->dropColumn('estimated_minutes');
            }
            if (Schema::hasColumn('job_edits', 'estimated_minutes_at')) {
                $table->dropColumn('estimated_minutes_at');
            }
        });
    }
};
