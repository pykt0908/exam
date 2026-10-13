<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\User;
use App\Services\MisSchoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MisSchoolSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_check_misschool_health(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $serviceMock = $this->createMock(MisSchoolService::class);
        $serviceMock->method('checkHealth')->willReturn([
            'connected' => true,
            'source' => 'Direct TSQL Connection',
            'counts' => [
                'teachers' => 45,
                'classrooms' => 30,
                'students' => 1200,
            ],
            'server' => 'stech.misschool.info:24252',
            'timestamp' => now()->toIso8601String(),
        ]);
        $this->app->instance(MisSchoolService::class, $serviceMock);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.misschool.health'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.counts.teachers', 45);
    }

    public function test_admin_can_sync_classrooms(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $serviceMock = $this->createMock(MisSchoolService::class);
        $serviceMock->method('syncAllClassrooms')->willReturn([
            'success' => true,
            'created' => 20,
            'updated' => 5,
            'total' => 25,
            'source' => 'Direct TSQL Connection',
        ]);
        $this->app->instance(MisSchoolService::class, $serviceMock);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.misschool.sync-classrooms'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('created', 20)
            ->assertJsonPath('total', 25);
    }

    public function test_admin_can_sync_teachers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $serviceMock = $this->createMock(MisSchoolService::class);
        $serviceMock->method('syncAllTeachers')->willReturn([
            'success' => true,
            'created' => 10,
            'updated' => 35,
            'total' => 45,
            'source' => 'Direct TSQL Connection',
        ]);
        $this->app->instance(MisSchoolService::class, $serviceMock);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.misschool.sync-teachers'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('created', 10)
            ->assertJsonPath('updated', 35);
    }

    public function test_admin_can_sync_students(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $serviceMock = $this->createMock(MisSchoolService::class);
        $serviceMock->method('syncAllStudents')->willReturn([
            'success' => true,
            'created' => 150,
            'updated' => 300,
            'total' => 450,
            'source' => 'Direct TSQL Connection',
        ]);
        $this->app->instance(MisSchoolService::class, $serviceMock);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.misschool.sync-students'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('created', 150)
            ->assertJsonPath('updated', 300);
    }

    public function test_teacher_cannot_access_sync_endpoints(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $response = $this->actingAs($teacher)
            ->postJson(route('admin.misschool.sync-classrooms'));

        $response->assertForbidden();

        $response = $this->actingAs($teacher)
            ->postJson(route('admin.misschool.sync-teachers'));

        $response->assertForbidden();

        $response = $this->actingAs($teacher)
            ->getJson(route('admin.misschool.health'));

        $response->assertForbidden();
    }

    public function test_admin_check_health_with_bridge_format_succeeds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $serviceMock = $this->createMock(MisSchoolService::class);
        $serviceMock->method('checkHealth')->willReturn([
            'success' => true,
            'message' => 'Bridge API is operational and connected to MisSchool SQL Server!',
            'stats' => [
                'active_teachers' => 56,
                'active_students' => 13784,
            ],
            'timestamp' => '2026-10-13 10:41:38',
        ]);
        $this->app->instance(MisSchoolService::class, $serviceMock);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.misschool.health'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stats.active_teachers', 56)
            ->assertJsonPath('stats.active_students', 13784);
    }

    public function test_sync_student_user_updates_existing_student_by_citizen_id_without_duplicate_error(): void
    {
        $existing = User::factory()->create([
            'student_code' => '8852',
            'citizen_id' => '3480100490060',
            'name' => 'ศศิธร เดิม',
            'role' => 'student',
        ]);

        $service = app(MisSchoolService::class);
        $syncedUser = $service->syncStudentUser([
            'std_no' => '0008852',
            'full_name' => 'ศศิธร พรมจักร์',
            'citizen_id' => '3480100490060',
            'level_no' => '3',
            'depart_no' => '01',
            'year' => '2',
            'room' => '1',
        ], '3480100490060');

        $this->assertEquals($existing->id, $syncedUser->id);
        $this->assertEquals('ศศิธร พรมจักร์', $syncedUser->name);
        $this->assertEquals('0008852', $syncedUser->student_code);
        $this->assertEquals('3480100490060', $syncedUser->citizen_id);
    }

    public function test_admin_can_cleanup_inactive_students(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Inactive historical student with no classroom
        $inactiveStudent = User::factory()->create([
            'role' => 'student',
            'classroom_id' => null,
            'year' => '-',
            'room' => '-',
            'mis_synced_at' => now(),
        ]);

        // Active student with classroom
        $classroom = Classroom::create(['name' => 'ปวช.1/1 การบัญชี เช้า']);
        $activeStudent = User::factory()->create([
            'role' => 'student',
            'classroom_id' => $classroom->id,
            'year' => '1',
            'room' => '1',
            'mis_synced_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.misschool.cleanup-students'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('deleted', 1);

        $this->assertDatabaseMissing('users', ['id' => $inactiveStudent->id]);
        $this->assertDatabaseHas('users', ['id' => $activeStudent->id]);
    }

    public function test_sync_student_handles_invalid_or_hyphen_email_gracefully(): void
    {
        $service = app(MisSchoolService::class);

        // Sync first student with '-' email
        $student1 = $service->syncStudentUser([
            'std_no' => '99001',
            'full_name' => 'นักเรียน หนึ่ง',
            'citizen_id' => '1111111111111',
            'email' => '-',
            'level_no' => '1',
            'depart_no' => '01',
            'year' => '1',
            'room' => '1',
        ], '1111111111111');

        // Sync second student with same '-' email
        $student2 = $service->syncStudentUser([
            'std_no' => '99002',
            'full_name' => 'นักเรียน สอง',
            'citizen_id' => '2222222222222',
            'email' => '-',
            'level_no' => '1',
            'depart_no' => '01',
            'year' => '1',
            'room' => '1',
        ], '2222222222222');

        $this->assertEquals('99001@exam.local', $student1->email);
        $this->assertEquals('99002@exam.local', $student2->email);
    }
}
