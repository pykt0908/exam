<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    protected $fillable = [
        'subject_id', 'title', 'description', 'duration_minutes', 'starts_at', 'ends_at',
        'passing_percentage', 'total_score', 'is_active', 'max_attempts', 'shuffle_questions',
        'shuffle_choices', 'force_fullscreen', 'max_focus_escapes', 'passcode', 'show_score',
        'show_answers', 'allow_review',
        'approval_status', 'submitted_at',
        'dept_approved_by', 'dept_approved_at', 'dept_feedback',
        'eval_approved_by', 'eval_approved_at', 'eval_feedback',
        'academic_approved_by', 'academic_approved_at', 'academic_feedback',
        'rejected_by', 'rejected_at', 'rejection_note', 'rejected_stage'
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'duration_minutes' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'passing_percentage' => 'integer',
            'total_score' => 'float',
            'shuffle_questions' => 'boolean',
            'shuffle_choices' => 'boolean',
            'force_fullscreen' => 'boolean',
            'max_attempts' => 'integer',
            'max_focus_escapes' => 'integer',
            'show_score' => 'boolean',
            'show_answers' => 'boolean',
            'allow_review' => 'boolean',
            'submitted_at' => 'datetime',
            'dept_approved_at' => 'datetime',
            'eval_approved_at' => 'datetime',
            'academic_approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function isUpcoming(): bool
    {
        return $this->starts_at && now()->lt($this->starts_at);
    }

    public function isExpired(): bool
    {
        return $this->ends_at && now()->gt($this->ends_at);
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isDraft(): bool
    {
        return $this->approval_status === 'draft';
    }

    public function isRejected(): bool
    {
        return $this->approval_status === 'rejected';
    }

    public function isPending(): bool
    {
        return in_array($this->approval_status, ['pending_dept', 'pending_eval', 'pending_academic']);
    }

    public function canBeEdited(): bool
    {
        return in_array($this->approval_status, ['draft', 'rejected']);
    }

    public function isAvailableNow(): bool
    {
        if (!$this->is_active || !$this->isApproved()) {
            return false;
        }
        if ($this->isUpcoming() || $this->isExpired()) {
            return false;
        }
        return true;
    }

    public function getApprovalStatusLabelAttribute(): string
    {
        return match ($this->approval_status) {
            'draft' => 'ฉบับร่าง',
            'pending_dept' => 'รอหัวหน้าหมวดอนุมัติ',
            'pending_eval' => 'รอหัวหน้างานวัดผลอนุมัติ',
            'pending_academic' => 'รอรองฝ่ายวิชาการอนุมัติ',
            'approved' => 'อนุมัติเรียบร้อย',
            'rejected' => 'ส่งกลับแก้ไข',
            default => $this->approval_status,
        };
    }

    public function getApprovalStatusBadgeAttribute(): string
    {
        return match ($this->approval_status) {
            'draft' => '<span class="badge badge-secondary px-2 py-1 font-weight-normal"><i class="fas fa-pencil-alt mr-1"></i>ฉบับร่าง</span>',
            'pending_dept' => '<span class="badge badge-warning text-dark px-2 py-1 font-weight-normal"><i class="fas fa-clock mr-1"></i>รอหัวหน้าหมวด</span>',
            'pending_eval' => '<span class="badge badge-primary px-2 py-1 font-weight-normal"><i class="fas fa-clock mr-1"></i>รอหัวหน้างานวัดผล</span>',
            'pending_academic' => '<span class="badge badge-info px-2 py-1 font-weight-normal"><i class="fas fa-clock mr-1"></i>รอรองฝ่ายวิชาการ</span>',
            'approved' => '<span class="badge badge-success px-2 py-1 font-weight-normal"><i class="fas fa-check-circle mr-1"></i>อนุมัติแล้ว</span>',
            'rejected' => '<span class="badge badge-danger px-2 py-1 font-weight-normal"><i class="fas fa-exclamation-circle mr-1"></i>ส่งกลับแก้ไข</span>',
            default => '<span class="badge badge-light px-2 py-1 font-weight-normal">' . e($this->approval_status) . '</span>',
        };
    }

    public function deptApprover()
    {
        return $this->belongsTo(User::class, 'dept_approved_by');
    }

    public function evalApprover()
    {
        return $this->belongsTo(User::class, 'eval_approved_by');
    }

    public function academicApprover()
    {
        return $this->belongsTo(User::class, 'academic_approved_by');
    }

    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function approvalLogs()
    {
        return $this->hasMany(ExamApprovalLog::class)->orderBy('created_at', 'desc');
    }

    public function getCreatorNameAttribute(): string
    {
        // 1. First check if there is a submit action in approval logs
        $submitLog = $this->approvalLogs->firstWhere('action', 'submitted');
        if ($submitLog && $submitLog->user) {
            return $submitLog->user->name;
        }

        // 2. Check teachers assigned to the subject
        if ($this->subject && $this->subject->teachers->isNotEmpty()) {
            return $this->subject->teachers->pluck('name')->implode(', ');
        }

        return '-';
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function sections()
    {
        return $this->hasMany(ExamSection::class)->orderBy('sort_order')->orderBy('id');
    }

    public function examAttempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function hasEssayQuestions(): bool
    {
        return $this->questions()->where('type', 'essay')->exists();
    }

    public function pendingGradingAttemptsCount(): int
    {
        return $this->examAttempts()
            ->where('status', 'completed')
            ->where('grading_status', 'pending_grading')
            ->count();
    }

    public function studentOverrides()
    {
        return $this->hasMany(ExamStudentOverride::class);
    }

    public function getAllowedAttemptsForUser(int $userId): int
    {
        $baseAttempts = (int) ($this->max_attempts ?? 1);
        $extraAttempts = $this->getExtraAttemptsForUser($userId);

        return $baseAttempts + $extraAttempts;
    }

    public function getExtraAttemptsForUser(int $userId): int
    {
        if ($this->relationLoaded('studentOverrides')) {
            $override = $this->studentOverrides->firstWhere('user_id', $userId);
        } else {
            $override = $this->studentOverrides()->where('user_id', $userId)->first();
        }

        return $override ? (int) $override->extra_attempts : 0;
    }
}

