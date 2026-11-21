<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('two_factor_recovery_codes', fn (Blueprint $table) => $table->index('user_id'));
        Schema::table('undo_records', fn (Blueprint $table) => $table->index('user_id'));
    }

    public function down(): void
    {
        Schema::table('two_factor_recovery_codes', fn (Blueprint $table) => $table->dropIndex(['user_id']));
        Schema::table('undo_records', fn (Blueprint $table) => $table->dropIndex(['user_id']));
    }
};
