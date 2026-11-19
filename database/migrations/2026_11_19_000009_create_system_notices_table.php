<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_notices', function (Blueprint $table) {
            $table->id();
            $table->string('kind');
            $table->string('fingerprint');
            $table->foreignId('canvas_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('details')->nullable();
            $table->integer('occurrences')->default(1);
            $table->timestamp('first_occurred_at');
            $table->timestamp('last_occurred_at');
            $table->timestamp('seen_at')->nullable();
            $table->foreignId('seen_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['fingerprint', 'seen_at']);
            $table->index('seen_at');
            $table->index(['kind', 'last_occurred_at']);
            $table->index('canvas_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notices');
    }
};
