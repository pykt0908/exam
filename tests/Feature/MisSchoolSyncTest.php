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
}
