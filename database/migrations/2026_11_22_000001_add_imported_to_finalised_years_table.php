<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finalised_years', function (Blueprint $table) {
            $table->boolean('imported')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('finalised_years', function (Blueprint $table) {
            $table->dropColumn('imported');
        });
    }
};
