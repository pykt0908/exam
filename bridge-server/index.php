<?php
/**
 * MisSchool Bridge API Server
 * สำหรับนำไปวางที่ public_html ของโดเมน bridge-technosrc-api.offriend.net
 */

header('Content-Type: application/json; charset=UTF-8');

// 1. ตั้งค่าความปลอดภัย (API Secret Key)
define('API_SECRET_KEY', 'technosri_secret_key_2026');

// 2. ข้อมูลการเชื่อมต่อ MisSchool SQL Server
$dbHost = 'stech.misschool.info,24252';
$dbUser = 'misadmin';
$dbPass = 'meng@2520';

// 3. ตรวจสอบ API Key จาก Header (X-Bridge-Key)
$headers = getallheaders();
$clientKey = $headers['X-Bridge-Key'] ?? $headers['x-bridge-key'] ?? '';

if ($clientKey !== API_SECRET_KEY) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Invalid API Key'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. ฟังก์ชันเชื่อมต่อฐานข้อมูลด้วย PDO sqlsrv
function getDbConnection($dbName, $host, $user, $pass) {
    try {
        $conn = new PDO("sqlsrv:Server=$host;Database=$dbName;TrustServerCertificate=true;Encrypt=false", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
        ]);
        return $conn;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 5. อ่านข้อมูล JSON Request Body
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';

// --- ROUTING ACTIONS ---

// Action 1: ตรวจสอบสถานะ Server (Health Check)
if ($action === 'health' || empty($action)) {
    try {
        $connTabain = getDbConnection('Tabain', $dbHost, $dbUser, $dbPass);
        $connPersonal = getDbConnection('Personal', $dbHost, $dbUser, $dbPass);

        $stmtTeachers = $connPersonal->query("SELECT COUNT(*) AS total FROM Personal.dbo.tblUserName u INNER JOIN Personal.dbo.tblTeacherHis t ON u.TeacherNo = t.TeacherNo WHERE t.IsUse = '1'");
        $totalTeachers = (int)($stmtTeachers->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $stmtStudents = $connTabain->query("SELECT COUNT(*) AS total FROM Tabain.dbo.tblStudentNumber sn INNER JOIN Tabain.dbo.tblStudent s ON sn.StdNoRef = s.StdNoRef WHERE s.IsUse = '1'");
        $totalStudents = (int)($stmtStudents->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        echo json_encode([
            'success' => true,
            'message' => 'Bridge API is operational and connected to MisSchool SQL Server!',
            'stats' => [
                'active_teachers' => $totalTeachers,
                'active_students' => $totalStudents,
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Health check failed: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Action 2: ตรวจสอบการ Login ของนักเรียน (Verify Student)
if ($action === 'verify-student') {
    $username = trim($input['username'] ?? $_GET['username'] ?? ''); // รหัสนักศึกษา (StdNo)
    $password = trim($input['password'] ?? $_GET['password'] ?? ''); // เลขบัตรประชาชน (IdNo)

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing username or password'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $conn = getDbConnection('Tabain', $dbHost, $dbUser, $dbPass);
        $sql = "SELECT TOP 1 
                    sn.StdNo, s.IdNo, s.PreFixNo, s.Fname, s.Lname, s.Email,
                    r.LavelNo, r.DepartNo, r.Year, r.Room, r.StatusNo
                FROM Tabain.dbo.tblStudentNumber sn
                INNER JOIN Tabain.dbo.tblStudent s ON sn.StdNoRef = s.StdNoRef
                LEFT JOIN Tabain.dbo.tblStudentRoom r ON sn.StdNo = r.StdNo
                WHERE sn.StdNo = :username 
                  AND s.IdNo = :password";

        $stmt = $conn->prepare($sql);
        $stmt->execute([':username' => $username, ':password' => $password]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student) {
            unset($student['IdNo']);
            echo json_encode([
                'success' => true,
                'data' => [
                    'std_no' => trim($student['StdNo']),
                    'full_name' => trim(($student['Fname'] ?? '') . ' ' . ($student['Lname'] ?? '')),
                    'level_no' => $student['LavelNo'] ?? null,
                    'depart_no' => $student['DepartNo'] ?? null,
                    'year' => $student['Year'] ?? null,
                    'room' => $student['Room'] ?? null,
                    'email' => $student['Email'] ?? null,
                ]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'รหัสนักศึกษาหรือเลขประจำตัวประชาชนไม่ถูกต้อง หรือพ้นสภาพการศึกษา'
            ], JSON_UNESCAPED_UNICODE);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database query error: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Action 3: ตรวจสอบการ Login ของครู/อาจารย์ (Verify Teacher)
if ($action === 'verify-teacher') {
    $username = trim($input['username'] ?? $_GET['username'] ?? '');
    $password = trim($input['password'] ?? $_GET['password'] ?? '');

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing username or password'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $conn = getDbConnection('Personal', $dbHost, $dbUser, $dbPass);
        $sql = "SELECT TOP 1
                    u.UserName, u.IsAdmin,
                    t.TeacherNo, t.FName, t.LName, t.Email,
                    i.DepartNo
                FROM Personal.dbo.tblUserName u
                INNER JOIN Personal.dbo.tblTeacherHis t ON u.TeacherNo = t.TeacherNo
                LEFT JOIN Personal.dbo.tblTeacherInfo i ON t.TeacherNo = i.TeacherNo
                WHERE (LOWER(u.UserName) = :u1 OR LOWER(t.TeacherNo) = :u2)
                  AND (u.Password = :p1 OR LOWER(u.Password) = :p2)
                  AND t.IsUse = '1'
                  AND ISNULL(i.IsStatusOut, '0') = '0'";

        $lowerUser = strtolower($username);
        $lowerPass = strtolower($password);

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':u1' => $lowerUser,
            ':u2' => $lowerUser,
            ':p1' => $password,
            ':p2' => $lowerPass,
        ]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($teacher) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'teacher_no' => trim($teacher['TeacherNo']),
                    'username' => trim($teacher['UserName']),
                    'full_name' => trim(($teacher['FName'] ?? '') . ' ' . ($teacher['LName'] ?? '')),
                    'email' => trim($teacher['Email'] ?? ''),
                    'depart_no' => $teacher['DepartNo'] ?? null,
                    'is_admin' => ($teacher['IsAdmin'] == '1')
                ]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'ชื่อผู้ใช้หรือรหัสผ่านอาจารย์ไม่ถูกต้อง หรือพ้นสภาพการปฏิบัติหน้าที่'
            ], JSON_UNESCAPED_UNICODE);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database query error: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Action 4: ดึงรายชื่ออาจารย์ทั้งหมดจาก MisSchool (Sync All Teachers)
if ($action === 'sync-teachers') {
    try {
        $conn = getDbConnection('Personal', $dbHost, $dbUser, $dbPass);
        $sql = "SELECT 
                    u.UserName, u.Password, u.IsAdmin,
                    t.TeacherNo, t.FName, t.LName, t.Email,
                    i.DepartNo
                FROM Personal.dbo.tblUserName u
                INNER JOIN Personal.dbo.tblTeacherHis t ON u.TeacherNo = t.TeacherNo
                LEFT JOIN Personal.dbo.tblTeacherInfo i ON t.TeacherNo = i.TeacherNo
                WHERE t.IsUse = '1'
                ORDER BY t.TeacherNo ASC";

        $stmt = $conn->query($sql);
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map(function($t) {
            return [
                'teacher_no' => trim($t['TeacherNo']),
                'username' => trim($t['UserName']),
                'password' => trim($t['Password']),
                'full_name' => trim(($t['FName'] ?? '') . ' ' . ($t['LName'] ?? '')),
                'email' => trim($t['Email'] ?? ''),
                'depart_no' => $t['DepartNo'] ?? null,
                'is_admin' => ($t['IsAdmin'] == '1'),
            ];
        }, $teachers);

        echo json_encode([
            'success' => true,
            'count' => count($data),
            'teachers' => $data,
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Sync teachers failed: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Action 5: ดึงรายชื่อห้องเรียนจาก MisSchool (Sync Classrooms)
if ($action === 'sync-classrooms') {
    try {
        $conn = getDbConnection('Tabain', $dbHost, $dbUser, $dbPass);
        $sql = "SELECT DISTINCT 
                    LavelName, Year, Room, รอบ AS Shift, DepartName
                FROM Tabain.dbo.v_StudentAll
                WHERE Year <> '-' AND Room <> '-' AND Year IS NOT NULL AND Room IS NOT NULL
                ORDER BY LavelName, Year, Room";

        $stmt = $conn->query($sql);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'count' => count($rooms),
            'classrooms' => $rooms,
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Sync classrooms failed: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Action 6: ดึงรายชื่อนักศึกษาจาก MisSchool (Sync Students)
if ($action === 'sync-students') {
    $year = $input['year'] ?? $_GET['year'] ?? null;
    $room = $input['room'] ?? $_GET['room'] ?? null;
    $limit = (int)($input['limit'] ?? $_GET['limit'] ?? 500);

    try {
        $conn = getDbConnection('Tabain', $dbHost, $dbUser, $dbPass);
        $sql = "SELECT TOP {$limit}
                    sn.StdNo, s.IdNo, s.PreFixNo, s.Fname, s.Lname, s.Email,
                    r.LavelNo, r.DepartNo, r.Year, r.Room
                FROM Tabain.dbo.tblStudentNumber sn
                INNER JOIN Tabain.dbo.tblStudent s ON sn.StdNoRef = s.StdNoRef
                LEFT JOIN Tabain.dbo.tblStudentRoom r ON sn.StdNo = r.StdNo
                WHERE s.IsUse = '1'";

        if ($year !== null && $year !== '') {
            $sql .= " AND r.Year = " . $conn->quote($year);
        }
        if ($room !== null && $room !== '') {
            $sql .= " AND r.Room = " . $conn->quote($room);
        }

        $sql .= " ORDER BY sn.StdNo ASC";

        $stmt = $conn->query($sql);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map(function($st) {
            return [
                'std_no' => trim($st['StdNo']),
                'citizen_id' => trim($st['IdNo']),
                'full_name' => trim(($st['Fname'] ?? '') . ' ' . ($st['Lname'] ?? '')),
                'level_no' => $st['LavelNo'],
                'depart_no' => $st['DepartNo'],
                'year' => $st['Year'],
                'room' => $st['Room'],
                'email' => $st['Email'] ?? null,
            ];
        }, $students);

        echo json_encode([
            'success' => true,
            'count' => count($data),
            'students' => $data,
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Sync students failed: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Invalid action endpoint'], JSON_UNESCAPED_UNICODE);
