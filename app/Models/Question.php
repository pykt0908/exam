<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['exam_id', 'exam_section_id', 'type', 'question_text', 'question_image', 'score', 'essay_answer'])]
class Question extends Model
{
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
