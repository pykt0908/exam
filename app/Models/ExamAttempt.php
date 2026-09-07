<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAttempt extends Model
{
    protected $fillable = [
        'user_id', 'exam_id', 'attempt_number', 'started_at', 'completed_at', 
        'score', 'raw_score', 'total_raw_score', 
        'total_questions', 'is_passed', 'status', 'grading_status', 'focus_escape_count'
    ];
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'score' => 'float',
            'raw_score' => 'float',
            'total_raw_score' => 'float',
            'total_questions' => 'integer',
            'is_passed' => 'boolean',
        ];
    }

    public function getAttemptNumberAttribute($value): int
    {
        if (!empty($value)) {
            return (int)$value;
        }

        if ($this->exists && $this->user_id && $this->exam_id) {
            return (int)static::where('user_id', $this->user_id)
                ->where('exam_id', $this->exam_id)
                ->where('id', '<=', $this->id)
                ->count() ?: 1;
        }

        return 1;
    }

    public function isScaled(): bool
    {
        if ($this->total_raw_score === null || $this->total_raw_score <= 0) {
            return false;
        }
        $examTotal = (float)($this->exam->total_score ?? 0);
        return abs((float)$this->total_raw_score - $examTotal) > 0.001;
    }

    public function isPendingGrading(): bool
    {
        return $this->grading_status === 'pending_grading';
    }

    public function isGraded(): bool
    {
        return $this->grading_status === 'graded';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class);
    }
}
