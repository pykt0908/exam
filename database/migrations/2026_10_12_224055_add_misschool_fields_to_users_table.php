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
            $table->string('username')->nullable()->index()->after('id');
            $table->string('level_no')->nullable()->after('citizen_id');
            $table->string('depart_no')->nullable()->after('level_no');
            $table->string('year')->nullable()->after('depart_no');
            $table->string('room')->nullable()->after('year');
            $table->timestamp('mis_synced_at')->nullable()->after('room');
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
