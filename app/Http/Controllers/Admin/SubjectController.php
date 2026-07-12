<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $searchPerformed = false;
        $subjects = collect();
        $user = auth()->user();

        if ($user->isTeacher() || $request->has('search')) {
            $searchPerformed = true;
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

            $subjects = $query->with(['teachers'])->withCount('students')->orderBy('code')->get();
        }

        return view('admin.subjects.index', compact('subjects', 'searchPerformed'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:subjects,code'],
            'name' => ['required', 'string', 'max:255'],
        ], [
            'code.unique' => 'รหัสวิชานี้มีอยู่ในระบบแล้ว',
        ]);

        $subject = Subject::create($request->only('code', 'name'));

        if (auth()->user()->isTeacher()) {
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
        ], [
            'code.unique' => 'รหัสวิชานี้มีอยู่ในระบบแล้ว',
        ]);

        $subject->update($request->only('code', 'name'));

        return redirect()->route('admin.subjects.index')->with('success', 'อัปเดตข้อมูลรายวิชาเรียบร้อยแล้ว');
    }

    public function destroy(Subject $subject)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถลบรายวิชาได้');
        }

        $subject->delete();
        return redirect()->route('admin.subjects.index')->with('success', 'ลบข้อมูลรายวิชาเรียบร้อยแล้ว');
    }
}
