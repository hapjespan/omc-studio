<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('deleted_user_number')->nullable()->index();
        });
        Schema::table('finalised_years', function (Blueprint $table) {
            $table->unsignedBigInteger('deleted_user_number')->nullable()->index();
        });
        Schema::table('snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('deleted_user_number')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('audit_entries', function (Blueprint $table) {
            $table->dropColumn('deleted_user_number');
        });
        Schema::table('finalised_years', function (Blueprint $table) {
            $table->dropColumn('deleted_user_number');
        });
        Schema::table('snapshots', function (Blueprint $table) {
            $table->dropColumn('deleted_user_number');
        });
    }
};
