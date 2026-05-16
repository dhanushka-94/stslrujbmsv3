<?php

namespace App\Console\Commands;

use Database\Seeders\OwnerSeeder;
use Database\Seeders\SampleUsersSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetStudioJobs extends Command
{
    protected $signature = 'studio:reset
                            {--users : Remove all app users and job-system data, then seed owner + one account per role (POS DB untouched)}
                            {--seed : After a jobs-only reset, run OwnerSeeder and SampleUsersSeeder (ignored when --users is set; --users always seeds)}
                            {--force : Do not ask for confirmation}';

    protected $description = 'Wipe studio job data in the app database. With --users, also clears activity log, block lists, editor categories, sessions, deletes all users, and re-seeds system access accounts. Never touches the POS/source database.';

    public function handle(): int
    {
        $purgeUsers = (bool) $this->option('users');

        $confirmMessage = $purgeUsers
            ? 'This deletes ALL users, every studio job, all activity log entries, blocked category/product lists, editor category assignments, and all sessions. Then it recreates the owner admin (from .env) plus one sample user per role. The POS database is not modified. Continue?'
            : 'This deletes every studio job (edits, editors, dismissals cascade), job-related activity log rows, and clears Job Pool “last checked” on users. User accounts are kept. POS database is not modified. Continue?';

        if (! $this->option('force') && ! $this->confirm($confirmMessage, false)) {
            $this->info('Aborted.');

            return self::FAILURE;
        }

        if ($purgeUsers) {
            $this->purgeApplicationJobDataAndUsers();
            $this->info('Job system data and all users removed.');
            $this->call(OwnerSeeder::class);
            $this->call(SampleUsersSeeder::class);
            $this->newLine();
            $this->warn('Sample role accounts use password: password');
            $this->line('Owner admin uses OWNER_EMAIL / OWNER_PASSWORD from .env when set, otherwise owner@studiosalaru.com / password.');

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            if (Schema::hasTable('activity_log')) {
                DB::table('activity_log')->where('subject_type', 'job')->delete();
            }
            if (Schema::hasTable('studio_jobs')) {
                DB::table('studio_jobs')->delete();
            }
        });

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'job_pool_last_checked_at')) {
            DB::table('users')->update(['job_pool_last_checked_at' => null]);
        }

        $this->info('Studio jobs cleared (job_edits, job_editor, job_dismissals removed via foreign keys).');
        $this->info('Job-related activity_log rows removed. User accounts unchanged.');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'job_pool_last_checked_at')) {
            $this->info('job_pool_last_checked_at cleared for all users (Job Pool badge resets).');
        }

        if ($this->option('seed')) {
            $this->call(OwnerSeeder::class);
            $this->call(SampleUsersSeeder::class);
            $this->newLine();
            $this->warn('Sample role accounts use password: password');
            $this->line('Primary admin uses OWNER_EMAIL / OWNER_PASSWORD from .env when set, otherwise owner@studiosalaru.com / password.');
        } else {
            $this->newLine();
            $this->line('Skipped seeding. Pass <fg=cyan>--seed</> if you want OwnerSeeder + SampleUsersSeeder.');
        }

        return self::SUCCESS;
    }

    private function purgeApplicationJobDataAndUsers(): void
    {
        DB::transaction(function (): void {
            if (Schema::hasTable('studio_jobs')) {
                DB::table('studio_jobs')->delete();
            }
            if (Schema::hasTable('activity_log')) {
                DB::table('activity_log')->delete();
            }
            if (Schema::hasTable('editor_categories')) {
                DB::table('editor_categories')->delete();
            }
            if (Schema::hasTable('blocked_categories')) {
                DB::table('blocked_categories')->delete();
            }
            if (Schema::hasTable('blocked_products')) {
                DB::table('blocked_products')->delete();
            }
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->delete();
            }
            if (Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->delete();
            }
            if (Schema::hasTable('users')) {
                DB::table('users')->delete();
            }
        });
    }
}
