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
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('shift')->nullable()->after('name');
        });

        // Backfill existing classrooms based on name content
        DB::table('classrooms')
            ->where('name', 'LIKE', '%บ่าย%')
            ->update(['shift' => 'afternoon']);

        DB::table('classrooms')
            ->where('name', 'NOT LIKE', '%บ่าย%')
            ->update(['shift' => 'morning']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('shift');
        });
    }
};
