<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('areas')->restrictOnDelete();
        });

        Schema::table('visual_standards', function (Blueprint $table) {
            $table->string('image_path')->nullable();
        });

        Schema::table('inspections', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('approved')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });

        Schema::table('visual_standards', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });

        Schema::table('areas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
