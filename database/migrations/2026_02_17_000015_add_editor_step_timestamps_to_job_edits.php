<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_edits', function (Blueprint $table) {
            if (! Schema::hasColumn('job_edits', 'claimed_at')) {
                $table->timestamp('claimed_at')->nullable()->after('claimed_by_user_id');
            }
            if (! Schema::hasColumn('job_edits', 'sent_to_customer_at')) {
                $table->timestamp('sent_to_customer_at')->nullable()->after('sent_to_customer_count');
            }
            if (! Schema::hasColumn('job_edits', 'reedit_at')) {
                $table->timestamp('reedit_at')->nullable()->after('reedit_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_edits', function (Blueprint $table) {
            if (Schema::hasColumn('job_edits', 'claimed_at')) {
                $table->dropColumn('claimed_at');
            }
            if (Schema::hasColumn('job_edits', 'sent_to_customer_at')) {
                $table->dropColumn('sent_to_customer_at');
            }
            if (Schema::hasColumn('job_edits', 'reedit_at')) {
                $table->dropColumn('reedit_at');
            }
        });
    }
};
