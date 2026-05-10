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
                            {--no-seed : Skip OwnerSeeder and SampleUsersSeeder}
                            {--force : Do not ask for confirmation}';

    protected $description = 'Delete all studio jobs (and cascaded edits, editors, dismissals) plus job-related activity log rows. By default re-seeds owner + sample users.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This removes every studio job and job activity log entry. Continue?', true)) {
            $this->info('Aborted.');
            return self::FAILURE;
        }

        DB::transaction(function (): void {
            if (Schema::hasTable('activity_log')) {
                DB::table('activity_log')->where('subject_type', 'job')->delete();
            }
            if (Schema::hasTable('studio_jobs')) {
                DB::table('studio_jobs')->delete();
            }
        });

        $this->info('Studio jobs and job activity log cleared.');

        if (! $this->option('no-seed')) {
            $this->call(OwnerSeeder::class);
            $this->call(SampleUsersSeeder::class);
            $this->newLine();
            $this->info('Sample role accounts use password: password');
            $this->line('Primary admin uses OWNER_EMAIL / OWNER_PASSWORD from .env when set, otherwise owner@studiosalaru.com / password.');
        }

        return self::SUCCESS;
    }
}
