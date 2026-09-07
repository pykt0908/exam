<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class Subject extends Model
{
    protected $fillable = ['code', 'name', 'department_id'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function exams()
    {
        return $this->hasMany(Exam::class);
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'subject_user', 'subject_id', 'user_id')
            ->withPivot('is_eligible', 'ineligible_reason')
            ->withTimestamps()
            ->where('role', 'student');
    }

    public function teachers()
    {
        return $this->belongsToMany(User::class, 'subject_user', 'subject_id', 'user_id')
            ->withPivot('is_eligible', 'ineligible_reason')
            ->withTimestamps()
            ->whereIn('role', ['admin', 'teacher']);
    }
}
