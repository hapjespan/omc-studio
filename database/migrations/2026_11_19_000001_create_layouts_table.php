<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canvas_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('element_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('x');
            $table->integer('y');
            $table->integer('width');
            $table->integer('height');
            $table->rawColumn('z', 'integer constraint layouts_one_owner check ((area_id is null) <> (element_id is null))');
            $table->json('style');
            $table->integer('format_version')->default(1);
            $table->timestamp('updated_at');

            $table->unique('element_id');
            $table->unique(['canvas_id', 'area_id']);
            $table->index('canvas_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layouts');
    }
};
