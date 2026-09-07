<?php

namespace Tests\Unit;

use App\Models\ExamApprovalLog;
use PHPUnit\Framework\TestCase;

class ExamApprovalLogTest extends TestCase
{
    public function test_approved_action_label_and_style()
    {
        $log = new ExamApprovalLog(['action' => 'approved']);
        $this->assertEquals('อนุมัติ', $log->action_label);
        $this->assertStringContainsString('#28a745', $log->action_text_style);
    }

    public function test_submitted_action_label_and_style()
    {
        $log = new ExamApprovalLog(['action' => 'submitted']);
        $this->assertEquals('รอ', $log->action_label);
        $this->assertStringContainsString('#d39e00', $log->action_text_style);
    }

    public function test_rejected_action_label_and_style()
    {
        $log = new ExamApprovalLog(['action' => 'rejected']);
        $this->assertEquals('ไม่อนุมัติ', $log->action_label);
        $this->assertStringContainsString('#dc3545', $log->action_text_style);
    }

    public function test_recalled_action_label_and_style()
    {
        $log = new ExamApprovalLog(['action' => 'recalled']);
        $this->assertEquals('ดึงกลับไปแก้', $log->action_label);
        $this->assertStringContainsString('#17a2b8', $log->action_text_style);
    }
}
