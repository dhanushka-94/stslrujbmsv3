<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_pos_apply_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_job_id')->constrained('studio_jobs')->cascadeOnDelete();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at');
            $table->timestamp('pos_sale_created_at')->nullable();
            $table->timestamp('pos_sale_updated_at')->nullable();
            $table->string('summary')->nullable();
            $table->json('previous_lines')->nullable();
            $table->json('new_lines')->nullable();
            $table->json('added')->nullable();
            $table->json('removed')->nullable();
            $table->json('changed')->nullable();
            $table->timestamps();

            $table->index(['studio_job_id', 'applied_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_pos_apply_histories');
    }
};
