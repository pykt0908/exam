<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Subject;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class UserController extends Controller
{
    public function create()
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }
        $classrooms = \App\Models\Classroom::orderBy('name')->get();
        $departments = \App\Models\Department::orderBy('name')->get();
        return view('admin.users.create', compact('classrooms', 'departments'));
    }

    public function edit(User $user)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }
        $classrooms = \App\Models\Classroom::orderBy('name')->get();
        $departments = \App\Models\Department::orderBy('name')->get();
        return view('admin.users.edit', compact('user', 'classrooms', 'departments'));
    }

    public function index(Request $request)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }
        $role = $request->query('role');
        $classroom_id = $request->query('classroom_id');

        $classrooms = \App\Models\Classroom::withCount(['users as students_count' => function($q) {
            $q->where('role', 'student');
        }])->orderBy('name')->get();

        $departments = \App\Models\Department::orderBy('name')->get();

        $totalStudentsCount = User::where('role', 'student')->count();
        $unassignedStudentsCount = User::where('role', 'student')->whereNull('classroom_id')->count();

        $selectedClassroom = null;
        if ($classroom_id && $classroom_id !== 'unassigned') {
            $selectedClassroom = $classrooms->firstWhere('id', $classroom_id);
        }

        $query = User::with(['classroom', 'department'])
            ->withCount(['examAttempts' => function($query) {
                $query->where('status', 'completed');
            }]);

        if ($role === 'student') {
            $query->where('role', 'student');
            if ($classroom_id === 'unassigned') {
                $query->whereNull('classroom_id');
            } elseif ($classroom_id) {
                $query->where('classroom_id', $classroom_id);
            }
            $query->orderBy('student_code', 'asc');
        } elseif ($role === 'staff') {
            $query->whereIn('role', ['admin', 'teacher'])
                ->orderByRaw("
                    CASE 
                        WHEN role = 'admin' THEN 1
                        WHEN academic_role LIKE '%academic_deputy%' THEN 2
                        WHEN academic_role LIKE '%evaluation_head%' THEN 3
                        WHEN academic_role LIKE '%department_head%' THEN 4
                        ELSE 5
                    END ASC
                ")
                ->orderBy('name', 'asc');
        } else {
            if ($classroom_id === 'unassigned') {
                $query->whereNull('classroom_id');
            } elseif ($classroom_id) {
                $query->where('classroom_id', $classroom_id);
            }
            $query->orderByRaw("
                    CASE 
                        WHEN role = 'admin' THEN 1
                        WHEN academic_role LIKE '%academic_deputy%' THEN 2
                        WHEN academic_role LIKE '%evaluation_head%' THEN 3
                        WHEN academic_role LIKE '%department_head%' THEN 4
                        WHEN role = 'teacher' THEN 5
                        ELSE 6
                    END ASC
                ")
                ->orderBy('name', 'asc');
        }

        $users = $query->get();

        return view('admin.users.index', compact(
            'users', 
            'classrooms', 
            'departments', 
            'role', 
            'classroom_id', 
            'selectedClassroom', 
            'totalStudentsCount', 
            'unassignedStudentsCount'
        ));
    }

    public function store(Request $request)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:admin,teacher,student'],
            'academic_roles' => ['nullable', 'array'],
            'academic_roles.*' => ['in:academic_deputy,evaluation_head,department_head'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        if ($request->role === 'student') {
            $rules['student_code'] = ['required', 'string', 'max:13', 'unique:users'];
            $rules['classroom_id'] = ['required', 'exists:classrooms,id'];
            $rules['citizen_id'] = ['required', 'string', 'digits:13', 'unique:users'];
        } else {
            $rules['teacher_code'] = ['required', 'string', 'max:50', 'unique:users'];
            $rules['password'] = ['required', 'string', 'min:6'];
        }

        $request->validate($rules, [
            'student_code.required' => 'กรุณากรอกรหัสนักศึกษา',
            'student_code.unique' => 'รหัสนักศึกษานี้มีในระบบแล้ว',
            'student_code.max' => 'รหัสนักศึกษาต้องไม่เกิน 13 หลัก',
            'teacher_code.required' => 'กรุณากรอกรหัสประจำตัวครู',
            'teacher_code.unique' => 'รหัสประจำตัวครูนี้มีในระบบแล้ว',
            'teacher_code.max' => 'รหัสประจำตัวครูต้องไม่เกิน 50 ตัวอักษร',
            'citizen_id.required' => 'กรุณากรอกเลขประจำตัวประชาชน',
            'citizen_id.unique' => 'เลขประจำตัวประชาชนนี้มีในระบบแล้ว',
            'citizen_id.digits' => 'เลขประจำตัวประชาชนต้องมี 13 หลัก',
            'password.required' => 'กรุณากรอกรหัสผ่านสำหรับอาจารย์/แอดมิน',
            'password.min' => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร',
            'department_id.exists' => 'ไม่พบหมวดวิชา/แผนกวิชาที่เลือก',
            'photo.image' => 'ไฟล์รูปต้องเป็นรูปภาพเท่านั้น',
            'photo.mimes' => 'รูปภาพรองรับเฉพาะ jpg, jpeg, png, webp',
            'photo.max' => 'ขนาดรูปภาพต้องไม่เกิน 2MB',
        ]);

        $userData = [
            'name' => $request->name,
            'role' => $request->role,
        ];

        if ($request->hasFile('photo')) {
            $userData['photo'] = $request->file('photo')->store('student_photos', 'public');
        }

        if ($request->role === 'student') {
            $userData['student_code'] = $request->student_code;
            $userData['classroom_id'] = $request->classroom_id;
            $userData['citizen_id'] = $request->citizen_id;
            $userData['password'] = Hash::make($request->citizen_id);
            $userData['email'] = null;
            $userData['teacher_code'] = null;
            $userData['academic_role'] = null;
            $userData['department_id'] = null;
        } else {
            $academicRolesInput = $request->input('academic_roles', $request->input('academic_role'));
            $academicRoleValue = null;
            if (is_array($academicRolesInput)) {
                $cleaned = array_values(array_filter($academicRolesInput));
                $academicRoleValue = !empty($cleaned) ? json_encode($cleaned, JSON_UNESCAPED_UNICODE) : null;
            } elseif (is_string($academicRolesInput) && trim($academicRolesInput) !== '') {
                $academicRoleValue = json_encode([trim($academicRolesInput)], JSON_UNESCAPED_UNICODE);
            }

            $userData['email'] = null;
            $userData['password'] = Hash::make($request->password);
            $userData['teacher_code'] = $request->teacher_code;
            $userData['academic_role'] = $academicRoleValue;
            $userData['department_id'] = $request->department_id;
            $userData['student_code'] = null;
            $userData['classroom_id'] = null;
            $userData['citizen_id'] = null;
        }

        $createdUser = User::create($userData);
        if ($createdUser->isStudent() && $createdUser->classroom_id) {
            $createdUser->syncClassroomSubjects();
        }

        $redirectRole = $request->role === 'student' ? 'student' : 'staff';
        return redirect()->route('admin.users.index', ['role' => $redirectRole])->with('success', 'เพิ่มข้อมูลผู้ใช้งานเรียบร้อยแล้ว');
    }

    public function update(Request $request, User $user)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:admin,teacher,student'],
            'academic_roles' => ['nullable', 'array'],
            'academic_roles.*' => ['in:academic_deputy,evaluation_head,department_head'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ];

        if ($request->role === 'student') {
            $rules['student_code'] = ['required', 'string', 'max:13', 'unique:users,student_code,' . $user->id];
            $rules['classroom_id'] = ['required', 'exists:classrooms,id'];
            $rules['citizen_id'] = ['required', 'string', 'digits:13', 'unique:users,citizen_id,' . $user->id];
        } else {
            $rules['teacher_code'] = ['required', 'string', 'max:50', 'unique:users,teacher_code,' . $user->id];
            $rules['password'] = ['nullable', 'string', 'min:6'];
        }

        $request->validate($rules, [
            'student_code.required' => 'กรุณากรอกรหัสนักศึกษา',
            'student_code.unique' => 'รหัสนักศึกษานี้มีในระบบแล้ว',
            'student_code.max' => 'รหัสนักศึกษาต้องไม่เกิน 13 หลัก',
            'teacher_code.required' => 'กรุณากรอกรหัสประจำตัวครู',
            'teacher_code.unique' => 'รหัสประจำตัวครูนี้มีในระบบแล้ว',
            'teacher_code.max' => 'รหัสประจำตัวครูต้องไม่เกิน 50 ตัวอักษร',
            'citizen_id.required' => 'กรุณากรอกเลขประจำตัวประชาชน',
            'citizen_id.unique' => 'เลขประจำตัวประชาชนนี้มีในระบบแล้ว',
            'citizen_id.digits' => 'เลขประจำตัวประชาชนต้องมี 13 หลัก',
            'password.min' => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร',
            'department_id.exists' => 'ไม่พบหมวดวิชา/แผนกวิชาที่เลือก',
        ]);

        $userData = [
            'name' => $request->name,
            'role' => $request->role,
        ];

        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }
            $userData['photo'] = $request->file('photo')->store('student_photos', 'public');
        }

        if ($request->role === 'student') {
            $userData['student_code'] = $request->student_code;
            $userData['classroom_id'] = $request->classroom_id;
            $userData['citizen_id'] = $request->citizen_id;

            if ($request->citizen_id !== $user->citizen_id) {
                $userData['password'] = Hash::make($request->citizen_id);
            }
            $userData['email'] = null;
            $userData['teacher_code'] = null;
            $userData['academic_role'] = null;
            $userData['department_id'] = null;
        } else {
            $academicRolesInput = $request->input('academic_roles', $request->input('academic_role'));
            $academicRoleValue = null;
            if (is_array($academicRolesInput)) {
                $cleaned = array_values(array_filter($academicRolesInput));
                $academicRoleValue = !empty($cleaned) ? json_encode($cleaned, JSON_UNESCAPED_UNICODE) : null;
            } elseif (is_string($academicRolesInput) && trim($academicRolesInput) !== '') {
                $academicRoleValue = json_encode([trim($academicRolesInput)], JSON_UNESCAPED_UNICODE);
            }

            $userData['email'] = null;
            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->password);
            }
            $userData['teacher_code'] = $request->teacher_code;
            $userData['academic_role'] = $academicRoleValue;
            $userData['department_id'] = $request->department_id;
            $userData['student_code'] = null;
            $userData['classroom_id'] = null;
            $userData['citizen_id'] = null;
        }

        $user->update($userData);
        if ($user->isStudent() && $user->classroom_id) {
            $user->syncClassroomSubjects();
        }

        return redirect()->route('admin.users.edit', $user)->with('success', 'แก้ไขข้อมูลผู้ใช้งานเรียบร้อยแล้ว');
    }

    public function destroy(Request $request, User $user)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }

        $isCurrentUser = auth()->id() === $user->id;
        $isStudent = $user->isStudent();

        // Delete photo if exists
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }

        $user->delete();

        if ($isCurrentUser) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('success', 'ลบบัญชีผู้ใช้งานของคุณเรียบร้อยแล้ว');
        }

        return redirect()->route('admin.users.index', ['role' => $isStudent ? 'student' : 'staff'])->with('success', 'ลบข้อมูลผู้ใช้งานเรียบร้อยแล้ว');
    }

    public function bulkDestroy(Request $request)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }

        $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['exists:users,id'],
        ], [
            'user_ids.required' => 'กรุณาเลือกผู้ใช้งานที่ต้องการลบอย่างน้อย 1 คน',
        ]);

        $userIds = $request->input('user_ids');
        $hasCurrentUser = in_array(auth()->id(), $userIds);

        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }
            $user->delete();
        }

        if ($hasCurrentUser) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('success', 'ลบบัญชีผู้ใช้งานของคุณเรียบร้อยแล้ว');
        }

        return redirect()->back()->with('success', 'ลบข้อมูลผู้ใช้งานที่เลือกเรียบร้อยแล้ว');
    }

    public function show(User $user)
    {
        if (auth()->user()->isTeacher() && !$user->isStudent()) {
            abort(403, 'คุณไม่มีสิทธิ์ดูข้อมูลผู้ใช้งานกลุ่มนี้');
        }

        $attempts = collect();
        if ($user->isStudent()) {
            $user->load([
                'classroom',
                'enrolledSubjects.teachers',
                'enrolledSubjects.department',
                'examStudentOverrides.exam.subject',
                'examStudentOverrides.granter'
            ]);

            $attempts = ExamAttempt::where('user_id', $user->id)
                ->with(['exam.subject', 'exam.questions'])
                ->orderBy('started_at', 'desc')
                ->get();
        } else {
            $user->load(['department', 'classroom']);
        }

        return view('admin.users.show', compact('user', 'attempts'));
    }

    public function destroyPhoto(User $user)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }

        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
            $user->update(['photo' => null]);
        }

        return redirect()->route('admin.users.edit', $user)->with('success', 'ลบรูปภาพเรียบร้อยแล้ว');
    }

    public function reports(Request $request)
    {
        $data = $this->getReportData($request);
        return view('admin.reports.index', $data);
    }

    public function exportReports(Request $request)
    {
        $data = $this->getReportData($request);

        $selectedSubject = $data['selectedSubject'];
        $selectedClassroom = $data['selectedClassroom'];
        $subjectExams = $data['subjectExams'];
        $students = $data['students'];
        $attemptsMatrix = $data['attemptsMatrix'];

        if (!$selectedSubject && $students->isEmpty()) {
            return redirect()->route('admin.reports.index')->with('error', 'กรุณาเลือกรายวิชาก่อนทำการส่งออกคะแนน');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('รายงานคะแนนสอบ');

        // Row 1: Subject Info
        $subjectTitle = $selectedSubject ? '[' . $selectedSubject->code . '] ' . $selectedSubject->name : 'ตารางคะแนนสอบ';
        $sheet->setCellValue('A1', $subjectTitle);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        // Row 2: Classroom & Export Date
        $classroomTitle = $selectedClassroom ? 'ห้องเรียน: ' . $selectedClassroom->name : 'ทุกห้องเรียน';
        $exportDate = 'วันที่ส่งออก: ' . now()->locale('th')->translatedFormat('d/m/Y H:i น.');
        $sheet->setCellValue('A2', $classroomTitle . ' | ' . $exportDate);
        $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setARGB('FF6C757D');

        // Row 4: Header
        $row = 4;
        $headers = ['#', 'รหัสนักศึกษา', 'ชื่อ-นามสกุล', 'ห้องเรียน'];
        foreach ($subjectExams as $exam) {
            $headers[] = $exam->title . ' (เต็ม ' . floatval($exam->total_score) . ')';
        }
        if ($subjectExams->count() > 1) {
            $headers[] = 'คะแนนรวม (เต็ม ' . floatval($subjectExams->sum('total_score')) . ')';
        }

        foreach ($headers as $idx => $h) {
            $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->setCellValue($colLetter . $row, $h);
        }
        $lastColLetter = Coordinate::stringFromColumnIndex(count($headers));

        // Style Header
        $headerRange = 'A' . $row . ':' . $lastColLetter . $row;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF2F4F8');
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(26);

        // Data rows
        $dataStartRow = 5;
        $currentRow = $dataStartRow;
        foreach ($students as $index => $student) {
            $colIdx = 1;
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $currentRow, $index + 1);
            $sheet->setCellValueExplicit(
                Coordinate::stringFromColumnIndex($colIdx++) . $currentRow,
                (string) ($student->student_code ?? '-'),
                DataType::TYPE_STRING
            );
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $currentRow, $student->name ?? '-');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $currentRow, $student->classroom->name ?? '-');

            $totalStudentScore = 0;
            foreach ($subjectExams as $exam) {
                $examAttempts = $attemptsMatrix->get($student->id . '_' . $exam->id, collect());
                if ($examAttempts->isEmpty()) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $currentRow, '-');
                } else {
                    $bestAttempt = $examAttempts->sortByDesc('score')->first();
                    $scoreVal = floatval($bestAttempt->score);
                    $totalStudentScore += $scoreVal;
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $currentRow, $scoreVal);
                }
            }

            if ($subjectExams->count() > 1) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . $currentRow, $totalStudentScore);
            }

            $sheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;
        }

        $dataEndRow = max($currentRow - 1, $dataStartRow);

        // Borders
        $tableRange = 'A' . $row . ':' . $lastColLetter . $dataEndRow;
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Alignments
        if ($dataEndRow >= $dataStartRow) {
            $sheet->getStyle('A' . $dataStartRow . ':A' . $dataEndRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $dataStartRow . ':B' . $dataEndRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('C' . $dataStartRow . ':C' . $dataEndRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('D' . $dataStartRow . ':D' . $dataEndRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            if (count($headers) >= 5) {
                $startExamCol = Coordinate::stringFromColumnIndex(5);
                $sheet->getStyle($startExamCol . $dataStartRow . ':' . $lastColLetter . $dataEndRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        // Auto-fit column widths
        for ($i = 1; $i <= count($headers); $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Clean and short filename (e.g. คะแนน_21910-1002_ปวช.2-2.xlsx)
        $subjectPart = $selectedSubject ? str_replace([' ', '/'], ['', '-'], $selectedSubject->code) : 'วิชา';
        $roomPart = '';
        if ($selectedClassroom) {
            $roomWords = explode(' ', trim($selectedClassroom->name));
            $shortRoom = $roomWords[0];
            if (count($roomWords) > 1 && in_array($shortRoom, ['ปวช.', 'ปวส.'])) {
                $shortRoom .= $roomWords[1];
            }
            $roomPart = '_' . str_replace(['/', ' '], ['-', ''], $shortRoom);
        }
        $fileName = 'คะแนน_' . $subjectPart . $roomPart . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function getReportData(Request $request): array
    {
        $user = auth()->user();

        // 1. Get available subjects for the current user
        $subjectsQuery = $user->isAdmin()
            ? Subject::query()
            : $user->enrolledSubjects();
        
        $subjects = $subjectsQuery->orderBy('code')->get();

        // If teacher has only 1 subject and no subject_id specified in query, default to it
        $selectedSubjectId = $request->get('subject_id');
        if (!$selectedSubjectId && $user->isTeacher() && $subjects->count() === 1) {
            $selectedSubjectId = $subjects->first()->id;
        }

        // Security check for teachers: ensure teacher only accesses their own subject
        if ($selectedSubjectId) {
            if ($user->isTeacher() && !$subjects->contains('id', $selectedSubjectId)) {
                abort(403, 'คุณไม่มีสิทธิ์เข้าถึงรายงานผลคะแนนของรายวิชานี้');
            }
            $selectedSubject = Subject::find($selectedSubjectId);
        } else {
            $selectedSubject = null;
        }

        // Security check for teachers: ensure teacher only filters by exams in their taught subjects
        if ($request->filled('exam_id') && $user->isTeacher()) {
            $examId = $request->get('exam_id');
            $allowedExamIds = Exam::whereIn('subject_id', $subjects->pluck('id'))->pluck('id');
            if (!$allowedExamIds->contains($examId)) {
                abort(403, 'คุณไม่มีสิทธิ์เข้าถึงรายงานผลคะแนนของชุดข้อสอบนี้');
            }
        }

        // 2. Get classrooms and exams
        if ($selectedSubject) {
            // Classrooms enrolled in this subject or having student attempts
            $classrooms = Classroom::where(function($query) use ($selectedSubject) {
                $query->whereHas('users.enrolledSubjects', function($sq) use ($selectedSubject) {
                    $sq->where('subjects.id', $selectedSubject->id);
                })->orWhereHas('users.examAttempts.exam', function($eq) use ($selectedSubject) {
                    $eq->where('subject_id', $selectedSubject->id);
                });
            })->orderBy('name')->get();

            // If none found by enrollment/attempt, fallback to all classrooms
            if ($classrooms->isEmpty()) {
                $classrooms = Classroom::orderBy('name')->get();
            }

            $exams = Exam::where('subject_id', $selectedSubject->id)->orderBy('title')->get();
        } else {
            $classrooms = Classroom::orderBy('name')->get();
            $exams = $user->isAdmin()
                ? Exam::orderBy('title')->get()
                : Exam::whereIn('subject_id', $subjects->pluck('id'))->orderBy('title')->get();
        }

        $selectedClassroomId = $request->get('classroom_id');
        $selectedClassroom = $selectedClassroomId ? Classroom::find($selectedClassroomId) : null;

        $examStats = collect();
        $reports = collect();
        $studentRoundsHistory = collect();
        $hasSearchedOrSelected = $selectedSubject !== null || $request->filled('q') || $request->filled('exam_id');

        if ($hasSearchedOrSelected) {
            // Get statistics per exam
            $statsQuery = Exam::with('subject')
                ->withCount(['examAttempts as total_attempts' => function ($query) use ($selectedClassroomId) {
                    $query->where('status', 'completed');
                    if ($selectedClassroomId) {
                        $query->whereHas('user', fn($uq) => $uq->where('classroom_id', $selectedClassroomId));
                    }
                }])
                ->withCount(['examAttempts as passed_attempts' => function ($query) use ($selectedClassroomId) {
                    $query->where('status', 'completed')->where('is_passed', true);
                    if ($selectedClassroomId) {
                        $query->whereHas('user', fn($uq) => $uq->where('classroom_id', $selectedClassroomId));
                    }
                }]);

            if ($selectedSubject) {
                $statsQuery->where('subject_id', $selectedSubject->id);
            } elseif ($user->isTeacher()) {
                $statsQuery->whereIn('subject_id', $subjects->pluck('id'));
            }

            if ($request->filled('exam_id')) {
                $statsQuery->where('id', $request->get('exam_id'));
            }

            $examStats = $statsQuery->orderBy('title')->get();

            // Calculate passing rate and average score for each exam
            foreach ($examStats as $exam) {
                $exam->passed_rate = $exam->total_attempts > 0 
                    ? round(($exam->passed_attempts / $exam->total_attempts) * 100, 2)
                    : 0;
                
                $avgQuery = ExamAttempt::where('exam_id', $exam->id)
                    ->where('status', 'completed');
                if ($selectedClassroomId) {
                    $avgQuery->whereHas('user', fn($uq) => $uq->where('classroom_id', $selectedClassroomId));
                }
                $avg = $avgQuery->avg('score') ?? 0;
                $exam->average_score = round($avg, 2);
            }

            // Determine the subject exams
            $examsQuery = Exam::query();
            if ($selectedSubject) {
                $examsQuery->where('subject_id', $selectedSubject->id);
            } elseif ($user->isTeacher()) {
                $examsQuery->whereIn('subject_id', $subjects->pluck('id'));
            }

            if ($request->filled('exam_id')) {
                $examsQuery->where('id', $request->get('exam_id'));
            }

            $subjectExams = $examsQuery->withSum('questions as total_raw_score', 'score')->orderBy('id')->get();
            $examIds = $subjectExams->pluck('id');

            // Find all students for this subject & classroom (enrolled or having attempts)
            $attemptStudentIds = ExamAttempt::whereIn('exam_id', $examIds)
                ->where('status', 'completed')
                ->pluck('user_id')
                ->unique();

            $studentsQuery = User::where('role', 'student');

            if ($selectedSubject) {
                $studentsQuery->where(function($q) use ($selectedSubject, $attemptStudentIds) {
                    $q->whereHas('enrolledSubjects', fn($sq) => $sq->where('subjects.id', $selectedSubject->id))
                      ->orWhereIn('id', $attemptStudentIds);
                });
            } else {
                $studentsQuery->whereIn('id', $attemptStudentIds);
            }

            if ($selectedClassroomId) {
                $studentsQuery->where('classroom_id', $selectedClassroomId);
            }

            if ($request->filled('q')) {
                $q = $request->get('q');
                $studentsQuery->where(function($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                          ->orWhere('student_code', 'like', "%{$q}%");
                });
            }

            $students = $studentsQuery->with('classroom')->orderBy('student_code')->get();
            $studentIds = $students->pluck('id');

            // Detailed score history for the report table
            $reportsQuery = ExamAttempt::with([
                    'user.classroom',
                    'exam' => fn($q) => $q->withSum('questions as total_raw_score', 'score'),
                    'exam.subject',
                ])
                ->whereIn('user_id', $studentIds)
                ->whereIn('exam_id', $examIds)
                ->where('status', 'completed')
                ->orderBy('attempt_number', 'asc')
                ->orderBy('completed_at', 'asc');

            $reports = $reportsQuery->get();
            $attemptsMatrix = $reports->groupBy(fn($att) => $att->user_id . '_' . $att->exam_id);
            $studentRoundsHistory = $attemptsMatrix;
        } else {
            $subjectExams = collect();
            $students = collect();
            $attemptsMatrix = collect();
        }

        return compact(
            'subjects',
            'selectedSubject',
            'classrooms',
            'selectedClassroom',
            'exams',
            'subjectExams',
            'students',
            'attemptsMatrix',
            'reports',
            'studentRoundsHistory',
            'hasSearchedOrSelected'
        );
    }

    public function downloadTemplate()
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="student_import_template.csv"',
        ];
        
        $callback = function() {
            $file = fopen('php://output', 'w');
            // Add UTF-8 BOM for Excel compatibility in Thai
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, ['ชื่อ-นามสกุล', 'รหัสนักศึกษา', 'เลขบัตรประชาชน', 'ระดับชั้น']);
            fputcsv($file, ['นายสมชาย ดีใจ', '6501012345678', '1234567890123', 'ปวช. 1/1']);
            fputcsv($file, ['นางสาวสมศรี เรียนดี', '6412345', '9876543210123', 'ปวส. 2/3']);
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    public function downloadTeacherTemplate()
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="teacher_import_template.csv"',
        ];
        
        $callback = function() {
            $file = fopen('php://output', 'w');
            // Add UTF-8 BOM for Excel compatibility in Thai
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, ['ชื่อ-นามสกุล', 'รหัสประจำตัวครู', 'แผนกวิชา/หมวดวิชา', 'รหัสผ่าน', 'บทบาทเสริม']);
            fputcsv($file, ['อาจารย์สมชาย ใจดี', 'T1001', 'แผนกวิชาคอมพิวเตอร์ธุรกิจ', '123456', 'หัวหน้าสาขา']);
            fputcsv($file, ['อาจารย์สมศรี มีสุข', 'T1002', 'แผนกวิชาการบัญชี', '123456', '']);
            fputcsv($file, ['อาจารย์สุรชัย รักสอน', 'T1003', 'หมวดวิชาสามัญสัมพันธ์', '123456', 'หัวหน้างานวัดและประเมินผล']);
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    public function import(Request $request)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }

        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // max 20MB
        ], [
            'file.required' => 'กรุณาเลือกไฟล์ที่จะอัปโหลด',
            'file.max' => 'ขนาดไฟล์ต้องไม่เกิน 20MB',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'txt', 'xlsx', 'xls'])) {
            return redirect()->back()->withErrors(['file' => 'ไฟล์ที่อัปโหลดต้องเป็นรูปแบบ .xlsx, .xls หรือ .csv เท่านั้น']);
        }

        @ini_set('max_execution_time', '300');
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $rows = [];

        try {
            if (in_array($extension, ['xlsx', 'xls'])) {
                $spreadsheet = IOFactory::load($file->getRealPath());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, false);
            } else {
                $content = file_get_contents($file->getRealPath());

                // Strip UTF-8 BOM if present
                $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

                // Convert character encoding if not UTF-8 (e.g. Thai Windows-874 / TIS-620)
                if (!mb_check_encoding($content, 'UTF-8')) {
                    $converted = @iconv('Windows-874', 'UTF-8//IGNORE', $content);
                    if ($converted === false) {
                        $converted = @iconv('TIS-620', 'UTF-8//IGNORE', $content);
                    }
                    if ($converted !== false) {
                        $content = $converted;
                    }
                }

                // Detect delimiter (comma, semicolon, tab)
                $firstLine = strtok($content, "\r\n");
                $commaCount = substr_count($firstLine, ',');
                $semicolonCount = substr_count($firstLine, ';');
                $tabCount = substr_count($firstLine, "\t");

                $delimiter = ',';
                if ($semicolonCount > $commaCount && $semicolonCount > $tabCount) {
                    $delimiter = ';';
                } elseif ($tabCount > $commaCount && $tabCount > $semicolonCount) {
                    $delimiter = "\t";
                }

                $stream = fopen('php://temp', 'r+');
                fwrite($stream, $content);
                rewind($stream);

                while (($r = fgetcsv($stream, 0, $delimiter)) !== false) {
                    $rows[] = $r;
                }
                fclose($stream);
            }
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['import_error' => 'ไม่สามารถอ่านไฟล์ได้: ' . $e->getMessage()]);
        }

        if (empty($rows)) {
            return redirect()->back()->withErrors(['import_error' => 'ไม่พบข้อมูลในไฟล์ที่อัปโหลด']);
        }

        // Check if first row is header row or data row
        $firstRow = $rows[0];
        $firstRowIsHeader = true;
        $firstCol1 = preg_replace('/[^0-9]/', '', (string)($firstRow[1] ?? ''));
        $firstCol2 = preg_replace('/[^0-9]/', '', (string)($firstRow[2] ?? ''));
        if ((strlen($firstCol1) >= 5 && is_numeric($firstCol1)) || (strlen($firstCol2) >= 10 && is_numeric($firstCol2))) {
            $firstRowIsHeader = false;
        }

        $startIndex = $firstRowIsHeader ? 1 : 0;
        $successCount = 0;
        $errors = [];
        $passwordHashCache = [];
        $affectedUsersByClassroom = [];
        $importRounds = min(10, (int) config('hashing.bcrypt.rounds', 10));

        // Preload existing classrooms
        $classroomMap = \App\Models\Classroom::pluck('id', 'name')->toArray();

        // Preload existing students keyed by lowercased student_code
        $existingStudents = User::where('role', 'student')
            ->whereNotNull('student_code')
            ->select('id', 'name', 'student_code', 'citizen_id', 'classroom_id')
            ->get()
            ->keyBy(fn($u) => mb_strtolower(trim($u->student_code)));

        DB::beginTransaction();
        try {
            for ($i = $startIndex; $i < count($rows); $i++) {
                $rowNum = $i + 1;
                $row = $rows[$i];

                if (empty(array_filter($row, fn($val) => $val !== null && trim((string)$val) !== ''))) {
                    continue; // skip empty rows
                }

                $name = isset($row[0]) ? trim((string)$row[0]) : '';
                $name = trim(preg_replace('/\s+/', ' ', $name));

                $student_code = isset($row[1]) ? trim((string)$row[1]) : '';
                $student_code = preg_replace('/\s+/', '', $student_code);

                $citizen_id = isset($row[2]) ? trim((string)$row[2]) : '';
                $citizen_id = preg_replace('/[^0-9]/', '', $citizen_id);
                if (strlen($citizen_id) == 12) {
                    // Restore potential leading zero stripped by Excel numeric format
                    $citizen_id = str_pad($citizen_id, 13, '0', STR_PAD_LEFT);
                }

                $classroom_name = isset($row[3]) ? trim((string)$row[3]) : '';

                if (!$name || !$student_code || !$citizen_id) {
                    $errors[] = "แถวที่ {$rowNum}: ข้อมูลไม่ครบถ้วน (ต้องระบุ ชื่อ-นามสกุล, รหัสนักศึกษา, เลขประจำตัวประชาชน)";
                    continue;
                }

                if (strlen($student_code) > 50) {
                    $errors[] = "แถวที่ {$rowNum}: รหัสนักศึกษาต้องมีความยาวไม่เกิน 50 ตัวอักษร";
                    continue;
                }

                if (strlen($citizen_id) != 13) {
                    $originalCid = $row[2] ?? '';
                    $errors[] = "แถวที่ {$rowNum}: เลขประจำตัวประชาชนต้องเป็นตัวเลข 13 หลัก (พบ '{$originalCid}')";
                    continue;
                }

                // Classroom lookup or creation
                $classroom_id = null;
                if ($classroom_name) {
                    if (!isset($classroomMap[$classroom_name])) {
                        $classroom = \App\Models\Classroom::firstOrCreate(['name' => $classroom_name]);
                        $classroomMap[$classroom_name] = $classroom->id;
                    }
                    $classroom_id = $classroomMap[$classroom_name];
                }

                $lookupStudentCode = mb_strtolower($student_code);
                $user = $existingStudents->get($lookupStudentCode)
                    ?? User::where('role', 'student')->whereRaw('LOWER(TRIM(student_code)) = ?', [$lookupStudentCode])->first();

                if ($user) {
                    // Update existing student
                    $updateData = [
                        'name' => $name,
                        'student_code' => $student_code,
                        'classroom_id' => $classroom_id,
                    ];
                    // Only rehash password if citizen_id changed
                    if ($user->citizen_id !== $citizen_id) {
                        $updateData['citizen_id'] = $citizen_id;
                        $updateData['password'] = $passwordHashCache[$citizen_id] ??= Hash::make($citizen_id, ['rounds' => $importRounds]);
                    }
                    User::where('id', $user->id)->update($updateData);
                    $savedUserId = $user->id;
                    $existingStudents->put($lookupStudentCode, $user);
                } else {
                    // Create new student
                    $hashedPassword = $passwordHashCache[$citizen_id] ??= Hash::make($citizen_id, ['rounds' => $importRounds]);
                    $createdUser = User::create([
                        'name' => $name,
                        'student_code' => $student_code,
                        'citizen_id' => $citizen_id,
                        'password' => $hashedPassword,
                        'role' => 'student',
                        'classroom_id' => $classroom_id,
                        'is_exam_eligible' => true,
                    ]);
                    $savedUserId = $createdUser->id;
                    $existingStudents->put($lookupStudentCode, $createdUser);
                }

                if ($classroom_id) {
                    $affectedUsersByClassroom[$classroom_id][] = $savedUserId;
                }

                $successCount++;
                if ($successCount % 50 === 0) {
                    @set_time_limit(60);
                }
            }

            // Sync classroom subjects in bulk for affected classrooms
            foreach ($affectedUsersByClassroom as $classId => $userIds) {
                if (!$classId || empty($userIds)) {
                    continue;
                }

                $subjectIds = DB::table('subject_user')
                    ->join('users', 'subject_user.user_id', '=', 'users.id')
                    ->where('users.classroom_id', $classId)
                    ->where('users.role', 'student')
                    ->distinct()
                    ->pluck('subject_user.subject_id')
                    ->toArray();

                if (!empty($subjectIds)) {
                    $now = now();
                    $batch = [];
                    foreach ($userIds as $uId) {
                        foreach ($subjectIds as $subId) {
                            $batch[] = [
                                'subject_id' => $subId,
                                'user_id' => $uId,
                                'is_eligible' => 1,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }
                    foreach (array_chunk($batch, 500) as $chunk) {
                        DB::table('subject_user')->insertOrIgnore($chunk);
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['import_error' => 'เกิดข้อผิดพลาดของระบบ: ' . $e->getMessage()]);
        }

        if (count($errors) > 0) {
            $msg = "นำเข้าสำเร็จ {$successCount} รายการ แต่พบข้อผิดพลาด " . count($errors) . " รายการ:<br>" . implode('<br>', array_slice($errors, 0, 10));
            if (count($errors) > 10) {
                $msg .= '<br>...และข้อผิดพลาดอื่น ๆ';
            }
            return redirect()->route('admin.users.index', ['role' => 'student'])->withErrors(['import_warning' => $msg])->with('success', "นำเข้าเสร็จสิ้นบางส่วน สำเร็จ {$successCount} รายการ");
        }

        return redirect()->route('admin.users.index', ['role' => 'student'])->with('success', "นำเข้าข้อมูลนักศึกษาสำเร็จทั้งหมดจำนวน {$successCount} รายการเรียบร้อยแล้ว");
    }

    public function importTeachers(Request $request)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }

        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // max 20MB
        ], [
            'file.required' => 'กรุณาเลือกไฟล์ที่จะอัปโหลด',
            'file.max' => 'ขนาดไฟล์ต้องไม่เกิน 20MB',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'txt', 'xlsx', 'xls'])) {
            return redirect()->back()->withErrors(['file' => 'ไฟล์ที่อัปโหลดต้องเป็นรูปแบบ .xlsx, .xls หรือ .csv เท่านั้น']);
        }

        @ini_set('max_execution_time', '300');
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $rows = [];

        try {
            if (in_array($extension, ['xlsx', 'xls'])) {
                $spreadsheet = IOFactory::load($file->getRealPath());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, false);
            } else {
                $content = file_get_contents($file->getRealPath());

                // Strip UTF-8 BOM if present
                $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

                // Convert encoding if not UTF-8
                if (!mb_check_encoding($content, 'UTF-8')) {
                    $converted = @iconv('Windows-874', 'UTF-8//IGNORE', $content);
                    if ($converted === false) {
                        $converted = @iconv('TIS-620', 'UTF-8//IGNORE', $content);
                    }
                    if ($converted !== false) {
                        $content = $converted;
                    }
                }

                // Detect delimiter
                $firstLine = strtok($content, "\r\n");
                $commaCount = substr_count($firstLine, ',');
                $semicolonCount = substr_count($firstLine, ';');
                $tabCount = substr_count($firstLine, "\t");

                $delimiter = ',';
                if ($semicolonCount > $commaCount && $semicolonCount > $tabCount) {
                    $delimiter = ';';
                } elseif ($tabCount > $commaCount && $tabCount > $semicolonCount) {
                    $delimiter = "\t";
                }

                $stream = fopen('php://temp', 'r+');
                fwrite($stream, $content);
                rewind($stream);

                while (($r = fgetcsv($stream, 0, $delimiter)) !== false) {
                    $rows[] = $r;
                }
                fclose($stream);
            }
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['import_error' => 'ไม่สามารถอ่านไฟล์ได้: ' . $e->getMessage()]);
        }

        if (empty($rows)) {
            return redirect()->back()->withErrors(['import_error' => 'ไม่พบข้อมูลในไฟล์ที่อัปโหลด']);
        }

        // Check if first row is header row
        $firstRow = $rows[0];
        $firstRowIsHeader = true;
        $col1 = trim((string)($firstRow[1] ?? ''));
        if (!preg_match('/(รหัส|code|teacher)/i', $col1) && (preg_match('/^[A-Za-z0-9_-]{2,}$/', $col1))) {
            $firstRowIsHeader = false;
        }

        $startIndex = $firstRowIsHeader ? 1 : 0;
        $successCount = 0;
        $errors = [];
        $passwordHashCache = [];
        $importRounds = min(10, (int) config('hashing.bcrypt.rounds', 10));

        // Preload departments
        $departmentMap = \App\Models\Department::pluck('id', 'name')->toArray();

        // Preload existing teachers keyed by lowercased teacher_code
        $existingTeachers = User::whereNotNull('teacher_code')
            ->select('id', 'name', 'teacher_code', 'department_id', 'academic_role')
            ->get()
            ->keyBy(fn($u) => mb_strtolower(trim($u->teacher_code)));

        DB::beginTransaction();
        try {
            for ($i = $startIndex; $i < count($rows); $i++) {
                $rowNum = $i + 1;
                $row = $rows[$i];

                if (empty(array_filter($row, fn($val) => $val !== null && trim((string)$val) !== ''))) {
                    continue; // skip empty rows
                }

                $name = isset($row[0]) ? trim((string)$row[0]) : '';
                $name = trim(preg_replace('/\s+/', ' ', $name));

                $teacher_code = isset($row[1]) ? trim((string)$row[1]) : '';
                $teacher_code = preg_replace('/\s+/', '', $teacher_code);

                $department_name = isset($row[2]) ? trim((string)$row[2]) : '';
                $rawPassword = isset($row[3]) ? trim((string)$row[3]) : '';
                $academic_role_raw = isset($row[4]) ? trim((string)$row[4]) : '';

                if (!$name || !$teacher_code) {
                    $errors[] = "แถวที่ {$rowNum}: ข้อมูลไม่ครบถ้วน (ต้องระบุ ชื่อ-นามสกุล และ รหัสประจำตัวครู)";
                    continue;
                }

                if (strlen($teacher_code) > 50) {
                    $errors[] = "แถวที่ {$rowNum}: รหัสประจำตัวครูต้องมีความยาวไม่เกิน 50 ตัวอักษร";
                    continue;
                }

                // Department lookup or creation
                $department_id = null;
                if ($department_name) {
                    if (!isset($departmentMap[$department_name])) {
                        $dept = \App\Models\Department::firstOrCreate(['name' => $department_name]);
                        $departmentMap[$department_name] = $dept->id;
                    }
                    $department_id = $departmentMap[$department_name];
                }

                // Academic roles parsing
                $academicRoles = [];
                if ($academic_role_raw) {
                    $parts = preg_split('/[,;\/]+/', $academic_role_raw);
                    foreach ($parts as $p) {
                        $p = trim($p);
                        if (!$p) continue;
                        if (str_contains($p, 'รอง') || str_contains($p, 'วิชาการ') || $p === 'academic_deputy') {
                            $academicRoles[] = 'academic_deputy';
                        } elseif (str_contains($p, 'วัดผล') || str_contains($p, 'ประเมิน') || $p === 'evaluation_head') {
                            $academicRoles[] = 'evaluation_head';
                        } elseif (str_contains($p, 'หัวหน้าสาขา') || str_contains($p, 'หัวหน้าแผนก') || $p === 'department_head') {
                            $academicRoles[] = 'department_head';
                        }
                    }
                    $academicRoles = array_values(array_unique($academicRoles));
                }
                $academicRoleValue = !empty($academicRoles) ? json_encode($academicRoles, JSON_UNESCAPED_UNICODE) : null;

                // Determine plain password
                $plainPassword = $rawPassword;
                if (!$plainPassword) {
                    $plainPassword = strlen($teacher_code) >= 6 ? $teacher_code : '123456';
                } elseif (strlen($plainPassword) < 6) {
                    $errors[] = "แถวที่ {$rowNum}: รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
                    continue;
                }

                $lookupCode = mb_strtolower($teacher_code);
                $user = $existingTeachers->get($lookupCode)
                    ?? User::whereRaw('LOWER(TRIM(teacher_code)) = ?', [$lookupCode])->first();

                if ($user) {
                    // Update existing teacher
                    $updateData = [
                        'name' => $name,
                        'teacher_code' => $teacher_code,
                        'department_id' => $department_id,
                    ];
                    if ($academicRoleValue !== null) {
                        $updateData['academic_role'] = $academicRoleValue;
                    }
                    if ($rawPassword) {
                        $updateData['password'] = $passwordHashCache[$plainPassword] ??= Hash::make($plainPassword, ['rounds' => $importRounds]);
                    }
                    User::where('id', $user->id)->update($updateData);
                    $existingTeachers->put($lookupCode, $user);
                } else {
                    // Create new teacher
                    $hashedPassword = $passwordHashCache[$plainPassword] ??= Hash::make($plainPassword, ['rounds' => $importRounds]);
                    $createdUser = User::create([
                        'name' => $name,
                        'teacher_code' => $teacher_code,
                        'password' => $hashedPassword,
                        'role' => 'teacher',
                        'department_id' => $department_id,
                        'academic_role' => $academicRoleValue,
                        'is_exam_eligible' => true,
                    ]);
                    $existingTeachers->put($lookupCode, $createdUser);
                }

                $successCount++;
                if ($successCount % 50 === 0) {
                    @set_time_limit(60);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['import_error' => 'เกิดข้อผิดพลาดของระบบ: ' . $e->getMessage()]);
        }

        if (count($errors) > 0) {
            $msg = "นำเข้าสำเร็จ {$successCount} รายการ แต่พบข้อผิดพลาด " . count($errors) . " รายการ:<br>" . implode('<br>', array_slice($errors, 0, 10));
            if (count($errors) > 10) {
                $msg .= '<br>...และข้อผิดพลาดอื่น ๆ';
            }
            return redirect()->route('admin.users.index', ['role' => 'staff'])->withErrors(['import_warning' => $msg])->with('success', "นำเข้าเสร็จสิ้นบางส่วน สำเร็จ {$successCount} รายการ");
        }

        return redirect()->route('admin.users.index', ['role' => 'staff'])->with('success', "นำเข้าข้อมูลอาจารย์สำเร็จทั้งหมดจำนวน {$successCount} รายการเรียบร้อยแล้ว");
    }

    public function toggleExamEligibility(Request $request, User $user)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์ดำเนินการนี้');
        }

        $request->validate([
            'is_exam_eligible' => ['required', 'in:0,1,true,false'],
            'ineligible_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $isEligible = filter_var($request->is_exam_eligible, FILTER_VALIDATE_BOOLEAN);
        $reason = $isEligible ? null : ($request->ineligible_reason ?: 'ระงับสิทธิ์การสอบ');

        $user->update([
            'is_exam_eligible' => $isEligible,
            'ineligible_reason' => $reason,
        ]);

        $statusText = $isEligible ? 'เปิดสิทธิ์การสอบ' : 'ระงับสิทธิ์การสอบ';
        return redirect()->back()->with('success', "{$statusText}ของนักศึกษา {$user->name} เรียบร้อยแล้ว");
    }

    public function bulkToggleExamEligibility(Request $request)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์ดำเนินการนี้');
        }

        $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['exists:users,id'],
            'is_exam_eligible' => ['required', 'in:0,1,true,false'],
            'ineligible_reason' => ['nullable', 'string', 'max:255'],
        ], [
            'user_ids.required' => 'กรุณาเลือกนักศึกษาอย่างน้อย 1 คน',
        ]);

        $isEligible = filter_var($request->is_exam_eligible, FILTER_VALIDATE_BOOLEAN);
        $reason = $isEligible ? null : ($request->ineligible_reason ?: 'ระงับสิทธิ์การสอบ');

        User::whereIn('id', $request->user_ids)->update([
            'is_exam_eligible' => $isEligible,
            'ineligible_reason' => $reason,
        ]);

        $count = count($request->user_ids);
        $statusText = $isEligible ? 'เปิดสิทธิ์การสอบ' : 'ระงับสิทธิ์การสอบ';
        return redirect()->back()->with('success', "{$statusText}สำหรับนักศึกษาจำนวน {$count} คนเรียบร้อยแล้ว");
    }
}
