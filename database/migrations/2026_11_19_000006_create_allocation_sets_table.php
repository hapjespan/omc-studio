<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allocation_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canvas_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('cost_record_id')->constrained()->noActionOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['canvas_id', 'fiscal_year_id', 'cost_record_id']);
            $table->index('cost_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_sets');
    }
};
