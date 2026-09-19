<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Subject;
use App\Models\Classroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectClassroomTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;
    private Subject $subject;
    private Classroom $room1;
    private Classroom $room2;
    private Classroom $room3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->teacher = User::create([
            'name' => 'Teacher User',
            'email' => 'teacher@test.com',
            'password' => bcrypt('password'),
            'role' => 'teacher',
        ]);

        $this->subject = Subject::create([
            'code' => 'TEST101',
            'name' => 'Test Subject',
            'teacher_id' => $this->teacher->id,
        ]);

        $this->room1 = Classroom::create(['name' => 'ปวช.1/1']);
        $this->room2 = Classroom::create(['name' => 'ปวช.1/2']);
        $this->room3 = Classroom::create(['name' => 'ปวช.1/3']);

        // Create students in room 1 and room 2
        User::create([
            'name' => 'Student 1-1',
            'student_code' => 'S101',
            'citizen_id' => '1111111111111',
            'password' => bcrypt('1111111111111'),
            'role' => 'student',
            'classroom_id' => $this->room1->id,
        ]);

        User::create([
            'name' => 'Student 2-1',
            'student_code' => 'S201',
            'citizen_id' => '2222222222222',
            'password' => bcrypt('2222222222222'),
            'role' => 'student',
            'classroom_id' => $this->room2->id,
        ]);

        User::create([
            'name' => 'Student 2-2',
            'student_code' => 'S202',
            'citizen_id' => '2222222222223',
            'password' => bcrypt('2222222222223'),
            'role' => 'student',
            'classroom_id' => $this->room2->id,
        ]);
    }

    public function test_can_add_multiple_classrooms_to_subject_at_once(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.subjects.students.store', $this->subject->id), [
            'classroom_ids' => [$this->room1->id, $this->room2->id],
        ]);

        $response->assertRedirect(route('admin.subjects.students.index', $this->subject->id));
        $response->assertSessionHas('success');

        // Verify that students from both rooms are enrolled
        $enrolledStudents = $this->subject->students;
        $this->assertCount(3, $enrolledStudents);
        $this->assertTrue($enrolledStudents->contains('student_code', 'S101'));
        $this->assertTrue($enrolledStudents->contains('student_code', 'S201'));
        $this->assertTrue($enrolledStudents->contains('student_code', 'S202'));
    }

    public function test_backward_compatibility_single_classroom_id(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.subjects.students.store', $this->subject->id), [
            'classroom_id' => $this->room1->id,
        ]);

        $response->assertRedirect(route('admin.subjects.students.index', $this->subject->id));
        $response->assertSessionHas('success');

        $enrolledStudents = $this->subject->students;
        $this->assertCount(1, $enrolledStudents);
        $this->assertTrue($enrolledStudents->contains('student_code', 'S101'));
    }

    public function test_requires_at_least_one_classroom(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.subjects.students.store', $this->subject->id), [
            'classroom_ids' => [],
        ]);

        $response->assertSessionHasErrors('classroom_ids');
    }

    public function test_can_add_individual_students_to_subject(): void
    {
        $student1 = User::where('student_code', 'S101')->first();
        $student2 = User::where('student_code', 'S201')->first();

        $response = $this->actingAs($this->admin)->post(route('admin.subjects.students.store', $this->subject->id), [
            'student_ids' => [$student1->id, $student2->id],
        ]);

        $response->assertSessionHas('success');
        $enrolled = $this->subject->fresh()->students;
        $this->assertCount(2, $enrolled);
        $this->assertTrue($enrolled->contains('id', $student1->id));
        $this->assertTrue($enrolled->contains('id', $student2->id));
        $this->assertFalse($enrolled->contains('student_code', 'S202'));
    }

    public function test_cannot_add_empty_student_ids(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.subjects.students.store', $this->subject->id), [
            'student_ids' => [],
        ]);

        $response->assertSessionHasErrors('student_ids');
    }

    public function test_subject_students_index_renders_with_individual_add_modal(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.subjects.students.index', $this->subject->id));
        $response->assertStatus(200);
        $response->assertSee('เพิ่มนักศึกษารายคน');
        $response->assertSee('id="addIndividualStudentModal"', false);
    }

    public function test_subject_students_classroom_view_renders_with_individual_add_modal(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.subjects.students.index', [
            'subject' => $this->subject->id,
            'classroom_id' => $this->room1->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee('เพิ่มนักศึกษารายคน');
        $response->assertSee('id="addIndividualStudentModal"', false);
    }
}
