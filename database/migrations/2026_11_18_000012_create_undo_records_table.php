<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('undo_records', function (Blueprint $table) {
            $table->string('token')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->json('payload');
            $table->timestamp('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('undo_records');
    }
};
