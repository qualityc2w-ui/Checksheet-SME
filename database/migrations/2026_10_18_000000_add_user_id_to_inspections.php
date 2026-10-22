<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('inspector_id')
                ->constrained('users')->nullOnDelete();
        });

        foreach (DB::table('users')->where('role', 'inspector')->orderBy('id')->get() as $user) {
            $inspectorIds = DB::table('inspectors')->where('email', $user->email)->pluck('id');
            if ($inspectorIds->isEmpty()) {
                $inspectorIds = DB::table('inspectors')
                    ->where('nama', $user->name)
                    ->whereNull('email')
                    ->pluck('id');
            }

            if ($inspectorIds->count() === 1) {
                DB::table('inspections')
                    ->where('inspector_id', $inspectorIds->first())
                    ->whereNull('user_id')
                    ->update(['user_id' => $user->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
