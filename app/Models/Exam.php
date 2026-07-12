<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['subject_id', 'title', 'description', 'duration_minutes', 'passing_percentage', 'total_score', 'is_active', 'max_attempts', 'shuffle_questions', 'shuffle_choices', 'force_fullscreen', 'max_focus_escapes', 'passcode', 'show_score', 'show_answers', 'allow_review'])]
class Exam extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'duration_minutes' => 'integer',
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
        ];
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
}
