<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('inspections', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('whatsapp_reported_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('inspector');
        });

        $legacyAreas = DB::table('products')->whereNotNull('area')->distinct()->pluck('area');
        foreach ($legacyAreas as $name) {
            $areaId = DB::table('areas')->insertGetId([
                'nama' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('products')->where('area', $name)->update(['area_id' => $areaId]);
        }

        DB::table('inspections')->whereNotNull('product_id')->update([
            'area_id' => DB::raw('(SELECT area_id FROM products WHERE products.id = inspections.product_id)'),
        ]);

        $firstUser = DB::table('users')->orderBy('id')->value('id');
        if ($firstUser !== null) {
            DB::table('users')->where('id', $firstUser)->update(['role' => 'admin']);
        }

        DB::table('inspections')->where('hasil_akhir', 'Lulus')->update(['hasil_akhir' => 'OK']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('inspections', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropColumn(['area_id', 'whatsapp_reported_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
        });

        Schema::dropIfExists('areas');
    }
};
