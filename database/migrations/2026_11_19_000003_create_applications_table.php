<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('name_normalised', 150);
            $table->string('supplier', 150)->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'name_normalised']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
