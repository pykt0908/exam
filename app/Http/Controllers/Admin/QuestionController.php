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

        // Load sections with their questions and choices
        $sections = ExamSection::where('exam_id', $exam->id)
            ->with(['questions' => function($q) {
                $q->orderBy('id');
            }, 'questions.choices'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.questions.index', compact('exam', 'sections', 'subjects'));
    }

    public function store(Request $request, Exam $exam)
    {
        $this->authorizeExam($exam);
        
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
            $newQuestion = Question::create([
                'exam_id' => $exam->id,
                'exam_section_id' => $request->exam_section_id,
                'type' => $type,
                'question_text' => $request->question_text,
                'score' => $request->score,
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
        $questionsCount = Question::where('exam_id', $exam->id)->count();

        if ($questionsCount === 0) {
            return redirect()->back()->withErrors(['error' => 'ไม่สามารถคำนวณคะแนนได้เนื่องจากยังไม่มีคำถามในข้อสอบนี้']);
        }

        $scorePerQuestion = $exam->total_score / $questionsCount;
        $scorePerQuestion = round($scorePerQuestion, 2);

        Question::where('exam_id', $exam->id)->update(['score' => $scorePerQuestion]);

        return redirect()->route('admin.exams.questions.index', $exam->id)
            ->with('success', "คำนวณคะแนนต่อข้อให้อัตโนมัติเรียบร้อยแล้ว (ข้อละ {$scorePerQuestion} คะแนน จากคะแนนรวม {$exam->total_score} คะแนน จำนวนทั้งหมด {$questionsCount} ข้อ)");
    }

    public function uploadImage(Request $request)
    {
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            // Ensure uploads directory exists
            $destinationPath = public_path('uploads/questions');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            
            $file->move($destinationPath, $filename);
            return response()->json([
                'url' => asset('uploads/questions/' . $filename)
            ]);
        }
        return response()->json(['error' => 'No image uploaded'], 400);
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
}
