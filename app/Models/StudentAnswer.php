<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAnswer extends Model
{
    protected $fillable = ['exam_attempt_id', 'question_id', 'choice_id', 'answer_text', 'is_correct', 'score_awarded', 'teacher_feedback'];
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'score_awarded' => 'float',
        ];
    }

    public function examAttempt()
    {
        return $this->belongsTo(ExamAttempt::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function choice()
    {
        return $this->belongsTo(Choice::class);
    }
}
