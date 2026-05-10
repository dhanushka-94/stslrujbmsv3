<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_edits', function (Blueprint $table) {
            if (! Schema::hasColumn('job_edits', 'sent_to_customer_count')) {
                $table->unsignedInteger('sent_to_customer_count')->default(0)->after('print_status');
            }
            if (! Schema::hasColumn('job_edits', 'reedit_count')) {
                $table->unsignedInteger('reedit_count')->default(0)->after('sent_to_customer_count');
            }
            if (! Schema::hasColumn('job_edits', 'edit_done_at')) {
                $table->timestamp('edit_done_at')->nullable()->after('customer_confirmed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_edits', function (Blueprint $table) {
            if (Schema::hasColumn('job_edits', 'sent_to_customer_count')) {
                $table->dropColumn('sent_to_customer_count');
            }
            if (Schema::hasColumn('job_edits', 'reedit_count')) {
                $table->dropColumn('reedit_count');
            }
            if (Schema::hasColumn('job_edits', 'edit_done_at')) {
                $table->dropColumn('edit_done_at');
            }
        });
    }
};

