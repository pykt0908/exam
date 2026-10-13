<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomShiftTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_can_create_classroom_with_explicit_afternoon_shift_without_word_in_name(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.classrooms.store'), [
            'name' => 'ปวส.1/1 การตลาด',
            'shift' => 'afternoon',
        ]);

        $response->assertRedirect(route('admin.classrooms.index'));

        $classroom = Classroom::where('name', 'ปวส.1/1 การตลาด')->first();
        $this->assertNotNull($classroom);
        $this->assertEquals('afternoon', $classroom->shift);
        $this->assertEquals('บ่าย', $classroom->shift_text);
    }

    public function test_can_update_classroom_to_remove_word_from_name_and_keep_afternoon_shift(): void
    {
        $classroom = Classroom::create([
            'name' => 'ปวส.1/1 การตลาด (บ่าย)',
            'shift' => 'afternoon',
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.classrooms.update', $classroom->id), [
            'name' => 'ปวส.1/1 การตลาด',
            'shift' => 'afternoon',
        ]);

        $response->assertRedirect(route('admin.classrooms.index'));

        $classroom->refresh();
        $this->assertEquals('ปวส.1/1 การตลาด', $classroom->name);
        $this->assertEquals('afternoon', $classroom->shift);
        $this->assertEquals('บ่าย', $classroom->shift_text);
    }

    public function test_filtering_by_shift_works_with_shift_column(): void
    {
        $morningRoom = Classroom::create([
            'name' => 'ปวช.1/1 ช่างยนต์',
            'shift' => 'morning',
        ]);

        $afternoonRoom = Classroom::create([
            'name' => 'ปวส.1/1 การตลาด',
            'shift' => 'afternoon',
        ]);

        // Filter afternoon
        $resAfternoon = $this->actingAs($this->admin)->get(route('admin.classrooms.index', ['shift' => 'บ่าย']));
        $resAfternoon->assertOk();
        $resAfternoon->assertSee('ปวส.1/1 การตลาด');
        $resAfternoon->assertDontSee('ปวช.1/1 ช่างยนต์');

        // Filter morning
        $resMorning = $this->actingAs($this->admin)->get(route('admin.classrooms.index', ['shift' => 'เช้า']));
        $resMorning->assertOk();
        $resMorning->assertSee('ปวช.1/1 ช่างยนต์');
        $resMorning->assertDontSee('ปวส.1/1 การตลาด');
    }

    public function test_morning_and_afternoon_classrooms_can_share_same_name(): void
    {
        $afternoon = Classroom::create([
            'name' => 'ปวช.3/4 โลจิสติกส์',
            'shift' => 'afternoon',
        ]);

        $morning = Classroom::create([
            'name' => 'ปวช.3/4 โลจิสติกส์ เช้า',
            'shift' => 'morning',
        ]);

        // Renaming morning to 'ปวช.3/4 โลจิสติกส์' should succeed because shift is different
        $response = $this->actingAs($this->admin)->put(route('admin.classrooms.update', $morning->id), [
            'name' => 'ปวช.3/4 โลจิสติกส์',
            'shift' => 'morning',
        ]);

        $response->assertRedirect(route('admin.classrooms.index'));
        $morning->refresh();
        $this->assertEquals('ปวช.3/4 โลจิสติกส์', $morning->name);
        $this->assertEquals('morning', $morning->shift);
        $this->assertEquals($afternoon->name, $morning->name);

        // But creating duplicate in same shift should fail validation
        $failResponse = $this->actingAs($this->admin)->post(route('admin.classrooms.store'), [
            'name' => 'ปวช.3/4 โลจิสติกส์',
            'shift' => 'morning',
        ]);
        $failResponse->assertSessionHasErrors('name');
    }
}
