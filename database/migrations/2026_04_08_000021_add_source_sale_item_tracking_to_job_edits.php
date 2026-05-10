<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_edits', function (Blueprint $table) {
            if (! Schema::hasColumn('job_edits', 'source_sale_item_id')) {
                $table->unsignedBigInteger('source_sale_item_id')->nullable();
            }
            if (! Schema::hasColumn('job_edits', 'source_quantity_unit_index')) {
                $table->unsignedSmallInteger('source_quantity_unit_index')->nullable();
            }
            if (! Schema::hasColumn('job_edits', 'source_quantity_unit_total')) {
                $table->unsignedSmallInteger('source_quantity_unit_total')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_edits', function (Blueprint $table) {
            if (Schema::hasColumn('job_edits', 'source_quantity_unit_total')) {
                $table->dropColumn('source_quantity_unit_total');
            }
            if (Schema::hasColumn('job_edits', 'source_quantity_unit_index')) {
                $table->dropColumn('source_quantity_unit_index');
            }
            if (Schema::hasColumn('job_edits', 'source_sale_item_id')) {
                $table->dropColumn('source_sale_item_id');
            }
        });
    }
};
