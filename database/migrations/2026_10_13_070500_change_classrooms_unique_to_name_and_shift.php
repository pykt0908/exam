<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::table('classrooms', function (Blueprint $table) {
                $table->dropUnique('classrooms_name_unique');
            });
        } catch (\Throwable $e) {
            // Ignore if index already dropped or not found
        }

        try {
            Schema::table('classrooms', function (Blueprint $table) {
                $table->unique(['name', 'shift']);
            });
        } catch (\Throwable $e) {
            // Ignore if index already exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropUnique(['name', 'shift']);
            $table->unique('name');
        });
    }
};
