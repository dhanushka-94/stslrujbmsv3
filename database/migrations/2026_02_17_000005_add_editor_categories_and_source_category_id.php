<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_edits', function (Blueprint $table) {
            $table->unsignedBigInteger('source_category_id')->nullable()->after('subcategory_name');
        });

        Schema::create('editor_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('source_category_id');
            $table->timestamps();
            $table->unique(['user_id', 'source_category_id']);
        });
    }

    public function down(): void
    {
        Schema::table('job_edits', function (Blueprint $table) {
            $table->dropColumn('source_category_id');
        });
        Schema::dropIfExists('editor_categories');
    }
};
