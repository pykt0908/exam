<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Subject;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Classroom;

class ExamController extends Controller
{
    public function index(Request $request)
    {
        $searchPerformed = false;
        $exams = collect();
        $user = auth()->user();

        // Load list options based on role
        if ($user->isAdmin()) {
            $subjects = Subject::orderBy('code')->get();
            $teachers = User::whereIn('role', ['admin', 'teacher'])->orderBy('name')->get();
            $classrooms = Classroom::orderBy('name')->get();
        } else {
            // Teacher - only show subjects they teach and classrooms of students in their subjects
            $subjects = $user->enrolledSubjects()->orderBy('code')->get();
            $teachers = collect([$user]); // Only themselves
            $classrooms = Classroom::whereHas('users', function($q) use ($user) {
                $q->where('role', 'student')->whereHas('enrolledSubjects.teachers', function($q2) use ($user) {
                    $q2->where('user_id', $user->id);
                });
            })->orderBy('name')->get();
        }

        if ($user->isTeacher() || $request->has('search')) {
            $searchPerformed = true;
            $query = Exam::with(['subject.teachers', 'subject.students.classroom'])->withCount('questions');

            // If teacher, strictly enforce scoping to subjects they teach
            if ($user->isTeacher()) {
                $query->whereHas('subject.teachers', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }

            if ($request->filled('subject_id')) {
                $query->where('subject_id', $request->get('subject_id'));
            }

            // Only admin filters by teacher (since teacher is already scoped to self)
            if ($user->isAdmin() && $request->filled('teacher_id')) {
                $teacherId = $request->get('teacher_id');
                $query->whereHas('subject.teachers', function($q) use ($teacherId) {
                    $q->where('user_id', $teacherId);
                });
            }

            if ($request->filled('classroom_id')) {
                $classroomId = $request->get('classroom_id');
                $query->whereHas('subject.students', function($q) use ($classroomId) {
                    $q->where('classroom_id', $classroomId);
                });
            }

            if ($request->filled('q')) {
                $q = $request->get('q');
                $query->where(function($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                          ->orWhere('description', 'like', "%{$q}%");
                });
            }

            $exams = $query->orderBy('created_at', 'desc')->get();
        }

        return view('admin.exams.index', compact('exams', 'subjects', 'teachers', 'classrooms', 'searchPerformed'));
    }

    public function store(Request $request)
    {
        $this->authorizeExamSubject(null, $request->subject_id);
        $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'passing_percentage' => ['required', 'integer', 'between:0,100'],
            'total_score' => ['required', 'numeric', 'min:0.01'],
            'max_attempts' => ['required', 'integer', 'min:1'],
            'max_focus_escapes' => ['required', 'integer', 'min:0'],
            'passcode' => ['nullable', 'string', 'max:255'],
        ]);

        $data = $request->only(
            'subject_id', 'title', 'description', 'duration_minutes', 'passing_percentage', 'total_score',
            'max_attempts', 'max_focus_escapes', 'passcode'
        ) + [
            'is_active' => $request->has('is_active'),
            'shuffle_questions' => $request->has('shuffle_questions'),
            'shuffle_choices' => $request->has('shuffle_choices'),
            'force_fullscreen' => $request->has('force_fullscreen'),
            'show_score' => $request->has('show_score'),
            'show_answers' => $request->has('show_answers'),
            'allow_review' => $request->has('allow_review')
        ];

        Exam::create($data);

        return redirect()->route('admin.exams.index')->with('success', 'สร้างข้อสอบใหม่เรียบร้อยแล้ว');
    }

    public function update(Request $request, Exam $exam)
    {
        $this->authorizeExamSubject($exam);
        $this->authorizeExamSubject(null, $request->subject_id);
        $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'passing_percentage' => ['required', 'integer', 'between:0,100'],
            'total_score' => ['required', 'numeric', 'min:0.01'],
            'max_attempts' => ['required', 'integer', 'min:1'],
            'max_focus_escapes' => ['required', 'integer', 'min:0'],
            'passcode' => ['nullable', 'string', 'max:255'],
        ]);

        $data = $request->only(
            'subject_id', 'title', 'description', 'duration_minutes', 'passing_percentage', 'total_score',
            'max_attempts', 'max_focus_escapes', 'passcode'
        ) + [
            'is_active' => $request->has('is_active'),
            'shuffle_questions' => $request->has('shuffle_questions'),
            'shuffle_choices' => $request->has('shuffle_choices'),
            'force_fullscreen' => $request->has('force_fullscreen'),
            'show_score' => $request->has('show_score'),
            'show_answers' => $request->has('show_answers'),
            'allow_review' => $request->has('allow_review')
        ];

        $exam->update($data);

        if ($request->input('redirect_to') === 'questions') {
            return redirect()->route('admin.exams.questions.index', $exam->id)->with('success', 'อัปเดตข้อมูลข้อสอบเรียบร้อยแล้ว');
        }

        return redirect()->route('admin.exams.index')->with('success', 'อัปเดตข้อมูลข้อสอบเรียบร้อยแล้ว');
    }

    public function destroy(Exam $exam)
    {
        $this->authorizeExamSubject($exam);
        $exam->delete();
        return redirect()->route('admin.exams.index')->with('success', 'ลบข้อสอบเรียบร้อยแล้ว');
    }

    public function toggleStatus(Exam $exam)
    {
        $this->authorizeExamSubject($exam);
        $exam->is_active = !$exam->is_active;
        $exam->save();
        return back()->with('success', 'เปลี่ยนสถานะเปิด/ปิดข้อสอบเรียบร้อยแล้ว');
    }

    private function authorizeExamSubject(?Exam $exam = null, $subjectId = null)
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return;
        }

        if ($exam) {
            $subjectId = $exam->subject_id;
        }

        if ($subjectId) {
            $teachesSubject = $user->enrolledSubjects()->where('subject_id', $subjectId)->exists();
            if (!$teachesSubject) {
                abort(403, 'คุณไม่มีสิทธิ์จัดการข้อสอบในวิชานี้');
            }
        }
    }
}
