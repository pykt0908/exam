<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ['name', 'description'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(User::class)->whereIn('role', ['admin', 'teacher']);
    }

    public function headTeacher(): ?User
    {
        if ($this->relationLoaded('teachers')) {
            return $this->teachers->first(fn($u) => $u->isDepartmentHead());
        }
        if ($this->relationLoaded('users')) {
            return $this->users->whereIn('role', ['admin', 'teacher'])->first(fn($u) => $u->isDepartmentHead());
        }
        return $this->teachers()->get()->first(fn($u) => $u->isDepartmentHead());
    }
}
