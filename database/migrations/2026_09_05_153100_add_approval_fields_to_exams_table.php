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
        Schema::table('exams', function (Blueprint $table) {
            if (!Schema::hasColumn('exams', 'approval_status')) {
                $table->string('approval_status', 30)->default('draft')->after('is_active');
            }
            if (!Schema::hasColumn('exams', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('approval_status');
            }
            if (!Schema::hasColumn('exams', 'dept_approved_by')) {
                $table->foreignId('dept_approved_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('exams', 'dept_approved_at')) {
                $table->timestamp('dept_approved_at')->nullable()->after('dept_approved_by');
            }
            if (!Schema::hasColumn('exams', 'dept_feedback')) {
                $table->text('dept_feedback')->nullable()->after('dept_approved_at');
            }
            if (!Schema::hasColumn('exams', 'eval_approved_by')) {
                $table->foreignId('eval_approved_by')->nullable()->after('dept_feedback')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('exams', 'eval_approved_at')) {
                $table->timestamp('eval_approved_at')->nullable()->after('eval_approved_by');
            }
            if (!Schema::hasColumn('exams', 'eval_feedback')) {
                $table->text('eval_feedback')->nullable()->after('eval_approved_at');
            }
            if (!Schema::hasColumn('exams', 'academic_approved_by')) {
                $table->foreignId('academic_approved_by')->nullable()->after('eval_feedback')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('exams', 'academic_approved_at')) {
                $table->timestamp('academic_approved_at')->nullable()->after('academic_approved_by');
            }
            if (!Schema::hasColumn('exams', 'academic_feedback')) {
                $table->text('academic_feedback')->nullable()->after('academic_approved_at');
            }
            if (!Schema::hasColumn('exams', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('academic_feedback')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('exams', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            if (!Schema::hasColumn('exams', 'rejection_note')) {
                $table->text('rejection_note')->nullable()->after('rejected_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe down
    }
};
