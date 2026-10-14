<?php

namespace App\Services;

use App\Models\User;
use App\Models\Classroom;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PDO;
use Exception;
use Throwable;

class MisSchoolService
{
    protected string $mode;
    protected array $bridgeConfig;
    protected array $sqlsrvConfig;

    public function __construct()
    {
        $this->mode = config('misschool.connection_mode', 'bridge');
        $this->bridgeConfig = config('misschool.bridge', []);
        $this->sqlsrvConfig = config('misschool.sqlsrv', []);
    }

    /**
     * ดึงโหมดการเชื่อมต่อปัจจุบัน ('bridge' หรือ 'sqlsrv')
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * ตรวจสอบสถานะการเชื่อมต่อ (Health Check)
     */
    public function checkHealth(): array
    {
        if ($this->mode === 'sqlsrv') {
            return $this->checkSqlSrvHealth();
        }

        $bridgeResult = $this->checkBridgeHealth();
        if (!empty($bridgeResult['success'])) {
            return $bridgeResult;
        }

        // หาก Bridge ล้มเหลวและเครื่องมี tsql ให้ลองทดสอบผ่าน tsql
        if ($this->isTsqlAvailable()) {
            $tsqlHealth = $this->checkTsqlHealth();
            if (!empty($tsqlHealth['success'])) {
                $tsqlHealth['message'] .= ' (โหมดสำรอง Direct SQL Server / tsql)';
                return $tsqlHealth;
            }
        }

        return $bridgeResult;
    }

    /**
     * ตรวจสอบสิทธิ์นักเรียน (Verify Student)
     * Username: รหัสนักศึกษา (StdNo)
     * Password: เลขบัตรประจำตัวประชาชน 13 หลัก (IdNo)
     */
    public function verifyStudent(string $username, string $password): array
    {
        $username = trim($username);
        $password = trim($password);

        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'กรุณากรอกรหัสนักศึกษาและเลขบัตรประชาชน'];
        }

        if ($this->mode === 'sqlsrv') {
            return $this->verifyStudentSqlSrv($username, $password);
        }

        // 1. ลองเรียกผ่าน Bridge API ก่อน
        $bridgeRes = $this->verifyStudentBridge($username, $password);
        if (!empty($bridgeRes['success']) || (isset($bridgeRes['message']) && str_contains($bridgeRes['message'], 'ไม่ถูกต้อง'))) {
            return $bridgeRes;
        }

        // 2. หาก Bridge API มีปัญหา ให้ใช้ tsql สำรองอัตโนมัติ
        if ($this->isTsqlAvailable()) {
            $tsqlRes = $this->verifyStudentTsql($username, $password);
            if (!empty($tsqlRes['success']) || (isset($tsqlRes['message']) && str_contains($tsqlRes['message'], 'ไม่ถูกต้อง'))) {
                return $tsqlRes;
            }
        }

        return $bridgeRes;
    }

    /**
     * ตรวจสอบสิทธิ์อาจารย์ (Verify Teacher)
     * Username: ชื่อผู้ใช้ (UserName) หรือ รหัสประจำตัวครู (TeacherNo)
     * Password: รหัสผ่านอาจารย์
     */
    public function verifyTeacher(string $username, string $password): array
    {
        $username = trim($username);
        $password = trim($password);

        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่านอาจารย์'];
        }

        if ($this->mode === 'sqlsrv') {
            return $this->verifyTeacherSqlSrv($username, $password);
        }

        // 1. ลองเรียกผ่าน Bridge API ก่อน
        $bridgeRes = $this->verifyTeacherBridge($username, $password);
        if (!empty($bridgeRes['success']) || (isset($bridgeRes['message']) && str_contains($bridgeRes['message'], 'ไม่ถูกต้อง'))) {
            return $bridgeRes;
        }

        // 2. หาก Bridge API มีปัญหา ให้ใช้ tsql สำรองอัตโนมัติ
        if ($this->isTsqlAvailable()) {
            $tsqlRes = $this->verifyTeacherTsql($username, $password);
            if (!empty($tsqlRes['success']) || (isset($tsqlRes['message']) && str_contains($tsqlRes['message'], 'ไม่ถูกต้อง'))) {
                return $tsqlRes;
            }
        }

        return $bridgeRes;
    }

    /**
     * ตรวจสอบผู้ใช้ทั่วไป (ตรวจสอบนักเรียนก่อน หากไม่ใช่ค่อยตรวจสอบอาจารย์)
     */
    public function verifyUser(string $identifier, string $password): ?array
    {
        $identifier = trim($identifier);
        $password = trim($password);

        if (empty($identifier) || empty($password)) {
            return null;
        }

        // 1. ลองตรวจสอบในฐานข้อมูลนักเรียนก่อน
        $studentResult = $this->verifyStudent($identifier, $password);
        if (!empty($studentResult['success']) && !empty($studentResult['data'])) {
            return [
                'type' => 'student',
                'data' => $studentResult['data'],
            ];
        }

        // 2. หากไม่ใช่นักเรียน ให้ลองตรวจสอบในฐานข้อมูลอาจารย์
        $teacherResult = $this->verifyTeacher($identifier, $password);
        if (!empty($teacherResult['success']) && !empty($teacherResult['data'])) {
            return [
                'type' => 'teacher',
                'data' => $teacherResult['data'],
            ];
        }

        return null;
    }

    /**
     * ซิงค์ข้อมูลผู้ใช้จาก MisSchool เข้าสู่ฐานข้อมูลระบบสอบ และคืนค่า Model User
     * ทุกครั้งที่ล็อกอินผ่าน MisSchool สำเร็จ จะอัปเดตรหัสผ่านในระบบสอบให้ตรงกันเสมอ
     */
    public function syncUser(array $verificationResult, string $password): User
    {
        $type = $verificationResult['type'] ?? 'student';
        $data = $verificationResult['data'] ?? [];

        if ($type === 'student') {
            return $this->syncStudentUser($data, $password);
        }

        return $this->syncTeacherUser($data, $password);
    }

    /**
     * ตรวจสอบและซิงค์ผู้ใช้ในคำสั่งเดียว (ใช้สำหรับระบบ Login)
     */
    public function authenticateAndSync(string $identifier, string $password): ?User
    {
        try {
            $verified = $this->verifyUser($identifier, $password);
            if (!$verified) {
                return null;
            }

            return $this->syncUser($verified, $password);
        } catch (Exception $e) {
            Log::error('MisSchool authenticateAndSync error: ' . $e->getMessage(), [
                'identifier' => $identifier,
                'exception' => $e,
            ]);
            return null;
        }
    }

    // =========================================================================
    // Sync Helpers
    // =========================================================================

    /**
     * ค้นหาห้องเรียนที่ตรงกันอย่างแม่นยำ (จับคู่ระดับชั้น, ห้องเรียน, แผนกวิชา, และรอบเช้า/บ่าย)
     */
    public function matchClassroom(?string $levelNo, ?string $departNo, ?string $year, ?string $room, ?string $shift = null): ?Classroom
    {
        if (empty($year) || empty($room) || $year === '-' || $room === '-') {
            return null;
        }

        $paddedDept = str_pad((string)$departNo, 2, '0', STR_PAD_LEFT);
        $deptKeywords = [
            '01' => ['บัญชี', 'AC'],
            '02' => ['การตลาด', 'MK'],
            '03' => ['คอมพิวเตอร์', 'ดิจิทัล', 'CT'],
            '04' => ['เครื่องกล', 'ช่างกล', 'MT'],
            '05' => ['โลจิสติกส์', 'LT'],
            '06' => ['ไอที', 'IT', 'ดิจิทัล'],
            '07' => ['ค้าปลีก', 'RB'],
            '08' => ['เครื่องกล', 'ช่างยนต์', 'MT'],
            '09' => ['ไฟฟ้า', 'ET'],
            '10' => ['เครื่องกล', 'เทคนิคเครื่องกล', 'DMT'],
            '11' => ['ไฟฟ้า', 'DET'],
            '12' => ['โลจิสติกส์', 'LT'],
            '13' => ['เทคโนโลยีธุรกิจดิจิทัล', 'ดิจิทัล', 'คอมพิวเตอร์', 'TBD'],
            '14' => ['โลจิสติกส์', 'LTS', 'LT'],
            '15' => ['ยานยนต์', 'เครื่องกล', 'DMT'],
            '16' => ['ไฟฟ้า', 'ET'],
            '17' => ['ไฟฟ้า', 'ET'],
            '18' => ['เมคาทรอนิกส์', 'หุ่นยนต์', 'ME'],
        ];
        $keywords = $deptKeywords[$paddedDept] ?? [];
        $targetRoom = "{$year}/{$room}";

        // แยกและแปลงรอบ (Shift): รอบ 1 = เช้า (morning), รอบ 7 = ทวิภาคี (morning ในระบบสอบ), รอบ 6 = บ่าย (afternoon)
        $normalizedShift = null;
        if (!empty($shift)) {
            $s = (string)$shift;
            if ($s === '1' || $s === '7' || str_contains($s, 'เช้า') || str_contains($s, 'ทวิภาคี') || $s === 'morning') {
                $normalizedShift = 'morning';
            } elseif ($s === '6' || str_contains($s, 'บ่าย') || $s === 'afternoon') {
                $normalizedShift = 'afternoon';
            }
        } elseif ((string)$levelNo === '3') {
            $normalizedShift = 'afternoon';
        }

        $query = Classroom::query();

        // 1. กรองตามระดับชั้น
        if ((string)$levelNo === '3') {
            $query->where('name', 'LIKE', "ปวส. พิเศษ{$targetRoom}%");
        } elseif ((string)$levelNo === '2') {
            $query->where('name', 'LIKE', "ปวส.{$targetRoom}%")
                  ->where('name', 'NOT LIKE', 'ปวส. พิเศษ%');
        } else {
            $query->where('name', 'LIKE', "ปวช.{$targetRoom}%");
        }

        // 2. กรองตามรอบ (เช้า / บ่าย) หากมีการระบุ
        if ($normalizedShift) {
            $query->where('shift', $normalizedShift);
        }

        // 3. กรองตามแผนกวิชาเพื่อป้องกันการสับสนระหว่างสาขาที่มีห้องซ้ำกัน
        if (!empty($keywords)) {
            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->orWhere('name', 'LIKE', "%{$kw}%");
                }
            });
        }

        $matched = $query->first();

        // Fallback 1: หากยังไม่พบและมีแผนกวิชา ให้ค้นหาตามแผนกเดิมโดยปลดล็อครอบ เพื่อป้องกันการข้ามไปแผนกอื่น
        if (!$matched && !empty($keywords)) {
            $prefix = ((string)$levelNo === '3') ? 'ปวส. พิเศษ' : (((string)$levelNo === '2') ? 'ปวส.' : 'ปวช.');
            $matched = Classroom::where('name', 'LIKE', "%{$prefix}{$targetRoom}%")
                ->where(function ($q) use ($keywords) {
                    foreach ($keywords as $kw) {
                        $q->orWhere('name', 'LIKE', "%{$kw}%");
                    }
                })->first();
        }

        // Fallback 2: ตรวจสอบห้องตามระดับชั้นและเลขห้อง
        if (!$matched) {
            $prefix = ((string)$levelNo === '3') ? 'ปวส. พิเศษ' : (((string)$levelNo === '2') ? 'ปวส.' : 'ปวช.');
            $fallbackQuery = Classroom::where('name', 'LIKE', "%{$prefix}{$targetRoom}%");
            if ($normalizedShift) {
                $fallbackQuery->where('shift', $normalizedShift);
            }
            $candidates = $fallbackQuery->get();
            if ($candidates->count() === 1) {
                // หากมีเพียงห้องเดียวที่ตรงกับระดับชั้นและเลขห้อง สามารถจับคู่ได้โดยตรง (ปลอดภัย)
                $matched = $candidates->first();
            } elseif ($candidates->isNotEmpty() && !empty($keywords)) {
                $matched = $candidates->first(function ($c) use ($keywords) {
                    foreach ($keywords as $kw) {
                        if (str_contains($c->name, $kw)) return true;
                    }
                    return false;
                });
            } elseif ($candidates->isNotEmpty() && empty($keywords)) {
                $matched = $candidates->first();
            }
        }

        return $matched;
    }

    /**
     * ค้นหาแผนกวิชาตามรหัสแผนกของ MisSchool
     */
    public function matchDepartment(?string $departNo): ?Department
    {
        if (empty($departNo)) {
            return null;
        }

        $paddedDept = str_pad((string)$departNo, 2, '0', STR_PAD_LEFT);
        $deptIdMap = [
            '01' => 2, // การบัญชี
            '02' => 4, // การตลาด
            '03' => 3, // คอมพิวเตอร์
            '04' => 7, // เทคนิคเครื่องกล
            '05' => 5, // การจัดการโลจิสติกส์
            '06' => 3, // เทคโนโลยีสารสนเทศ
            '07' => 4, // ค้าปลีก
            '08' => 7, // ช่างยนต์ / เครื่องกล
            '09' => 6, // ไฟฟ้า
            '10' => 7, // เทคนิคเครื่องกล
            '11' => 6, // ไฟฟ้า
            '12' => 5, // โลจิสติกส์
            '13' => 3, // เทคโนโลยีธุรกิจดิจิทัล
            '14' => 5, // การจัดการโลจิสติกส์และซัพพลายเชน
            '15' => 7, // เทคนิคยานยนต์
            '16' => 6, // ช่างไฟฟ้า
            '17' => 6, // ไฟฟ้า
            '18' => 8, // เมคาทรอนิกส์และหุ่นยนต์
        ];

        if (isset($deptIdMap[$paddedDept])) {
            $dept = Department::find($deptIdMap[$paddedDept]);
            if ($dept) {
                return $dept;
            }
        }

        return Department::where('id', $departNo)
            ->orWhere('name', 'LIKE', "%{$departNo}%")
            ->first();
    }

    /**
     * ค้นหาแผนกวิชาของอาจารย์ตามรหัสหมวด/ฝ่ายในตาราง tblDepartTeacher ของ MisSchool
     */
    public function matchTeacherDepartment(?string $departNo): ?Department
    {
        if (empty($departNo)) {
            return null;
        }

        $padded = str_pad(trim((string)$departNo), 2, '0', STR_PAD_LEFT);

        // แผนที่รหัสหมวดวิชา (tblDepartTeacher ใน Personal db ของ MisSchool) กับตาราง departments ในระบบสอบ
        // 09: หมวดการบัญชี -> 2 (สาขาวิชาการบัญชี)
        // 10: หมวดการตลาด -> 4 (สาขาวิชาการตลาด)
        // 11: หมวดการจัดการโลจิสติกส์ -> 5 (สาขาวิชาการจัดการโลจิสติกส์และซัพพลายเชน)
        // 12: หมวดสามัญ -> 1 (สาขาวิชาสามัญ)
        // 14: หมวดคอมพิวเตอร์ธุรกิจ -> 3 (สาขาเทคโนโลยีธุรกิจดิจิทัล)
        $deptIdMap = [
            '09' => 2,
            '10' => 4,
            '11' => 5,
            '12' => 1,
            '14' => 3,
        ];

        if (isset($deptIdMap[$padded])) {
            $dept = Department::find($deptIdMap[$padded]);
            if ($dept) {
                return $dept;
            }
        }

        // รหัสฝ่ายบริหารอื่นๆ เช่น 01 (บริหารทั่วไป), 02 (กิจการ นร.), 07 (ทะเบียนและธุรการ), 08 (การเงิน), 16 (จนท.)
        // ไม่ใช่แผนกวิชาการสอน จึงไม่จับคู่อัตโนมัติ (ป้องกันการแปลง 07 เป็น ID 7 เทคนิคเครื่องกล)
        return null;
    }

    public function syncStudentUser(array $data, string $password): User
    {
        $stdNo = trim($data['std_no'] ?? '');
        $fullName = trim($data['full_name'] ?? '');
        $rawEmail = trim($data['email'] ?? '');
        $isValidEmail = !empty($rawEmail) 
            && !in_array($rawEmail, ['-', 'ไม่ระบุ', 'none', 'null', 'N/A'], true)
            && filter_var($rawEmail, FILTER_VALIDATE_EMAIL) !== false;
        $email = $isValidEmail ? $rawEmail : "{$stdNo}@exam.local";
        $levelNo = $data['level_no'] ?? null;
        $departNo = $data['depart_no'] ?? null;
        $year = $data['year'] ?? null;
        $room = $data['room'] ?? null;
        $citizenId = !empty($data['citizen_id']) ? trim($data['citizen_id']) : $password;

        // ค้นหาห้องเรียนและแผนกวิชาที่ตรงกัน
        $shift = $data['shift'] ?? $data['รอบ'] ?? null;
        $classroom = $this->matchClassroom($levelNo, $departNo, $year, $room, $shift);
        $classroomId = $classroom?->id;

        $department = $this->matchDepartment($departNo);
        $departmentId = $department?->id;

        // ค้นหา User เดิมโดยใช้ citizen_id ก่อนเป็นอันดับแรก (เพราะ citizen_id unique และแน่นอนที่สุด)
        $user = null;
        if (!empty($citizenId)) {
            $user = User::where('citizen_id', $citizenId)->first();
        }

        // หากไม่เจอด้วย citizen_id ให้ค้นหาด้วย student_code หรือ username (ทั้งแบบปกติและ unpadded)
        if (!$user && !empty($stdNo)) {
            $unpadded = ltrim($stdNo, '0');
            $user = User::where('student_code', $stdNo)
                ->orWhere('username', $stdNo)
                ->when(!empty($unpadded), function ($q) use ($unpadded) {
                    $q->orWhere('student_code', $unpadded)
                      ->orWhere('username', $unpadded);
                })
                ->first();
        }

        static $userColumns = null;
        if ($userColumns === null) {
            $userColumns = Schema::getColumnListing('users');
        }

        $attributes = [
            'name' => $fullName ?: ($user ? $user->name : "นักศึกษา {$stdNo}"),
            'student_code' => $stdNo,
            'role' => 'student',
            'citizen_id' => $citizenId,
            'is_exam_eligible' => true,
        ];

        // จัดการอีเมล: หากยังไม่มี user ให้ตรวจว่าอีเมลชนกับคนอื่นหรือไม่
        if (!$user) {
            if (User::where('email', $email)->exists()) {
                $email = "{$stdNo}_" . substr(uniqid(), -4) . "@exam.local";
            }
            $attributes['email'] = $email;
            $attributes['password'] = Hash::make($password);
        } else {
            // ถ้ามี user อยู่แล้ว ให้อัปเดตเฉพาะเมื่อมีอีเมลที่ถูกต้องจาก MisSchool
            if ($isValidEmail) {
                $attributes['email'] = $rawEmail;
            } elseif (empty($user->email) || in_array($user->email, ['-', 'ไม่ระบุ'], true)) {
                $attributes['email'] = $email;
            }
            // Hash รหัสผ่านใหม่เฉพาะเมื่อ citizen_id เปลี่ยนแปลง
            if ($user->citizen_id !== $citizenId) {
                $attributes['password'] = Hash::make($password);
            }
        }

        if (in_array('username', $userColumns)) {
            $attributes['username'] = $stdNo;
        }
        if (in_array('level_no', $userColumns)) {
            $attributes['level_no'] = $levelNo;
        }
        if (in_array('depart_no', $userColumns)) {
            $attributes['depart_no'] = $departNo;
        }
        if (in_array('year', $userColumns)) {
            $attributes['year'] = $year;
        }
        if (in_array('room', $userColumns)) {
            $attributes['room'] = $room;
        }
        if (in_array('mis_synced_at', $userColumns)) {
            $attributes['mis_synced_at'] = now();
        }

        if ($classroomId) {
            $attributes['classroom_id'] = $classroomId;
        }
        if ($departmentId) {
            $attributes['department_id'] = $departmentId;
        }

        if ($user) {
            $user->update($attributes);
            return $user->fresh();
        }

        try {
            return User::create($attributes);
        } catch (\Illuminate\Database\QueryException $e) {
            // ป้องกัน duplicate entry 1062 ในกรณี race condition หรือข้อมูลซ้ำ
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                if (!empty($citizenId)) {
                    $existing = User::where('citizen_id', $citizenId)->first();
                    if ($existing) {
                        unset($attributes['email']);
                        $existing->update($attributes);
                        return $existing->fresh();
                    }
                }
                if (!empty($stdNo)) {
                    $existing = User::where('student_code', $stdNo)->first();
                    if ($existing) {
                        unset($attributes['email']);
                        $existing->update($attributes);
                        return $existing->fresh();
                    }
                }
            }
            throw $e;
        }
    }

    public function syncTeacherUser(array $data, string $password): User
    {
        $teacherNo = trim($data['teacher_no'] ?? '');
        $username = trim($data['username'] ?? $teacherNo);
        $fullName = trim($data['full_name'] ?? '');
        $email = trim($data['email'] ?? '');
        $departNo = $data['depart_no'] ?? null;
        $isAdmin = !empty($data['is_admin']);

        $user = User::where('teacher_code', $teacherNo)
            ->orWhere('username', $username)
            ->orWhereRaw('LOWER(teacher_code) = ?', [strtolower($teacherNo)])
            ->orWhereRaw('LOWER(username) = ?', [strtolower($username)])
            ->first();

        // ค้นหาแผนกวิชา:
        // 1. หากอาจารย์มีแผนกวิชาในระบบสอบอยู่แล้ว ให้คงเดิมไว้เสมอ (ไม่เขียนทับจากการล็อกอิน)
        // 2. หากยังไม่มีแผนกวิชา จึงค่อยค้นหาจากรหัสหมวดวิชาของ MisSchool
        $departmentId = $user?->department_id;
        if (!$departmentId && !empty($departNo)) {
            $department = $this->matchTeacherDepartment($departNo);
            if ($department) {
                $departmentId = $department->id;
            }
        }

        static $teacherColumns = null;
        if ($teacherColumns === null) {
            $teacherColumns = Schema::getColumnListing('users');
        }

        $role = $isAdmin ? 'admin' : ($user && $user->role === 'admin' ? 'admin' : 'teacher');

        $attributes = [
            'name' => $fullName ?: ($user ? $user->name : "อาจารย์ {$username}"),
            'teacher_code' => $teacherNo,
            'role' => $role,
        ];

        if (!$user) {
            $attributes['password'] = Hash::make($password);
        }

        if (in_array('username', $teacherColumns)) {
            $attributes['username'] = $username;
        }
        if (in_array('depart_no', $teacherColumns)) {
            $attributes['depart_no'] = $departNo;
        }
        if (in_array('mis_synced_at', $teacherColumns)) {
            $attributes['mis_synced_at'] = now();
        }

        if ($email) {
            $attributes['email'] = $email;
        } elseif (!$user || empty($user->email)) {
            $attributes['email'] = "{$username}@exam.local";
        }

        if ($departmentId) {
            $attributes['department_id'] = $departmentId;
        }

        if ($user) {
            $user->update($attributes);
            return $user->fresh();
        }

        return User::create($attributes);
    }

    // =========================================================================
    // Bulk Sync Methods (สำหรับปุ่ม Sync ในหน้าจอ)
    // =========================================================================

    /**
     * ดึงและซิงค์ข้อมูลอาจารย์ทั้งหมดจาก MisSchool
     */
    public function syncAllTeachers(): array
    {
        $teachers = [];

        // 1. ลองผ่าน Bridge API
        try {
            $url = rtrim($this->bridgeConfig['url'] ?? '', '/');
            $key = $this->bridgeConfig['key'] ?? '';
            $timeout = (int)($this->bridgeConfig['timeout'] ?? 15);

            $response = Http::withHeaders(['X-Bridge-Key' => $key])
                ->timeout($timeout)
                ->get("{$url}/index.php?action=sync-teachers");

            if ($response->successful()) {
                $json = $response->json();
                if (!empty($json['success']) && !empty($json['teachers'])) {
                    $teachers = $json['teachers'];
                }
            }
        } catch (Exception $e) {
            Log::info('Bridge sync-teachers failed, trying direct fallback: ' . $e->getMessage());
        }

        // 2. หากไม่ได้ผ่าน Bridge ให้ดึงผ่าน tsql หรือ sqlsrv
        if (empty($teachers)) {
            $teachers = $this->fetchTeachersDirect();
        }

        if (empty($teachers)) {
            return [
                'success' => false,
                'message' => 'ไม่สามารถเชื่อมต่อเพื่อดึงข้อมูลอาจารย์จาก MisSchool ได้',
            ];
        }

        $createdCount = 0;
        $updatedCount = 0;

        static $teacherColumns = null;
        if ($teacherColumns === null) {
            $teacherColumns = Schema::getColumnListing('users');
        }

        foreach ($teachers as $t) {
            $teacherNo = trim($t['teacher_no'] ?? '');
            $username = trim($t['username'] ?? $teacherNo);
            $fullName = trim($t['full_name'] ?? '');
            $rawPass = trim($t['password'] ?? $teacherNo);
            $email = trim($t['email'] ?? '');
            $isAdmin = !empty($t['is_admin']);

            if (!$teacherNo) {
                continue;
            }

            $user = User::where('teacher_code', $teacherNo)
                ->orWhere('username', $username)
                ->orWhereRaw('LOWER(teacher_code) = ?', [strtolower($teacherNo)])
                ->first();

            $data = [
                'name' => $fullName ?: ($user ? $user->name : "อาจารย์ {$username}"),
                'teacher_code' => $teacherNo,
                'role' => $isAdmin ? 'admin' : ($user && $user->role === 'admin' ? 'admin' : 'teacher'),
            ];

            if (in_array('username', $teacherColumns)) {
                $data['username'] = $username;
            }
            if (in_array('mis_synced_at', $teacherColumns)) {
                $data['mis_synced_at'] = now();
            }

            if ($email) {
                $data['email'] = $email;
            } elseif (!$user || empty($user->email)) {
                $data['email'] = "{$username}@exam.local";
            }

            if (!$user) {
                $data['password'] = Hash::make($rawPass ?: '123456');
                User::create($data);
                $createdCount++;
            } else {
                $user->update($data);
                $updatedCount++;
            }
        }

        return [
            'success' => true,
            'message' => "ซิงค์ข้อมูลอาจารย์จาก MisSchool เรียบร้อยแล้ว (ทั้งหมด {$createdCount} เพิ่มใหม่, {$updatedCount} อัปเดต)",
            'total' => count($teachers),
            'created' => $createdCount,
            'updated' => $updatedCount,
        ];
    }

    /**
     * ดึงและซิงค์รายชื่อห้องเรียนจาก MisSchool
     */
    public function syncAllClassrooms(): array
    {
        $classrooms = [];

        // 1. ลองผ่าน Bridge API
        try {
            $url = rtrim($this->bridgeConfig['url'] ?? '', '/');
            $key = $this->bridgeConfig['key'] ?? '';
            $timeout = (int)($this->bridgeConfig['timeout'] ?? 15);

            $response = Http::withHeaders(['X-Bridge-Key' => $key])
                ->timeout($timeout)
                ->get("{$url}/index.php?action=sync-classrooms");

            if ($response->successful()) {
                $json = $response->json();
                if (!empty($json['success']) && !empty($json['classrooms'])) {
                    $classrooms = $json['classrooms'];
                }
            }
        } catch (Exception $e) {
            Log::info('Bridge sync-classrooms failed, trying direct fallback: ' . $e->getMessage());
        }

        // 2. ดึงตรงหาก Bridge ไม่พร้อม
        if (empty($classrooms)) {
            $classrooms = $this->fetchClassroomsDirect();
        }

        if (empty($classrooms)) {
            return [
                'success' => false,
                'message' => 'ไม่พบข้อมูลห้องเรียนใหม่ หรือไม่สามารถเชื่อมต่อ MisSchool ได้',
            ];
        }

        $createdCount = 0;
        $existingCount = 0;

        foreach ($classrooms as $c) {
            $levelName = trim($c['LavelName'] ?? $c['level_name'] ?? '');
            $year = trim($c['Year'] ?? $c['year'] ?? '');
            $room = trim($c['Room'] ?? $c['room'] ?? '');
            $shift = trim($c['Shift'] ?? $c['shift'] ?? '');
            $dept = trim($c['DepartName'] ?? $c['depart_name'] ?? '');

            if (!$year || !$room || $year === '-' || $room === '-') {
                continue;
            }

            $roomName = "{$levelName}{$year}/{$room} {$dept}" . ($shift ? " {$shift}" : '');
            $roomName = trim(preg_replace('/\s+/', ' ', $roomName));

            $existing = Classroom::where('name', $roomName)
                ->orWhere('name', 'LIKE', "%{$levelName}{$year}/{$room}%")
                ->first();

            $isAfternoon = str_contains($shift, 'บ่าย') || str_contains($dept, 'บ่าย');
            $classroomShift = $isAfternoon ? 'afternoon' : 'morning';

            if (!$existing) {
                Classroom::create([
                    'name' => $roomName,
                    'shift' => $classroomShift,
                ]);
                $createdCount++;
            } else {
                if (empty($existing->shift)) {
                    $existing->update(['shift' => $classroomShift]);
                }
                $existingCount++;
            }
        }

        return [
            'success' => true,
            'message' => "ซิงค์ห้องเรียนจาก MisSchool สำเร็จ (พบห้องเรียนทั้งหมด " . count($classrooms) . " ห้อง, เพิ่มใหม่ {$createdCount} ห้อง)",
            'total' => count($classrooms),
            'created' => $createdCount,
            'existing' => $existingCount,
        ];
    }

    /**
     * ดึงและซิงค์นักศึกษาจาก MisSchool
     */
    public function syncAllStudents(array $options = []): array
    {
        $students = [];
        $limit = $options['limit'] ?? 5000;
        $onlyActive = $options['only_active'] ?? true;

        // 1. ลองผ่าน Bridge API
        try {
            $url = rtrim($this->bridgeConfig['url'] ?? '', '/');
            $key = $this->bridgeConfig['key'] ?? '';
            $timeout = (int)($this->bridgeConfig['timeout'] ?? 30);

            $query = http_build_query([
                'action' => 'sync-students',
                'limit' => $limit,
                'year' => $options['year'] ?? '',
                'room' => $options['room'] ?? '',
            ]);

            $response = Http::withHeaders(['X-Bridge-Key' => $key])
                ->timeout($timeout)
                ->get("{$url}/index.php?{$query}");

            if ($response->successful()) {
                $json = $response->json();
                if (!empty($json['success']) && !empty($json['students'])) {
                    $students = $json['students'];
                }
            }
        } catch (Exception $e) {
            Log::info('Bridge sync-students failed, trying direct fallback: ' . $e->getMessage());
        }

        // 2. ดึงตรงหาก Bridge ไม่พร้อม
        if (empty($students)) {
            $students = $this->fetchStudentsDirect($limit, $options);
        }

        if (empty($students)) {
            return [
                'success' => false,
                'message' => 'ไม่พบข้อมูลนักศึกษา หรือไม่สามารถเชื่อมต่อ MisSchool ได้',
            ];
        }

        $createdCount = 0;
        $updatedCount = 0;
        $failedCount = 0;
        $skippedInactiveCount = 0;
        $failedItems = [];

        foreach ($students as $st) {
            $stdNo = trim($st['std_no'] ?? '');
            $citizenId = trim($st['citizen_id'] ?? '');
            $year = trim((string)($st['year'] ?? ''));
            $room = trim((string)($st['room'] ?? ''));

            if (!$stdNo || !$citizenId) {
                continue;
            }

            $statusNo = (string)($st['status_no'] ?? '0');

            // กรองเอาเฉพาะนักศึกษาปัจจุบันที่มีห้องเรียนจริง และยังศึกษาอยู่ (ข้ามศิษย์เก่า / ลาออก / พักการเรียน)
            if ($onlyActive && ($year === '-' || $room === '-' || empty($year) || empty($room) || ($statusNo !== '0' && $statusNo !== ''))) {
                $skippedInactiveCount++;
                continue;
            }

            try {
                $user = User::where('citizen_id', $citizenId)
                    ->orWhere('student_code', $stdNo)
                    ->first();

                $this->syncStudentUser($st, $citizenId);

                if ($user) {
                    $updatedCount++;
                } else {
                    $createdCount++;
                }
            } catch (\Throwable $e) {
                $failedCount++;
                $failedItems[] = [
                    'std_no' => $stdNo,
                    'name' => trim($st['full_name'] ?? ''),
                    'citizen_id' => $citizenId,
                    'reason' => $e->getMessage(),
                ];
                Log::warning("Skipped student sync for {$stdNo} ({$citizenId}): " . $e->getMessage());
            }
        }

        $msg = "ซิงค์ข้อมูลนักศึกษาปัจจุบันจาก MisSchool สำเร็จ (เพิ่มใหม่ {$createdCount} คน, อัปเดต {$updatedCount} คน)";
        if ($skippedInactiveCount > 0) {
            $msg .= " [กรองข้ามศิษย์เก่า/ลาออก/ไม่มีห้องเรียน {$skippedInactiveCount} คน]";
        }
        if ($failedCount > 0) {
            $msg .= " (ข้าม {$failedCount} คนที่ข้อมูลไม่สมบูรณ์)";
        }

        return [
            'success' => true,
            'message' => $msg,
            'total' => count($students),
            'created' => $createdCount,
            'updated' => $updatedCount,
            'skipped_inactive' => $skippedInactiveCount,
            'failed' => $failedCount,
            'failed_items' => $failedItems,
        ];
    }

    /**
     * ดึงรายชื่อนักศึกษาที่ลาออกหรือพ้นสภาพจาก MisSchool
     */
    public function fetchResignedStudents(): array
    {
        if ($this->mode === 'bridge') {
            $url = rtrim($this->bridgeConfig['url'] ?? '', '/') . '/?action=resigned-students';
            $key = $this->bridgeConfig['key'] ?? '';
            $timeout = (int)($this->bridgeConfig['timeout'] ?? 15);

            try {
                $response = Http::withHeaders(['X-Bridge-Key' => $key])
                    ->timeout($timeout)
                    ->get($url);

                if ($response->successful()) {
                    return $response->json('students') ?? [];
                }
            } catch (\Throwable $e) {
                Log::warning('Fetch resigned students via bridge failed: ' . $e->getMessage());
            }
        }

        // Direct SQL Server fallback (tsql)
        if ($this->isTsqlAvailable()) {
            $sql = "SELECT sn.StdNo, r.StatusNo, ss.LeavingCause 
                    FROM Tabain.dbo.tblStudentNumber sn
                    INNER JOIN Tabain.dbo.tblStudentRoom r ON sn.StdNo = r.StdNo
                    LEFT JOIN Tabain.dbo.tblStudentStatus ss ON sn.StdNo = ss.StdNo
                    WHERE r.StatusNo IN ('1', '2', '3', '4', '8')";
            $rows = $this->executeTsql('Tabain', $sql);
            if (!empty($rows)) {
                return array_map(function ($r) {
                    return [
                        'std_no' => trim($r['StdNo'] ?? ''),
                        'status_no' => trim($r['StatusNo'] ?? ''),
                        'cause' => trim($r['LeavingCause'] ?? ''),
                    ];
                }, $rows);
            }
        }

        return [];
    }

    /**
     * ล้างหรือระงับสิทธิ์นักศึกษาที่ลาออก/พ้นสภาพการศึกษา
     */
    public function cleanupResignedStudents(): array
    {
        $resignedList = $this->fetchResignedStudents();
        $deleted = 0;
        $inactivated = 0;

        foreach ($resignedList as $st) {
            $code = trim($st['std_no'] ?? '');
            if (!$code) {
                continue;
            }

            $unpadded = ltrim($code, '0');
            $users = User::where('role', 'student')
                ->where(function ($q) use ($code, $unpadded) {
                    $q->where('student_code', $code)
                      ->orWhere('username', $code)
                      ->when(!empty($unpadded), function ($sq) use ($unpadded) {
                          $sq->orWhere('student_code', $unpadded)
                             ->orWhere('username', $unpadded);
                      });
                })
                ->get();

            foreach ($users as $user) {
                // หากไม่เคยสอบ ให้ลบออกจากระบบ
                if (!$user->examAttempts()->exists()) {
                    $user->delete();
                    $deleted++;
                } else {
                    // หากเคยสอบแล้ว ให้ระงับสิทธิ์สอบและปลดออกจากห้องเรียนเพื่อเก็บบันทึกประวัติ
                    $cause = !empty($st['cause']) ? $st['cause'] : 'พ้นสภาพการศึกษา (ลาออก)';
                    $user->update([
                        'is_exam_eligible' => false,
                        'ineligible_reason' => "พ้นสภาพการศึกษา (MisSchool): {$cause}",
                        'classroom_id' => null,
                    ]);
                    $inactivated++;
                }
            }
        }

        return [
            'total_resigned_in_mis' => count($resignedList),
            'deleted' => $deleted,
            'inactivated' => $inactivated,
        ];
    }

    /**
     * ล้างข้อมูลนักศึกษาเก่าที่ไม่มีห้องเรียน และไม่เคยมีประวัติการสอบ
     */
    public function cleanupInactiveStudents(): int
    {
        return User::where('role', 'student')
            ->whereNotNull('mis_synced_at')
            ->where(function ($q) {
                $q->whereNull('classroom_id')
                  ->orWhere('year', '-')
                  ->orWhere('room', '-')
                  ->orWhereNull('year')
                  ->orWhereNull('room');
            })
            ->whereDoesntHave('examAttempts')
            ->delete();
    }

    // =========================================================================
    // Bridge API Methods
    // =========================================================================

    protected function checkBridgeHealth(): array
    {
        $url = rtrim($this->bridgeConfig['url'] ?? '', '/');
        $key = $this->bridgeConfig['key'] ?? '';
        $timeout = (int)($this->bridgeConfig['timeout'] ?? 10);

        try {
            $response = Http::withHeaders([
                'X-Bridge-Key' => $key,
            ])->timeout($timeout)->get("{$url}/index.php?action=health");

            if ($response->successful()) {
                return $response->json() ?? ['success' => true, 'message' => 'Bridge operational'];
            }

            return [
                'success' => false,
                'message' => 'Bridge API HTTP Error: ' . $response->status(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Bridge Connection Failed: ' . $e->getMessage(),
            ];
        }
    }

    protected function verifyStudentBridge(string $username, string $password): array
    {
        $url = rtrim($this->bridgeConfig['url'] ?? '', '/');
        $key = $this->bridgeConfig['key'] ?? '';
        $timeout = (int)($this->bridgeConfig['timeout'] ?? 10);

        try {
            $response = Http::withHeaders([
                'X-Bridge-Key' => $key,
            ])->timeout($timeout)->post("{$url}/index.php?action=verify-student", [
                'username' => $username,
                'password' => $password,
            ]);

            return $response->json() ?? ['success' => false, 'message' => 'Invalid bridge response'];
        } catch (Exception $e) {
            Log::warning('MisSchool verifyStudentBridge error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'ไม่สามารถเชื่อมต่อ Bridge API: ' . $e->getMessage()];
        }
    }

    protected function verifyTeacherBridge(string $username, string $password): array
    {
        $url = rtrim($this->bridgeConfig['url'] ?? '', '/');
        $key = $this->bridgeConfig['key'] ?? '';
        $timeout = (int)($this->bridgeConfig['timeout'] ?? 10);

        try {
            $response = Http::withHeaders([
                'X-Bridge-Key' => $key,
            ])->timeout($timeout)->post("{$url}/index.php?action=verify-teacher", [
                'username' => $username,
                'password' => $password,
            ]);

            return $response->json() ?? ['success' => false, 'message' => 'Invalid bridge response'];
        } catch (Exception $e) {
            Log::warning('MisSchool verifyTeacherBridge error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'ไม่สามารถเชื่อมต่อ Bridge API: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    // Direct / TSQL Methods (สำรองเมื่อไม่มี Bridge หรือทดสอบภายใน)
    // =========================================================================

    public function isTsqlAvailable(): bool
    {
        if (is_executable('/opt/homebrew/bin/tsql')) {
            return true;
        }
        $which = @shell_exec('which tsql');
        return !empty(trim((string)$which));
    }

    public function executeTsql(string $db, string $sql): ?array
    {
        $tsqlPath = is_executable('/opt/homebrew/bin/tsql') ? '/opt/homebrew/bin/tsql' : trim((string)@shell_exec('which tsql'));
        if (!$tsqlPath || !is_executable($tsqlPath)) {
            return null;
        }

        $host = $this->sqlsrvConfig['host'] ?? 'stech.misschool.info';
        $port = $this->sqlsrvConfig['port'] ?? '24252';
        $user = $this->sqlsrvConfig['username'] ?? 'misadmin';
        $pass = $this->sqlsrvConfig['password'] ?? 'meng@2520';

        $cmd = sprintf(
            "%s -H %s -p %s -U %s -P %s -D %s",
            escapeshellcmd($tsqlPath),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($pass),
            escapeshellarg($db)
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return null;
        }

        fwrite($pipes[0], $sql . "\nGO\n");
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $this->parseTsqlOutput($output);
    }

    protected function parseTsqlOutput(string $output): array
    {
        $lines = preg_split('/\r?\n/', $output);
        $headers = null;
        $rows = [];

        foreach ($lines as $line) {
            if (strpos($line, "\t") !== false) {
                $cleanLine = preg_replace('/^([0-9]+>\s*)+/', '', trim($line));
                $cols = explode("\t", $cleanLine);
                if ($headers === null) {
                    $headers = array_map('trim', $cols);
                } else {
                    $row = [];
                    foreach ($headers as $idx => $h) {
                        $row[$h] = isset($cols[$idx]) ? trim($cols[$idx]) : null;
                    }
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    protected function checkTsqlHealth(): array
    {
        try {
            $rows = $this->executeTsql('Tabain', 'SELECT 1 AS alive;');
            if (!empty($rows)) {
                return [
                    'success' => true,
                    'message' => 'Direct SQL Server (tsql) operational and connected!',
                    'timestamp' => date('Y-m-d H:i:s'),
                ];
            }
            return ['success' => false, 'message' => 'tsql query returned empty'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'tsql check error: ' . $e->getMessage()];
        }
    }

    protected function verifyStudentTsql(string $username, string $password): array
    {
        try {
            $safeUser = addslashes($username);
            $safePass = addslashes($password);

            $sql = "SELECT TOP 1 
                        sn.StdNo, s.IdNo, s.PreFixNo, s.Fname, s.Lname, s.Email,
                        r.LavelNo, r.DepartNo, r.Year, r.Room, r.StatusNo
                    FROM Tabain.dbo.tblStudentNumber sn
                    INNER JOIN Tabain.dbo.tblStudent s ON sn.StdNoRef = s.StdNoRef
                    LEFT JOIN Tabain.dbo.tblStudentRoom r ON sn.StdNo = r.StdNo
                    WHERE sn.StdNo = '{$safeUser}' 
                      AND s.IdNo = '{$safePass}';";

            $rows = $this->executeTsql('Tabain', $sql);
            if (!empty($rows) && !empty($rows[0]['StdNo'])) {
                $st = $rows[0];
                return [
                    'success' => true,
                    'data' => [
                        'std_no' => $st['StdNo'],
                        'full_name' => trim(($st['Fname'] ?? '') . ' ' . ($st['Lname'] ?? '')),
                        'level_no' => $st['LavelNo'] ?? null,
                        'depart_no' => $st['DepartNo'] ?? null,
                        'year' => $st['Year'] ?? null,
                        'room' => $st['Room'] ?? null,
                        'email' => $st['Email'] ?? null,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => 'รหัสนักศึกษาหรือเลขประจำตัวประชาชนไม่ถูกต้อง หรือพ้นสภาพการศึกษา',
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'tsql student query error: ' . $e->getMessage()];
        }
    }

    protected function verifyTeacherTsql(string $username, string $password): array
    {
        try {
            $safeUser = addslashes($username);
            $safePass = addslashes($password);

            $sql = "SELECT TOP 1
                        u.UserName, u.Password, u.IsAdmin,
                        t.TeacherNo, t.FName, t.LName, t.Email,
                        i.DepartNo
                    FROM Personal.dbo.tblUserName u
                    INNER JOIN Personal.dbo.tblTeacherHis t ON u.TeacherNo = t.TeacherNo
                    LEFT JOIN Personal.dbo.tblTeacherInfo i ON t.TeacherNo = i.TeacherNo
                    WHERE (LOWER(u.UserName) = LOWER('{$safeUser}') OR LOWER(t.TeacherNo) = LOWER('{$safeUser}'))
                      AND (u.Password = '{$safePass}' OR LOWER(u.Password) = LOWER('{$safePass}'))
                      AND t.IsUse = '1'
                      AND ISNULL(i.IsStatusOut, '0') = '0';";

            $rows = $this->executeTsql('Personal', $sql);
            if (!empty($rows) && !empty($rows[0]['TeacherNo'])) {
                $t = $rows[0];
                return [
                    'success' => true,
                    'data' => [
                        'teacher_no' => $t['TeacherNo'],
                        'username' => $t['UserName'],
                        'full_name' => trim(($t['FName'] ?? '') . ' ' . ($t['LName'] ?? '')),
                        'email' => $t['Email'] ?? null,
                        'depart_no' => $t['DepartNo'] ?? null,
                        'is_admin' => ($t['IsAdmin'] == '1'),
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => 'ชื่อผู้ใช้หรือรหัสผ่านอาจารย์ไม่ถูกต้อง หรือพ้นสภาพการปฏิบัติหน้าที่',
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'tsql teacher query error: ' . $e->getMessage()];
        }
    }

    protected function fetchTeachersDirect(): array
    {
        if ($this->isTsqlAvailable()) {
            $sql = "SELECT 
                        u.UserName, u.Password, u.IsAdmin,
                        t.TeacherNo, t.FName, t.LName, t.Email,
                        i.DepartNo
                    FROM Personal.dbo.tblUserName u
                    INNER JOIN Personal.dbo.tblTeacherHis t ON u.TeacherNo = t.TeacherNo
                    LEFT JOIN Personal.dbo.tblTeacherInfo i ON t.TeacherNo = i.TeacherNo
                    WHERE t.IsUse = '1'
                    ORDER BY t.TeacherNo ASC;";

            $rows = $this->executeTsql('Personal', $sql);
            return array_map(function($r) {
                return [
                    'teacher_no' => trim($r['TeacherNo'] ?? ''),
                    'username' => trim($r['UserName'] ?? ''),
                    'password' => trim($r['Password'] ?? ''),
                    'full_name' => trim(($r['FName'] ?? '') . ' ' . ($r['LName'] ?? '')),
                    'email' => trim($r['Email'] ?? ''),
                    'depart_no' => $r['DepartNo'] ?? null,
                    'is_admin' => ($r['IsAdmin'] == '1'),
                ];
            }, $rows);
        }

        return [];
    }

    protected function fetchClassroomsDirect(): array
    {
        if ($this->isTsqlAvailable()) {
            $sql = "SELECT DISTINCT 
                        LavelName, Year, Room, รอบ AS Shift, DepartName
                    FROM Tabain.dbo.v_StudentAll
                    WHERE Year <> '-' AND Room <> '-' AND Year IS NOT NULL AND Room IS NOT NULL
                    ORDER BY LavelName, Year, Room;";

            return $this->executeTsql('Tabain', $sql);
        }

        return [];
    }

    protected function fetchStudentsDirect(int $limit = 5000, array $options = []): array
    {
        if ($this->isTsqlAvailable()) {
            $topClause = ($limit > 0) ? "TOP {$limit}" : "";
            $sql = "SELECT {$topClause}
                        sn.StdNo, s.IdNo, s.PreFixNo, s.Fname, s.Lname, s.Email,
                        r.LavelNo, r.DepartNo, r.Year, r.Room
                    FROM Tabain.dbo.tblStudentNumber sn
                    INNER JOIN Tabain.dbo.tblStudent s ON sn.StdNoRef = s.StdNoRef
                    INNER JOIN Tabain.dbo.tblStudentRoom r ON sn.StdNo = r.StdNo
                    WHERE s.IsUse = '1'
                      AND r.Year <> '-' AND r.Room <> '-'
                      AND r.Year IS NOT NULL AND r.Room IS NOT NULL";

            if (!empty($options['year'])) {
                $sql .= " AND r.Year = '" . addslashes($options['year']) . "'";
            }
            if (!empty($options['room'])) {
                $sql .= " AND r.Room = '" . addslashes($options['room']) . "'";
            }

            $sql .= " ORDER BY r.Year ASC, r.Room ASC, sn.StdNo ASC;";

            $rows = $this->executeTsql('Tabain', $sql);
            return array_map(function($r) {
                return [
                    'std_no' => trim($r['StdNo'] ?? ''),
                    'citizen_id' => trim($r['IdNo'] ?? ''),
                    'full_name' => trim(($r['Fname'] ?? '') . ' ' . ($r['Lname'] ?? '')),
                    'level_no' => $r['LavelNo'] ?? null,
                    'depart_no' => $r['DepartNo'] ?? null,
                    'year' => $r['Year'] ?? null,
                    'room' => $r['Room'] ?? null,
                    'email' => $r['Email'] ?? null,
                ];
            }, $rows);
        }

        return [];
    }

    // =========================================================================
    // SQL Server Direct PDO Methods (เมื่อติดตั้ง extension pdo_sqlsrv)
    // =========================================================================

    protected function getSqlSrvPdo(string $dbName): PDO
    {
        $host = $this->sqlsrvConfig['host'] ?? 'stech.misschool.info';
        $port = $this->sqlsrvConfig['port'] ?? '24252';
        $user = $this->sqlsrvConfig['username'] ?? 'misadmin';
        $pass = $this->sqlsrvConfig['password'] ?? 'meng@2520';

        $server = "{$host},{$port}";
        $dsn = "sqlsrv:Server={$server};Database={$dbName};TrustServerCertificate=true;Encrypt=false";

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
            PDO::ATTR_TIMEOUT => 8,
        ]);
    }

    protected function checkSqlSrvHealth(): array
    {
        try {
            $dbName = $this->sqlsrvConfig['database_student'] ?? 'Tabain';
            if (extension_loaded('pdo_sqlsrv')) {
                $pdo = $this->getSqlSrvPdo($dbName);
                $stmt = $pdo->query('SELECT 1');
                $stmt->fetch();

                return [
                    'success' => true,
                    'message' => 'SQL Server operational and connected directly!',
                    'timestamp' => date('Y-m-d H:i:s'),
                ];
            }

            if ($this->isTsqlAvailable()) {
                return $this->checkTsqlHealth();
            }

            return ['success' => false, 'message' => 'pdo_sqlsrv driver is not installed on this server.'];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SQL Server Connection Failed: ' . $e->getMessage(),
            ];
        }
    }

    protected function verifyStudentSqlSrv(string $username, string $password): array
    {
        try {
            if (!extension_loaded('pdo_sqlsrv')) {
                return $this->verifyStudentTsql($username, $password);
            }

            $dbName = $this->sqlsrvConfig['database_student'] ?? 'Tabain';
            $pdo = $this->getSqlSrvPdo($dbName);

            $sql = "SELECT TOP 1 
                        sn.StdNo, s.IdNo, s.PreFixNo, s.Fname, s.Lname, s.Email,
                        r.LavelNo, r.DepartNo, r.Year, r.Room, r.StatusNo
                    FROM {$dbName}.dbo.tblStudentNumber sn
                    INNER JOIN {$dbName}.dbo.tblStudent s ON sn.StdNoRef = s.StdNoRef
                    LEFT JOIN {$dbName}.dbo.tblStudentRoom r ON sn.StdNo = r.StdNo
                    WHERE sn.StdNo = :username 
                      AND s.IdNo = :password";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':username' => $username, ':password' => $password]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($student) {
                return [
                    'success' => true,
                    'data' => [
                        'std_no' => $student['StdNo'],
                        'full_name' => trim(($student['Fname'] ?? '') . ' ' . ($student['Lname'] ?? '')),
                        'level_no' => $student['LavelNo'] ?? null,
                        'depart_no' => $student['DepartNo'] ?? null,
                        'year' => $student['Year'] ?? null,
                        'room' => $student['Room'] ?? null,
                        'email' => $student['Email'] ?? null,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => 'รหัสนักศึกษาหรือเลขประจำตัวประชาชนไม่ถูกต้อง หรือพ้นสภาพการศึกษา',
            ];
        } catch (Exception $e) {
            Log::warning('MisSchool verifyStudentSqlSrv error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'ข้อผิดพลาด SQL Server: ' . $e->getMessage()];
        }
    }

    protected function verifyTeacherSqlSrv(string $username, string $password): array
    {
        try {
            if (!extension_loaded('pdo_sqlsrv')) {
                return $this->verifyTeacherTsql($username, $password);
            }

            $dbName = $this->sqlsrvConfig['database_teacher'] ?? 'Personal';
            $pdo = $this->getSqlSrvPdo($dbName);

            $sql = "SELECT TOP 1
                        u.UserName, u.IsAdmin,
                        t.TeacherNo, t.FName, t.LName, t.Email,
                        i.DepartNo
                    FROM {$dbName}.dbo.tblUserName u
                    INNER JOIN {$dbName}.dbo.tblTeacherHis t ON u.TeacherNo = t.TeacherNo
                    LEFT JOIN {$dbName}.dbo.tblTeacherInfo i ON t.TeacherNo = i.TeacherNo
                    WHERE (LOWER(u.UserName) = :u1 OR LOWER(t.TeacherNo) = :u2)
                      AND (u.Password = :p1 OR LOWER(u.Password) = :p2)
                      AND t.IsUse = '1'
                      AND ISNULL(i.IsStatusOut, '0') = '0'";

            $lowerUser = strtolower($username);
            $lowerPass = strtolower($password);

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':u1' => $lowerUser,
                ':u2' => $lowerUser,
                ':p1' => $password,
                ':p2' => $lowerPass,
            ]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($teacher) {
                return [
                    'success' => true,
                    'data' => [
                        'teacher_no' => $teacher['TeacherNo'],
                        'username' => $teacher['UserName'],
                        'full_name' => trim(($teacher['FName'] ?? '') . ' ' . ($teacher['LName'] ?? '')),
                        'email' => $teacher['Email'] ?? null,
                        'depart_no' => $teacher['DepartNo'] ?? null,
                        'is_admin' => ($teacher['IsAdmin'] == '1'),
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => 'ชื่อผู้ใช้หรือรหัสผ่านอาจารย์ไม่ถูกต้อง หรือพ้นสภาพการปฏิบัติหน้าที่',
            ];
        } catch (Exception $e) {
            Log::warning('MisSchool verifyTeacherSqlSrv error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'ข้อผิดพลาด SQL Server: ' . $e->getMessage()];
        }
    }
}
