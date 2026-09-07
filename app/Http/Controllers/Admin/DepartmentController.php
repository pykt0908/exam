<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Department::withCount(['users as teachers_count' => function($query) {
            $query->whereIn('role', ['admin', 'teacher']);
        }])->with(['users' => function($query) {
            $query->whereIn('role', ['admin', 'teacher']);
        }]);

        if ($request->filled('q')) {
            $q = $request->get('q');
            $query->where('name', 'like', "%{$q}%");
        }

        $departments = $query->orderBy('name')->get();

        return view('admin.departments.index', compact('departments'));
    }

    public function show(Department $department)
    {
        $teachers = User::where('department_id', $department->id)
            ->whereIn('role', ['admin', 'teacher'])
            ->withCount('enrolledSubjects')
            ->orderByRaw("
                CASE 
                    WHEN role = 'admin' THEN 1
                    WHEN academic_role LIKE '%academic_deputy%' THEN 2
                    WHEN academic_role LIKE '%evaluation_head%' THEN 3
                    WHEN academic_role LIKE '%department_head%' THEN 4
                    ELSE 5
                END ASC
            ")
            ->orderBy('name', 'asc')
            ->get();

        return view('admin.departments.show', compact('department', 'teachers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
        ], [
            'name.required' => 'กรุณากรอกชื่อหมวดวิชา/แผนกวิชา',
            'name.unique' => 'มีหมวดวิชา/แผนกวิชานี้ในระบบแล้ว',
        ]);

        Department::create([
            'name' => $request->name,
        ]);

        return redirect()->route('admin.departments.index')->with('success', 'เพิ่มหมวดวิชา/แผนกวิชาใหม่เรียบร้อยแล้ว');
    }

    public function update(Request $request, Department $department)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:departments,name,' . $department->id],
        ], [
            'name.required' => 'กรุณากรอกชื่อหมวดวิชา/แผนกวิชา',
            'name.unique' => 'มีหมวดวิชา/แผนกวิชานี้ในระบบแล้ว',
        ]);

        $department->update([
            'name' => $request->name,
        ]);

        return redirect()->route('admin.departments.index')->with('success', 'แก้ไขข้อมูลหมวดวิชา/แผนกวิชาเรียบร้อยแล้ว');
    }

    public function destroy(Department $department)
    {
        // Deleting the department sets department_id to null for users
        $department->delete();

        return redirect()->route('admin.departments.index')->with('success', 'ลบข้อมูลหมวดวิชา/แผนกวิชาเรียบร้อยแล้ว');
    }
}
