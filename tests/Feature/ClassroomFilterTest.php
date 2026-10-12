<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Classroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Classroom $pvc1;
    private Classroom $pvc2;
    private Classroom $pvs1Morning;
    private Classroom $pvs2Afternoon;
    private Classroom $pvsSpecialDvi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->pvc1 = Classroom::create(['name' => 'ปวช.1 ช่างยนต์ 1']);
        $this->pvc2 = Classroom::create(['name' => 'ปวช.2 ช่างไฟฟ้า 2']);
        $this->pvs1Morning = Classroom::create(['name' => 'ปวส.1 คอมพิวเตอร์ธุรกิจ 1']);
        $this->pvs2Afternoon = Classroom::create(['name' => 'ปวส.2 เทคโนโลยีธุรกิจดิจิทัล 1 (บ่าย)']);
        $this->pvsSpecialDvi = Classroom::create(['name' => 'ปวส.2 บัญชี ทวิภาคี']);
    }

    public function test_classroom_level_and_year_accessors(): void
    {
        $this->assertEquals('ปวช.', $this->pvc1->level);
        $this->assertEquals(1, $this->pvc1->year);
        $this->assertEquals('ปี 1', $this->pvc1->year_label);
        $this->assertEquals('เช้า', $this->pvc1->shift_text);

        $this->assertEquals('ปวช.', $this->pvc2->level);
        $this->assertEquals(2, $this->pvc2->year);
        $this->assertEquals('ปี 2', $this->pvc2->year_label);

        $this->assertEquals('ปวส.', $this->pvs1Morning->level);
        $this->assertEquals(1, $this->pvs1Morning->year);
        $this->assertEquals('เช้า', $this->pvs1Morning->shift_text);

        $this->assertEquals('ปวส.', $this->pvs2Afternoon->level);
        $this->assertEquals(2, $this->pvs2Afternoon->year);
        $this->assertEquals('บ่าย', $this->pvs2Afternoon->shift_text);

        $this->assertEquals('ปวส.', $this->pvsSpecialDvi->level);
        $this->assertEquals(2, $this->pvsSpecialDvi->year);
        $this->assertEquals('เช้า', $this->pvsSpecialDvi->shift_text); // ทวิภาคี counts as เช้า
    }

    public function test_classroom_scopes(): void
    {
        $pvcRooms = Classroom::level('ปวช.')->get();
        $this->assertCount(2, $pvcRooms);

        $pvsRooms = Classroom::level('ปวส.')->get();
        $this->assertCount(3, $pvsRooms);

        $year1Rooms = Classroom::year(1)->get();
        $this->assertCount(2, $year1Rooms);

        $year2Rooms = Classroom::year(2)->get();
        $this->assertCount(3, $year2Rooms);

        $morningRooms = Classroom::shift('เช้า')->get();
        $this->assertCount(4, $morningRooms);

        $afternoonRooms = Classroom::shift('บ่าย')->get();
        $this->assertCount(1, $afternoonRooms);
    }

    public function test_admin_classrooms_index_filters(): void
    {
        $this->actingAs($this->admin);

        // Filter by level
        $response = $this->get(route('admin.classrooms.index', ['level' => 'ปวช.']));
        $response->assertOk();
        $response->assertSee('ปวช.1 ช่างยนต์ 1');
        $response->assertSee('ปวช.2 ช่างไฟฟ้า 2');
        $response->assertDontSee('ปวส.1 คอมพิวเตอร์ธุรกิจ 1');

        // Filter by year
        $response = $this->get(route('admin.classrooms.index', ['year' => '1']));
        $response->assertOk();
        $response->assertSee('ปวช.1 ช่างยนต์ 1');
        $response->assertSee('ปวส.1 คอมพิวเตอร์ธุรกิจ 1');
        $response->assertDontSee('ปวช.2 ช่างไฟฟ้า 2');

        // Filter by shift
        $response = $this->get(route('admin.classrooms.index', ['shift' => 'บ่าย']));
        $response->assertOk();
        $response->assertSee('ปวส.2 เทคโนโลยีธุรกิจดิจิทัล 1 (บ่าย)');
        $response->assertDontSee('ปวช.1 ช่างยนต์ 1');
    }

    public function test_admin_users_index_filters_students_by_level_and_year(): void
    {
        $this->actingAs($this->admin);

        $student1 = User::create([
            'name' => 'Student PVC',
            'student_code' => '65201001',
            'classroom_id' => $this->pvc1->id,
            'role' => 'student',
            'password' => bcrypt('password'),
        ]);

        $student2 = User::create([
            'name' => 'Student PVS Afternoon',
            'student_code' => '66301001',
            'classroom_id' => $this->pvs2Afternoon->id,
            'role' => 'student',
            'password' => bcrypt('password'),
        ]);

        // Filter students by level
        $response = $this->get(route('admin.users.index', ['role' => 'student', 'level' => 'ปวช.']));
        $response->assertOk();
        $response->assertSee('Student PVC');
        $response->assertDontSee('Student PVS Afternoon');

        // Filter students by shift
        $response = $this->get(route('admin.users.index', ['role' => 'student', 'shift' => 'บ่าย']));
        $response->assertOk();
        $response->assertSee('Student PVS Afternoon');
        $response->assertDontSee('Student PVC');
    }
}
