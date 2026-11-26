<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canvases', function (Blueprint $table) {
            $table->integer('structure_version')->default(0);
            $table->integer('layout_version')->default(0);
            $table->timestamp('layout_saved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('canvases', function (Blueprint $table) {
            $table->dropColumn(['structure_version', 'layout_version', 'layout_saved_at']);
        });
    }
};
