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
            $table->foreignId('classroom_id')->nullable()->after('id')->constrained('classrooms')->nullOnDelete();
            $table->string('citizen_id', 20)->nullable()->unique()->after('student_code');
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
            $table->dropColumn(['classroom_id', 'citizen_id']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
