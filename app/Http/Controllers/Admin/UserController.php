<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function create()
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }
        $classrooms = \App\Models\Classroom::orderBy('name')->get();
        return view('admin.users.create', compact('classrooms'));
    }

    public function edit(User $user)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }
        $classrooms = \App\Models\Classroom::orderBy('name')->get();
        return view('admin.users.edit', compact('user', 'classrooms'));
    }

    public function index(Request $request)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }
        $role = $request->query('role');
        $searchPerformed = false;
        $users = collect();
        $classrooms = \App\Models\Classroom::orderBy('name')->get();

        if ($request->has('search')) {
            $searchPerformed = true;
            $query = User::with(['classroom'])
                ->withCount(['examAttempts' => function($query) {
                    $query->where('status', 'completed');
                }]);

            if ($role === 'student') {
                $query->where('role', 'student');
            } elseif ($role === 'staff') {
                $query->whereIn('role', ['admin', 'teacher']);
            }

            if ($request->filled('classroom_id')) {
                $query->where('classroom_id', $request->get('classroom_id'));
            }

            if ($request->filled('q')) {
                $q = $request->get('q');
                $query->where(function($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                          ->orWhere('student_code', 'like', "%{$q}%")
                          ->orWhere('teacher_code', 'like', "%{$q}%")
                          ->orWhere('email', 'like', "%{$q}%");
                });
            }

            $users = $query->get();
        }

        return view('admin.users.index', compact('users', 'classrooms', 'role', 'searchPerformed'));
    }

    public function store(Request $request)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:admin,teacher,student'],
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
        } else {
            $userData['email'] = null;
            $userData['password'] = Hash::make($request->password);
            $userData['teacher_code'] = $request->teacher_code;
            $userData['student_code'] = null;
            $userData['classroom_id'] = null;
            $userData['citizen_id'] = null;
        }

        User::create($userData);

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
        } else {
            $userData['email'] = null;
            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->password);
            }
            $userData['teacher_code'] = $request->teacher_code;
            $userData['student_code'] = null;
            $userData['classroom_id'] = null;
            $userData['citizen_id'] = null;
        }

        $user->update($userData);

        return redirect()->route('admin.users.edit', $user)->with('success', 'แก้ไขข้อมูลผู้ใช้งานเรียบร้อยแล้ว');
    }

    public function destroy(User $user)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }

        // Don't allow a logged-in admin to delete their own account
        if (auth()->id() === $user->id) {
            return redirect()->route('admin.users.index')->withErrors(['error' => 'ไม่สามารถลบบัญชีตนเองขณะใช้งานระบบได้']);
        }

        // Delete photo if exists
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }

        $user->delete();

        return redirect()->route('admin.users.index', ['role' => $user->isStudent() ? 'student' : 'staff'])->with('success', 'ลบข้อมูลผู้ใช้งานเรียบร้อยแล้ว');
    }

    public function show(User $user)
    {
        if (auth()->user()->isTeacher() && !$user->isStudent()) {
            abort(403, 'คุณไม่มีสิทธิ์ดูข้อมูลผู้ใช้งานกลุ่มนี้');
        }

        $attempts = [];
        if ($user->isStudent()) {
            $attempts = ExamAttempt::where('user_id', $user->id)
                ->with('exam.subject')
                ->orderBy('started_at', 'desc')
                ->get();
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
        $searchPerformed = false;
        $examStats = collect();
        $reports = collect();
        $exams = Exam::orderBy('title')->get();

        if (auth()->user()->isTeacher() || $request->has('search')) {
            $searchPerformed = true;

            // Get statistics per exam
            $statsQuery = Exam::with('subject')
                ->withCount(['examAttempts as total_attempts' => function ($query) {
                    $query->where('status', 'completed');
                }])
                ->withCount(['examAttempts as passed_attempts' => function ($query) {
                    $query->where('status', 'completed')->where('is_passed', true);
                }]);

            if ($request->filled('exam_id')) {
                $statsQuery->where('id', $request->get('exam_id'));
            }
            $examStats = $statsQuery->get();

            // Calculate passing rate for each exam
            foreach ($examStats as $exam) {
                $exam->passed_rate = $exam->total_attempts > 0 
                    ? round(($exam->passed_attempts / $exam->total_attempts) * 100, 2)
                    : 0;
                
                $exam->average_score = ExamAttempt::where('exam_id', $exam->id)
                    ->where('status', 'completed')
                    ->avg('score') ?? 0;
                $exam->average_score = round($exam->average_score, 2);
            }

            // Get detailed score history for the report table
            $reportsQuery = ExamAttempt::with(['user.classroom', 'exam.subject'])
                ->where('status', 'completed');

            if ($request->filled('exam_id')) {
                $reportsQuery->where('exam_id', $request->get('exam_id'));
            }

            if ($request->filled('q')) {
                $q = $request->get('q');
                $reportsQuery->whereHas('user', function($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                          ->orWhere('student_code', 'like', "%{$q}%");
                });
            }

            $reports = $reportsQuery->orderBy('completed_at', 'desc')->get();
        }

        return view('admin.reports.index', compact('examStats', 'reports', 'exams', 'searchPerformed'));
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

    public function import(Request $request)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนจัดการผู้ใช้งาน');
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:4096',
        ], [
            'file.required' => 'กรุณาเลือกไฟล์ที่จะอัปโหลด',
            'file.mimes' => 'ไฟล์ที่อัปโหลดต้องเป็นรูปแบบ .csv เท่านั้น',
            'file.max' => 'ขนาดไฟล์ต้องไม่เกิน 4MB',
        ]);

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());

        // If content is not valid UTF-8, convert from Windows-874 to UTF-8
        if (!mb_check_encoding($content, 'UTF-8')) {
            if (function_exists('iconv')) {
                $converted = @iconv('Windows-874', 'UTF-8//IGNORE', $content);
                if ($converted !== false) {
                    $content = $converted;
                }
            } else {
                $content = mb_convert_encoding($content, 'UTF-8', 'Windows-874');
            }
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        // Read header row
        $headers = fgetcsv($stream);
        if ($headers) {
            $headers[0] = preg_replace('/[\x{FEFF}\x{FFFE}]/u', '', $headers[0]); // strip UTF-8 BOM if present
            $headers = array_map('trim', $headers);
        }

        $successCount = 0;
        $errors = [];
        $rowNum = 1;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($stream)) !== false) {
                $rowNum++;
                if (empty(array_filter($row))) {
                    continue; // Skip empty rows
                }

                $name = isset($row[0]) ? trim($row[0]) : '';
                $student_code = isset($row[1]) ? trim($row[1]) : '';
                $citizen_id = isset($row[2]) ? trim($row[2]) : '';
                $classroom_name = isset($row[3]) ? trim($row[3]) : '';

                if (!$name || !$student_code || !$citizen_id) {
                    $errors[] = "แถวที่ {$rowNum}: ข้อมูลไม่ครบถ้วน (ต้องระบุ ชื่อ-นามสกุล, รหัสนักศึกษา, เลขประจำตัวประชาชน)";
                    continue;
                }

                if (strlen($student_code) > 13) {
                    $errors[] = "แถวที่ {$rowNum}: รหัสนักศึกษาต้องมีความยาวไม่เกิน 13 หลัก";
                    continue;
                }

                if (strlen($citizen_id) != 13) {
                    $errors[] = "แถวที่ {$rowNum}: เลขประจำตัวประชาชนต้องมีความยาวเท่ากับ 13 หลัก";
                    continue;
                }

                // Classroom lookup or creation
                $classroom_id = null;
                if ($classroom_name) {
                    $classroom = \App\Models\Classroom::firstOrCreate(['name' => $classroom_name]);
                    $classroom_id = $classroom->id;
                }

                // Check duplicate by student_code
                $user = User::where('student_code', $student_code)->first();

                if ($user) {
                    // Update existing
                    $user->update([
                        'name' => $name,
                        'citizen_id' => $citizen_id,
                        'password' => Hash::make($citizen_id),
                        'classroom_id' => $classroom_id,
                    ]);
                } else {
                    // Create new
                    User::create([
                        'name' => $name,
                        'username' => $student_code,
                        'student_code' => $student_code,
                        'citizen_id' => $citizen_id,
                        'password' => Hash::make($citizen_id),
                        'role' => 'student',
                        'classroom_id' => $classroom_id,
                    ]);
                }

                $successCount++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['import_error' => 'เกิดข้อผิดพลาดของระบบ: ' . $e->getMessage()]);
        } finally {
            fclose($stream);
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
}
