<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canvas_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('title', 150)->nullable();
            $table->text('body');
            $table->integer('position');

            $table->index(['canvas_id', 'kind', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statements');
    }
};
