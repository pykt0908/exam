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

        return $this->checkBridgeHealth();
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

        return $this->verifyStudentBridge($username, $password);
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

        return $this->verifyTeacherBridge($username, $password);
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

    protected function syncStudentUser(array $data, string $password): User
    {
        $stdNo = trim($data['std_no'] ?? '');
        $fullName = trim($data['full_name'] ?? '');
        $email = !empty($data['email']) ? trim($data['email']) : "{$stdNo}@exam.local";
        $levelNo = $data['level_no'] ?? null;
        $departNo = $data['depart_no'] ?? null;
        $year = $data['year'] ?? null;
        $room = $data['room'] ?? null;

        // ค้นหาห้องเรียนที่ตรงกัน (เช่น "1/1", "2/1")
        $classroomId = null;
        if (!empty($year) && !empty($room)) {
            $levelPrefix = ($levelNo == '2') ? 'ปวส.' : 'ปวช.';
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
            'citizen_id' => $password,
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

    protected function syncTeacherUser(array $data, string $password): User
    {
        $teacherNo = trim($data['teacher_no'] ?? '');
        $username = trim($data['username'] ?? $teacherNo);
        $fullName = trim($data['full_name'] ?? '');
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
            ->first();

        $role = $isAdmin ? 'admin' : 'teacher';

        $attributes = [
            'name' => $fullName ?: ($user ? $user->name : "อาจารย์ {$username}"),
            'teacher_code' => $teacherNo,
            'username' => $username,
            'password' => Hash::make($password),
            'role' => $role,
            'depart_no' => $departNo,
            'mis_synced_at' => now(),
        ];

        if (!$user || empty($user->email)) {
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
    // SQL Server Direct Methods
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
            $pdo = $this->getSqlSrvPdo($dbName);
            $stmt = $pdo->query('SELECT 1');
            $stmt->fetch();

            return [
                'success' => true,
                'message' => 'SQL Server operational and connected directly!',
                'timestamp' => date('Y-m-d H:i:s'),
            ];
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
            $dbName = $this->sqlsrvConfig['database_student'] ?? 'Tabain';
            $pdo = $this->getSqlSrvPdo($dbName);

            $sql = "SELECT TOP 1 
                        s.StdNo, s.IdNo, s.PrefixNo, s.FName, s.LName, s.Email,
                        r.LavelNo, r.DepartNo, r.Year, r.Room, r.StatusNo
                    FROM {$dbName}.dbo.tblStudent s
                    LEFT JOIN {$dbName}.dbo.tblStudentRoom r ON s.StdNo = r.StdNo
                    WHERE s.StdNo = :username 
                      AND s.IdNo = :password
                      AND r.StatusNo = '1'";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':username' => $username, ':password' => $password]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($student) {
                return [
                    'success' => true,
                    'data' => [
                        'std_no' => $student['StdNo'],
                        'full_name' => trim(($student['FName'] ?? '') . ' ' . ($student['LName'] ?? '')),
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
            $dbName = $this->sqlsrvConfig['database_teacher'] ?? 'Personal';
            $pdo = $this->getSqlSrvPdo($dbName);

            $sql = "SELECT TOP 1
                        u.UserName, u.IsAdmin,
                        t.TeacherNo, t.FName, t.LName,
                        i.DepartNo
                    FROM {$dbName}.dbo.tblUserName u
                    INNER JOIN {$dbName}.dbo.tblTeacherHis t ON u.TeacherNo = t.TeacherNo
                    LEFT JOIN {$dbName}.dbo.tblTeacherInfo i ON t.TeacherNo = i.TeacherNo
                    WHERE (u.UserName = :username OR t.TeacherNo = :username)
                      AND u.Password = :password
                      AND t.IsUse = '1'
                      AND ISNULL(i.IsStatusOut, '0') = '0'";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':username' => $username, ':password' => $password]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($teacher) {
                return [
                    'success' => true,
                    'data' => [
                        'teacher_no' => $teacher['TeacherNo'],
                        'username' => $teacher['UserName'],
                        'full_name' => trim(($teacher['FName'] ?? '') . ' ' . ($teacher['LName'] ?? '')),
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
