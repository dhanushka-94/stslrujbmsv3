<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('job_edits', 'claimed_by_user_id')) {
            Schema::table('job_edits', function (Blueprint $table) {
                $table->unsignedBigInteger('claimed_by_user_id')->nullable()->after('studio_job_id');
            });
        }

        $fkName = 'job_edits_claimed_by_user_id_foreign';
        $exists = DB::selectOne("
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'job_edits' AND CONSTRAINT_NAME = ?
        ", [config('database.connections.mysql.database'), $fkName]);
        if (! $exists) {
            Schema::table('job_edits', function (Blueprint $table) {
                $table->foreign('claimed_by_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('job_edits', 'customer_confirmed_at')) {
            Schema::table('job_edits', function (Blueprint $table) {
                $table->timestamp('customer_confirmed_at')->nullable()->after('completed_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('job_edits', function (Blueprint $table) {
            $table->dropForeign(['claimed_by_user_id']);
        });
        if (Schema::hasColumn('job_edits', 'claimed_by_user_id')) {
            Schema::table('job_edits', function (Blueprint $table) {
                $table->dropColumn('claimed_by_user_id');
            });
        }
        if (Schema::hasColumn('job_edits', 'customer_confirmed_at')) {
            Schema::table('job_edits', function (Blueprint $table) {
                $table->dropColumn('customer_confirmed_at');
            });
        }
    }
};
