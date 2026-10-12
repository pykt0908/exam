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
     * ดึงรหัสภาค ('morning', 'afternoon') โดยทวิภาคีนับเป็นภาคเช้าด้วย
     */
    public function getShiftAttribute(): string
    {
        if (str_contains($this->name, 'บ่าย')) {
            return 'afternoon';
        }
        return 'morning';
    }

    /**
     * ดึงข้อความชื่อภาค ('เช้า', 'บ่าย') โดยทวิภาคีนับเป็นเช้า
     */
    public function getShiftTextAttribute(): string
    {
        if (str_contains($this->name, 'บ่าย')) {
            return 'บ่าย';
        }
        return 'เช้า';
    }

    /**
     * ดึงข้อความชื่อภาคภาษาไทย
     */
    public function getShiftLabelAttribute(): string
    {
        return $this->shift_text;
    }

    /**
     * ดึงข้อความภาคสำหรับแสดงผลในตาราง (ไม่ใช้ badge ตามความต้องการผู้ใช้)
     */
    public function getShiftBadgeAttribute(): string
    {
        return $this->shift_text;
    }

    /**
     * Scope กรองตามภาค
     */
    public function scopeShift($query, string $shift)
    {
        if ($shift === 'afternoon' || $shift === 'บ่าย') {
            return $query->where('name', 'LIKE', '%บ่าย%');
        }
        if ($shift === 'morning' || $shift === 'เช้า') {
            return $query->where('name', 'NOT LIKE', '%บ่าย%');
        }
        if ($shift === 'dual' || $shift === 'ทวิภาคี') {
            return $query->where('name', 'LIKE', '%ทวิภาคี%');
        }
        return $query;
    }
}
