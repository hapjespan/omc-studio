<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elements', function (Blueprint $table) {
            $table->uuid('id');
            $table->primary('id');
            $table->foreignId('canvas_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('parent_id')->nullable()->constrained('elements')->noActionOnDelete();
            $table->string('kind');
            $table->string('name', 150);
            $table->string('name_normalised', 150);
            $table->text('description')->nullable();
            $table->foreignId('modality_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('rationale')->nullable();
            $table->string('icon')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_unknown_allocation')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['canvas_id', 'parent_id', 'position']);
            $table->index(['canvas_id', 'area_id', 'parent_id', 'name_normalised']);
            $table->index(['canvas_id', 'is_unknown_allocation']);
            $table->index(['canvas_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elements');
    }
};
