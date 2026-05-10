<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_edits', function (Blueprint $table) {
            if (! Schema::hasColumn('job_edits', 'print_status_at')) {
                $table->timestamp('print_status_at')->nullable()->after('print_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_edits', function (Blueprint $table) {
            if (Schema::hasColumn('job_edits', 'print_status_at')) {
                $table->dropColumn('print_status_at');
            }
        });
    }
};
