<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['code', 'name'])]
class Subject extends Model
{
    public function exams()
    {
        return $this->hasMany(Exam::class);
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'subject_user', 'subject_id', 'user_id')->where('role', 'student');
    }

    public function teachers()
    {
        return $this->belongsToMany(User::class, 'subject_user', 'subject_id', 'user_id')->where('role', 'teacher');
    }
}
