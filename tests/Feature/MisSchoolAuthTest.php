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

    public function test_student_login_via_misschool_bridge_creates_local_user_and_authenticates(): void
    {
        // สร้างห้องเรียนรอไว้เพื่อทดสอบการจับคู่ห้องเรียนอัตโนมัติ
        $classroom = Classroom::create(['name' => 'ปวช.1/1 การบัญชี เช้า']);

        Http::fake([
            'https://bridge-technosri-api.offriend.net/index.php?action=verify-student' => Http::response([
                'success' => true,
                'data' => [
                    'std_no' => '66209010001',
                    'full_name' => 'นายสมชาย ใจดี',
                    'level_no' => '1',
                    'depart_no' => '2',
                    'year' => '1',
                    'room' => '1',
                    'email' => 'somchai@gmail.com',
                ],
            ], 200),
        ]);

        $response = $this->post('/login', [
            'login_identifier' => '66209010001',
            'password' => '1234567890123',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated();

        $user = User::where('student_code', '66209010001')->first();
        $this->assertNotNull($user);
        $this->assertEquals('นายสมชาย ใจดี', $user->name);
        $this->assertEquals('student', $user->role);
        $this->assertEquals('1234567890123', $user->citizen_id);
        $this->assertEquals('somchai@gmail.com', $user->email);
        $this->assertEquals($classroom->id, $user->classroom_id);
        $this->assertNotNull($user->mis_synced_at);
        $this->assertTrue((bool)$user->is_exam_eligible);
    }

    public function test_teacher_login_via_misschool_bridge_creates_local_teacher_and_authenticates(): void
    {
        Http::fake([
            'https://bridge-technosri-api.offriend.net/index.php?action=verify-student' => Http::response([
                'success' => false,
                'message' => 'ไม่พบข้อมูลนักเรียน',
            ], 200),
            'https://bridge-technosri-api.offriend.net/index.php?action=verify-teacher' => Http::response([
                'success' => true,
                'data' => [
                    'teacher_no' => 'T2005',
                    'username' => 'somchai_t',
                    'full_name' => 'อาจารย์สมชาย มั่นคง',
                    'depart_no' => '1',
                    'is_admin' => false,
                ],
            ], 200),
        ]);

        $response = $this->post('/login', [
            'login_identifier' => 'somchai_t',
            'password' => 'pass1234',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();

        $user = User::where('teacher_code', 'T2005')->first();
        $this->assertNotNull($user);
        $this->assertEquals('อาจารย์สมชาย มั่นคง', $user->name);
        $this->assertEquals('teacher', $user->role);
        $this->assertEquals('somchai_t', $user->username);
        $this->assertNotNull($user->mis_synced_at);
    }

    public function test_admin_teacher_login_via_misschool_bridge_assigned_admin_role(): void
    {
        Http::fake([
            'https://bridge-technosri-api.offriend.net/index.php?action=verify-student' => Http::response([
                'success' => false,
            ], 200),
            'https://bridge-technosri-api.offriend.net/index.php?action=verify-teacher' => Http::response([
                'success' => true,
                'data' => [
                    'teacher_no' => 'ADMIN01',
                    'username' => 'head_mis',
                    'full_name' => 'อาจารย์หัวหน้างาน แอดมิน',
                    'depart_no' => '1',
                    'is_admin' => true,
                ],
            ], 200),
        ]);

        $response = $this->post('/login', [
            'login_identifier' => 'head_mis',
            'password' => 'adminpass',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();

        $user = User::where('teacher_code', 'ADMIN01')->first();
        $this->assertNotNull($user);
        $this->assertEquals('admin', $user->role);
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
            'https://bridge-technosri-api.offriend.net/*' => Http::response([
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
            'https://bridge-technosri-api.offriend.net/*' => Http::response([
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
}
