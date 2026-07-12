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
        $searchPerformed = false;
        $classrooms = collect();

        if ($request->has('search')) {
            $searchPerformed = true;
            $query = Classroom::withCount(['users as students_count' => function($query) {
                $query->where('role', 'student');
            }]);

            if ($request->filled('q')) {
                $q = $request->get('q');
                $query->where('name', 'like', "%{$q}%");
            }

            $classrooms = $query->orderBy('name')->get();
        }

        return view('admin.classrooms.index', compact('classrooms', 'searchPerformed'));
    }

    public function show(Classroom $classroom)
    {
        // Fetch all students in this classroom
        $students = User::where('classroom_id', $classroom->id)
            ->where('role', 'student')
            ->withCount(['examAttempts' => function($query) {
                $query->where('status', 'completed');
            }])
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
        // Note: classroom_id on users table is set to nullOnDelete, so students in this class will not be deleted.
        $classroom->delete();

        return redirect()->route('admin.classrooms.index')->with('success', 'ลบข้อมูลห้องเรียน/ระดับชั้นเรียบร้อยแล้ว');
    }
}
