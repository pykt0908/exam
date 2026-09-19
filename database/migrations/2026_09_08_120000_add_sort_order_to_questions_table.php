<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('score');
        });

        // Initialize sort_order for existing questions to match their id
        DB::statement('UPDATE questions SET sort_order = id WHERE sort_order = 0 OR sort_order IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
