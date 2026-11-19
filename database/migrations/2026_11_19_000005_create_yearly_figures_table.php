<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yearly_figures', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('cost_record_id')->constrained()->noActionOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('budget_cents');
            $table->unsignedBigInteger('forecast_cents')->nullable();
            $table->unsignedSmallInteger('run_bp')->nullable();
            $table->unsignedSmallInteger('change_bp')->nullable();
            $table->unsignedInteger('fte_hundredths')->nullable();
            $table->date('reference_date')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['cost_record_id', 'fiscal_year_id']);
            $table->index('fiscal_year_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yearly_figures');
    }
};
