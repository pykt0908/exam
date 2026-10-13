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

        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        try {
            $result = $this->misSchool->syncAllTeachers();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json($result);
            }

            if ($result['success']) {
                return back()->with('success', $result['message']);
            }

            return back()->with('error', $result['message']);
        } catch (\Throwable $e) {
            \Log::error('MisSchool syncTeachers error: ' . $e->getMessage());

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'เกิดข้อผิดพลาดในการซิงค์ข้อมูลอาจารย์: ' . $e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'เกิดข้อผิดพลาดในการซิงค์ข้อมูลอาจารย์: ' . $e->getMessage());
        }
    }

    /**
     * ซิงค์รายชื่อห้องเรียนจาก MisSchool
     */
    public function syncClassrooms(Request $request)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้');
        }

        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        try {
            $result = $this->misSchool->syncAllClassrooms();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json($result);
            }

            if ($result['success']) {
                return back()->with('success', $result['message']);
            }

            return back()->with('error', $result['message']);
        } catch (\Throwable $e) {
            \Log::error('MisSchool syncClassrooms error: ' . $e->getMessage());

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'เกิดข้อผิดพลาดในการซิงค์ข้อมูลห้องเรียน: ' . $e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'เกิดข้อผิดพลาดในการซิงค์ข้อมูลห้องเรียน: ' . $e->getMessage());
        }
    }

    /**
     * ซิงค์ข้อมูลนักศึกษาจาก MisSchool
     */
    public function syncStudents(Request $request)
    {
        if (auth()->user()->isTeacher()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้');
        }

        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        try {
            $options = [
                'limit' => (int) $request->input('limit', 1000),
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
        } catch (\Throwable $e) {
            \Log::error('MisSchool syncStudents error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'เกิดข้อผิดพลาดในการซิงค์ข้อมูลนักศึกษา: ' . $e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'เกิดข้อผิดพลาดในการซิงค์ข้อมูลนักศึกษา: ' . $e->getMessage());
        }
    }
}
