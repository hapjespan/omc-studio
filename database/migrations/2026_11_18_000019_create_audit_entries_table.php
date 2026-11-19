<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_entries', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at')->index();
            $table->string('actor_type');
            $table->foreignId('actor_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->string('subject_id');
            $table->string('subject_label', 150);
            $table->unsignedBigInteger('organisation_id')->nullable();
            $table->unsignedBigInteger('canvas_id')->nullable();
            $table->json('changes');

            $table->index(['canvas_id', 'occurred_at']);
            $table->index(['organisation_id', 'occurred_at']);
            $table->index(['subject_type', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_entries');
    }
};
