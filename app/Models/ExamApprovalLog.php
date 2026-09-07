<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamApprovalLog extends Model
{
    protected $fillable = [
        'exam_id',
        'user_id',
        'stage',
        'action',
        'comment',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getStageLabelAttribute(): string
    {
        return match ($this->stage) {
            'submit' => 'ส่งขออนุมัติ',
            'dept_head' => 'หัวหน้าหมวด/แผนก',
            'evaluation_head' => 'หัวหน้างานวัดและประเมินผล',
            'academic_deputy' => 'รองผู้อำนวยการฝ่ายวิชาการ',
            'recall' => 'ดึงกลับไปแก้ไข',
            default => $this->stage,
        };
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'submitted', 'submit', 'pending', 'รอ' => 'รอ',
            'approved', 'approve', 'อนุมัติ' => 'อนุมัติ',
            'rejected', 'reject', 'ไม่อนุมัติ', 'ส่งกลับแก้ไข' => 'ไม่อนุมัติ',
            'recalled', 'recall', 'ดึงกลับไปแก้', 'ดึงกลับไปแก้ไข' => 'ดึงกลับไปแก้',
            default => $this->action,
        };
    }

    public function getActionTextStyleAttribute(): string
    {
        return match ($this->action) {
            'submitted', 'submit', 'pending', 'รอ' => 'color: #d39e00; font-weight: 600;',
            'approved', 'approve', 'อนุมัติ' => 'color: #28a745; font-weight: 600;',
            'rejected', 'reject', 'ไม่อนุมัติ', 'ส่งกลับแก้ไข' => 'color: #dc3545; font-weight: 600;',
            'recalled', 'recall', 'ดึงกลับไปแก้', 'ดึงกลับไปแก้ไข' => 'color: #17a2b8; font-weight: 600;',
            default => 'color: #6c757d; font-weight: 600;',
        };
    }

    public function getActionTextClassAttribute(): string
    {
        return match ($this->action) {
            'submitted', 'submit', 'pending', 'รอ' => 'text-warning',
            'approved', 'approve', 'อนุมัติ' => 'text-success',
            'rejected', 'reject', 'ไม่อนุมัติ', 'ส่งกลับแก้ไข' => 'text-danger',
            'recalled', 'recall', 'ดึงกลับไปแก้', 'ดึงกลับไปแก้ไข' => 'text-info',
            default => 'text-secondary',
        };
    }

    public function getActionBadgeAttribute(): string
    {
        return '<span style="' . $this->action_text_style . '">' . e($this->action_label) . '</span>';
    }
}
