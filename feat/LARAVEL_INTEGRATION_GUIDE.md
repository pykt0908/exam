# คู่มือการเชื่อมต่อ Laravel เข้ากับฐานข้อมูล MisSchool (Tabain)

คู่มือนี้แนะนำขั้นตอนการตั้งค่าและการดึงข้อมูลนักศึกษาจากฐานข้อมูลของ MisSchool (Microsoft SQL Server) มาใช้งานในระบบสอบที่พัฒนาด้วย **Laravel** โดยใช้:
- **Username:** รหัสนักศึกษา (`StdNo`)
- **Password:** เลขประจำตัวประชาชน 13 หลัก (`IdNo`)

---

## สารบัญ
1. [สิ่งที่ต้องเตรียมล่วงหน้า (Prerequisites)](#1-สิ่งที่ต้องเตรียมล่วงหน้า)
2. [การตั้งค่าการเชื่อมต่อใน Laravel (.env & database.php)](#2-การตั้งค่าการเชื่อมต่อใน-laravel)
3. [การสร้าง Eloquent Model สำหรับนักเรียน](#3-การสร้าง-eloquent-model)
4. [ระบบ Authentication สำหรับนักเรียนเข้าสอบ](#4-ระบบ-authentication)
5. [การดึงข้อมูลด้วย Query Builder (ทางเลือก)](#5-การดึงข้อมูลด้วย-query-builder)
6. [การดึงข้อมูลและระบบ Authentication สำหรับครู/อาจารย์ (Database Personal)](#7-การดึงข้อมูลและระบบ-authentication-สำหรับครูอาจารย์)
7. [ข้อควรระวังและ Best Practices](#8-ข้อควรระวังและ-best-practices)

---

## 1. สิ่งที่ต้องเตรียมล่วงหน้า

เนื่องจากฐานข้อมูลเป็น **Microsoft SQL Server** เซิร์ฟเวอร์ที่รัน PHP จำเป็นต้องเปิดใช้งาน Driver:
- `pdo_sqlsrv`
- `sqlsrv`

### สำหรับ Windows (XAMPP / Laragon / PHP Standalone):
1. ตรวจสอบว่ามีไฟล์ `php_pdo_sqlsrv_*.dll` ในโฟลเดอร์ `php/ext`
2. เปิดไฟล์ `php.ini` แล้วเพิ่ม/ปลดคอมเมนต์:
   ```ini
   extension=php_pdo_sqlsrv.dll
   extension=php_sqlsrv.dll
   ```
3. Restart Web Server (Apache/Nginx) แล้วทดสอบด้วยคำสั่ง:
   ```bash
   php -m | grep sqlsrv
   ```

### สำหรับ Linux / Docker:
ติดตั้งแพ็กเกจ `msodbcsql17` หรือ `msodbcsql18` และคอมไพล์ส่วนขยายผ่าน `pecl install pdo_sqlsrv`.

---

## 2. การตั้งค่าการเชื่อมต่อใน Laravel

แนะนำให้ตั้งค่าเป็น **Secondary Connection** เพื่อให้ระบบสอบยังคงใช้ฐานข้อมูลหลักของ Laravel (MySQL/PostgreSQL/SQLite) สำหรับเก็บข้อสอบและประวัติการสอบตามปกติ

### 2.1 เพิ่มค่าใน `.env`
เพิ่มค่าการเชื่อมต่อไปยังท้ายไฟล์ `.env` ของโปรเจกต์ Laravel:

```env
# MisSchool SQL Server Connection
DB_MISSCHOOL_HOST=stech.misschool.info
DB_MISSCHOOL_PORT=24252
DB_MISSCHOOL_DATABASE=Tabain
DB_MISSCHOOL_USERNAME=misadmin
DB_MISSCHOOL_PASSWORD=meng@2520
DB_MISSCHOOL_ENCRYPT=no
DB_MISSCHOOL_TRUST_SERVER_CERTIFICATE=true
```

> **หมายเหตุ:** หากระบบสอบรันอยู่ในวง LAN เดียวกันกับ SQL Server สามารถเปลี่ยน `DB_MISSCHOOL_HOST` และ `DB_MISSCHOOL_PORT` เป็น IP ภายในเครื่องเซิร์ฟเวอร์ได้

### 2.2 ตั้งค่าใน `config/database.php`
เพิ่ม Connection ชนิด `sqlsrv` ชื่อ `misschool` ในอาเรย์ `'connections'`:

```php
'connections' => [

    // ... connection อื่นๆ เช่น mysql, sqlite ...

    'misschool' => [
        'driver' => 'sqlsrv',
        'url' => env('DATABASE_URL'),
        'host' => env('DB_MISSCHOOL_HOST', 'stech.misschool.info'),
        'port' => env('DB_MISSCHOOL_PORT', '24252'),
        'database' => env('DB_MISSCHOOL_DATABASE', 'Tabain'),
        'username' => env('DB_MISSCHOOL_USERNAME', 'misadmin'),
        'password' => env('DB_MISSCHOOL_PASSWORD', 'meng@2520'),
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'encrypt' => env('DB_MISSCHOOL_ENCRYPT', 'no'),
        'trust_server_certificate' => env('DB_MISSCHOOL_TRUST_SERVER_CERTIFICATE', 'true'),
    ],

],
```

---

## 3. การสร้าง Eloquent Model

สร้าง Model สำหรับอ่านข้อมูลนักเรียนและห้องเรียน โดยระบุ `$connection = 'misschool'` เพื่อป้องกันไม่ให้กระทบกับตารางของ Laravel

### 3.1 Model นักเรียน (`app/Models/MisStudent.php`)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MisStudent extends Model
{
    // กำหนดให้ใช้ connection misschool
    protected $connection = 'misschool';
    protected $table = 'tblStudent';
    
    // Primary Key ของตาราง
    protected $primaryKey = 'StdNo';
    public $incrementing = false;
    protected $keyType = 'string';
    
    // ตารางนี้ไม่มี created_at, updated_at
    public $timestamps = false;

    // ซ่อนเลขบัตรประชาชนเมื่อแปลงเป็น JSON
    protected $hidden = [
        'IdNo',
    ];

    /**
     * ความสัมพันธ์กับตารางห้องเรียน
     */
    public function room()
    {
        return $this->hasOne(MisStudentRoom::class, 'StdNo', 'StdNo');
    }

    /**
     * ดึงชื่อ-นามสกุลเต็ม
     */
    public function getFullNameAttribute()
    {
        return trim("{$this->FName} {$this->LName}");
    }
}
```

### 3.2 Model ข้อมูลห้องเรียน/ชั้นปี (`app/Models/MisStudentRoom.php`)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MisStudentRoom extends Model
{
    protected $connection = 'misschool';
    protected $table = 'tblStudentRoom';
    
    protected $primaryKey = 'StdNo';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public function student()
    {
        return $this->belongsTo(MisStudent::class, 'StdNo', 'StdNo');
    }
}
```

---

## 4. ระบบ Authentication

วิธีที่แนะนำที่สุดสำหรับระบบสอบคือ **"ตรวจสอบรหัสผ่านกับ MisSchool แล้ว Sync ข้อมูลสร้าง Local User ในระบบสอบ"** 

**ข้อดีของวิธีนี้:**
1. สามารถใช้ระบบ Auth มาตรฐานของ Laravel (`Auth::login($user)`, Session, Sanctum, Middleware) ได้ตามปกติ
2. ตารางข้อสอบและการส่งคำตอบสามารถผูก Foreign Key กับ `users.id` ของระบบสอบได้โดยตรง
3. ปลอดภัย ไม่ต้องเปิด session ค้างไว้กับ SQL Server ของโรงเรียนตลอดเวลา

### 4.1 ตัวอย่าง Controller สำหรับ Login (`app/Http/Controllers/Auth/StudentLoginController.php`)

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MisStudent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentLoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.student-login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string', // รหัสนักศึกษา (StdNo)
            'password' => 'required|string', // เลขบัตรประชาชน (IdNo)
        ], [
            'username.required' => 'กรุณากรอกรหัสนักศึกษา',
            'password.required' => 'กรุณากรอกเลขประจำตัวประชาชน',
        ]);

        $stdNo = trim($request->username);
        $idNo = trim($request->password);

        // 1. ค้นหานักศึกษาในฐานข้อมูล MisSchool (Tabain)
        $student = MisStudent::with('room')
            ->where('StdNo', $stdNo)
            ->where('IdNo', $idNo)
            ->whereHas('room', function ($query) {
                // กรองเฉพาะสถานะ '1' = กำลังศึกษา
                $query->where('StatusNo', '1');
            })
            ->first();

        if (!$student) {
            return back()->withErrors([
                'login_error' => 'รหัสนักศึกษาหรือเลขประจำตัวประชาชนไม่ถูกต้อง หรือไม่มีสิทธิ์เข้าสอบ',
            ])->withInput($request->only('username'));
        }

        // 2. ซิงค์ข้อมูลลงตาราง users ในระบบสอบของ Laravel (Local DB)
        $user = User::updateOrCreate(
            ['username' => $student->StdNo],
            [
                'name' => $student->full_name,
                'email' => $student->Email ?: "{$student->StdNo}@exam.local",
                'password' => bcrypt($idNo), // บันทึก hash ไว้ในระบบสอบ
                'role' => 'student',
                'level_no' => $student->room->LavelNo ?? null,
                'depart_no' => $student->room->DepartNo ?? null,
                'year' => $student->room->Year ?? null,
                'room' => $student->room->Room ?? null,
            ]
        );

        // 3. เข้าสู่ระบบใน Laravel
        Auth::login($user, $request->filled('remember'));

        $request->session()->regenerate();

        return redirect()->intended('/exam-dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
```

### 4.2 การเพิ่มฟิลด์ในตาราง `users` ของ Laravel (Migration)
เพื่อเก็บระดับชั้นและแผนกวิชาของนักศึกษา ให้สร้าง Migration เพิ่มฟิลด์:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('username')->unique()->after('id');
    $table->string('role')->default('student');
    $table->string('level_no')->nullable();    // ระดับชั้น (เช่น ปวช./ปวส.)
    $table->string('depart_no')->nullable();   // แผนกวิชา
    $table->string('year')->nullable();        // ชั้นปี
    $table->string('room')->nullable();        // ห้องเรียน
});
```

---

## 5. การดึงข้อมูลด้วย Query Builder (ทางเลือก)

หากไม่ต้องการสร้าง Model สามารถเรียกคำสั่งตรงผ่าน `DB::connection('misschool')` ได้ทันที:

```php
use Illuminate\Support\Facades\DB;

// ค้นหานักศึกษา
$student = DB::connection('misschool')
    ->table('tblStudent as s')
    ->leftJoin('tblStudentRoom as r', 's.StdNo', '=', 'r.StdNo')
    ->select([
        's.StdNo',
        's.FName',
        's.LName',
        's.DateOfBirth',
        'r.LavelNo',
        'r.DepartNo',
        'r.Year',
        'r.Room'
    ])
    ->where('s.StdNo', $stdNo)
    ->where('s.IdNo', $idNo)
    ->where('r.StatusNo', '1')
    ->first();
```

---

## 7. การดึงข้อมูลและระบบ Authentication สำหรับครู/อาจารย์

นอกจากข้อมูลนักเรียนแล้ว ใน SQL Server เดียวกันยังมีฐานข้อมูลชื่อ **`Personal`** ซึ่งเก็บข้อมูลครูและบุคลากรทางการศึกษา สำหรับนำมาใช้ในระบบ **อาจารย์ผู้ออกข้อสอบ / ผู้คุมสอบ / แอดมิน** ได้อย่างสมบูรณ์

### 7.1 โครงสร้างตารางครูที่สำคัญในฐานข้อมูล `Personal`
1. **`Personal.dbo.tblTeacherHis`**: ข้อมูลประวัติครู
   - `TeacherNo`: รหัสประจำตัวครู (Primary Key)
   - `IdNo`: เลขประจำตัวประชาชน 13 หลัก
   - `PrefixNo`: คำนำหน้าชื่อ
   - `FName`, `LName`: ชื่อและนามสกุลภาษาไทย
   - `FNameEng`, `LNameEng`: ชื่อและนามสกุลภาษาอังกฤษ
   - `IsUse`: สถานะการทำงาน (`'1'` = ยังปฏิบัติหน้าที่)
2. **`Personal.dbo.tblTeacherInfo`**: ข้อมูลการสังกัด
   - `TeacherNo`: รหัสครู
   - `DepartNo`: รหัสแผนก/หมวดวิชาที่สังกัด
   - `IsStatusOut`: สถานะพ้นสภาพ (`'0'` = ยังอยู่, `'1'` = ลาออก/เกษียณ)
3. **`Personal.dbo.tblDepartTeacher`**: ชื่อแผนก/หมวดวิชา
   - `DepartNo`: รหัสแผนก
   - `DepartName`: ชื่อแผนก (เช่น หมวดวิชาช่างยนต์, แผนกคอมพิวเตอร์)
4. **`Personal.dbo.tblUserName`**: ตารางบัญชีผู้ใช้ระบบของครูใน MisSchool
   - `UserName`: ชื่อผู้ใช้
   - `Password`: รหัสผ่าน (Plain text)
   - `TeacherNo`: รหัสครู
   - `IsAdmin`: สถานะสิทธิ์แอดมิน (`'1'` = ใช่)

### 7.2 Eloquent Model สำหรับครู (`app/Models/MisTeacher.php`)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MisTeacher extends Model
{
    // กำหนด connection ชี้ไปที่ misschool
    protected $connection = 'misschool';
    // ระบุ database Personal นำหน้าชื่อตาราง
    protected $table = 'Personal.dbo.tblTeacherHis';
    
    protected $primaryKey = 'TeacherNo';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $hidden = [
        'IdNo',
    ];

    /**
     * ข้อมูลสังกัดแผนก
     */
    public function info()
    {
        return $this->hasOne(MisTeacherInfo::class, 'TeacherNo', 'TeacherNo');
    }

    /**
     * บัญชีผู้ใช้ในระบบ MisSchool
     */
    public function account()
    {
        return $this->hasOne(MisTeacherAccount::class, 'TeacherNo', 'TeacherNo');
    }

    public function getFullNameAttribute()
    {
        return trim("{$this->FName} {$this->LName}");
    }
}
```

### 7.3 Model บัญชีล็อกอินของครู (`app/Models/MisTeacherAccount.php`)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MisTeacherAccount extends Model
{
    protected $connection = 'misschool';
    protected $table = 'Personal.dbo.tblUserName';
    
    protected $primaryKey = 'UserName';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public function teacher()
    {
        return $this->belongsTo(MisTeacher::class, 'TeacherNo', 'TeacherNo');
    }
}
```

### 7.4 ตัวอย่าง Controller สำหรับการเข้าสู่ระบบของครู (`TeacherLoginController.php`)

สามารถเลือกระบบยืนยันตัวตนได้ 2 แบบ:
- **แบบที่ 1:** ใช้ Username & Password เดิมที่อาจารย์ใช้เข้า MisSchool
- **แบบที่ 2:** ใช้รหัสครู (`TeacherNo`) คู่กับเลขบัตรประชาชน (`IdNo`) (เหมือนนักเรียน)

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MisTeacher;
use App\Models\MisTeacherAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeacherLoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = trim($request->username);
        $password = trim($request->password);

        // วิธีที่ 1: ตรวจสอบผ่านบัญชี MisSchool (tblUserName)
        $account = MisTeacherAccount::with(['teacher.info'])
            ->where('UserName', $username)
            ->where('Password', $password)
            ->first();

        // หรือ วิธีที่ 2: ตรวจสอบผ่าน รหัสครู (TeacherNo) + เลขบัตรประชาชน (IdNo)
        /*
        $teacher = MisTeacher::with('info')
            ->where('TeacherNo', $username)
            ->where('IdNo', $password)
            ->where('IsUse', '1')
            ->first();
        */

        if (!$account || !$account->teacher || $account->teacher->IsUse !== '1') {
            return back()->withErrors(['login_error' => 'ชื่อผู้ใช้หรือรหัสผ่านอาจารย์ไม่ถูกต้อง']);
        }

        $teacher = $account->teacher;

        // ซิงค์สร้างหรืออัปเดต Local User ในระบบสอบ
        $user = User::updateOrCreate(
            ['username' => $account->UserName],
            [
                'name' => $teacher->full_name,
                'email' => "teacher_{$teacher->TeacherNo}@exam.local",
                'password' => bcrypt($password),
                'role' => $account->IsAdmin == '1' ? 'admin' : 'teacher',
                'depart_no' => $teacher->info->DepartNo ?? null,
            ]
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/teacher/dashboard');
    }
}
```

---

## 8. ข้อควรระวังและ Best Practices

1. **ห้ามรัน Migration บน Connection `misschool`:**
   - ตรวจสอบให้แน่ใจว่าไฟล์ Migration ทั้งหมดใช้ default connection (อย่าระบุ connection `misschool` ใน migration)
2. **Read-Only Operation:**
   - ให้ระบบสอบทำหน้าที่ **อ่านอย่างเดียว (SELECT)** เท่านั้น ไม่ควรเขียนคำสั่ง UPDATE/DELETE ใส่ตารางของ MisSchool เพื่อความปลอดภัยของข้อมูลสถานศึกษา
3. **การเข้าถึงข้ามฐานข้อมูลใน SQL Server:**
   - User `misadmin` มีสิทธิ์เข้าถึงทั้ง Database `Tabain` และ `Personal` สามารถเขียนคำสั่ง Join ข้ามฐานข้อมูลได้ทันที เช่น `Personal.dbo.tblTeacherHis`
4. **การดึงรูปภาพประจำตัว:**
   - สามารถแสดงรูปนักเรียนในหน้าระบบสอบได้โดยตรงผ่าน URL:
     ```html
     <img src="https://misphoto.misschool.info/pic/picstudent/{{ $schoolNo }}/{{ $student->StdNo }}.webp" alt="รูปนักศึกษา" />
     ```
5. **Network Latency & Caching:**
   - แนะนำให้แคชข้อมูลแผนกวิชา และรายชื่อครูไว้ใน Redis/Cache ของ Laravel เพื่อความเร็วในการโหลดหน้าระบบสอบ
