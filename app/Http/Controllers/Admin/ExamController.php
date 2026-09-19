<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Subject;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Classroom;
use App\Models\Department;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $selectedDepartmentId = $request->get('department_id');
        $selectedTeacherId = $request->get('teacher_id');
        $selectedSubjectId = $request->get('subject_id');

        if ($selectedSubjectId && !$selectedDepartmentId) {
            $currentSubj = Subject::find($selectedSubjectId);
            if ($currentSubj && $currentSubj->department_id) {
                $selectedDepartmentId = $currentSubj->department_id;
            }
        }

        $departments = Department::orderBy('name')->get();

        // Load list options based on role
        if ($user->isAdmin()) {
            // Filter teachers based on selected department
            $teachersQuery = User::whereIn('role', ['admin', 'teacher'])->orderBy('name');
            if ($selectedDepartmentId) {
                $teachersQuery->where('department_id', $selectedDepartmentId);
            }
            $teachers = $teachersQuery->get();

            // Filter subjects based on selected teacher or department
            $subjectsQuery = Subject::with('teachers')->orderBy('code');
            if ($selectedTeacherId) {
                // Show subjects taught by this teacher
                $subjectsQuery->whereHas('teachers', function($q) use ($selectedTeacherId) {
                    $q->where('users.id', $selectedTeacherId);
                });
            } elseif ($selectedDepartmentId) {
                // Show subjects of this department
                $subjectsQuery->where(function($q) use ($selectedDepartmentId) {
                    $q->where('department_id', $selectedDepartmentId)
                      ->orWhereHas('teachers', function($tq) use ($selectedDepartmentId) {
                          $tq->where('department_id', $selectedDepartmentId);
                      });
                });
            }
            $subjects = $subjectsQuery->get();
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

        $query = Exam::with(['subject.teachers', 'approvalLogs.user', 'subject.students.classroom'])->withCount('questions');

        // If teacher, strictly enforce scoping to subjects they teach
        if ($user->isTeacher()) {
            $query->whereHas('subject.teachers', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Filter by Department (admin only)
        if ($user->isAdmin() && $request->filled('department_id')) {
            $departmentId = $request->get('department_id');
            $query->whereHas('subject', function($q) use ($departmentId) {
                $q->where(function($sq) use ($departmentId) {
                    $sq->where('department_id', $departmentId)
                       ->orWhereHas('teachers', function($tq) use ($departmentId) {
                           $tq->where('department_id', $departmentId);
                       });
                });
            });
        }

        // Filter by Subject
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->get('subject_id'));
        }

        // Only admin filters by teacher (since teacher is already scoped to self)
        if ($user->isAdmin() && $selectedTeacherId) {
            $teacherId = $selectedTeacherId;
            $query->where(function($q) use ($teacherId) {
                $q->whereHas('subject.teachers', function($sq) use ($teacherId) {
                    $sq->where('users.id', $teacherId);
                })->orWhereHas('approvalLogs', function($sq) use ($teacherId) {
                    $sq->where('action', 'submitted')->where('user_id', $teacherId);
                });
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

        return view('admin.exams.index', compact(
            'exams', 'subjects', 'teachers', 'departments', 'classrooms', 'selectedDepartmentId'
        ));
    }

    public function create(Request $request)
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            $subjects = Subject::orderBy('code')->get();
        } else {
            $subjects = $user->enrolledSubjects()->orderBy('code')->get();
        }

        $selectedSubjectId = $request->get('subject_id');

        return view('admin.exams.create', compact('subjects', 'selectedSubjectId'));
    }

    public function store(Request $request)
    {
        $this->authorizeExamSubject(null, $request->subject_id);
        $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'passing_percentage' => ['required', 'integer', 'between:0,100'],
            'total_score' => ['required', 'numeric', 'min:0.01'],
            'max_attempts' => ['required', 'integer', 'min:1'],
            'max_focus_escapes' => ['required', 'integer', 'min:0'],
            'passcode' => ['nullable', 'string', 'max:255'],
        ]);

        $data = $request->only(
            'subject_id', 'title', 'description', 'duration_minutes', 'starts_at', 'ends_at',
            'passing_percentage', 'total_score', 'max_attempts', 'max_focus_escapes', 'passcode'
        ) + [
            'is_active' => false, // New exams start inactive until approved
            'approval_status' => 'draft',
            'shuffle_questions' => $request->has('shuffle_questions'),
            'shuffle_choices' => $request->has('shuffle_choices'),
            'force_fullscreen' => $request->has('force_fullscreen'),
            'show_score' => $request->has('show_score'),
            'show_answers' => $request->has('show_answers'),
            'allow_review' => $request->has('allow_review')
        ];

        Exam::create($data);

        return redirect()->route('admin.exams.index')->with('success', 'สร้างข้อสอบใหม่เรียบร้อยแล้ว (สถานะ: ฉบับร่าง)');
    }

    public function update(Request $request, Exam $exam)
    {
        $this->authorizeExamSubject($exam);
        $this->authorizeExamSubject(null, $request->subject_id);

        $user = auth()->user();
        if (!$user->isAdmin() && !$exam->canBeEdited()) {
            return back()->with('error', 'ข้อสอบนี้อยู่ระหว่างรออนุมัติหรือได้รับการอนุมัติแล้ว ไม่สามารถแก้ไขได้ กรุณาดึงกลับมาเป็นฉบับร่างก่อน');
        }

        $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'passing_percentage' => ['required', 'integer', 'between:0,100'],
            'total_score' => ['required', 'numeric', 'min:0.01'],
            'max_attempts' => ['required', 'integer', 'min:1'],
            'max_focus_escapes' => ['required', 'integer', 'min:0'],
            'passcode' => ['nullable', 'string', 'max:255'],
        ]);

        $data = $request->only(
            'subject_id', 'title', 'description', 'duration_minutes', 'starts_at', 'ends_at',
            'passing_percentage', 'total_score', 'max_attempts', 'max_focus_escapes', 'passcode'
        ) + [
            'shuffle_questions' => $request->has('shuffle_questions'),
            'shuffle_choices' => $request->has('shuffle_choices'),
            'force_fullscreen' => $request->has('force_fullscreen'),
            'show_score' => $request->has('show_score'),
            'show_answers' => $request->has('show_answers'),
            'allow_review' => $request->has('allow_review')
        ];

        // Only allow toggling is_active if approved
        if ($request->has('is_active')) {
            if ($exam->isApproved()) {
                $data['is_active'] = true;
            }
        } else {
            $data['is_active'] = false;
        }

        $exam->update($data);

        if ($request->input('redirect_to') === 'questions') {
            return redirect()->route('admin.exams.questions.index', $exam->id)->with('success', 'อัปเดตข้อมูลข้อสอบเรียบร้อยแล้ว');
        }

        return redirect()->route('admin.exams.index')->with('success', 'อัปเดตข้อมูลข้อสอบเรียบร้อยแล้ว');
    }

    public function destroy(Exam $exam)
    {
        $this->authorizeExamSubject($exam);

        $user = auth()->user();
        if (!$user->isAdmin() && !$exam->canBeEdited()) {
            return back()->with('error', 'ข้อสอบนี้อยู่ระหว่างรออนุมัติหรือได้รับการอนุมัติแล้ว ไม่สามารถลบได้');
        }

        $exam->delete();
        return redirect()->route('admin.exams.index')->with('success', 'ลบข้อสอบเรียบร้อยแล้ว');
    }

    public function toggleStatus(Exam $exam)
    {
        $this->authorizeExamSubject($exam);

        // If trying to turn on, verify approval
        if (!$exam->is_active && !$exam->isApproved()) {
            return back()->with('error', 'ไม่สามารถเปิดใช้งานข้อสอบได้ เนื่องจากยังไม่ผ่านการอนุมัติครบทั้ง 3 ขั้นตอน (สถานะปัจจุบัน: ' . $exam->approval_status_label . ')');
        }

        $exam->is_active = !$exam->is_active;
        $exam->save();
        return back()->with('success', 'เปลี่ยนสถานะเปิด/ปิดข้อสอบเรียบร้อยแล้ว');
    }

    public function duplicate(Exam $exam)
    {
        $this->authorizeExamSubject($exam);

        $newExam = DB::transaction(function () use ($exam) {
            // Eager load sections, questions, and choices
            $exam->load(['sections', 'questions.choices']);

            // 1. Create duplicate Exam with reset approval status to draft
            $newExam = Exam::create([
                'subject_id' => $exam->subject_id,
                'title' => $exam->title . ' (สำเนา)',
                'description' => $exam->description,
                'duration_minutes' => $exam->duration_minutes,
                'starts_at' => $exam->starts_at,
                'ends_at' => $exam->ends_at,
                'passing_percentage' => $exam->passing_percentage,
                'total_score' => $exam->total_score,
                'is_active' => false, // Always start inactive until approved
                'max_attempts' => $exam->max_attempts,
                'shuffle_questions' => $exam->shuffle_questions,
                'shuffle_choices' => $exam->shuffle_choices,
                'force_fullscreen' => $exam->force_fullscreen,
                'max_focus_escapes' => $exam->max_focus_escapes,
                'passcode' => $exam->passcode,
                'show_score' => $exam->show_score,
                'show_answers' => $exam->show_answers,
                'allow_review' => $exam->allow_review,
                // Approval fields strictly reset to draft
                'approval_status' => 'draft',
                'submitted_at' => null,
                'dept_approved_by' => null,
                'dept_approved_at' => null,
                'dept_feedback' => null,
                'eval_approved_by' => null,
                'eval_approved_at' => null,
                'eval_feedback' => null,
                'academic_approved_by' => null,
                'academic_approved_at' => null,
                'academic_feedback' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_note' => null,
                'rejected_stage' => null,
            ]);

            // 2. Duplicate sections & map old ID to new ID
            $sectionIdMap = [];
            foreach ($exam->sections as $oldSection) {
                $newSection = $newExam->sections()->create([
                    'title' => $oldSection->title,
                    'instruction' => $oldSection->instruction,
                    'sort_order' => $oldSection->sort_order,
                ]);
                $sectionIdMap[$oldSection->id] = $newSection->id;
            }

            // 3. Duplicate questions and choices
            foreach ($exam->questions as $oldQuestion) {
                $newQuestion = $newExam->questions()->create([
                    'exam_section_id' => $oldQuestion->exam_section_id ? ($sectionIdMap[$oldQuestion->exam_section_id] ?? null) : null,
                    'type' => $oldQuestion->type,
                    'question_text' => $oldQuestion->question_text,
                    'question_image' => $oldQuestion->question_image,
                    'score' => $oldQuestion->score,
                    'essay_answer' => $oldQuestion->essay_answer,
                ]);

                foreach ($oldQuestion->choices as $oldChoice) {
                    $newQuestion->choices()->create([
                        'choice_text' => $oldChoice->choice_text,
                        'choice_image' => $oldChoice->choice_image,
                        'is_correct' => $oldChoice->is_correct,
                    ]);
                }
            }

            return $newExam;
        });

        return redirect()->route('admin.exams.index')->with('success', "คัดลอกข้อสอบ '{$newExam->title}' สำเร็จแล้ว (สถานะ: ฉบับร่าง) กรุณาตรวจสอบและยื่นขออนุมัติใหม่ก่อนเปิดใช้งาน");
    }

    public function preview(Request $request, Exam $exam)
    {
        $user = auth()->user();
        $isStaffWithAcademicRole = $user->isStaff() && !empty($user->academic_roles);
        if (!$user->isAdmin() && !$isStaffWithAcademicRole) {
            $this->authorizeExamSubject($exam);
        }

        $mode = $request->get('mode', 'approval');

        if ($mode === 'take') {
            $questions = \App\Models\Question::where('questions.exam_id', $exam->id)
                ->leftJoin('exam_sections', 'questions.exam_section_id', '=', 'exam_sections.id')
                ->select('questions.*')
                ->orderByRaw('COALESCE(exam_sections.sort_order, 99999) ASC')
                ->orderByRaw('COALESCE(exam_sections.id, 99999) ASC')
                ->orderByRaw('COALESCE(questions.sort_order, questions.id) ASC')
                ->orderBy('questions.id', 'ASC')
                ->with(['examSection', 'choices'])
                ->get();

            $exam->load(['subject.department', 'subject.teachers', 'sections']);

            return view('admin.exams.preview_take', compact('exam', 'questions'));
        }

        // Mode: approval
        $exam->load([
            'subject.department',
            'subject.teachers',
            'sections.questions.choices',
            'deptApprover',
            'evalApprover',
            'academicApprover',
            'rejecter',
            'approvalLogs.user'
        ]);

        return view('admin.exams.preview_approval', compact('exam'));
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
