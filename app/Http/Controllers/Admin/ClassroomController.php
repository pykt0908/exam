<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function index(Request $request)
    {
        $query = Classroom::withCount([
            'users as students_count' => function($query) {
                $query->where('role', 'student');
            },
            'users as eligible_students_count' => function($query) {
                $query->where('role', 'student')
                      ->where(function($q) {
                          $q->whereNull('is_exam_eligible')
                            ->orWhere('is_exam_eligible', true);
                      });
            },
            'users as ineligible_students_count' => function($query) {
                $query->where('role', 'student')
                      ->where('is_exam_eligible', false);
            }
        ]);

        if ($request->filled('q')) {
            $q = $request->get('q');
            $query->where('name', 'like', "%{$q}%");
        }

        $classrooms = $query->orderBy('name')->get();

        return view('admin.classrooms.index', compact('classrooms'));
    }

    public function show(Classroom $classroom)
    {
        // Fetch all students in this classroom
        $students = User::where('classroom_id', $classroom->id)
            ->where('role', 'student')
            ->withCount(['examAttempts' => function($query) {
                $query->where('status', 'completed');
            }])
            ->orderBy('student_code', 'asc')
            ->get();

        return view('admin.classrooms.show', compact('classroom', 'students'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:classrooms,name'],
        ], [
            'name.required' => 'กรุณากรอกชื่อห้องเรียน/ระดับชั้น',
            'name.unique' => 'มีห้องเรียนชื่อนี้ในระบบแล้ว',
        ]);

        Classroom::create([
            'name' => $request->name,
        ]);

        return redirect()->route('admin.classrooms.index')->with('success', 'เพิ่มห้องเรียน/ระดับชั้นใหม่เรียบร้อยแล้ว');
    }

    public function update(Request $request, Classroom $classroom)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:classrooms,name,' . $classroom->id],
        ], [
            'name.required' => 'กรุณากรอกชื่อห้องเรียน/ระดับชั้น',
            'name.unique' => 'มีห้องเรียนชื่อนี้ในระบบแล้ว',
        ]);

        $classroom->update([
            'name' => $request->name,
        ]);

        return redirect()->route('admin.classrooms.index')->with('success', 'แก้ไขข้อมูลห้องเรียน/ระดับชั้นเรียบร้อยแล้ว');
    }

    public function destroy(Classroom $classroom)
    {
        // Delete the classroom
        $classroom->delete();

        return redirect()->route('admin.classrooms.index')->with('success', 'ลบข้อมูลห้องเรียน/ระดับชั้นเรียบร้อยแล้ว');
    }

    public function toggleStudentEligibility(Request $request, Classroom $classroom, User $student)
    {
        if ($student->classroom_id !== $classroom->id) {
            abort(404, 'ไม่พบนักศึกษาในห้องเรียนนี้');
        }

        $request->validate([
            'is_exam_eligible' => ['required', 'in:0,1,true,false'],
            'ineligible_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $isEligible = filter_var($request->is_exam_eligible, FILTER_VALIDATE_BOOLEAN);
        $reason = $isEligible ? null : ($request->ineligible_reason ?: 'ระงับสิทธิ์การสอบ');

        $student->update([
            'is_exam_eligible' => $isEligible,
            'ineligible_reason' => $reason,
        ]);

        $statusText = $isEligible ? 'เปิดสิทธิ์การสอบ' : 'ระงับสิทธิ์การสอบ';
        return redirect()->back()->with('success', "{$statusText}ของ [{$student->student_code}] {$student->name} เรียบร้อยแล้ว (มีผลกับทุกวิชาสอบ)");
    }

    public function bulkToggleEligibility(Request $request, Classroom $classroom)
    {
        $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['exists:users,id'],
            'is_exam_eligible' => ['required', 'in:0,1,true,false'],
            'ineligible_reason' => ['nullable', 'string', 'max:255'],
        ], [
            'student_ids.required' => 'กรุณาเลือกนักศึกษาอย่างน้อย 1 คน',
        ]);

        $isEligible = filter_var($request->is_exam_eligible, FILTER_VALIDATE_BOOLEAN);
        $reason = $isEligible ? null : ($request->ineligible_reason ?: 'ระงับสิทธิ์การสอบ');

        User::where('classroom_id', $classroom->id)
            ->whereIn('id', $request->student_ids)
            ->update([
                'is_exam_eligible' => $isEligible,
                'ineligible_reason' => $reason,
            ]);

        $count = count($request->student_ids);
        $statusText = $isEligible ? 'เปิดสิทธิ์การสอบ' : 'ระงับสิทธิ์การสอบ';
        return redirect()->back()->with('success', "{$statusText}สำหรับนักศึกษาจำนวน {$count} คนในห้อง {$classroom->name} เรียบร้อยแล้ว (มีผลกับทุกวิชาสอบ)");
    }

    public function toggleAllEligibility(Request $request, Classroom $classroom)
    {
        $request->validate([
            'is_exam_eligible' => ['required', 'in:0,1,true,false'],
            'ineligible_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $isEligible = filter_var($request->is_exam_eligible, FILTER_VALIDATE_BOOLEAN);
        $reason = $isEligible ? null : ($request->ineligible_reason ?: 'ระงับสิทธิ์การสอบทั้งห้อง');

        $count = User::where('classroom_id', $classroom->id)
            ->where('role', 'student')
            ->update([
                'is_exam_eligible' => $isEligible,
                'ineligible_reason' => $reason,
            ]);

        $statusText = $isEligible ? 'เปิดสิทธิ์การสอบ' : 'ระงับสิทธิ์การสอบ';
        return redirect()->back()->with('success', "{$statusText}นักศึกษาทั้งหมด ({$count} คน) ในห้อง {$classroom->name} เรียบร้อยแล้ว");
    }
}
