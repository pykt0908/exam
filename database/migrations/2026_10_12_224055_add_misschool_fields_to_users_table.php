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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->index()->after('id');
            }
            if (!Schema::hasColumn('users', 'level_no')) {
                $table->string('level_no')->nullable()->after('citizen_id');
            }
            if (!Schema::hasColumn('users', 'depart_no')) {
                $table->string('depart_no')->nullable()->after('level_no');
            }
            if (!Schema::hasColumn('users', 'year')) {
                $table->string('year')->nullable()->after('depart_no');
            }
            if (!Schema::hasColumn('users', 'room')) {
                $table->string('room')->nullable()->after('year');
            }
            if (!Schema::hasColumn('users', 'mis_synced_at')) {
                $table->timestamp('mis_synced_at')->nullable()->after('room');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'level_no', 'depart_no', 'year', 'room', 'mis_synced_at']);
        });
    }
};
