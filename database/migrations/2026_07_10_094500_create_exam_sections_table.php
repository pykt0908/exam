<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id');
            $table->string('title')->default('ตอนที่ 1');
            $table->text('instruction')->nullable();
            $table->integer('sort_order')->default(1);
            $table->timestamps();

            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedBigInteger('exam_section_id')->nullable()->after('exam_id');
            $table->foreign('exam_section_id')->references('id')->on('exam_sections')->onDelete('set null');
            
            // Drop columns from the previous quick fix
            $table->dropColumn(['part', 'part_title']);
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['exam_section_id']);
            $table->dropColumn('exam_section_id');
            $table->integer('part')->default(1);
            $table->string('part_title')->nullable();
        });

        Schema::dropIfExists('exam_sections');
    }
};
