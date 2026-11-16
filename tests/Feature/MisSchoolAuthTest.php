<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Classroom;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MisSchoolAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'misschool.bridge.url' => 'https://bridge.test',
            'misschool.bridge.key' => 'test-key',
        ]);
    }

    public function test_student_login_via_local_database_authenticates(): void
    {
        $classroom = Classroom::create(['name' => 'ปวช.1/1 การบัญชี เช้า']);

        $student = User::create([
            'name' => 'นายสมชาย ใจดี',
            'username' => '66209010001',
            'student_code' => '66209010001',
            'citizen_id' => '1234567890123',
            'email' => 'somchai@gmail.com',
            'password' => Hash::make('1234567890123'),
            'role' => 'student',
            'classroom_id' => $classroom->id,
            'is_exam_eligible' => 1,
        ]);

        $response = $this->post('/login', [
            'login_identifier' => '66209010001',
            'password' => '1234567890123',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student);
    }

    public function test_teacher_login_via_local_database_authenticates(): void
    {
        $dept = Department::create(['name' => 'สาขาวิชาการบัญชี']);

        $teacher = User::create([
            'name' => 'อาจารย์สมชาย มั่นคง',
            'username' => 'somchai_t',
            'teacher_code' => 'T2005',
            'email' => 'somchai_t@test.com',
            'password' => Hash::make('pass1234'),
            'role' => 'teacher',
            'department_id' => $dept->id,
        ]);

        $response = $this->post('/login', [
            'login_identifier' => 'somchai_t',
            'password' => 'pass1234',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($teacher);
    }

    public function test_admin_teacher_login_via_local_database_assigned_admin_role(): void
    {
        $admin = User::create([
            'name' => 'อาจารย์หัวหน้างาน แอดมิน',
            'username' => 'head_mis',
            'teacher_code' => 'ADMIN01',
            'email' => 'admin@test.com',
            'password' => Hash::make('adminpass'),
            'role' => 'admin',
        ]);

        $response = $this->post('/login', [
            'login_identifier' => 'head_mis',
            'password' => 'adminpass',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_fallback_to_local_user_when_misschool_verification_fails(): void
    {
        // สร้างผู้ใช้ Admin ท้องถิ่น
        $localAdmin = User::create([
            'name' => 'Local SuperAdmin',
            'email' => 'localadmin@stech.ac.th',
            'password' => Hash::make('local_secret'),
            'role' => 'admin',
        ]);

        Http::fake([
            'https://bridge.test/*' => Http::response([
                'success' => false,
                'message' => 'Not found in MisSchool',
            ], 200),
        ]);

        $response = $this->post('/login', [
            'login_identifier' => 'localadmin@stech.ac.th',
            'password' => 'local_secret',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($localAdmin);
    }

    public function test_invalid_credentials_returns_error(): void
    {
        Http::fake([
            'https://bridge.test/*' => Http::response([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 200),
        ]);

        $response = $this->post('/login', [
            'login_identifier' => 'unknown_user',
            'password' => 'wrong_pass',
        ]);

        $response->assertSessionHasErrors('login_identifier');
        $this->assertGuest();
    }

    public function test_teacher_login_preserves_existing_department_and_does_not_misassign_admin_depart_no(): void
    {
        $logisticsDept = Department::create(['name' => 'สาขาวิชาการจัดการโลจิสติกส์และซัพพลายเชน']);
        $mechanicDept = Department::create(['name' => 'สาขาอุตสาหกรรมเทคนิคเครื่องกล']);

        $teacher = User::create([
            'name' => 'โสภณัฐ ราชมี',
            'teacher_code' => 'T56005',
            'username' => 'T56005',
            'email' => 'T56005@exam.local',
            'password' => Hash::make('password123'),
            'role' => 'teacher',
            'department_id' => $logisticsDept->id,
        ]);

        Http::fake([
            'https://bridge.test/index.php?action=verify-student' => Http::response([
                'success' => false,
            ], 200),
            'https://bridge.test/index.php?action=verify-teacher' => Http::response([
                'success' => true,
                'data' => [
                    'teacher_no' => 'T56005',
                    'username' => 'T56005',
                    'full_name' => 'โสภณัฐ ราชมี',
                    'depart_no' => '07', // ใน MisSchool 07 คือฝ่ายทะเบียนและธุรการ
                    'is_admin' => false,
                ],
            ], 200),
        ]);

        $response = $this->post('/login', [
            'login_identifier' => 'T56005',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();

        $teacher->refresh();
        // ต้องคงเดิมเป็นโลจิสติกส์ ไม่ถูกเขียนทับเป็นเครื่องกล (id 7)
        $this->assertEquals($logisticsDept->id, $teacher->department_id);
    }
}
