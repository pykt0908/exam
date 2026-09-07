<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Classroom;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    public function test_admin_can_import_students_from_csv(): void
    {
        $csvContent = "ชื่อ-นามสกุล,รหัสนักศึกษา,เลขบัตรประชาชน,ระดับชั้น\n"
            . "สมศักดิ์ รักเรียน,STD001,1234567890123,ปวช.1/1\n"
            . "สมหญิง จริงใจ,STD002,9876543210123,ปวช.1/1\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('admin.users.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.users.index', ['role' => 'student']));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'สมศักดิ์ รักเรียน',
            'student_code' => 'STD001',
            'citizen_id' => '1234567890123',
            'role' => 'student',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'สมหญิง จริงใจ',
            'student_code' => 'STD002',
            'citizen_id' => '9876543210123',
            'role' => 'student',
        ]);

        $this->assertDatabaseHas('classrooms', [
            'name' => 'ปวช.1/1',
        ]);

        // Verify password check
        $student = User::where('student_code', 'STD001')->first();
        $this->assertTrue(Hash::check('1234567890123', $student->password));
    }

    public function test_import_handles_semicolon_delimiter_and_padded_citizen_id(): void
    {
        // Notice 12-digit citizen ID (Excel stripped leading 0) and semicolon delimiter
        $csvContent = "ชื่อ-นามสกุล;รหัสนักศึกษา;เลขบัตรประชาชน;ระดับชั้น\n"
            . "กิตติพงษ์ มั่งมี;STD003;123456789012;ปวส.2/1\n";

        $file = UploadedFile::fake()->createWithContent('students_semi.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('admin.users.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.users.index', ['role' => 'student']));

        $this->assertDatabaseHas('users', [
            'name' => 'กิตติพงษ์ มั่งมี',
            'student_code' => 'STD003',
            'citizen_id' => '0123456789012', // padded to 13 digits
        ]);
    }

    public function test_import_updates_existing_student_without_error(): void
    {
        // Pre-create student
        $existing = User::create([
            'name' => 'ชื่อเดิม',
            'student_code' => 'STD004',
            'citizen_id' => '1111222233334',
            'password' => Hash::make('1111222233334'),
            'role' => 'student',
        ]);

        $csvContent = "ชื่อ-นามสกุล,รหัสนักศึกษา,เลขบัตรประชาชน,ระดับชั้น\n"
            . "ชื่อใหม่ นามสกุลใหม่,STD004,1111222233334,ปวช.2/2\n";

        $file = UploadedFile::fake()->createWithContent('update_students.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('admin.users.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.users.index', ['role' => 'student']));

        $this->assertDatabaseHas('users', [
            'id' => $existing->id,
            'name' => 'ชื่อใหม่ นามสกุลใหม่',
            'student_code' => 'STD004',
        ]);
    }

    public function test_admin_can_import_from_excel_file(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'ชื่อ-นามสกุล');
        $sheet->setCellValue('B1', 'รหัสนักศึกษา');
        $sheet->setCellValue('C1', 'เลขบัตรประชาชน');
        $sheet->setCellValue('D1', 'ระดับชั้น');

        $sheet->setCellValue('A2', 'นักเรียน เอ็กเซล');
        $sheet->setCellValue('B2', 'STD005');
        $sheet->setCellValue('C2', '5555666677778');
        $sheet->setCellValue('D2', 'ปวช.3/1');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_xlsx_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $file = new UploadedFile(
            $tempPath,
            'students.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->admin)->post(route('admin.users.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.users.index', ['role' => 'student']));
        $this->assertDatabaseHas('users', [
            'name' => 'นักเรียน เอ็กเซล',
            'student_code' => 'STD005',
            'citizen_id' => '5555666677778',
        ]);

        @unlink($tempPath);
    }

    public function test_large_batch_import_is_fast_and_does_not_timeout(): void
    {
        $lines = ["ชื่อ-นามสกุล,รหัสนักศึกษา,เลขบัตรประชาชน,ระดับชั้น"];
        for ($i = 1; $i <= 100; $i++) {
            $code = 'STD' . str_pad($i, 5, '0', STR_PAD_LEFT);
            $cid = '123' . str_pad($i, 10, '0', STR_PAD_LEFT);
            $lines[] = "นักเรียน คนที่ {$i},{$code},{$cid},ปวช.1/1";
        }
        $csvContent = implode("\n", $lines);

        $file = UploadedFile::fake()->createWithContent('students_100.csv', $csvContent);

        $start = microtime(true);
        $response = $this->actingAs($this->admin)->post(route('admin.users.import'), [
            'file' => $file,
        ]);
        $elapsed = microtime(true) - $start;

        $response->assertRedirect(route('admin.users.index', ['role' => 'student']));
        $this->assertEquals(100, User::where('role', 'student')->where('student_code', 'like', 'STD0%')->count());
        $this->assertLessThan(5.0, $elapsed); // Must finish within 5 seconds
    }

    public function test_admin_can_filter_students_by_classroom(): void
    {
        $room1 = Classroom::create(['name' => 'ปวช.1/1']);
        $room2 = Classroom::create(['name' => 'ปวช.1/2']);

        $s1 = User::create([
            'name' => 'เด็กห้องหนึ่ง',
            'student_code' => 'STD_R1',
            'citizen_id' => '1111111111111',
            'password' => bcrypt('1111111111111'),
            'role' => 'student',
            'classroom_id' => $room1->id,
        ]);

        $s2 = User::create([
            'name' => 'เด็กห้องสอง',
            'student_code' => 'STD_R2',
            'citizen_id' => '2222222222222',
            'password' => bcrypt('2222222222222'),
            'role' => 'student',
            'classroom_id' => $room2->id,
        ]);

        $s3 = User::create([
            'name' => 'เด็กไม่มีห้อง',
            'student_code' => 'STD_NONE',
            'citizen_id' => '3333333333333',
            'password' => bcrypt('3333333333333'),
            'role' => 'student',
            'classroom_id' => null,
        ]);

        // Filter room 1
        $res1 = $this->actingAs($this->admin)->get(route('admin.users.index', [
            'role' => 'student',
            'classroom_id' => $room1->id,
        ]));
        $res1->assertOk();
        $res1->assertSee('เด็กห้องหนึ่ง');
        $res1->assertDontSee('เด็กห้องสอง');
        $res1->assertDontSee('เด็กไม่มีห้อง');
        $res1->assertSee('รายชื่อนักศึกษา: ปวช.1/1 (1 คน)');

        // Filter unassigned
        $resUnassigned = $this->actingAs($this->admin)->get(route('admin.users.index', [
            'role' => 'student',
            'classroom_id' => 'unassigned',
        ]));
        $resUnassigned->assertOk();
        $resUnassigned->assertSee('เด็กไม่มีห้อง');
        $resUnassigned->assertDontSee('เด็กห้องหนึ่ง');
        $resUnassigned->assertDontSee('เด็กห้องสอง');

        // All rooms
        $resAll = $this->actingAs($this->admin)->get(route('admin.users.index', [
            'role' => 'student',
        ]));
        $resAll->assertOk();
        $resAll->assertSee('เด็กห้องหนึ่ง');
        $resAll->assertSee('เด็กห้องสอง');
        $resAll->assertSee('เด็กไม่มีห้อง');
    }
}
