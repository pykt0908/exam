# คู่มือระบบ Bridge API สำหรับเชื่อมต่อ MisSchool

คู่มือนี้อธิบายสถาปัตยกรรมการทำงาน วิธีติดตั้ง และวิธีเรียกใช้งาน **Bridge API** ผ่านโดเมน:
🌐 **`https://bridge-technosrc-api.offriend.net`**

เพื่อแก้ปัญหา Shared Hosting เดิมที่ถูกบล็อกพอร์ต โดยใช้โฮสต์ตัวที่สองเป็นตัวกลางรับคำขอจากระบบสอบแล้วดึงข้อมูลจาก SQL Server ของโรงเรียน

---

## สารบัญ
1. [ภาพรวมสถาปัตยกรรม (Architecture)](#1-ภาพรวมสถาปัตยกรรม)
2. [ส่วนที่ 1: โค้ดฝั่ง Bridge API (วางบน bridge-technosrc-api.offriend.net)](#2-ส่วนที่-1-โค้ดฝั่ง-bridge-api)
3. [ส่วนที่ 2: โค้ดฝั่ง Laravel ระบบสอบ (Client)](#3-ส่วนที่-2-โค้ดฝั่ง-laravel-ระบบสอบ)
4. [Endpoints และ Format ข้อมูล](#4-endpoints-และ-format-ข้อมูล)
5. [การทดสอบระบบ (Testing)](#5-การทดสอบระบบ)

---

## 1. ภาพรวมสถาปัตยกรรม

```
  [ผู้ใช้งาน / นักเรียน]
           │
           ▼
┌──────────────────────────────────────────────┐
│  ระบบสอบ Laravel                             │
│  (อยู่บน Shared Hosting เดิม)                │
└──────────────────────┬───────────────────────┘
                       │ HTTPS (Port 443 - ไม่ถูกบล็อก)
                       ▼
┌──────────────────────────────────────────────┐
│  Bridge API Server                           │
│  https://bridge-technosrc-api.offriend.net   │
│  (อยู่บน Shared Hosting ตัวที่ไม่บล็อก)       │
└──────────────────────┬───────────────────────┘
                       │ TCP Port 24252 (SQL Server)
                       ▼
┌──────────────────────────────────────────────┐
│  MisSchool Database Server                   │
│  stech.misschool.info:24252                  │
│  (Database: Tabain, Personal)                │
└──────────────────────────────────────────────┘
```

---

## 2. ส่วนที่ 1: โค้ดฝั่ง Bridge API
*(นำไปวางที่ `public_html` ของโดเมน `bridge-technosrc-api.offriend.net`)*

คุณสามารถสร้างไฟล์ **`index.php`** (ไฟล์เดียวจบ ไม่ต้องลงเฟรมเวิร์กเพิ่ม) เพื่อทำหน้าที่เป็น REST API ได้ทันที:

### ไฟล์: `public_html/index.php`
```php
<?php
header('Content-Type: application/json; charset=UTF-8');

// 1. ตั้งค่าความปลอดภัย (กำหนด Secret Key เพื่อป้องกันคนนอกมายิง API เล่น)
define('API_SECRET_KEY', 'technosri_secret_key_2026'); // เปลี่ยนเป็นคีย์ที่คุณต้องการ

// 2. ข้อมูลการเชื่อมต่อ MisSchool SQL Server
$dbHost = 'stech.misschool.info,24252';
$dbUser = 'misadmin';
$dbPass = 'meng@2520';

// 3. ตรวจสอบ API Key จาก Header (X-Bridge-Key)
$headers = getallheaders();
$clientKey = $headers['X-Bridge-Key'] ?? $headers['x-bridge-key'] ?? '';

if ($clientKey !== API_SECRET_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid API Key']);
    exit;
}

// 4. เชื่อมต่อฐานข้อมูลด้วย PDO sqlsrv
function getDbConnection($dbName, $host, $user, $pass) {
    try {
        $conn = new PDO("sqlsrv:Server=$host;Database=$dbName;TrustServerCertificate=true;Encrypt=false", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8
        ]);
        return $conn;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
}

// 5. อ่านข้อมูล JSON Request Body
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';

// --- ROUTING ACTIONS ---

// Action 1: ตรวจสอบสถานะ Server (Health Check)
if ($action === 'health' || empty($action)) {
    $conn = getDbConnection('Tabain', $dbHost, $dbUser, $dbPass);
    echo json_encode([
        'success' => true,
        'message' => 'Bridge API is operational and connected to MisSchool SQL Server!',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Action 2: ตรวจสอบการ Login ของนักเรียน (Verify Student)
if ($action === 'verify-student') {
    $username = trim($input['username'] ?? ''); // รหัสนักศึกษา (StdNo)
    $password = trim($input['password'] ?? ''); // เลขบัตรประชาชน (IdNo)

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing username or password']);
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
        // ซ่อนเลขบัตรประชาชนก่อนส่งกลับ
        unset($student['IdNo']);
        echo json_encode([
            'success' => true,
            'data' => [
                'std_no' => $student['StdNo'],
                'full_name' => trim($student['FName'] . ' ' . $student['LName']),
                'level_no' => $student['LavelNo'],
                'depart_no' => $student['DepartNo'],
                'year' => $student['Year'],
                'room' => $student['Room'],
                'email' => $student['Email']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'รหัสนักศึกษาหรือเลขประจำตัวประชาชนไม่ถูกต้อง หรือพ้นสภาพการศึกษา']);
    }
    exit;
}

// Action 3: ตรวจสอบการ Login ของครู/อาจารย์ (Verify Teacher)
if ($action === 'verify-teacher') {
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing username or password']);
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
            WHERE u.UserName = :username 
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
                'full_name' => trim($teacher['FName'] . ' ' . $teacher['LName']),
                'depart_no' => $teacher['DepartNo'],
                'is_admin' => $teacher['IsAdmin'] == '1'
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านอาจารย์ไม่ถูกต้อง']);
    }
    exit;
}

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Invalid action endpoint']);
```

---

## 3. ส่วนที่ 2: โค้ดฝั่ง Laravel ระบบสอบ (Client)
*(ทำงานบน Shared Hosting ตัวเดิม)*

บนระบบสอบ Laravel ของคุณ ไม่ต้องตั้งค่า SQL Server Connection อีกต่อไป แต่จะเรียกผ่านคำสั่ง `Http::post()` ปกติ:

### 3.1 ตั้งค่าใน `.env` ของ Laravel
```env
# ตั้งค่า Bridge API
MIS_BRIDGE_URL=https://bridge-technosrc-api.offriend.net
MIS_BRIDGE_KEY=technosri_secret_key_2026
```

### 3.2 ตัวอย่าง Service เชื่อมต่อ Bridge (`app/Services/MisBridgeService.php`)

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MisBridgeService
{
    protected string $url;
    protected string $key;

    public function __construct()
    {
        $this->url = rtrim(config('services.mis_bridge.url', env('MIS_BRIDGE_URL')), '/');
        $this->key = config('services.mis_bridge.key', env('MIS_BRIDGE_KEY'));
    }

    /**
     * ตรวจสอบสิทธิ์นักเรียน
     */
    public function verifyStudent(string $username, string $password): array
    {
        $response = Http::withHeaders([
            'X-Bridge-Key' => $this->key,
        ])->timeout(10)->post("{$this->url}/index.php?action=verify-student", [
            'username' => $username,
            'password' => $password,
        ]);

        return $response->json() ?? ['success' => false, 'message' => 'Network error'];
    }

    /**
     * ตรวจสอบสิทธิ์อาจารย์
     */
    public function verifyTeacher(string $username, string $password): array
    {
        $response = Http::withHeaders([
            'X-Bridge-Key' => $this->key,
        ])->timeout(10)->post("{$this->url}/index.php?action=verify-teacher", [
            'username' => $username,
            'password' => $password,
        ]);

        return $response->json() ?? ['success' => false, 'message' => 'Network error'];
    }
}
```

### 3.3 เรียกใช้งานใน Controller ของ Laravel (`StudentLoginController.php`)

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MisBridgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentLoginController extends Controller
{
    public function login(Request $request, MisBridgeService $bridge)
    {
        $request->validate([
            'username' => 'required|string', // รหัสนักศึกษา
            'password' => 'required|string', // เลขบัตรประชาชน
        ]);

        // 1. เรียก Bridge API เพื่อยืนยันตัวตน
        $result = $bridge->verifyStudent($request->username, $request->password);

        if (!$result['success']) {
            return back()->withErrors([
                'login_error' => $result['message'] ?? 'เข้าสู่ระบบไม่สำเร็จ',
            ])->withInput($request->only('username'));
        }

        $data = $result['data'];

        // 2. ซิงค์ข้อมูลนักเรียนลงตาราง users ในระบบสอบ
        $user = User::updateOrCreate(
            ['username' => $data['std_no']],
            [
                'name' => $data['full_name'],
                'email' => $data['email'] ?: "{$data['std_no']}@exam.local",
                'password' => bcrypt($request->password),
                'role' => 'student',
                'level_no' => $data['level_no'],
                'depart_no' => $data['depart_no'],
                'year' => $data['year'],
                'room' => $data['room'],
            ]
        );

        // 3. ล็อกอินเข้า Laravel
        Auth::login($user, $request->filled('remember'));
        $request->session()->regenerate();

        return redirect()->intended('/exam-dashboard');
    }
}
```

---

## 4. Endpoints และ Format ข้อมูล

### 1) ตรวจสอบสถานะระบบ (Health Check)
* **Method:** `GET`
* **URL:** `https://bridge-technosrc-api.offriend.net/index.php?action=health`
* **Header:** `X-Bridge-Key: technosri_secret_key_2026`
* **Response:**
  ```json
  {
    "success": true,
    "message": "Bridge API is operational and connected to MisSchool SQL Server!",
    "timestamp": "2026-10-12 19:50:00"
  }
  ```

### 2) ตรวจสอบนักเรียน (Verify Student)
* **Method:** `POST`
* **URL:** `https://bridge-technosrc-api.offriend.net/index.php?action=verify-student`
* **Header:** `X-Bridge-Key: technosri_secret_key_2026`
* **Request Body:**
  ```json
  {
    "username": "66209010001",
    "password": "1234567890123"
  }
  ```
* **Success Response:**
  ```json
  {
    "success": true,
    "data": {
      "std_no": "66209010001",
      "full_name": "สมชาย ใจดี",
      "level_no": "1",
      "depart_no": "201",
      "year": "1",
      "room": "1",
      "email": "somchai@gmail.com"
    }
  }
  ```

---

## 5. การทดสอบระบบ

หลังจากอัปโหลดไฟล์ `index.php` ขึ้นโดเมน `bridge-technosrc-api.offriend.net` แล้ว สามารถทดสอบด้วยคำสั่ง cURL ได้ทันที:

```bash
curl -X GET "https://bridge-technosrc-api.offriend.net/index.php?action=health" \
     -H "X-Bridge-Key: technosri_secret_key_2026"
```

เมื่อได้รับข้อความ `success: true` แสดงว่าระบบตัวกลางเชื่อมต่อพร้อมใช้งาน 100% แล้วครับ!
