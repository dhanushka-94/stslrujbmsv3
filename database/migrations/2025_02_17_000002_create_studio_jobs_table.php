<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('ref_number')->unique();
            $table->string('source_id')->nullable()->index(); // id from external DB for sync
            $table->string('customer_name')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('new'); // new, assigned, in_progress, completed, delivered
            $table->foreignId('assigned_editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->string('delivery_method', 30)->nullable(); // online, walkin, courier
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_jobs');
    }
};
