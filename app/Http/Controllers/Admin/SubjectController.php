<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Department;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = $user->isAdmin()
            ? Subject::query()
            : $user->enrolledSubjects();

        if ($request->filled('q')) {
            $q = $request->get('q');
            $query->where(function($query) use ($q) {
                $query->where('code', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%");
            });
        }

        $subjects = $query->with(['teachers', 'department'])->withCount('students')->orderBy('code')->get();
        $departments = Department::orderBy('name')->get();

        return view('admin.subjects.index', compact('subjects', 'departments'));
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
