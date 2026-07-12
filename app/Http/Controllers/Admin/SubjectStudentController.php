<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\User;
use App\Models\Classroom;
use Illuminate\Http\Request;

class SubjectStudentController extends Controller
{
    public function index(Subject $subject)
    {
        $this->authorizeSubject($subject);
        $enrolledStudents = $subject->students()->with('classroom')->orderBy('name')->get();
        $classrooms = Classroom::orderBy('name')->get();
        
        $availableStudents = User::where('role', 'student')
            ->whereNotIn('id', $enrolledStudents->pluck('id'))
            ->with('classroom')
            ->orderBy('name')
            ->get();

        return view('admin.subjects.students', compact('subject', 'enrolledStudents', 'classrooms', 'availableStudents'));
    }

    public function store(Request $request, Subject $subject)
    {
        $this->authorizeSubject($subject);
        $request->validate([
            'enroll_type' => ['required', 'string', 'in:classroom,individual'],
            'classroom_id' => ['required_if:enroll_type,classroom', 'nullable', 'exists:classrooms,id'],
            'student_ids' => ['required_if:enroll_type,individual', 'nullable', 'array'],
            'student_ids.*' => ['exists:users,id'],
        ], [
            'classroom_id.required_if' => 'กรุณาเลือกห้องเรียน',
            'student_ids.required_if' => 'กรุณาเลือกนักศึกษาอย่างน้อย 1 คน',
        ]);

        if ($request->enroll_type === 'classroom') {
            $studentIds = User::where('role', 'student')
                ->where('classroom_id', $request->classroom_id)
                ->pluck('id')
                ->toArray();

            if (empty($studentIds)) {
                return redirect()->back()->withErrors(['error' => 'ไม่พบนักศึกษาในห้องเรียนที่เลือก']);
            }

            $subject->students()->syncWithoutDetaching($studentIds);
            
            $classroom = Classroom::find($request->classroom_id);
            return redirect()->back()->with('success', "เพิ่มนักศึกษาจากห้อง {$classroom->name} เข้าร่วมรายวิชาเรียบร้อยแล้ว");
        } else {
            $subject->students()->syncWithoutDetaching($request->student_ids);
            return redirect()->back()->with('success', 'เพิ่มนักศึกษาเข้าร่วมรายวิชาเรียบร้อยแล้ว');
        }
    }

    public function destroy(Subject $subject, User $student)
    {
        $this->authorizeSubject($subject);
        $subject->students()->detach($student->id);
        return redirect()->back()->with('success', 'นำนักศึกษาออกจากรายวิชาเรียบร้อยแล้ว');
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
