<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['audit_entries', 'finalised_years', 'snapshots'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->unsignedBigInteger('deleted_user_number')->nullable();
                $table->index('deleted_user_number', $name.'_deleted_user_number_index');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropIndex($name.'_deleted_user_number_index');
                $table->dropColumn('deleted_user_number');
            });
        }
    }
};
