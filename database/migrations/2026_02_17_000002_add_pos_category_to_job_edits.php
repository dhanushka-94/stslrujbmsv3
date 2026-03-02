<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_edits', function (Blueprint $table) {
            $table->unsignedBigInteger('source_product_id')->nullable()->after('name');
            $table->string('category_name')->nullable()->after('source_product_id');
            $table->string('subcategory_name')->nullable()->after('category_name');
        });
    }

    public function down(): void
    {
        Schema::table('job_edits', function (Blueprint $table) {
            $table->dropColumn(['source_product_id', 'category_name', 'subcategory_name']);
        });
    }
};
