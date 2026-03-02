<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_editor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_job_id')->constrained('studio_jobs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['studio_job_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_editor');
    }
};
