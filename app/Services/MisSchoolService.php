<?php

namespace App\Services;

use App\Models\User;
use App\Models\Classroom;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    public function syncStudentUser(array $data, string $password): User
    {
        $stdNo = trim($data['std_no'] ?? '');
        $fullName = trim($data['full_name'] ?? '');
        $email = !empty($data['email']) ? trim($data['email']) : "{$stdNo}@exam.local";
        $levelNo = $data['level_no'] ?? null;
        $departNo = $data['depart_no'] ?? null;
        $year = $data['year'] ?? null;
        $room = $data['room'] ?? null;
        $citizenId = !empty($data['citizen_id']) ? trim($data['citizen_id']) : $password;

        // ค้นหาห้องเรียนที่ตรงกัน (เช่น "1/1", "2/1")
        $classroomId = null;
        if (!empty($year) && !empty($room) && $year !== '-' && $room !== '-') {
            $levelPrefix = ($levelNo == '2' || $levelNo == '3') ? 'ปวส.' : 'ปวช.';
            $targetRoom = "{$year}/{$room}";

            $classroom = Classroom::where('name', 'LIKE', "%{$levelPrefix}{$targetRoom}%")
                ->orWhere('name', 'LIKE', "%{$targetRoom}%")
                ->first();

            if ($classroom) {
                $classroomId = $classroom->id;
            }
        }

        // ค้นหาแผนกวิชา
        $departmentId = null;
        if (!empty($departNo)) {
            $department = Department::where('id', $departNo)
                ->orWhere('name', 'LIKE', "%{$departNo}%")
                ->first();
            if ($department) {
                $departmentId = $department->id;
            }
        }

        // ค้นหา User เดิมโดยใช้ student_code หรือ username
        $user = User::where('student_code', $stdNo)
            ->orWhere('username', $stdNo)
            ->first();

        $attributes = [
            'name' => $fullName ?: ($user ? $user->name : "นักศึกษา {$stdNo}"),
            'email' => $email,
            'password' => Hash::make($password),
            'student_code' => $stdNo,
            'username' => $stdNo,
            'role' => 'student',
            'citizen_id' => $citizenId,
            'level_no' => $levelNo,
            'depart_no' => $departNo,
            'year' => $year,
            'room' => $room,
            'mis_synced_at' => now(),
            'is_exam_eligible' => true,
        ];

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

        return User::create($attributes);
    }

    public function syncTeacherUser(array $data, string $password): User
    {
        $teacherNo = trim($data['teacher_no'] ?? '');
        $username = trim($data['username'] ?? $teacherNo);
        $fullName = trim($data['full_name'] ?? '');
        $email = trim($data['email'] ?? '');
        $departNo = $data['depart_no'] ?? null;
        $isAdmin = !empty($data['is_admin']);

        // ค้นหาแผนกวิชา
        $departmentId = null;
        if (!empty($departNo)) {
            $department = Department::where('id', $departNo)
                ->orWhere('name', 'LIKE', "%{$departNo}%")
                ->first();
            if ($department) {
                $departmentId = $department->id;
            }
        }

        $user = User::where('teacher_code', $teacherNo)
            ->orWhere('username', $username)
            ->orWhereRaw('LOWER(teacher_code) = ?', [strtolower($teacherNo)])
            ->orWhereRaw('LOWER(username) = ?', [strtolower($username)])
            ->first();

        $role = $isAdmin ? 'admin' : ($user && $user->role === 'admin' ? 'admin' : 'teacher');

        $attributes = [
            'name' => $fullName ?: ($user ? $user->name : "อาจารย์ {$username}"),
            'teacher_code' => $teacherNo,
            'username' => $username,
            'password' => Hash::make($password),
            'role' => $role,
            'depart_no' => $departNo,
            'mis_synced_at' => now(),
        ];

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
                'username' => $username,
                'role' => $isAdmin ? 'admin' : ($user && $user->role === 'admin' ? 'admin' : 'teacher'),
                'mis_synced_at' => now(),
            ];

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
        $limit = $options['limit'] ?? 1000;

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

        foreach ($students as $st) {
            $stdNo = trim($st['std_no'] ?? '');
            $citizenId = trim($st['citizen_id'] ?? '');
            if (!$stdNo || !$citizenId) {
                continue;
            }

            $user = User::where('student_code', $stdNo)->first();
            $this->syncStudentUser($st, $citizenId);

            if ($user) {
                $updatedCount++;
            } else {
                $createdCount++;
            }
        }

        return [
            'success' => true,
            'message' => "ซิงค์ข้อมูลนักศึกษาจาก MisSchool สำเร็จ (ทั้งหมด " . count($students) . " คน: เพิ่มใหม่ {$createdCount} คน, อัปเดต {$updatedCount} คน)",
            'total' => count($students),
            'created' => $createdCount,
            'updated' => $updatedCount,
        ];
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

    protected function fetchStudentsDirect(int $limit = 500, array $options = []): array
    {
        if ($this->isTsqlAvailable()) {
            $sql = "SELECT TOP {$limit}
                        sn.StdNo, s.IdNo, s.PreFixNo, s.Fname, s.Lname, s.Email,
                        r.LavelNo, r.DepartNo, r.Year, r.Room
                    FROM Tabain.dbo.tblStudentNumber sn
                    INNER JOIN Tabain.dbo.tblStudent s ON sn.StdNoRef = s.StdNoRef
                    LEFT JOIN Tabain.dbo.tblStudentRoom r ON sn.StdNo = r.StdNo
                    WHERE s.IsUse = '1'";

            if (!empty($options['year'])) {
                $sql .= " AND r.Year = '" . addslashes($options['year']) . "'";
            }
            if (!empty($options['room'])) {
                $sql .= " AND r.Room = '" . addslashes($options['room']) . "'";
            }

            $sql .= " ORDER BY sn.StdNo ASC;";

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
