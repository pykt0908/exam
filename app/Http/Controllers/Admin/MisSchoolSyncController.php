<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MisSchoolService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MisSchoolSyncController extends Controller
{
    protected MisSchoolService $misSchool;

    public function __construct(MisSchoolService $misSchool)
    {
        $this->misSchool = $misSchool;
    }

    /**
     * ตรวจสอบสถานะการเชื่อมต่อ MisSchool
     */
    public function health(): JsonResponse
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้');
        }

        $result = $this->misSchool->checkHealth();
        $connected = (bool) ($result['connected'] ?? false);

        return response()->json([
            'success' => $connected,
            'message' => $connected ? 'เชื่อมต่อฐานข้อมูล MisSchool ได้เรียบร้อย' : 'ไม่สามารถเชื่อมต่อ MisSchool ได้',
            'source' => $result['source'] ?? '-',
            'stats' => [
                'active_teachers' => $result['counts']['teachers'] ?? 0,
                'active_classrooms' => $result['counts']['classrooms'] ?? 0,
                'active_students' => $result['counts']['students'] ?? 0,
            ],
            'timestamp' => $result['timestamp'] ?? now()->toIso8601String(),
            'data' => $result,
        ]);
    }

    /**
     * ซิงค์รายชื่ออาจารย์ทั้งหมดจาก MisSchool
     */
    public function syncTeachers(Request $request)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้');
        }

        $result = $this->misSchool->syncAllTeachers();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($result);
        }

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    /**
     * ซิงค์รายชื่อห้องเรียนจาก MisSchool
     */
    public function syncClassrooms(Request $request)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้');
        }

        $result = $this->misSchool->syncAllClassrooms();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($result);
        }

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    /**
     * ซิงค์ข้อมูลนักศึกษาจาก MisSchool
     */
    public function syncStudents(Request $request)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้');
        }

        $options = [
            'limit' => $request->input('limit', 1000),
            'year' => $request->input('year'),
            'room' => $request->input('room'),
        ];

        $result = $this->misSchool->syncAllStudents($options);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($result);
        }

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }
}
