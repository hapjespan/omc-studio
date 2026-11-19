<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modalities', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('letter', 1);
            $table->string('colour', 7);
            $table->integer('position')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modalities');
    }
};
