<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_job_id')->constrained('studio_jobs')->cascadeOnDelete();
            $table->string('name'); // e.g. "Edit 1", "Item 1"
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('edit_status', 30)->default('pending'); // pending, in_progress, completed
            $table->string('print_status', 30)->default('not_required'); // not_required, pending, sent_to_print, printed
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_edits');
    }
};
