<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Choice;
use App\Models\ExamSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    public function index(Exam $exam)
    {
        $this->authorizeExam($exam);
        $exam->load('subject');
        $subjects = auth()->user()->isAdmin()
            ? \App\Models\Subject::orderBy('code')->get()
            : auth()->user()->enrolledSubjects()->orderBy('code')->get();

        // Check if sections are empty, if so, initialize at least one section
        $sectionsCount = ExamSection::where('exam_id', $exam->id)->count();
        if ($sectionsCount === 0) {
            $defaultSection = ExamSection::create([
                'exam_id' => $exam->id,
                'title' => 'ตอนที่ 1',
                'instruction' => 'ข้อสอบเลือกตอบ (ปรนัย)',
                'sort_order' => 1
            ]);
            // Assign all existing questions to this default section
            Question::where('exam_id', $exam->id)->update(['exam_section_id' => $defaultSection->id]);
        } else {
            // Also ensure any unassigned questions get assigned to the first section
            $firstSection = ExamSection::where('exam_id', $exam->id)->orderBy('sort_order')->orderBy('id')->first();
            if ($firstSection) {
                Question::where('exam_id', $exam->id)->whereNull('exam_section_id')->update(['exam_section_id' => $firstSection->id]);
            }
        }

        // Load sections with their questions and choices ordered by sort_order
        $sections = ExamSection::where('exam_id', $exam->id)
            ->with(['questions' => function($q) {
                $q->orderByRaw('COALESCE(sort_order, id) ASC')->orderBy('id', 'ASC');
            }, 'questions.choices'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.questions.index', compact('exam', 'sections', 'subjects'));
    }

    public function store(Request $request, Exam $exam)
    {
        $this->authorizeExam($exam);
        $this->authorizeExamEditable($exam);
        
        $rules = [
            'question_text' => ['required', 'string'],
            'score' => ['required', 'numeric', 'min:0'],
            'type' => ['required', 'in:choice,essay'],
            'exam_section_id' => ['required', 'exists:exam_sections,id'],
        ];

        if ($request->input('type', 'choice') === 'choice') {
            $rules['choices'] = ['required', 'array', 'min:2'];
            $rules['choices.*'] = ['required', 'string'];
            $rules['choice_images'] = ['nullable', 'array'];
            $rules['choice_images.*'] = ['nullable', 'string'];
            $rules['correct_choice'] = ['required', 'integer'];
        } else {
            $rules['essay_answer'] = ['nullable', 'string'];
        }

        $request->validate($rules, [
            'choices.*.required' => 'กรุณากรอกตัวเลือกให้ครบทุกข้อ',
            'correct_choice.required' => 'กรุณาเลือกคำตอบที่ถูกต้อง',
        ]);

        $newQuestion = null;
        DB::transaction(function () use ($request, $exam, &$newQuestion) {
            $type = $request->input('type', 'choice');
            $maxSort = Question::where('exam_id', $exam->id)
                ->where('exam_section_id', $request->exam_section_id)
                ->max('sort_order') ?? 0;

            $newQuestion = Question::create([
                'exam_id' => $exam->id,
                'exam_section_id' => $request->exam_section_id,
                'type' => $type,
                'question_text' => $request->question_text,
                'score' => $request->score,
                'sort_order' => $maxSort + 1,
                'essay_answer' => $type === 'essay' ? $request->essay_answer : null,
            ]);

            if ($type === 'choice') {
                foreach ($request->choices as $index => $choiceText) {
                    $choiceImage = $request->choice_images[$index] ?? null;
                    if ($choiceImage) {
                        $baseUrl = asset('');
                        if (strpos($choiceImage, $baseUrl) === 0) {
                            $choiceImage = substr($choiceImage, strlen($baseUrl));
                        }
                    }

                    Choice::create([
                        'question_id' => $newQuestion->id,
                        'choice_text' => $choiceText,
                        'choice_image' => $choiceImage ?: null,
                        'is_correct' => ($index == $request->correct_choice),
                    ]);
                }
            }
            $newQuestion->load('choices');
        });

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'question' => $newQuestion,
                'message' => 'บันทึกโจทย์คำถามเรียบร้อยแล้ว'
            ]);
        }

        return redirect()->route('admin.exams.questions.index', $exam->id)
            ->with('success', 'เพิ่มโจทย์คำถามเรียบร้อยแล้ว');
    }

    public function edit(Exam $exam, Question $question)
    {
        $this->authorizeExam($exam);
        $question->load('choices');
        return view('admin.questions.edit', compact('exam', 'question'));
    }

    public function update(Request $request, Exam $exam, Question $question)
    {
        $this->authorizeExam($exam);
        $this->authorizeExamEditable($exam);
        
        $rules = [
            'question_text' => ['required', 'string'],
            'score' => ['required', 'numeric', 'min:0'],
            'type' => ['required', 'in:choice,essay'],
            'exam_section_id' => ['required', 'exists:exam_sections,id'],
        ];

        if ($request->input('type', 'choice') === 'choice') {
            $rules['choices'] = ['required', 'array', 'min:2'];
            $rules['choices.*'] = ['required', 'string'];
            $rules['choice_images'] = ['nullable', 'array'];
            $rules['choice_images.*'] = ['nullable', 'string'];
            $rules['correct_choice'] = ['required', 'integer'];
        } else {
            $rules['essay_answer'] = ['nullable', 'string'];
        }

        $request->validate($rules, [
            'choices.*.required' => 'กรุณากรอกตัวเลือกให้ครบทุกข้อ',
            'correct_choice.required' => 'กรุณาเลือกคำตอบที่ถูกต้อง',
        ]);

        DB::transaction(function () use ($request, $question) {
            $type = $request->input('type', 'choice');
            $question->update([
                'exam_section_id' => $request->exam_section_id,
                'type' => $type,
                'question_text' => $request->question_text,
                'score' => $request->score,
                'essay_answer' => $type === 'essay' ? $request->essay_answer : null,
            ]);

            // Delete existing choices
            $question->choices()->delete();

            if ($type === 'choice') {
                foreach ($request->choices as $index => $choiceText) {
                    $choiceImage = $request->choice_images[$index] ?? null;
                    if ($choiceImage) {
                        $baseUrl = asset('');
                        if (strpos($choiceImage, $baseUrl) === 0) {
                            $choiceImage = substr($choiceImage, strlen($baseUrl));
                        }
                    }

                    Choice::create([
                        'question_id' => $question->id,
                        'choice_text' => $choiceText,
                        'choice_image' => $choiceImage ?: null,
                        'is_correct' => ($index == $request->correct_choice),
                    ]);
                }
            }
            $question->load('choices');
        });

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'question' => $question,
                'message' => 'แก้ไขโจทย์คำถามเรียบร้อยแล้ว'
            ]);
        }

        return redirect()->route('admin.exams.questions.index', $exam->id)
            ->with('success', 'แก้ไขโจทย์คำถามเรียบร้อยแล้ว');
    }

    public function destroy(Request $request, Exam $exam, Question $question)
    {
        $this->authorizeExam($exam);
        $this->authorizeExamEditable($exam);
        $question->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'ลบคำถามเรียบร้อยแล้ว'
            ]);
        }

        return redirect()->route('admin.exams.questions.index', $exam->id)
            ->with('success', 'ลบคำถามเรียบร้อยแล้ว');
    }

    public function recalculateScores(Exam $exam)
    {
        $this->authorizeExam($exam);
        $this->authorizeExamEditable($exam);
        $questionsCount = Question::where('exam_id', $exam->id)->count();

        if ($questionsCount === 0) {
            return redirect()->back()->withErrors(['error' => 'ไม่สามารถคำนวณคะแนนได้เนื่องจากยังไม่มีคำถามในข้อสอบนี้']);
        }

        $sections = ExamSection::where('exam_id', $exam->id)->get();
        $hasSectionScores = $sections->contains(fn($s) => $s->total_score !== null && (float)$s->total_score > 0);

        if ($hasSectionScores) {
            $sectionDetails = [];
            foreach ($sections as $sec) {
                $secCount = Question::where('exam_id', $exam->id)->where('exam_section_id', $sec->id)->count();
                if ($secCount === 0) {
                    continue;
                }

                if ($sec->total_score !== null && (float)$sec->total_score > 0) {
                    $target = (float)$sec->total_score;
                    $perQuestion = $target / $secCount;
                    $rounded = round($perQuestion, 2);
                    $clean = abs(($rounded * $secCount) - $target) < 0.001;
                    $scoreToSet = $clean ? $rounded : 1.0;

                    Question::where('exam_id', $exam->id)
                        ->where('exam_section_id', $sec->id)
                        ->update(['score' => $scoreToSet]);

                    $detailMsg = $clean
                        ? "{$sec->title}: ข้อละ {$rounded} คะแนน ({$secCount} ข้อ รวม {$target} คะแนน)"
                        : "{$sec->title}: ข้อละ 1.0 คะแนนดิบ ({$secCount} ข้อ แปลงสัดส่วนเป็น {$target} คะแนน)";
                    $sectionDetails[] = $detailMsg;
                }
            }

            $title = "คำนวณคะแนนแยกตามตอนเรียบร้อย";
            $msg = "คำนวณคะแนนคำถามแยกตามตอนที่มีการกำหนดคะแนนเต็มไว้เรียบร้อยแล้ว: " . implode(' | ', $sectionDetails);
            $icon = "success";
        } else {
            $rawScorePerQuestion = $exam->total_score / $questionsCount;
            $roundedScore = round($rawScorePerQuestion, 2);
            $isCleanDivision = abs(($roundedScore * $questionsCount) - (float)$exam->total_score) < 0.001;

            if ($isCleanDivision) {
                Question::where('exam_id', $exam->id)->update(['score' => $roundedScore]);
                $title = "คำนวณคะแนนต่อข้อเรียบร้อย";
                $msg = "คำนวณคะแนนต่อข้อให้อัตโนมัติเรียบร้อยแล้ว (ข้อละ {$roundedScore} คะแนน จากคะแนนรวม {$exam->total_score} คะแนน จำนวนทั้งหมด {$questionsCount} ข้อ)";
                $icon = "success";
            } else {
                // Set 1.0 raw point per question so proportional scaling can convert accurately
                Question::where('exam_id', $exam->id)->update(['score' => 1.0]);
                $title = "การคำนวณคะแนนตามสัดส่วน (Proportional Scaling)";
                $msg = "เนื่องจากคะแนนเต็ม ({$exam->total_score}) หารจำนวนข้อ ({$questionsCount} ข้อ) ไม่ลงตัว ระบบจึงกำหนดคะแนนคำถามทุกข้อเป็น 1.0 คะแนนดิบ (รวม {$questionsCount} คะแนนดิบ) โดยระบบจะคำนวณแปลงสัดส่วนคะแนนเป็น {$exam->total_score} คะแนนให้อัตโนมัติเมื่อนักเรียนส่งข้อสอบ";
                $icon = "info";
            }
        }

        return redirect()->route('admin.exams.questions.index', $exam->id)
            ->with('recalculate_info', [
                'title' => $title,
                'message' => $msg,
                'icon' => $icon,
            ])
            ->with('success', $msg);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:10240'],
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $extension = $file->getClientOriginalExtension() ?: 'png';
            $filename = time() . '_' . uniqid() . '.' . $extension;
            
            // Candidate upload directories across different web server setups (DirectAdmin, cPanel, local)
            $targetDirs = [
                public_path('uploads/questions'),
            ];

            if (!empty($_SERVER['DOCUMENT_ROOT'])) {
                $docUploads = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/uploads/questions';
                if (!in_array($docUploads, $targetDirs)) {
                    $targetDirs[] = $docUploads;
                }
            }

            $publicHtml = base_path('public_html/uploads/questions');
            if (is_dir(base_path('public_html')) && !in_array($publicHtml, $targetDirs)) {
                $targetDirs[] = $publicHtml;
            }

            $siblingPublicHtml = dirname(base_path()) . '/public_html/uploads/questions';
            if (is_dir(dirname(base_path()) . '/public_html') && !in_array($siblingPublicHtml, $targetDirs)) {
                $targetDirs[] = $siblingPublicHtml;
            }

            // Primary save destination
            $primaryPath = $targetDirs[0];
            if (!file_exists($primaryPath)) {
                @mkdir($primaryPath, 0755, true);
            }
            
            $file->move($primaryPath, $filename);
            @chmod($primaryPath . '/' . $filename, 0644);

            // Sync to any secondary public directories if they exist
            foreach (array_slice($targetDirs, 1) as $dir) {
                try {
                    if (!file_exists($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    if (file_exists($dir)) {
                        @copy($primaryPath . '/' . $filename, $dir . '/' . $filename);
                        @chmod($dir . '/' . $filename, 0644);
                    }
                } catch (\Throwable $e) {
                    // Ignore secondary sync failures
                }
            }

            return response()->json([
                'url' => asset('uploads/questions/' . $filename)
            ]);
        }
        return response()->json(['error' => 'No image uploaded'], 400);
    }

    public function serveImage(string $filename)
    {
        // Prevent path traversal attacks
        $safeFilename = basename($filename);

        $candidateDirs = [
            public_path('uploads/questions'),
        ];

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $candidateDirs[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/uploads/questions';
        }

        $candidateDirs[] = base_path('public_html/uploads/questions');
        $candidateDirs[] = base_path('public/uploads/questions');
        $candidateDirs[] = base_path('uploads/questions');
        $candidateDirs[] = dirname(base_path()) . '/public_html/uploads/questions';
        $candidateDirs[] = storage_path('app/public/uploads/questions');

        foreach (array_unique($candidateDirs) as $dir) {
            $filePath = $dir . DIRECTORY_SEPARATOR . $safeFilename;
            if (file_exists($filePath) && is_file($filePath)) {
                // If secondary web root exists without this file, sync it for future static serving
                if (!empty($_SERVER['DOCUMENT_ROOT'])) {
                    $docDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/uploads/questions';
                    if (is_dir($docDir) && !file_exists($docDir . '/' . $safeFilename)) {
                        @copy($filePath, $docDir . '/' . $safeFilename);
                        @chmod($docDir . '/' . $safeFilename, 0644);
                    }
                }

                $mime = mime_content_type($filePath) ?: 'image/png';
                return response()->file($filePath, [
                    'Content-Type' => $mime,
                    'Cache-Control' => 'public, max-age=31536000',
                ]);
            }
        }

        abort(404, 'Question image not found');
    }

    public function reorder(Request $request, Exam $exam)
    {
        $this->authorizeExam($exam);
        $this->authorizeExamEditable($exam);

        $request->validate([
            'order' => ['required', 'array'],
            'order.*.id' => ['required', 'exists:questions,id'],
            'order.*.sort_order' => ['required', 'integer'],
            'order.*.exam_section_id' => ['nullable', 'exists:exam_sections,id'],
        ]);

        DB::transaction(function () use ($request, $exam) {
            foreach ($request->input('order', []) as $item) {
                $updateData = ['sort_order' => $item['sort_order']];
                if (isset($item['exam_section_id'])) {
                    $updateData['exam_section_id'] = $item['exam_section_id'];
                }
                Question::where('id', $item['id'])
                    ->where('exam_id', $exam->id)
                    ->update($updateData);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'บันทึกการจัดลำดับข้อสอบเรียบร้อยแล้ว'
        ]);
    }

    private function authorizeExam(Exam $exam)
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return;
        }
        $teaches = $user->enrolledSubjects()->where('subject_id', $exam->subject_id)->exists();
        if (!$teaches) {
            abort(403, 'คุณไม่มีสิทธิ์จัดการคำถามสำหรับข้อสอบในวิชานี้');
        }
    }

    private function authorizeExamEditable(Exam $exam)
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return;
        }
        if (!$exam->canBeEdited()) {
            abort(403, 'ข้อสอบนี้อยู่ระหว่างรอการอนุมัติหรือได้รับการอนุมัติแล้ว ไม่สามารถแก้ไขได้ หากต้องการแก้ไขกรุณาดึงข้อสอบกลับมาเป็นฉบับร่างก่อน');
        }
    }
}
