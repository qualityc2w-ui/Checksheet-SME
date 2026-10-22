<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finding_pics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('department')->default('');
            $table->string('phone');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'name']);
        });

        Schema::create('finding_lookup_options', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30);
            $table->string('value');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['kind', 'value']);
        });

        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('title');
            $table->string('part_name');
            $table->string('part_type');
            $table->date('found_date');
            $table->string('shift', 40);
            $table->string('area_line');
            $table->string('problem_type');
            $table->text('description')->default('');
            $table->string('category', 20);
            $table->string('priority', 20)->default('medium');
            $table->text('analysis_man')->default('');
            $table->text('analysis_method')->default('');
            $table->text('analysis_machine')->default('');
            $table->text('analysis_material')->default('');
            $table->text('root_cause');
            $table->foreignId('pic_id')->nullable()->constrained('finding_pics')->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->string('response_token', 64)->unique();
            $table->date('due_date')->nullable();
            $table->text('pic_plan')->default('');
            $table->timestamp('responded_at')->nullable();
            $table->text('completion_note')->default('');
            $table->string('problem_photo_path')->nullable();
            $table->string('completion_photo_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_note')->default('');
            $table->timestamp('wa_sent_at')->nullable();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_name');
            $table->timestamps();
            $table->index(['status', 'found_date']);
            $table->index(['part_name', 'part_type']);
            $table->index('area_line');
        });

        foreach ([
            ['area_line', 'Line 1'],
            ['area_line', 'Line 2'],
            ['area_line', 'Line 3'],
            ['part_name', 'Cover Cylinder Head'],
            ['part_name', 'Bracket Engine Mount'],
            ['part_name', 'Housing Bearing'],
            ['part_type', 'CCH-250A'],
            ['part_type', 'BEM-100'],
            ['part_type', 'HB-75'],
            ['problem_type', 'Dimensi tidak sesuai'],
            ['problem_type', 'Penyok / cacat permukaan'],
            ['problem_type', 'Karatan / korosi'],
            ['problem_type', 'Retak / pecah'],
            ['problem_type', 'Salah perakitan'],
        ] as [$kind, $value]) {
            DB::table('finding_lookup_options')->insert([
                'kind' => $kind,
                'value' => $value,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
        Schema::dropIfExists('finding_lookup_options');
        Schema::dropIfExists('finding_pics');
    }
};
