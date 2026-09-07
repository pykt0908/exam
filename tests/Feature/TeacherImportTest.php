<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TeacherImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;

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
            'name' => 'Regular Teacher',
            'email' => 'teacher@test.com',
            'password' => bcrypt('password'),
            'role' => 'teacher',
        ]);
    }

    public function test_admin_can_download_teacher_template(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.users.import-teacher-template'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="teacher_import_template.csv"');
    }

    public function test_teacher_cannot_download_teacher_template(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('admin.users.import-teacher-template'));

        $response->assertStatus(403);
    }

    public function test_admin_can_import_teachers_from_csv(): void
    {
        $csvContent = "ชื่อ-นามสกุล,รหัสประจำตัวครู,แผนกวิชา/หมวดวิชา,รหัสผ่าน,บทบาทเสริม\n"
            . "อาจารย์สมชาย ใจดี,T1001,แผนกวิชาคอมพิวเตอร์ธุรกิจ,pass123,หัวหน้าสาขา\n"
            . "อาจารย์สมศรี มีสุข,T1002,แผนกวิชาการบัญชี,secret456,หัวหน้างานวัดและประเมินผล\n"
            . "อาจารย์สุรชัย รักสอน,T1003,หมวดวิชาสามัญสัมพันธ์,,รองผู้อำนวยการฝ่ายวิชาการ\n";

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('admin.users.import-teachers'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.users.index', ['role' => 'staff']));
        $response->assertSessionHas('success');

        // Check department creation
        $this->assertDatabaseHas('departments', ['name' => 'แผนกวิชาคอมพิวเตอร์ธุรกิจ']);
        $this->assertDatabaseHas('departments', ['name' => 'แผนกวิชาการบัญชี']);
        $this->assertDatabaseHas('departments', ['name' => 'หมวดวิชาสามัญสัมพันธ์']);

        $deptComp = Department::where('name', 'แผนกวิชาคอมพิวเตอร์ธุรกิจ')->first();

        // Check teacher 1
        $this->assertDatabaseHas('users', [
            'name' => 'อาจารย์สมชาย ใจดี',
            'teacher_code' => 'T1001',
            'role' => 'teacher',
            'department_id' => $deptComp->id,
            'is_exam_eligible' => 1,
        ]);
        $teacher1 = User::where('teacher_code', 'T1001')->first();
        $this->assertTrue(Hash::check('pass123', $teacher1->password));
        $this->assertEquals(['department_head'], json_decode($teacher1->academic_role, true));

        // Check teacher 2
        $teacher2 = User::where('teacher_code', 'T1002')->first();
        $this->assertTrue(Hash::check('secret456', $teacher2->password));
        $this->assertEquals(['evaluation_head'], json_decode($teacher2->academic_role, true));

        // Check teacher 3 (default password fallback when blank: since T1003 is 5 chars < 6, fallback is 123456)
        $teacher3 = User::where('teacher_code', 'T1003')->first();
        $this->assertTrue(Hash::check('123456', $teacher3->password));
        $this->assertEquals(['academic_deputy'], json_decode($teacher3->academic_role, true));
    }

    public function test_teacher_code_used_as_default_password_when_at_least_6_chars(): void
    {
        $csvContent = "ชื่อ-นามสกุล,รหัสประจำตัวครู,แผนกวิชา/หมวดวิชา,รหัสผ่าน,บทบาทเสริม\n"
            . "อาจารย์ยาว พอดี,TEA12345,แผนกวิชาช่างยนต์,,\n";

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('admin.users.import-teachers'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.users.index', ['role' => 'staff']));

        $teacher = User::where('teacher_code', 'TEA12345')->first();
        $this->assertNotNull($teacher);
        $this->assertTrue(Hash::check('TEA12345', $teacher->password));
    }

    public function test_import_updates_existing_teacher_without_overwriting_password_if_blank(): void
    {
        $existing = User::create([
            'name' => 'อาจารย์เดิม',
            'teacher_code' => 'T9999',
            'password' => Hash::make('original_pass'),
            'role' => 'teacher',
        ]);

        $csvContent = "ชื่อ-นามสกุล,รหัสประจำตัวครู,แผนกวิชา/หมวดวิชา,รหัสผ่าน,บทบาทเสริม\n"
            . "อาจารย์ชื่อใหม่,T9999,แผนกวิชาไฟฟ้ากำลัง,,หัวหน้าสาขา\n";

        $file = UploadedFile::fake()->createWithContent('update_teachers.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('admin.users.import-teachers'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.users.index', ['role' => 'staff']));

        $updated = User::where('id', $existing->id)->first();
        $this->assertEquals('อาจารย์ชื่อใหม่', $updated->name);
        $this->assertEquals(['department_head'], json_decode($updated->academic_role, true));
        // Password should remain original
        $this->assertTrue(Hash::check('original_pass', $updated->password));
    }

    public function test_admin_can_import_teachers_from_excel_file(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'ชื่อ-นามสกุล');
        $sheet->setCellValue('B1', 'รหัสประจำตัวครู');
        $sheet->setCellValue('C1', 'แผนกวิชา/หมวดวิชา');
        $sheet->setCellValue('D1', 'รหัสผ่าน');
        $sheet->setCellValue('E1', 'บทบาทเสริม');

        $sheet->setCellValue('A2', 'อาจารย์ เอ็กเซล');
        $sheet->setCellValue('B2', 'TEXCEL01');
        $sheet->setCellValue('C2', 'แผนกวิชาเทคโนโลยีสารสนเทศ');
        $sheet->setCellValue('D2', 'excelPass99');
        $sheet->setCellValue('E2', 'หัวหน้าสาขา');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_teacher_xlsx_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $file = new UploadedFile(
            $tempPath,
            'teachers.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->admin)->post(route('admin.users.import-teachers'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.users.index', ['role' => 'staff']));
        $this->assertDatabaseHas('users', [
            'name' => 'อาจารย์ เอ็กเซล',
            'teacher_code' => 'TEXCEL01',
            'role' => 'teacher',
        ]);

        $teacher = User::where('teacher_code', 'TEXCEL01')->first();
        $this->assertTrue(Hash::check('excelPass99', $teacher->password));
        $this->assertEquals(['department_head'], json_decode($teacher->academic_role, true));
    }

    public function test_import_fails_with_invalid_file_extension(): void
    {
        $file = UploadedFile::fake()->create('teachers.pdf', 100);

        $response = $this->actingAs($this->admin)->post(route('admin.users.import-teachers'), [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_import_reports_row_errors_when_name_or_code_missing(): void
    {
        $csvContent = "ชื่อ-นามสกุล,รหัสประจำตัวครู,แผนกวิชา/หมวดวิชา,รหัสผ่าน,บทบาทเสริม\n"
            . ",T1111,แผนกวิชาคอมพิวเตอร์ธุรกิจ,123456,\n"
            . "อาจารย์ไม่มีรหัส,,แผนกวิชาคอมพิวเตอร์ธุรกิจ,123456,\n"
            . "อาจารย์สมบูรณ์,T2222,แผนกวิชาคอมพิวเตอร์ธุรกิจ,123456,\n";

        $file = UploadedFile::fake()->createWithContent('partial_teachers.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('admin.users.import-teachers'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.users.index', ['role' => 'staff']));
        $response->assertSessionHasErrors('import_warning');

        $this->assertDatabaseHas('users', [
            'name' => 'อาจารย์สมบูรณ์',
            'teacher_code' => 'T2222',
        ]);
        $this->assertDatabaseMissing('users', [
            'name' => 'อาจารย์ไม่มีรหัส',
        ]);
    }

    public function test_import_handles_teacher_code_case_insensitively_and_updates_without_duplicate_error(): void
    {
        // Existing teacher has lowercase teacher_code 't69005'
        $existing = User::create([
            'name' => 'อาจารย์ปัญญา เดิม',
            'teacher_code' => 't69005',
            'password' => Hash::make('mypassword'),
            'role' => 'admin',
        ]);

        // CSV file has uppercase 'T69005'
        $csvContent = "ชื่อ-นามสกุล,รหัสประจำตัวครู,แผนกวิชา/หมวดวิชา,รหัสผ่าน,บทบาทเสริม\n"
            . "นายปัญญา โกตูม,T69005,แผนกวิชาคอมพิวเตอร์ธุรกิจ,,หัวหน้าสาขา\n";

        $file = UploadedFile::fake()->createWithContent('teacher_case.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('admin.users.import-teachers'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('admin.users.index', ['role' => 'staff']));
        $response->assertSessionHas('success');

        // Verify existing user was updated and not duplicated
        $updated = User::where('id', $existing->id)->first();
        $this->assertEquals('นายปัญญา โกตูม', $updated->name);
        $this->assertEquals(['department_head'], json_decode($updated->academic_role, true));
        $this->assertEquals(1, User::whereRaw('LOWER(teacher_code) = ?', ['t69005'])->count());
    }
}

