<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_jobs', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('notes');
            $table->boolean('is_active')->default(true)->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('studio_jobs', function (Blueprint $table) {
            $table->dropColumn(['due_date', 'is_active']);
        });
    }
};
