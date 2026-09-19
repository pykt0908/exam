<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamSectionController extends Controller
{
    private function authorizeExam(Exam $exam)
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return;
        }

        // Check if teacher is attached to the subject of this exam
        $isAttached = DB::table('subject_user')
            ->where('subject_id', $exam->subject_id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isAttached) {
            abort(403, 'Unauthorized');
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

    public function store(Request $request, Exam $exam)
    {
        $this->authorizeExam($exam);
        $this->authorizeExamEditable($exam);

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'instruction' => ['nullable', 'string'],
            'total_score' => ['nullable', 'numeric', 'min:0'],
        ]);

        $maxOrder = ExamSection::where('exam_id', $exam->id)->max('sort_order') ?? 0;

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => $request->title,
            'instruction' => $request->instruction,
            'total_score' => $request->filled('total_score') ? $request->total_score : null,
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json([
            'success' => true,
            'section' => $section,
            'message' => 'เพิ่มตอนข้อสอบเรียบร้อยแล้ว'
        ]);
    }

    public function update(Request $request, Exam $exam, ExamSection $section)
    {
        $this->authorizeExam($exam);
        $this->authorizeExamEditable($exam);
        
        if ($section->exam_id !== $exam->id) {
            abort(400, 'Invalid Section');
        }

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'instruction' => ['nullable', 'string'],
            'total_score' => ['nullable', 'numeric', 'min:0'],
        ]);

        $section->update([
            'title' => $request->title,
            'instruction' => $request->instruction,
            'total_score' => $request->filled('total_score') ? $request->total_score : null,
        ]);

        return response()->json([
            'success' => true,
            'section' => $section,
            'message' => 'บันทึกตอนข้อสอบเรียบร้อยแล้ว'
        ]);
    }

    public function destroy(Exam $exam, ExamSection $section)
    {
        $this->authorizeExam($exam);
        $this->authorizeExamEditable($exam);

        if ($section->exam_id !== $exam->id) {
            abort(400, 'Invalid Section');
        }

        $section->delete();

        return response()->json([
            'success' => true,
            'message' => 'ลบตอนข้อสอบเรียบร้อยแล้ว'
        ]);
    }
}
