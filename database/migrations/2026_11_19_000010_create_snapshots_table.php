<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canvas_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->integer('fiscal_year');
            $table->text('remark')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->boolean('imported')->default(false);
            $table->integer('schema_version');
            $table->longText('content');
            $table->char('checksum', 64);

            $table->index(['canvas_id', 'archived_at', 'created_at']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshots');
    }
};
