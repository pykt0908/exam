<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'shift',
    ];

    protected static function booted()
    {
        static::saving(function ($classroom) {
            if (empty($classroom->shift)) {
                $classroom->shift = str_contains($classroom->name, 'บ่าย') ? 'afternoon' : 'morning';
            }
        });
    }

    public function users()
    {
        return $this->hasMany(User::class, 'classroom_id');
    }

    /**
     * ดึงรหัสภาค ('morning', 'afternoon') โดยทวิภาคีนับเป็นภาคเช้าด้วย
     */
    public function getShiftAttribute(): string
    {
        $shift = $this->attributes['shift'] ?? null;
        if (!empty($shift)) {
            return $shift;
        }
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
        return $this->shift === 'afternoon' ? 'บ่าย' : 'เช้า';
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
     * ดึงระดับการศึกษา ('ปวช.', 'ปวส.')
     */
    public function getLevelAttribute(): string
    {
        if (str_contains($this->name, 'ปวส')) {
            return 'ปวส.';
        }
        if (str_contains($this->name, 'ปวช')) {
            return 'ปวช.';
        }
        return '';
    }

    /**
     * ดึงชั้นปี ('1', '2', '3')
     */
    public function getYearAttribute(): string
    {
        if (preg_match('/(?:ปวช\.|ปวส\.(?:\s*พิเศษ)?)\s*(\d+)/u', $this->name, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * ดึงข้อความชั้นปี ('ปี 1', 'ปี 2', 'ปี 3')
     */
    public function getYearLabelAttribute(): string
    {
        return $this->year ? 'ปี ' . $this->year : '';
    }

    /**
     * Scope กรองตามภาค
     */
    public function scopeShift($query, string $shift)
    {
        if ($shift === 'afternoon' || $shift === 'บ่าย') {
            return $query->where(function($q) {
                $q->where('shift', 'afternoon')
                  ->orWhere(function($sub) {
                      $sub->whereNull('shift')->where('name', 'LIKE', '%บ่าย%');
                  });
            });
        }
        if ($shift === 'morning' || $shift === 'เช้า') {
            return $query->where(function($q) {
                $q->where('shift', 'morning')
                  ->orWhere(function($sub) {
                      $sub->whereNull('shift')->where('name', 'NOT LIKE', '%บ่าย%');
                  });
            });
        }
        if ($shift === 'dual' || $shift === 'ทวิภาคี') {
            return $query->where('name', 'LIKE', '%ทวิภาคี%');
        }
        return $query;
    }

    /**
     * Scope กรองตามระดับชั้น (ปวช./ปวส.)
     */
    public function scopeLevel($query, string $level)
    {
        if ($level === 'ปวช.' || $level === 'ปวช') {
            return $query->where('name', 'LIKE', '%ปวช%');
        }
        if ($level === 'ปวส.' || $level === 'ปวส') {
            return $query->where('name', 'LIKE', '%ปวส%');
        }
        return $query;
    }

    /**
     * Scope กรองตามชั้นปี (1, 2, 3)
     */
    public function scopeYear($query, string $year)
    {
        if (!empty($year)) {
            return $query->where(function($q) use ($year) {
                $q->where('name', 'LIKE', "%ปวช.{$year}%")
                  ->orWhere('name', 'LIKE', "%ปวส.{$year}%")
                  ->orWhere('name', 'LIKE', "%ปวส. พิเศษ{$year}%")
                  ->orWhere('name', 'LIKE', "%ปวส.พิเศษ{$year}%");
            });
        }
        return $query;
    }
}
