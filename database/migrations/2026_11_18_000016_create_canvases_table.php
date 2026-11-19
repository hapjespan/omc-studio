<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canvases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind')->index();
            $table->boolean('is_blank_template')->default(false);
            $table->string('name', 150);
            $table->string('name_normalised', 150);
            $table->string('scope_key');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['scope_key', 'name_normalised']);
            $table->index(['organisation_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canvases');
    }
};
