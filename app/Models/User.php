<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'student_code', 'teacher_code', 'role', 'academic_role', 'department_id', 'classroom_id', 'citizen_id', 'photo', 'is_exam_eligible', 'ineligible_reason'];
    protected $hidden = ['password', 'remember_token'];
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_exam_eligible' => 'boolean',
        ];
    }

    public function examAttempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function examStudentOverrides()
    {
        return $this->hasMany(ExamStudentOverride::class);
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isTeacher()
    {
        return $this->role === 'teacher';
    }

    public function isStudent()
    {
        return $this->role === 'student';
    }

    public function isStaff()
    {
        return in_array($this->role, ['admin', 'teacher']);
    }

    public function getPhotoUrlAttribute(): string
    {
        if ($this->photo) {
            return asset('storage/' . $this->photo);
        }
        return asset('images/default-avatar.png');
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->photo_url;
    }

    public function getAcademicRolesAttribute(): array
    {
        if (empty($this->academic_role)) {
            return [];
        }
        if (is_array($this->academic_role)) {
            return $this->academic_role;
        }
        $decoded = json_decode($this->academic_role, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        return array_values(array_filter(array_map('trim', explode(',', $this->academic_role))));
    }

    public function isAcademicDeputy(): bool
    {
        return $this->isStaff() && in_array('academic_deputy', $this->academic_roles);
    }

    public function isEvaluationHead(): bool
    {
        return $this->isStaff() && in_array('evaluation_head', $this->academic_roles);
    }

    public function isDepartmentHead(): bool
    {
        return $this->isStaff() && in_array('department_head', $this->academic_roles);
    }

    public function getAcademicRoleLabelAttribute(): string
    {
        $labels = [];
        $map = [
            'academic_deputy' => 'รองผู้อำนวยการฝ่ายวิชาการ',
            'evaluation_head' => 'หัวหน้างานวัดและประเมินผล',
            'department_head' => 'หัวหน้าสาขา',
        ];

        foreach ($this->academic_roles as $r) {
            if (isset($map[$r])) {
                $labels[] = $map[$r];
            }
        }

        if (empty($labels)) {
            return $this->isAdmin() ? 'ผู้ดูแลระบบ' : 'ครูผู้สอน';
        }

        return implode(', ', $labels);
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id');
    }

    public function enrolledSubjects()
    {
        return $this->belongsToMany(Subject::class, 'subject_user', 'user_id', 'subject_id')
            ->withPivot('is_eligible', 'ineligible_reason')
            ->withTimestamps();
    }

    public function syncClassroomSubjects(): void
    {
        if (!$this->classroom_id || $this->role !== 'student') {
            return;
        }

        $subjectIds = \Illuminate\Support\Facades\DB::table('subject_user')
            ->join('users', 'subject_user.user_id', '=', 'users.id')
            ->where('users.classroom_id', $this->classroom_id)
            ->where('users.role', 'student')
            ->where('users.id', '!=', $this->id)
            ->distinct()
            ->pluck('subject_user.subject_id')
            ->toArray();

        if (!empty($subjectIds)) {
            $this->enrolledSubjects()->syncWithoutDetaching($subjectIds);
        }
    }

    public function getExamEligibility($subjectOrExam): array
    {
        // Check global user eligibility first (admin/finance level hold)
        if ($this->is_exam_eligible === false || (int)$this->is_exam_eligible === 0) {
            return [
                'eligible' => false,
                'reason' => $this->ineligible_reason ?: 'ระงับสิทธิ์สอบระดับผู้ใช้งาน (ติดต่อฝ่ายการเงิน/ทะเบียน)',
                'level' => 'global'
            ];
        }

        if ($subjectOrExam instanceof \App\Models\ExamAttempt) {
            $subjectOrExam = $subjectOrExam->exam ?? \App\Models\Exam::find($subjectOrExam->exam_id);
        }

        $subjectId = null;
        if ($subjectOrExam instanceof \App\Models\Exam) {
            $subjectId = $subjectOrExam->subject_id;
        } elseif ($subjectOrExam instanceof \App\Models\Subject) {
            $subjectId = $subjectOrExam->id;
        } elseif (is_numeric($subjectOrExam)) {
            // Check if ID matches an Exam first
            $exam = \App\Models\Exam::find($subjectOrExam);
            if ($exam && $exam->subject_id) {
                $subjectId = $exam->subject_id;
            } else {
                $subjectId = (int)$subjectOrExam;
            }
        }

        // Check subject-specific enrollment eligibility
        if (!$this->relationLoaded('enrolledSubjects')) {
            $this->load('enrolledSubjects');
        }
        $enrollment = $this->enrolledSubjects->firstWhere('id', $subjectId);
        if (!$enrollment) {
            return [
                'eligible' => false,
                'reason' => 'ยังไม่ได้ลงทะเบียนในรายวิชานี้',
                'level' => 'subject'
            ];
        }

        if ($enrollment->pivot && ($enrollment->pivot->is_eligible === false || (int)$enrollment->pivot->is_eligible === 0)) {
            return [
                'eligible' => false,
                'reason' => $enrollment->pivot->ineligible_reason ?: 'ระงับสิทธิ์สอบในรายวิชานี้ (ติดต่ออาจารย์ผู้สอน)',
                'level' => 'subject'
            ];
        }

        return [
            'eligible' => true,
            'reason' => null,
            'level' => 'none'
        ];
    }
}
