<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->foreignId('inspection_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('inspection_section', 20)->nullable();
            $table->unsignedInteger('inspection_item_index')->nullable();
            $table->string('point_check')->nullable();
            $table->string('observed_value')->nullable();
            $table->string('standard_value')->nullable();
            $table->string('minimum_value')->nullable();
            $table->string('maximum_value')->nullable();
            $table->string('unit', 40)->nullable();
            $table->unique(
                ['inspection_id', 'inspection_section', 'inspection_item_index'],
                'findings_inspection_point_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropUnique('findings_inspection_point_unique');
            $table->dropConstrainedForeignId('inspection_id');
            $table->dropColumn([
                'inspection_section', 'inspection_item_index', 'point_check', 'observed_value',
                'standard_value', 'minimum_value', 'maximum_value', 'unit',
            ]);
        });
    }
};
