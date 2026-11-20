<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_records', function (Blueprint $table) {
            $table->uuid('id');
            $table->primary('id');
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->string('code', 50)->nullable();
            $table->string('code_normalised', 50)->nullable();
            $table->string('name', 150);
            $table->string('cost_kind');
            $table->string('supplier', 150)->nullable();
            $table->foreignId('owner_id')->constrained()->restrictOnDelete();
            $table->string('cost_centre', 50)->nullable();
            $table->foreignId('application_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('description')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'code_normalised']);
            $table->index(['organisation_id', 'archived_at']);
            $table->index('owner_id');
            $table->index('application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_records');
    }
};
