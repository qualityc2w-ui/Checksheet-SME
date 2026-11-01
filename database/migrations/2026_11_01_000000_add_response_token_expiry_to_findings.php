<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->timestamp('response_token_expires_at')->nullable();
        });

        DB::table('findings')
            ->whereNull('response_token_expires_at')
            ->orderBy('id')
            ->chunkById(500, function ($findings): void {
                foreach ($findings as $finding) {
                    $createdAt = $finding->created_at
                        ? Carbon::parse($finding->created_at)
                        : now();

                    DB::table('findings')
                        ->where('id', $finding->id)
                        ->update(['response_token_expires_at' => $createdAt->addDays(7)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropColumn('response_token_expires_at');
        });
    }
};
