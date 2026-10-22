<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finding_pics', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
            $table->string('email')->nullable()->after('department');
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->timestamp('email_sent_at')->nullable();
        });

        Schema::table('inspections', function (Blueprint $table) {
            $table->timestamp('email_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropColumn('email_sent_at');
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->dropColumn('email_sent_at');
        });

        Schema::table('finding_pics', function (Blueprint $table) {
            $table->dropColumn('email');
        });

        DB::table('finding_pics')->whereNull('phone')->update(['phone' => '']);
        Schema::table('finding_pics', function (Blueprint $table) {
            $table->string('phone')->nullable(false)->change();
        });
    }
};
