<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Department;
use App\Models\User;
use App\Models\Exam;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $selectedDepartmentId = $request->get('department_id');
        $selectedTeacherId = $request->get('teacher_id');
        $selectedSubjectId = $request->get('subject_id');
        $selectedExamStatus = $request->get('exam_status');
        $q = $request->get('q');

        // Auto-detect department if subject is selected but department is not
        if ($selectedSubjectId && !$selectedDepartmentId) {
            $currentSubj = Subject::find($selectedSubjectId);
            if ($currentSubj && $currentSubj->department_id) {
                $selectedDepartmentId = $currentSubj->department_id;
            }
        }

        $departments = Department::orderBy('name')->get();

        // 1. Teachers: filtered by selected department
        if ($user->isAdmin()) {
            $teachersQuery = User::whereIn('role', ['admin', 'teacher'])->orderBy('name');
            if ($selectedDepartmentId) {
                $teachersQuery->where(function ($tq) use ($selectedDepartmentId) {
                    $tq->where('department_id', $selectedDepartmentId)
                       ->orWhereHas('enrolledSubjects', function ($sq) use ($selectedDepartmentId) {
                           $sq->where('department_id', $selectedDepartmentId);
                       });
                });
            }
            $teachers = $teachersQuery->get();
        } else {
            $teachers = collect([$user]);
        }

        // 2. Subjects: filtered by selected department, teacher, and exam status
        $subjectsQuery = $user->isAdmin()
            ? Subject::with(['teachers', 'department'])->orderBy('code')
            : $user->enrolledSubjects()->with(['teachers', 'department'])->orderBy('code');

        if ($selectedDepartmentId) {
            $subjectsQuery->where('department_id', $selectedDepartmentId);
        }

        if ($user->isAdmin() && $selectedTeacherId) {
            $subjectsQuery->whereHas('teachers', function ($tq) use ($selectedTeacherId) {
                $tq->where('users.id', $selectedTeacherId);
            });
        }

        if ($selectedExamStatus === 'no_exams') {
            $subjectsQuery->has('exams', '=', 0);
        } elseif ($selectedExamStatus === 'has_exams') {
            $subjectsQuery->has('exams', '>', 0);
        }

        if ($request->filled('q')) {
            $subjectsQuery->where(function($sq) use ($q) {
                $sq->where('code', 'like', "%{$q}%")
                   ->orWhere('name', 'like', "%{$q}%")
                   ->orWhereHas('exams', function($eq) use ($q) {
                       $eq->where('title', 'like', "%{$q}%")
                          ->orWhere('description', 'like', "%{$q}%");
                   });
            });
        }

        $subjects = $subjectsQuery->withCount(['students', 'exams'])->get();

        // 3. Exams: filtered by department, teacher, subject, keyword, and exam status
        $examsQuery = Exam::with(['subject.department', 'subject.teachers', 'approvalLogs.user', 'subject.students.classroom'])
            ->withCount('questions');

        if ($user->isTeacher()) {
            $examsQuery->whereHas('subject.teachers', function($tq) use ($user) {
                $tq->where('users.id', $user->id);
            });
        }

        if ($selectedDepartmentId) {
            $examsQuery->whereHas('subject', function($sq) use ($selectedDepartmentId) {
                $sq->where('department_id', $selectedDepartmentId);
            });
        }

        if ($user->isAdmin() && $selectedTeacherId) {
            $examsQuery->where(function($eq) use ($selectedTeacherId) {
                $eq->whereHas('subject.teachers', function($tq) use ($selectedTeacherId) {
                    $tq->where('users.id', $selectedTeacherId);
                })->orWhereHas('approvalLogs', function($lq) use ($selectedTeacherId) {
                    $lq->where('action', 'submitted')->where('user_id', $selectedTeacherId);
                });
            });
        }

        if ($selectedSubjectId) {
            $examsQuery->where('subject_id', $selectedSubjectId);
        }

        if ($selectedExamStatus === 'no_exams') {
            $examsQuery->whereRaw('1 = 0');
        }

        if ($request->filled('q')) {
            $examsQuery->where(function($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%")
                      ->orWhereHas('subject', function($sq) use ($q) {
                          $sq->where('code', 'like', "%{$q}%")
                             ->orWhere('name', 'like', "%{$q}%");
                      });
            });
        }

        $exams = $examsQuery->orderBy('created_at', 'desc')->get();

        return view('admin.subjects.index', compact(
            'exams',
            'subjects',
            'departments',
            'teachers',
            'selectedDepartmentId',
            'selectedTeacherId',
            'selectedSubjectId',
            'selectedExamStatus'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:subjects,code'],
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ], [
            'code.unique' => 'รหัสวิชานี้มีอยู่ในระบบแล้ว',
        ]);

        $data = $request->only('code', 'name', 'department_id');

        // If department_id was not explicitly specified, auto-assign from the creating teacher/staff's department
        if (empty($data['department_id']) && auth()->user()->department_id) {
            $data['department_id'] = auth()->user()->department_id;
        }

        $subject = Subject::create($data);

        if (auth()->user()->isStaff()) {
            $subject->teachers()->attach(auth()->id());
        }

        return redirect()->route('admin.subjects.index')->with('success', 'บันทึกข้อมูลรายวิชาเรียบร้อยแล้ว');
    }

    public function update(Request $request, Subject $subject)
    {
        $user = auth()->user();
        if (!$user->isAdmin()) {
            $teaches = $user->enrolledSubjects()->where('subject_id', $subject->id)->exists();
            if (!$teaches) {
                abort(403, 'คุณไม่มีสิทธิ์แก้ไขรายวิชานี้');
            }
        }

        $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:subjects,code,' . $subject->id],
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ], [
            'code.unique' => 'รหัสวิชานี้มีอยู่ในระบบแล้ว',
        ]);

        $subject->update($request->only('code', 'name', 'department_id'));

        return redirect()->route('admin.subjects.index')->with('success', 'อัปเดตข้อมูลรายวิชาเรียบร้อยแล้ว');
    }

    public function destroy(Subject $subject)
    {
        $user = auth()->user();
        if (!$user->isAdmin()) {
            $teaches = $user->enrolledSubjects()->where('subject_id', $subject->id)->exists();
            if (!$teaches) {
                abort(403, 'คุณไม่มีสิทธิ์ลบรายวิชานี้');
            }
        }

        $subject->delete();
        return redirect()->route('admin.subjects.index')->with('success', 'ลบข้อมูลรายวิชาเรียบร้อยแล้ว');
    }
}
