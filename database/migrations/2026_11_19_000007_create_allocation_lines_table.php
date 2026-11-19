<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allocation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_set_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('element_id')->constrained()->noActionOnDelete();
            $table->unsignedSmallInteger('basis_points');
            $table->integer('position')->default(0);

            $table->unique(['allocation_set_id', 'element_id']);
            $table->index('element_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_lines');
    }
};
