<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('tipe')->nullable();
            $table->string('area')->nullable();
            $table->timestamps();
        });

        Schema::create('inspectors', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('dept')->default('');
            $table->string('nomor_wa')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('defects', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('tipe')->default('Visual');
            $table->string('kategori')->default('Minor');
            $table->timestamps();
        });

        Schema::create('dimension_standards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('parameter');
            $table->decimal('nilai_standar', 12, 4);
            $table->decimal('toleransi_min', 12, 4)->default(0);
            $table->decimal('toleransi_max', 12, 4)->default(0);
            $table->string('satuan')->default('mm');
            $table->timestamps();
        });

        foreach (['visual_standards', 'function_standards'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('checklist');
                $table->timestamps();
            });
        }

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inspector_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shift')->default('Shift 1');
            $table->string('sesi')->default('Start');
            $table->unsignedInteger('sample')->default(1);
            $table->json('dimensi');
            $table->json('visual');
            $table->json('fungsi');
            $table->string('hasil_akhir')->default('Lulus');
            $table->boolean('approved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('function_standards');
        Schema::dropIfExists('visual_standards');
        Schema::dropIfExists('dimension_standards');
        Schema::dropIfExists('defects');
        Schema::dropIfExists('inspectors');
        Schema::dropIfExists('products');
    }
};
