<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamStudentOverride extends Model
{
    protected $fillable = [
        'exam_id',
        'user_id',
        'extra_attempts',
        'reason',
        'granted_by',
    ];

    protected function casts(): array
    {
        return [
            'extra_attempts' => 'integer',
        ];
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function granter()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
