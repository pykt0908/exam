<?php
/**
 * MisSchool Bridge API Server
 * สำหรับนำไปวางที่ public_html ของโดเมน bridge-technosri-api.offriend.net
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
        $conn = getDbConnection('Tabain', $dbHost, $dbUser, $dbPass);
        echo json_encode([
            'success' => true,
            'message' => 'Bridge API is operational and connected to MisSchool SQL Server!',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
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
    $username = trim($input['username'] ?? ''); // รหัสนักศึกษา (StdNo)
    $password = trim($input['password'] ?? ''); // เลขบัตรประชาชน (IdNo)

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing username or password'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $conn = getDbConnection('Tabain', $dbHost, $dbUser, $dbPass);
    $sql = "SELECT TOP 1 
                s.StdNo, s.IdNo, s.PrefixNo, s.FName, s.LName, s.Email,
                r.LavelNo, r.DepartNo, r.Year, r.Room, r.StatusNo
            FROM Tabain.dbo.tblStudent s
            LEFT JOIN Tabain.dbo.tblStudentRoom r ON s.StdNo = r.StdNo
            WHERE s.StdNo = :username 
              AND s.IdNo = :password
              AND r.StatusNo = '1'";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':username' => $username, ':password' => $password]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        unset($student['IdNo']);
        echo json_encode([
            'success' => true,
            'data' => [
                'std_no' => $student['StdNo'],
                'full_name' => trim(($student['FName'] ?? '') . ' ' . ($student['LName'] ?? '')),
                'level_no' => $student['LavelNo'],
                'depart_no' => $student['DepartNo'],
                'year' => $student['Year'],
                'room' => $student['Room'],
                'email' => $student['Email']
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'รหัสนักศึกษาหรือเลขประจำตัวประชาชนไม่ถูกต้อง หรือพ้นสภาพการศึกษา'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Action 3: ตรวจสอบการ Login ของครู/อาจารย์ (Verify Teacher)
if ($action === 'verify-teacher') {
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing username or password'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $conn = getDbConnection('Personal', $dbHost, $dbUser, $dbPass);
    $sql = "SELECT TOP 1
                u.UserName, u.IsAdmin,
                t.TeacherNo, t.FName, t.LName,
                i.DepartNo
            FROM Personal.dbo.tblUserName u
            INNER JOIN Personal.dbo.tblTeacherHis t ON u.TeacherNo = t.TeacherNo
            LEFT JOIN Personal.dbo.tblTeacherInfo i ON t.TeacherNo = i.TeacherNo
            WHERE (u.UserName = :username OR t.TeacherNo = :username)
              AND u.Password = :password
              AND t.IsUse = '1'
              AND ISNULL(i.IsStatusOut, '0') = '0'";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':username' => $username, ':password' => $password]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($teacher) {
        echo json_encode([
            'success' => true,
            'data' => [
                'teacher_no' => $teacher['TeacherNo'],
                'username' => $teacher['UserName'],
                'full_name' => trim(($teacher['FName'] ?? '') . ' ' . ($teacher['LName'] ?? '')),
                'depart_no' => $teacher['DepartNo'],
                'is_admin' => ($teacher['IsAdmin'] == '1')
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'ชื่อผู้ใช้หรือรหัสผ่านอาจารย์ไม่ถูกต้อง หรือพ้นสภาพการปฏิบัติหน้าที่'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Invalid action endpoint'], JSON_UNESCAPED_UNICODE);
