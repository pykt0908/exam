<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\User;
use App\Models\Classroom;
use Illuminate\Http\Request;

class SubjectStudentController extends Controller
{
    public function index(Request $request, Subject $subject)
    {
        $this->authorizeSubject($subject);
        $selectedClassroomId = $request->get('classroom_id');

        // Students not yet enrolled in this subject (candidates for individual addition)
        $enrolledStudentIds = $subject->students()->pluck('users.id')->toArray();
        $availableStudents = User::where('role', 'student')
            ->whereNotIn('id', $enrolledStudentIds)
            ->with('classroom:id,name')
            ->orderBy('student_code', 'asc')
            ->get(['id', 'name', 'student_code', 'classroom_id']);

        $allClassrooms = Classroom::withCount(['users as users_count' => function($q) {
            $q->where('role', 'student');
        }])->orderBy('name')->get();

        if ($selectedClassroomId) {
            if ($selectedClassroomId === 'unassigned') {
                $selectedClassroom = (object)[
                    'id' => 'unassigned',
                    'name' => 'ไม่มีกลุ่มเรียน (ไม่ระบุห้อง)'
                ];
                $students = $subject->students()
                    ->whereNull('classroom_id')
                    ->orderBy('student_code', 'asc')
                    ->get();
            } else {
                $selectedClassroom = Classroom::findOrFail($selectedClassroomId);
                $students = $subject->students()
                    ->where('classroom_id', $selectedClassroom->id)
                    ->orderBy('student_code', 'asc')
                    ->get();
            }

            return view('admin.subjects.students', compact(
                'subject',
                'selectedClassroom',
                'students',
                'allClassrooms',
                'availableStudents'
            ));
        }

        // List classrooms enrolled in this subject
        $enrolledClassrooms = Classroom::whereHas('users', function($q) use ($subject) {
            $q->whereHas('enrolledSubjects', function($sq) use ($subject) {
                $sq->where('subjects.id', $subject->id);
            });
        })->withCount(['users as subject_students_count' => function($q) use ($subject) {
            $q->where('role', 'student')
              ->whereHas('enrolledSubjects', function($sq) use ($subject) {
                  $sq->where('subjects.id', $subject->id);
              });
        }])->orderBy('name')->get();

        $unassignedStudentsCount = $subject->students()->whereNull('classroom_id')->count();
        $totalStudentsCount = $subject->students()->count();

        return view('admin.subjects.students', compact(
            'subject',
            'enrolledClassrooms',
            'allClassrooms',
            'availableStudents',
            'unassignedStudentsCount',
            'totalStudentsCount'
        ));
    }

    public function store(Request $request, Subject $subject)
    {
        $this->authorizeSubject($subject);

        // 1. เพิ่มนักศึกษารายคน (Individual Students)
        if ($request->has('student_ids')) {
            $studentIds = $request->input('student_ids');
            if (empty($studentIds) || !is_array($studentIds)) {
                return redirect()->back()->withErrors(['student_ids' => 'กรุณาเลือกนักศึกษาอย่างน้อย 1 คน']);
            }

            $students = User::where('role', 'student')
                ->whereIn('id', $studentIds)
                ->get();

            if ($students->isEmpty()) {
                return redirect()->back()->withErrors(['student_ids' => 'ไม่พบข้อมูลนักศึกษาที่เลือกในระบบ']);
            }

            $subject->students()->syncWithoutDetaching($students->pluck('id')->toArray());

            $count = $students->count();
            $msg = "เพิ่มนักศึกษาจำนวน {$count} คน เข้าร่วมรายวิชาเรียบร้อยแล้ว";

            return redirect()->back()->with('success', $msg);
        }

        // 2. เพิ่มทั้งกลุ่มเรียน (Classrooms)
        $classroomIds = $request->input('classroom_ids');
        if (empty($classroomIds) && $request->filled('classroom_id')) {
            $classroomIds = [$request->input('classroom_id')];
        }

        if (empty($classroomIds) || !is_array($classroomIds)) {
            return redirect()->back()->withErrors(['classroom_ids' => 'กรุณาเลือกห้องเรียน / ระดับชั้นอย่างน้อย 1 ห้อง']);
        }

        $classrooms = Classroom::whereIn('id', $classroomIds)->get();
        if ($classrooms->isEmpty()) {
            return redirect()->back()->withErrors(['classroom_ids' => 'ไม่พบห้องเรียนที่เลือกในระบบ']);
        }

        $studentIds = User::where('role', 'student')
            ->whereIn('classroom_id', $classrooms->pluck('id'))
            ->pluck('id')
            ->toArray();

        if (empty($studentIds)) {
            return redirect()->back()->withErrors(['error' => 'ไม่พบนักศึกษาในห้องเรียนที่เลือก']);
        }

        $subject->students()->syncWithoutDetaching($studentIds);

        $classroomNames = $classrooms->pluck('name')->implode(', ');
        $roomCount = $classrooms->count();
        $studentCount = count($studentIds);

        $msg = $roomCount === 1
            ? "เพิ่มห้องเรียน {$classroomNames} เข้าร่วมรายวิชาเรียบร้อยแล้ว ({$studentCount} คน)"
            : "เพิ่มห้องเรียนจำนวน {$roomCount} ห้อง ({$classroomNames}) เข้าร่วมรายวิชาเรียบร้อยแล้ว (รวม {$studentCount} คน)";

        return redirect()->route('admin.subjects.students.index', $subject->id)
            ->with('success', $msg);
    }

    public function destroy(Subject $subject, User $student)
    {
        $this->authorizeSubject($subject);
        $subject->students()->detach($student->id);
        return redirect()->back()->with('success', 'นำนักศึกษาออกจากรายวิชาเรียบร้อยแล้ว');
    }

    public function destroyClassroom(Subject $subject, Classroom $classroom)
    {
        $this->authorizeSubject($subject);
        $studentIds = User::where('classroom_id', $classroom->id)
            ->where('role', 'student')
            ->pluck('id')
            ->toArray();

        $subject->students()->detach($studentIds);

        return redirect()->route('admin.subjects.students.index', $subject->id)
            ->with('success', "นำห้องเรียน {$classroom->name} และนักศึกษาทั้งหมดออกจากรายวิชาเรียบร้อยแล้ว");
    }

    public function bulkDestroy(Request $request, Subject $subject)
    {
        $this->authorizeSubject($subject);
        $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['exists:users,id'],
        ], [
            'student_ids.required' => 'กรุณาเลือกนักศึกษาที่ต้องการนำออกอย่างน้อย 1 คน',
        ]);

        $subject->students()->detach($request->student_ids);

        return redirect()->back()->with('success', 'นำนักศึกษาที่เลือกออกจากรายวิชาเรียบร้อยแล้ว');
    }

    private function authorizeSubject(Subject $subject)
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return;
        }
        $teaches = $user->enrolledSubjects()->where('subject_id', $subject->id)->exists();
        if (!$teaches) {
            abort(403, 'คุณไม่มีสิทธิ์จัดการนักศึกษาในรายวิชานี้');
        }
    }
}
