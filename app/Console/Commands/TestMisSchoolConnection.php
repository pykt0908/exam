<?php

namespace App\Console\Commands;

use App\Services\MisSchoolService;
use Illuminate\Console\Command;

class TestMisSchoolConnection extends Command
{
    protected $signature = 'misschool:test 
                            {--username= : รหัสนักศึกษา หรือ ชื่อผู้ใช้อาจารย์ที่ต้องการทดสอบ}
                            {--password= : รหัสผ่าน (เลขบัตรประชาชน หรือ รหัสผ่านอาจารย์)}
                            {--type=auto : ชนิดผู้ใช้ที่ต้องการทดสอบ (student, teacher, auto)}';

    protected $description = 'ทดสอบการเชื่อมต่อระบบ MisSchool (Bridge API หรือ SQL Server)';

    public function handle(MisSchoolService $misSchool)
    {
        $this->info('==========================================');
        $this->info('  ทดสอบการเชื่อมต่อระบบ MisSchool');
        $this->info('==========================================');

        $mode = $misSchool->getMode();
        $this->line("สถานะการเปิดใช้งาน: " . (config('misschool.enabled') ? '<fg=green>เปิดใช้งาน (Enabled)</>' : '<fg=red>ปิดใช้งาน (Disabled)</>'));
        $this->line("โหมดการเชื่อมต่อ (Mode): <fg=yellow>{$mode}</>");

        if ($mode === 'bridge') {
            $this->line("Bridge URL: " . config('misschool.bridge.url'));
            $this->line("Bridge Timeout: " . config('misschool.bridge.timeout') . " วินาที");
        } else {
            $this->line("SQL Server Host: " . config('misschool.sqlsrv.host') . ":" . config('misschool.sqlsrv.port'));
            $this->line("Student DB: " . config('misschool.sqlsrv.database_student'));
            $this->line("Teacher DB: " . config('misschool.sqlsrv.database_teacher'));
        }

        $this->newLine();
        $this->info('1. ทดสอบ Health Check...');
        $health = $misSchool->checkHealth();

        if (!empty($health['success'])) {
            $this->info('✔ เชื่อมต่อสำเร็จ: ' . ($health['message'] ?? 'OK'));
        } else {
            $this->error('✖ เชื่อมต่อล้มเหลว: ' . ($health['message'] ?? 'Unknown Error'));
        }

        $username = $this->option('username');
        $password = $this->option('password');
        $type = $this->option('type');

        if ($username && $password) {
            $this->newLine();
            $this->info("2. ทดสอบตรวจสอบบัญชีผู้ใช้ [{$username}]...");

            if ($type === 'student') {
                $result = $misSchool->verifyStudent($username, $password);
            } elseif ($type === 'teacher') {
                $result = $misSchool->verifyTeacher($username, $password);
            } else {
                $verified = $misSchool->verifyUser($username, $password);
                $result = $verified ? ['success' => true, 'data' => $verified['data'], 'type' => $verified['type']] : ['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้'];
            }

            if (!empty($result['success'])) {
                $this->info('✔ ตรวจสอบผู้ใช้สำเร็จ!');
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->error('✖ ตรวจสอบผู้ใช้ไม่สำเร็จ: ' . ($result['message'] ?? 'Invalid credentials'));
            }
        }

        $this->newLine();
        return Command::SUCCESS;
    }
}
