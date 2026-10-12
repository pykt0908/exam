<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'classroom_id');
    }

    /**
     * ดึงรหัสรอบเวลา ('morning', 'afternoon', 'dual')
     */
    public function getShiftAttribute(): string
    {
        if (str_contains($this->name, 'บ่าย')) {
            return 'afternoon';
        }
        if (str_contains($this->name, 'ทวิภาคี')) {
            return 'dual';
        }
        return 'morning';
    }

    /**
     * ดึงข้อความชื่อรอบเวลาภาษาไทย
     */
    public function getShiftLabelAttribute(): string
    {
        if (str_contains($this->name, 'บ่าย')) {
            return 'ภาคบ่าย';
        }
        if (str_contains($this->name, 'ทวิภาคี')) {
            return 'ภาคทวิภาคี';
        }
        return 'ภาคเช้า';
    }

    /**
     * ดึง HTML Badge สำหรับแสดงผล
     */
    public function getShiftBadgeAttribute(): string
    {
        if (str_contains($this->name, 'บ่าย')) {
            return '<span class="badge badge-warning text-dark"><i class="fas fa-sun mr-1"></i>ภาคบ่าย</span>';
        }
        if (str_contains($this->name, 'ทวิภาคี')) {
            return '<span class="badge badge-info"><i class="fas fa-handshake mr-1"></i>ภาคทวิภาคี</span>';
        }
        return '<span class="badge badge-success"><i class="fas fa-cloud-sun mr-1"></i>ภาคเช้า</span>';
    }

    /**
     * Scope กรองตามรอบเวลา
     */
    public function scopeShift($query, string $shift)
    {
        if ($shift === 'afternoon' || $shift === 'บ่าย') {
            return $query->where('name', 'LIKE', '%บ่าย%');
        }
        if ($shift === 'dual' || $shift === 'ทวิภาคี') {
            return $query->where('name', 'LIKE', '%ทวิภาคี%');
        }
        if ($shift === 'morning' || $shift === 'เช้า') {
            return $query->where('name', 'LIKE', '%เช้า%');
        }
        return $query;
    }
}
