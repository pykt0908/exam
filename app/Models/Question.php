<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = ['exam_id', 'exam_section_id', 'type', 'question_text', 'question_image', 'score', 'essay_answer'];
    protected function casts(): array
    {
        return [
            'score' => 'float',
        ];
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function examSection()
    {
        return $this->belongsTo(ExamSection::class);
    }

    public function choices()
    {
        return $this->hasMany(Choice::class);
    }

    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class);
    }
}
