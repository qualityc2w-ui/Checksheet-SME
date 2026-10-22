<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['Hasil pemeriksaan visual NG', 'Hasil pemeriksaan fungsi NG'] as $value) {
            DB::table('finding_lookup_options')->insertOrIgnore([
                'kind' => 'problem_type',
                'value' => $value,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('finding_lookup_options')
            ->where('kind', 'problem_type')
            ->whereIn('value', ['Hasil pemeriksaan visual NG', 'Hasil pemeriksaan fungsi NG'])
            ->delete();
    }
};
