<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSection extends Model
{
    protected $fillable = ['exam_id', 'title', 'instruction', 'total_score', 'sort_order'];

    protected function casts(): array
    {
        return [
            'total_score' => 'float',
            'sort_order' => 'integer',
        ];
    }
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class)
            ->orderByRaw('COALESCE(sort_order, id) ASC')
            ->orderBy('id', 'ASC');
    }
}
