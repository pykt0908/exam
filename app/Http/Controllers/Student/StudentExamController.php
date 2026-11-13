<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Choice;
use App\Models\ExamAttempt;
use App\Models\ExamSection;
use App\Models\StudentAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentExamController extends Controller
{
    public function index()
    {
        $student = Auth::user();
        $student->load('enrolledSubjects');
        
        // List active exams that the student can take (only for enrolled subjects)
        $enrolledSubjectIds = $student->enrolledSubjects->pluck('id');

        $exams = Exam::where('is_active', true)
            ->where('approval_status', 'approved')
            ->whereIn('subject_id', $enrolledSubjectIds)
            ->with('subject')
            ->withCount('questions')
            ->orderByRaw('CASE WHEN starts_at IS NULL THEN 1 ELSE 0 END, starts_at ASC, id ASC')
            ->get();

        // Previous attempts
        $attempts = ExamAttempt::where('user_id', $student->id)
            ->with('exam.subject')
            ->orderBy('completed_at', 'desc')
            ->get();

        // ซ่อนข้อสอบที่เข้าข่ายสถานะ "หมดเวลาสอบแล้ว" สำหรับนักเรียน
        $exams = $exams->reject(function ($exam) use ($student, $attempts) {
            if (!$exam->isExpired()) {
                return false;
            }

            $eligibility = $student->getExamEligibility($exam);
            if (!$eligibility['eligible']) {
                return true;
            }

            $hasInProgress = $attempts->where('exam_id', $exam->id)->where('status', 'in_progress')->first();
            if ($hasInProgress) {
                return false;
            }

            $completedAttempts = $attempts->where('exam_id', $exam->id)->where('status', 'completed');
            $latestCompleted = $completedAttempts->first();
            if ($latestCompleted && $latestCompleted->isPendingGrading()) {
                return false;
            }

            $hasPassed = $completedAttempts->where('is_passed', true)->isNotEmpty();
            $allowedAttempts = $exam->getAllowedAttemptsForUser($student->id);
            $extraAttempts = $exam->getExtraAttemptsForUser($student->id);
            $completedAttemptsCount = $completedAttempts->count();
            $canRetake = ($completedAttemptsCount < $allowedAttempts) && (($completedAttemptsCount > 0 && !$hasPassed) || ($extraAttempts > 0));

            if ($canRetake) {
                return false;
            }

            if ($hasPassed && $extraAttempts === 0) {
                return false;
            }

            // หมดเวลาสอบแล้ว และไม่มีสิทธิ์สอบซ่อม/ทำต่อ -> ปิดการแสดงผล
            return true;
        })->values();

        return view('student.dashboard', compact('exams', 'attempts'));
    }

    public function lobby(Exam $exam)
    {
        if (!$exam->is_active || !$exam->isApproved()) {
            abort(404, 'ข้อสอบนี้ยังไม่เปิดให้เข้าทำหรือไม่ผ่านการอนุมัติ');
        }

        $student = Auth::user();
        $student->load('enrolledSubjects');
        $eligibility = $student->getExamEligibility($exam);
        if (!$eligibility['eligible']) {
            return redirect()->route('student.dashboard')->with('error', 'คุณไม่มีสิทธิ์เข้าสอบวิชานี้ (' . ($eligibility['reason'] ?: 'ถูกระงับสิทธิ์') . ') กรุณาติดต่ออาจารย์ผู้สอนหรือฝ่ายการเงิน/ทะเบียน');
        }

        if ($exam->isUpcoming()) {
            return redirect()->route('student.dashboard')->with('error', 'ยังไม่ถึงกำหนดเวลาสอบ ข้อสอบจะเปิดให้เข้าทำในวันที่ ' . $exam->starts_at->format('d/m/Y H:i น.'));
        }

        $completedAttemptsCount = ExamAttempt::where('user_id', Auth::id())
            ->where('exam_id', $exam->id)
            ->where('status', 'completed')
            ->count();

        $allowedAttempts = $exam->getAllowedAttemptsForUser(Auth::id());
        $extraAttempts = $exam->getExtraAttemptsForUser(Auth::id());

        $hasPassed = ExamAttempt::where('user_id', Auth::id())
            ->where('exam_id', $exam->id)
            ->where('status', 'completed')
            ->where('is_passed', true)
            ->exists();

        // สามารถสอบซ่อม/สอบแก้ตัวได้ หากยังใช้สิทธิ์ไม่ครบ และ (ยังสอบไม่ผ่าน หรือ ครูได้ให้สิทธิ์สอบเพิ่มพิเศษ)
        $canRetake = ($completedAttemptsCount < $allowedAttempts) && (($completedAttemptsCount > 0 && !$hasPassed) || ($extraAttempts > 0));

        if ($exam->isExpired() && !$canRetake) {
            return redirect()->route('student.dashboard')->with('error', 'หมดเวลาการทำข้อสอบชุดนี้แล้ว (ปิดระบบเมื่อ ' . $exam->ends_at->format('d/m/Y H:i น.') . ')');
        }

        if ($completedAttemptsCount >= $allowedAttempts) {
            if ($hasPassed) {
                return redirect()->route('student.dashboard')->with('error', 'คุณผ่านเกณฑ์การสอบวิชานี้เรียบร้อยแล้ว');
            }
            return redirect()->route('student.dashboard')->with('error', 'คุณทำข้อสอบนี้ครบกำหนดจำนวนครั้งแล้ว (' . $allowedAttempts . ' ครั้ง) หากต้องการสอบแก้ตัว กรุณาติดต่ออาจารย์ผู้สอน');
        }

        if ($hasPassed && $extraAttempts === 0) {
            return redirect()->route('student.dashboard')->with('error', 'คุณผ่านเกณฑ์การสอบวิชานี้เรียบร้อยแล้ว ไม่จำเป็นต้องสอบซ่อม');
        }

        $exam->load('subject')->loadCount('questions');
        return view('student.exam.lobby', compact('exam', 'completedAttemptsCount', 'allowedAttempts', 'canRetake'));
    }

    public function start(Request $request, Exam $exam)
    {
        if (!$exam->is_active || !$exam->isApproved()) {
            abort(404, 'ข้อสอบนี้ยังไม่เปิดให้เข้าทำหรือไม่ผ่านการอนุมัติ');
        }

        $student = Auth::user();
        $student->load('enrolledSubjects');
        $eligibility = $student->getExamEligibility($exam);
        if (!$eligibility['eligible']) {
            return redirect()->route('student.dashboard')->with('error', 'คุณไม่มีสิทธิ์เข้าสอบวิชานี้ (' . ($eligibility['reason'] ?: 'ถูกระงับสิทธิ์') . ') กรุณาติดต่ออาจารย์ผู้สอนหรือฝ่ายการเงิน/ทะเบียน');
        }

        if ($exam->isUpcoming()) {
            return redirect()->route('student.dashboard')->with('error', 'ยังไม่ถึงกำหนดเวลาสอบ ข้อสอบจะเปิดให้เข้าทำในวันที่ ' . $exam->starts_at->format('d/m/Y H:i น.'));
        }

        $completedAttemptsCount = ExamAttempt::where('user_id', $student->id)
            ->where('exam_id', $exam->id)
            ->where('status', 'completed')
            ->count();

        $allowedAttempts = $exam->getAllowedAttemptsForUser($student->id);
        $extraAttempts = $exam->getExtraAttemptsForUser($student->id);

        $hasPassed = ExamAttempt::where('user_id', $student->id)
            ->where('exam_id', $exam->id)
            ->where('status', 'completed')
            ->where('is_passed', true)
            ->exists();

        // สามารถสอบซ่อม/สอบแก้ตัวได้ หากยังใช้สิทธิ์ไม่ครบ และ (ยังสอบไม่ผ่าน หรือ ครูได้ให้สิทธิ์สอบเพิ่มพิเศษ)
        $canRetake = ($completedAttemptsCount < $allowedAttempts) && (($completedAttemptsCount > 0 && !$hasPassed) || ($extraAttempts > 0));

        if ($exam->isExpired() && !$canRetake) {
            return redirect()->route('student.dashboard')->with('error', 'หมดเวลาการทำข้อสอบชุดนี้แล้ว (ปิดระบบเมื่อ ' . $exam->ends_at->format('d/m/Y H:i น.') . ')');
        }

        if ($completedAttemptsCount >= $allowedAttempts) {
            if ($hasPassed) {
                return redirect()->route('student.dashboard')->with('error', 'คุณผ่านเกณฑ์การสอบวิชานี้เรียบร้อยแล้ว');
            }
            return redirect()->route('student.dashboard')->with('error', 'คุณทำข้อสอบนี้ครบกำหนดจำนวนครั้งแล้ว');
        }

        if ($hasPassed && $extraAttempts === 0) {
            return redirect()->route('student.dashboard')->with('error', 'คุณผ่านเกณฑ์การสอบวิชานี้เรียบร้อยแล้ว ไม่จำเป็นต้องสอบซ่อม');
        }

        // If there's an existing in-progress attempt, redirect to it instead of creating a new one
        $existingAttempt = ExamAttempt::where('user_id', $student->id)
            ->where('exam_id', $exam->id)
            ->where('status', 'in_progress')
            ->first();

        if ($existingAttempt) {
            session()->put('exam_just_started_' . $existingAttempt->id, true);
            return redirect()->route('student.exam.take', [$exam->id, $existingAttempt->id]);
        }

        // Validate passcode if set
        if ($exam->passcode) {
            $request->validate([
                'passcode' => ['required', 'string'],
            ], [
                'passcode.required' => 'กรุณากรอกรหัสผ่านเข้าห้องสอบ',
            ]);

            if ($request->passcode !== $exam->passcode) {
                return redirect()->back()->withErrors(['passcode' => 'รหัสผ่านเข้าห้องสอบไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง หรือติดต่ออาจารย์ผู้สอน']);
            }
        }

        // Determine attempt number for this student and exam
        $attemptNumber = ExamAttempt::where('user_id', $student->id)
            ->where('exam_id', $exam->id)
            ->count() + 1;

        // Create new attempt
        $attempt = ExamAttempt::create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'attempt_number' => $attemptNumber,
            'started_at' => now(),
            'total_questions' => $exam->questions()->count(),
            'status' => 'in_progress',
        ]);

        session()->put('exam_just_started_' . $attempt->id, true);

        return redirect()->route('student.exam.take', [$exam->id, $attempt->id]);
    }

    public function take(Exam $exam, ExamAttempt $attempt)
    {
        // Safety checks
        if ($attempt->user_id !== Auth::id() || $attempt->exam_id !== $exam->id) {
            abort(403, 'เข้าถึงส่วนนี้ไม่ได้');
        }

        $student = Auth::user();
        $student->load('enrolledSubjects');
        $eligibility = $student->getExamEligibility($exam);
        if (!$eligibility['eligible']) {
            return redirect()->route('student.dashboard')->with('error', 'คุณไม่มีสิทธิ์เข้าสอบวิชานี้ (' . ($eligibility['reason'] ?: 'ถูกระงับสิทธิ์') . ') กรุณาติดต่ออาจารย์ผู้สอนหรือฝ่ายการเงิน/ทะเบียน');
        }

        if ($attempt->status === 'completed') {
            return redirect()->route('student.exam.result', $attempt->id)
                ->with('error', 'ข้อสอบนี้ถูกส่งไปเรียบร้อยแล้ว');
        }

        // Calculate time remaining
        $started = $attempt->started_at;
        $duration = $exam->duration_minutes;
        $expiresAt = $started->copy()->addMinutes($duration);
        // Only cap by exam ends_at if ends_at was set and in the future relative to when the attempt was started
        if ($exam->ends_at && $exam->ends_at->gt($started) && $exam->ends_at->lt($expiresAt)) {
            $expiresAt = $exam->ends_at;
        }
        $timeRemainingSeconds = $expiresAt->timestamp - now()->timestamp;

        // If time is up, auto-submit
        if ($timeRemainingSeconds <= 0) {
            return $this->submit($attempt);
        }

        // Check if this is initial entry from lobby or a page refresh/re-entry
        $isInitialEntry = session()->pull('exam_just_started_' . $attempt->id, false);
        $wasRefreshed = false;

        if (!$isInitialEntry && $exam->max_focus_escapes > 0) {
            $lastAjaxEscape = session('last_ajax_focus_escape_at_' . $attempt->id, 0);
            // If an AJAX focus escape was already recorded within the last 3 seconds (e.g. from pagehide/visibilitychange right before reload), avoid double-counting
            if (now()->timestamp - $lastAjaxEscape >= 3) {
                $attempt->increment('focus_escape_count');
                $attempt->refresh();
            }
            $wasRefreshed = true;
        }

        // If focus escape limit exceeded, auto-submit
        if ($exam->max_focus_escapes > 0 && $attempt->focus_escape_count >= $exam->max_focus_escapes) {
            return $this->submit($attempt);
        }

        // Load questions and choices sorted by section sort order, section ID, and question ID
        $questions = Question::where('questions.exam_id', $exam->id)
            ->leftJoin('exam_sections', 'questions.exam_section_id', '=', 'exam_sections.id')
            ->select('questions.*')
            ->orderByRaw('COALESCE(exam_sections.sort_order, 99999) ASC')
            ->orderByRaw('COALESCE(exam_sections.id, 99999) ASC')
            ->orderByRaw('COALESCE(questions.sort_order, questions.id) ASC')
            ->orderBy('questions.id', 'ASC')
            ->with(['examSection', 'choices' => function($query) {
                // Select only id, question_id, choice_text, choice_image to avoid leaking is_correct via frontend
                $query->select('id', 'question_id', 'choice_text', 'choice_image');
            }])
            ->get();

        // Stable shuffle within each section using attempt ID + section ID as seed so refreshes do not change the shuffled order
        if ($exam->shuffle_questions) {
            $grouped = $questions->groupBy('exam_section_id');
            $shuffled = collect();
            foreach ($grouped as $sectId => $sectQuestions) {
                $shuffled = $shuffled->concat($sectQuestions->shuffle($attempt->id + ($sectId ?? 0)));
            }
            $questions = $shuffled;
        }

        if ($exam->shuffle_choices) {
            $questions->each(function($question) use ($attempt) {
                // Seed with attempt ID + question ID to keep it stable per question
                $question->setRelation('choices', $question->choices->shuffle($attempt->id + $question->id));
            });
        }

        // Get student's current saved answers
        $savedAnswers = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->whereNotNull('choice_id')
            ->pluck('choice_id', 'question_id')
            ->toArray();

        $savedTextAnswers = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->whereNotNull('answer_text')
            ->pluck('answer_text', 'question_id')
            ->toArray();

        return view('student.exam.take', compact('exam', 'attempt', 'questions', 'savedAnswers', 'savedTextAnswers', 'timeRemainingSeconds', 'wasRefreshed'));
    }

    public function saveAnswer(Request $request, ExamAttempt $attempt)
    {
        if ($attempt->user_id !== Auth::id() || $attempt->status !== 'in_progress') {
            return response()->json(['error' => 'Unauthorized or exam already completed'], 403);
        }

        // Fast check if student exam eligibility is revoked in real-time (Global check)
        $student = Auth::user();
        if ($student->is_exam_eligible === false || (int)$student->is_exam_eligible === 0) {
            $reason = $student->ineligible_reason ?: 'ระงับสิทธิ์สอบระดับผู้ใช้งาน (ติดต่อฝ่ายการเงิน/ทะเบียน)';
            return response()->json([
                'ineligible' => true,
                'error' => 'สิทธิ์การสอบของคุณถูกระงับ (' . $reason . ')',
                'reason' => $reason
            ], 403);
        }

        // Subject enrollment eligibility check via fast direct pivot query
        $attempt->loadMissing('exam:id,subject_id');
        $subjectId = $attempt->exam?->subject_id;
        if ($subjectId) {
            $pivot = \Illuminate\Support\Facades\DB::table('subject_user')
                ->where('user_id', $student->id)
                ->where('subject_id', $subjectId)
                ->select('is_eligible', 'ineligible_reason')
                ->first();

            if (!$pivot || $pivot->is_eligible === false || (int)$pivot->is_eligible === 0) {
                $reason = ($pivot && $pivot->ineligible_reason) ? $pivot->ineligible_reason : 'ระงับสิทธิ์สอบในรายวิชานี้ (ติดต่ออาจารย์ผู้สอน)';
                return response()->json([
                    'ineligible' => true,
                    'error' => 'สิทธิ์การสอบของคุณถูกระงับ (' . $reason . ')',
                    'reason' => $reason
                ], 403);
            }
        }

        $request->validate([
            'question_id' => ['required', 'integer'],
            'choice_id' => ['nullable', 'integer'],
            'answer_text' => ['nullable', 'string'],
        ]);

        $questionId = (int)$request->question_id;
        $question = Question::find($questionId);
        if (!$question || $question->exam_id !== $attempt->exam_id) {
            return response()->json(['error' => 'Invalid question for this exam'], 422);
        }

        if ($question->type === 'essay') {
            if ($request->filled('answer_text')) {
                $isCorrect = false;
                if ($question->essay_answer !== null) {
                    $studentAnswerText = trim($request->answer_text);
                    $expectedAnswerText = trim($question->essay_answer);
                    // Case-insensitive comparison
                    $isCorrect = (mb_strtolower($studentAnswerText) === mb_strtolower($expectedAnswerText));
                }

                $this->storeAnswer($attempt->id, $questionId, [
                    'choice_id' => null,
                    'answer_text' => $request->answer_text,
                    'is_correct' => $isCorrect,
                ]);
            } else {
                StudentAnswer::where('exam_attempt_id', $attempt->id)
                    ->where('question_id', $questionId)
                    ->delete();
            }
            return response()->json(['success' => true]);
        }

        // Standard choice processing
        $choiceId = $request->choice_id ? (int)$request->choice_id : null;

        // If choice_id is null, delete answer
        if (empty($choiceId)) {
            StudentAnswer::where('exam_attempt_id', $attempt->id)
                ->where('question_id', $questionId)
                ->delete();
            return response()->json(['success' => true, 'cleared' => true]);
        }

        // Validate choice belongs to the question
        $choice = Choice::where('id', $choiceId)
            ->where('question_id', $questionId)
            ->first();

        if (!$choice) {
            return response()->json(['error' => 'Invalid choice for this question'], 422);
        }

        // Atomic Upsert using Composite Unique Key
        $this->storeAnswer($attempt->id, $questionId, [
            'choice_id' => $choice->id,
            'answer_text' => null,
            'is_correct' => (bool)$choice->is_correct,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Helper สำหรับบันทึกคำตอบแบบ Atomic Upsert (ป้องกันการเกิดแถวซ้ำ 100%)
     */
    private function storeAnswer(int $attemptId, int $questionId, array $values): void
    {
        $attributes = array_merge([
            'exam_attempt_id' => $attemptId,
            'question_id' => $questionId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $values);

        $updateColumns = array_merge(array_keys($values), ['updated_at']);

        StudentAnswer::upsert(
            [$attributes],
            ['exam_attempt_id', 'question_id'],
            $updateColumns
        );
    }

    public function checkEligibility(ExamAttempt $attempt)
    {
        if ($attempt->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'eligible' => false,
                'completed' => true,
                'reason' => 'การสอบชุดนี้เสร็จสิ้นหรือถูกส่งไปแล้ว',
                'message' => 'การสอบชุดนี้เสร็จสิ้นหรือถูกส่งไปแล้ว'
            ]);
        }

        $userId = Auth::id();
        // Fast direct lookup for global eligibility
        $studentData = \App\Models\User::where('id', $userId)
            ->select('id', 'is_exam_eligible', 'ineligible_reason')
            ->first();

        if ($studentData && ($studentData->is_exam_eligible === false || (int)$studentData->is_exam_eligible === 0)) {
            $reason = $studentData->ineligible_reason ?: 'ระงับสิทธิ์สอบระดับผู้ใช้งาน (ติดต่อฝ่ายการเงิน/ทะเบียน)';
            return response()->json([
                'eligible' => false,
                'reason' => $reason,
                'message' => 'คุณถูกระงับสิทธิ์การสอบ: ' . $reason
            ]);
        }

        // Fast check for subject enrollment eligibility
        $attempt->loadMissing('exam:id,subject_id');
        $subjectId = $attempt->exam?->subject_id;
        if ($subjectId) {
            $pivot = \Illuminate\Support\Facades\DB::table('subject_user')
                ->where('user_id', $userId)
                ->where('subject_id', $subjectId)
                ->select('is_eligible', 'ineligible_reason')
                ->first();

            if (!$pivot) {
                return response()->json([
                    'eligible' => false,
                    'reason' => 'ยังไม่ได้ลงทะเบียนในรายวิชานี้',
                    'message' => 'คุณถูกระงับสิทธิ์การสอบ: ยังไม่ได้ลงทะเบียนในรายวิชานี้'
                ]);
            }

            if ($pivot->is_eligible === false || (int)$pivot->is_eligible === 0) {
                $reason = $pivot->ineligible_reason ?: 'ระงับสิทธิ์สอบในรายวิชานี้ (ติดต่ออาจารย์ผู้สอน)';
                return response()->json([
                    'eligible' => false,
                    'reason' => $reason,
                    'message' => 'คุณถูกระงับสิทธิ์การสอบ: ' . $reason
                ]);
            }
        }

        return response()->json([
            'eligible' => true,
            'reason' => null
        ]);
    }

    public function recordFocusEscape(ExamAttempt $attempt)
    {
        if ($attempt->user_id !== Auth::id() || $attempt->status !== 'in_progress') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $student = Auth::user();
        if ($student->is_exam_eligible === false || (int)$student->is_exam_eligible === 0) {
            $reason = $student->ineligible_reason ?: 'ถูกระงับสิทธิ์สอบ';
            return response()->json([
                'ineligible' => true,
                'error' => 'สิทธิ์การสอบของคุณถูกระงับ',
                'reason' => $reason
            ], 403);
        }

        $attempt->loadMissing('exam:id,subject_id');
        $subjectId = $attempt->exam?->subject_id;
        if ($subjectId) {
            $pivot = \Illuminate\Support\Facades\DB::table('subject_user')
                ->where('user_id', $student->id)
                ->where('subject_id', $subjectId)
                ->select('is_eligible', 'ineligible_reason')
                ->first();

            if (!$pivot || $pivot->is_eligible === false || (int)$pivot->is_eligible === 0) {
                $reason = ($pivot && $pivot->ineligible_reason) ? $pivot->ineligible_reason : 'ถูกระงับสิทธิ์สอบในรายวิชานี้';
                return response()->json([
                    'ineligible' => true,
                    'error' => 'สิทธิ์การสอบของคุณถูกระงับ',
                    'reason' => $reason
                ], 403);
            }
        }

        $lastAjax = session('last_ajax_focus_escape_at_' . $attempt->id, 0);
        if (now()->timestamp - $lastAjax < 2) {
            return response()->json([
                'success' => true,
                'focus_escape_count' => $attempt->focus_escape_count,
            ]);
        }

        session(['last_ajax_focus_escape_at_' . $attempt->id => now()->timestamp]);
        $attempt->increment('focus_escape_count');
        $attempt->refresh();

        return response()->json([
            'success' => true,
            'focus_escape_count' => $attempt->focus_escape_count,
        ]);
    }

    public function sectionScore(Request $request, ExamAttempt $attempt)
    {
        if ($attempt->user_id !== Auth::id() || $attempt->status !== 'in_progress') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $student = Auth::user();
        if ($student->is_exam_eligible === false || (int)$student->is_exam_eligible === 0) {
            $reason = $student->ineligible_reason ?: 'ถูกระงับสิทธิ์สอบ';
            return response()->json([
                'ineligible' => true,
                'error' => 'สิทธิ์การสอบของคุณถูกระงับ',
                'reason' => $reason
            ], 403);
        }

        $attempt->loadMissing('exam:id,subject_id');
        $subjectId = $attempt->exam?->subject_id;
        if ($subjectId) {
            $pivot = \Illuminate\Support\Facades\DB::table('subject_user')
                ->where('user_id', $student->id)
                ->where('subject_id', $subjectId)
                ->select('is_eligible', 'ineligible_reason')
                ->first();

            if (!$pivot || $pivot->is_eligible === false || (int)$pivot->is_eligible === 0) {
                $reason = ($pivot && $pivot->ineligible_reason) ? $pivot->ineligible_reason : 'ถูกระงับสิทธิ์สอบในรายวิชานี้';
                return response()->json([
                    'ineligible' => true,
                    'error' => 'สิทธิ์การสอบของคุณถูกระงับ',
                    'reason' => $reason
                ], 403);
            }
        }

        $request->validate(['section_id' => ['required']]);
        $sectionId = $request->section_id === 'null' ? null : $request->section_id;

        // Get all questions in this section
        $questions = Question::where('exam_id', $attempt->exam_id)
            ->where('exam_section_id', $sectionId)
            ->get();

        $questionIds   = $questions->pluck('id');
        $questionCount = $questions->count();
        $totalScore    = (float)$questions->sum('score');

        // Get student answers for this section
        $studentAnswers = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->whereIn('question_id', $questionIds)
            ->get();

        $answeredCount = $studentAnswers->count();
        $earnedScore   = 0.0;
        foreach ($studentAnswers as $ans) {
            if ($ans->is_correct) {
                $q = $questions->firstWhere('id', $ans->question_id);
                if ($q) $earnedScore += (float)$q->score;
            }
        }

        $section = $sectionId ? ExamSection::find($sectionId) : null;
        $displayEarned = $earnedScore;
        $displayTotal = $totalScore;

        if ($section && $section->total_score !== null && (float)$section->total_score > 0) {
            $displayTotal = (float)$section->total_score;
            $displayEarned = $totalScore > 0 ? round(($earnedScore / $totalScore) * $displayTotal, 2) : 0.0;
        }

        return response()->json([
            'success'        => true,
            'earned'         => $displayEarned,
            'total'          => $displayTotal,
            'raw_earned'     => $earnedScore,
            'raw_total'      => $totalScore,
            'answered'       => $answeredCount,
            'question_count' => $questionCount,
        ]);
    }

    public function submitAttempt(Request $request, ExamAttempt $attempt)
    {
        return $this->submit($attempt, $request->input('answers', []), $request->input('text_answers', []));
    }

    private function submit(ExamAttempt $attempt, array $finalChoices = [], array $finalTexts = [])
    {
        if ($attempt->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($attempt, $finalChoices, $finalTexts) {
            // Lock attempt for update to prevent concurrent submissions (e.g., auto-submit on timeout vs manual click)
            $lockedAttempt = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->first();
            if (!$lockedAttempt || $lockedAttempt->status === 'completed') {
                return redirect()->route('student.exam.result', $attempt->id);
            }

            $lockedAttempt->loadMissing('exam');
            $student = Auth::user();
            if ($student->is_exam_eligible === false || (int)$student->is_exam_eligible === 0) {
                return redirect()->route('student.dashboard')->with('error', 'คุณไม่มีสิทธิ์ส่งข้อสอบวิชานี้เนื่องจากถูกระงับสิทธิ์');
            }

            $lockedAttempt->load(['exam.questions.choices', 'exam.sections']);
            $exam = $lockedAttempt->exam;

            // 1. Final Sync: Sync on-screen answers if submitted through form (safety net if autosave missed any)
            $this->applyFinalAnswers($lockedAttempt, $exam, $finalChoices, $finalTexts);

            // 2. Score questions
            $essayQuestions = $exam->questions->where('type', 'essay');
            $hasEssayWithoutKey = $essayQuestions->contains(function ($q) {
                return empty(trim($q->essay_answer ?? ''));
            });
            $gradingStatus = $hasEssayWithoutKey ? 'pending_grading' : 'graded';

            // Calculate score and populate score_awarded for each answer
            $studentAnswers = StudentAnswer::where('exam_attempt_id', $lockedAttempt->id)->get()->keyBy('question_id');
            
            foreach ($exam->questions as $question) {
                $ans = $studentAnswers->get($question->id);
                if ($question->type === 'essay') {
                    $hasKey = !empty(trim($question->essay_answer ?? ''));
                    if ($hasKey) {
                        $studentText = trim($ans->answer_text ?? '');
                        $expectedText = trim($question->essay_answer);
                        $isCorrect = (!empty($studentText) && mb_strtolower($studentText) === mb_strtolower($expectedText));
                        $awarded = $isCorrect ? (float)$question->score : 0.0;
                        $this->storeAnswer($lockedAttempt->id, $question->id, [
                            'choice_id' => null,
                            'answer_text' => $ans?->answer_text,
                            'is_correct' => $isCorrect,
                            'score_awarded' => $awarded,
                        ]);
                    } else {
                        if ($ans) {
                            $ans->update([
                                'is_correct' => false,
                                'score_awarded' => null,
                            ]);
                        }
                    }
                } else {
                    if ($ans) {
                        $awarded = $ans->is_correct ? (float)$question->score : 0.0;
                        $ans->update(['score_awarded' => $awarded]);
                    }
                }
            }

            // Set grading status before calculation
            $lockedAttempt->grading_status = $gradingStatus;
            $lockedAttempt->unsetRelation('studentAnswers');
            $calc = $lockedAttempt->calculateFinalScore();

            // Update attempt
            $lockedAttempt->update([
                'completed_at' => now(),
                'score' => $calc['score'],
                'raw_score' => $calc['raw_score'],
                'total_raw_score' => $calc['total_raw_score'],
                'is_passed' => $calc['is_passed'],
                'status' => 'completed',
                'grading_status' => $gradingStatus,
            ]);

            session()->flash('just_submitted', true);

            $successMsg = $hasEssayWithoutKey 
                ? 'ส่งข้อสอบเรียบร้อยแล้ว (มีข้อสอบข้อเขียนที่อยู่ระหว่างรอผู้สอนตรวจให้คะแนน)' 
                : 'ส่งข้อสอบและบันทึกคะแนนเรียบร้อยแล้ว';

            return redirect()->route('student.exam.result', $lockedAttempt->id)
                ->with('success', $successMsg);
        });
    }

    private function applyFinalAnswers(ExamAttempt $attempt, Exam $exam, array $finalChoices, array $finalTexts): void
    {
        $questions = $exam->questions->keyBy('id');

        // Apply choice answers
        foreach ($finalChoices as $questionId => $choiceId) {
            $questionId = (int)$questionId;
            $choiceId = (int)$choiceId;
            $question = $questions->get($questionId);
            if (!$question || $question->type === 'essay') {
                continue;
            }

            $choice = $question->choices->firstWhere('id', $choiceId);
            if ($choice) {
                $this->storeAnswer($attempt->id, $questionId, [
                    'choice_id' => $choice->id,
                    'answer_text' => null,
                    'is_correct' => (bool)$choice->is_correct,
                ]);
            }
        }

        // Apply essay text answers
        foreach ($finalTexts as $questionId => $answerText) {
            $questionId = (int)$questionId;
            $question = $questions->get($questionId);
            if (!$question || $question->type !== 'essay') {
                continue;
            }

            if ($answerText !== null && trim($answerText) !== '') {
                $isCorrect = false;
                if ($question->essay_answer !== null) {
                    $studentAnswerText = trim($answerText);
                    $expectedAnswerText = trim($question->essay_answer);
                    $isCorrect = (mb_strtolower($studentAnswerText) === mb_strtolower($expectedAnswerText));
                }

                $this->storeAnswer($attempt->id, $questionId, [
                    'choice_id' => null,
                    'answer_text' => $answerText,
                    'is_correct' => $isCorrect,
                ]);
            }
        }
    }

    public function result(ExamAttempt $attempt)
    {
        if ($attempt->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $attempt->load(['exam.subject', 'exam.questions' => function($q) {
            $q->leftJoin('exam_sections', 'questions.exam_section_id', '=', 'exam_sections.id')
              ->select('questions.*')
              ->orderByRaw('COALESCE(exam_sections.sort_order, 99999) ASC')
              ->orderByRaw('COALESCE(exam_sections.id, 99999) ASC')
              ->orderByRaw('COALESCE(questions.sort_order, questions.id) ASC')
              ->orderBy('questions.id', 'ASC');
        }, 'exam.questions.examSection', 'exam.questions.choices']);
        $exam = $attempt->exam;

        // If attempt is marked pending_grading, but all essay questions now have keys, sync and mark as graded
        if ($attempt->isPendingGrading()) {
            $essayQuestions = $attempt->exam->questions->where('type', 'essay');
            $hasEssayWithoutKey = $essayQuestions->contains(function ($q) {
                return empty(trim($q->essay_answer ?? ''));
            });

            if (!$hasEssayWithoutKey && $essayQuestions->isNotEmpty()) {
                $studentAnswers = StudentAnswer::where('exam_attempt_id', $attempt->id)->get()->keyBy('question_id');
                foreach ($attempt->exam->questions as $q) {
                    if ($q->type === 'essay') {
                        $ans = $studentAnswers->get($q->id);
                        $studentText = trim($ans->answer_text ?? '');
                        $expectedText = trim($q->essay_answer ?? '');
                        $isCorrect = (!empty($studentText) && mb_strtolower($studentText) === mb_strtolower($expectedText));
                        $awarded = $isCorrect ? (float)$q->score : 0.0;
                        $this->storeAnswer($attempt->id, $q->id, [
                            'choice_id' => null,
                            'answer_text' => $ans?->answer_text,
                            'is_correct' => $isCorrect,
                            'score_awarded' => $awarded,
                        ]);
                    }
                }
                $attempt->grading_status = 'graded';
                $attempt->unsetRelation('studentAnswers');
                $calc = $attempt->calculateFinalScore();
                $attempt->update([
                    'score' => $calc['score'],
                    'raw_score' => $calc['raw_score'],
                    'total_raw_score' => $calc['total_raw_score'],
                    'is_passed' => $calc['is_passed'],
                    'grading_status' => 'graded',
                ]);
                $attempt->refresh();
            }
        }

        // If allow_review is disabled and the student didn't just submit the exam, block review
        if (!$exam->allow_review && !session('just_submitted')) {
            return redirect()->route('student.dashboard')->with('error', 'ผู้สอนไม่อนุญาตให้ดูผลการสอบและเฉลยย้อนหลัง');
        }
        
        $savedAnswers = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->whereNotNull('choice_id')
            ->pluck('choice_id', 'question_id')
            ->toArray();

        $savedTextAnswers = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->whereNotNull('answer_text')
            ->pluck('answer_text', 'question_id')
            ->toArray();

        $correctness = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->pluck('is_correct', 'question_id')
            ->toArray();

        $awardedScores = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->pluck('score_awarded', 'question_id')
            ->toArray();

        $teacherFeedbacks = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->whereNotNull('teacher_feedback')
            ->pluck('teacher_feedback', 'question_id')
            ->toArray();

        return view('student.exam.result', compact(
            'attempt',
            'savedAnswers',
            'savedTextAnswers',
            'correctness',
            'awardedScores',
            'teacherFeedbacks'
        ));
    }
}
