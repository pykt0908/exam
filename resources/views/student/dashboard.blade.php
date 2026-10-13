@extends('adminlte::page')

@section('title', 'หน้าแรกนักศึกษา')

@section('meta_tags')
<meta name="viewport"
    content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
@stop

@section('content_header')
<!-- <h3 class="text-dark font-weight-bold">ระบบสอบออนไลน์</h1> -->
@stop

@section('content')
<!-- Student Profile Card -->
<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h3 class="card-title font-weight-bold text-dark">ข้อมูลส่วนตัวนักศึกษา</h3>
            </div>
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <!-- Profile Image -->
                    <div class="col-12 col-sm-3 col-md-2 text-center mb-3 mb-sm-0">
                        @if(Auth::user()->photo)
                            <img src="{{ asset('storage/' . Auth::user()->photo) }}"
                                class="rounded-circle border student-avatar" alt="User Image"
                                onerror="this.onerror=null; this.src='{{ asset('images/default-avatar.png') }}';">
                        @else
                            <img src="{{ asset('images/default-avatar.png') }}" class="rounded-circle border student-avatar"
                                alt="User Image">
                        @endif
                    </div>
                    <!-- Profile Info Details -->
                    <div class="col-12 col-sm-9 col-md-10">
                        <div class="row">
                            <div class="col-6 col-md-3 mb-2 mb-md-0">
                                <span class="text-muted d-block text-xs">ชื่อ-นามสกุล</span>
                                <span class="text-sm font-weight-bold text-dark">{{ Auth::user()->name }}</span>
                            </div>
                            <div class="col-6 col-md-2 mb-2 mb-md-0">
                                <span class="text-muted d-block text-xs">รหัสนักศึกษา</span>
                                <span class="text-sm font-weight-bold text-dark">{{ Auth::user()->student_code }}</span>
                            </div>
                            <div class="col-6 col-md-3 mb-2 mb-md-0">
                                <span class="text-muted d-block text-xs">ห้องเรียน / ระดับชั้น</span>
                                <span
                                    class="text-sm font-weight-bold text-dark">{{ Auth::user()->classroom ? Auth::user()->classroom->name : 'ไม่ได้ระบุห้องเรียน' }}</span>
                            </div>
                            <div class="col-6 col-md-2 mb-2 mb-md-0">
                                <span class="text-muted d-block text-xs">ภาค</span>
                                <span
                                    class="text-sm font-weight-bold text-dark">{{ Auth::user()->classroom ? (Auth::user()->classroom->shift_text === 'บ่าย' ? 'ภาคบ่าย' : 'ภาคเช้า') : '-' }}</span>
                            </div>
                            <div class="col-6 col-md-2 mb-2 mb-md-0">
                                <span class="text-muted d-block text-xs">เลขประจำตัวประชาชน</span>
                                <span
                                    class="text-sm font-weight-bold text-dark">{{ Auth::user()->citizen_id ?: '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if(Auth::user()->is_exam_eligible === false || (int) Auth::user()->is_exam_eligible === 0)
    <!-- Suspended Exam Notice Card (Compact & Well-proportioned) -->
    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card shadow-sm border-0" style="border-radius: 12px; border-left: 5px solid #dc3545 !important;">
                <div class="card-body p-3 p-md-4 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2"
                        style="width: 52px; height: 52px; background-color: #fee2e2;">
                        <i class="fas fa-user-lock text-danger" style="font-size: 1.4rem;"></i>
                    </div>
                    <h5 class="font-weight-bold text-dark mb-1">
                        ระงับสิทธิ์การเข้าสอบชั่วคราว
                    </h5>

                    <div class="mt-2">
                        <span class="badge badge-danger font-weight-normal px-3 py-1 text-xs"
                            style="font-size: 0.85rem; border-radius: 20px;">
                            <i class="fas fa-exclamation-circle mr-1"></i> สาเหตุ: <strong
                                class="font-weight-bold">{{ Auth::user()->ineligible_reason ?: 'ระงับสิทธิ์ระดับผู้ใช้งาน (ติดต่อฝ่ายการเงิน/ทะเบียน)' }}</strong>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@else
    <!-- Exam Timetable (Shown only when student has exam rights) -->
    <div class="row mt-3 mt-md-4">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold text-dark mb-0">
                        ตารางสอบ
                    </h3>
                    <span class="text-muted text-sm">
                        ทั้งหมด {{ $exams->count() }} วิชา
                    </span>
                </div>
                <div class="card-body p-0">
                    <!-- Desktop Timetable Table (>= 768px) -->
                    <div class="d-none d-md-block table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center align-middle" style="width: 60px;">ลำดับ</th>
                                    <th class="text-center align-middle text-nowrap" style="width: 140px;">วันที่</th>
                                    <th class="text-center align-middle text-nowrap" style="width: 135px;">เวลาสอบ</th>
                                    <th class="align-middle" style="width: 28%; min-width: 200px;">รหัส ชื่อวิชา</th>
                                    <th class="align-middle" style="width: 32%; min-width: 220px;">รายละเอียดเบื้องต้นข้อสอบ</th>
                                    <th class="text-center align-middle text-nowrap" style="width: 125px;">สถานะ</th>
                                    <th class="text-center align-middle text-nowrap" style="width: 120px;">เข้าสอบ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $thaiDays = [
                                        'Sunday' => 'อาทิตย์',
                                        'Monday' => 'จันทร์',
                                        'Tuesday' => 'อังคาร',
                                        'Wednesday' => 'พุธ',
                                        'Thursday' => 'พฤหัสบดี',
                                        'Friday' => 'ศุกร์',
                                        'Saturday' => 'เสาร์'
                                    ];
                                    $thaiMonths = [
                                        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
                                        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
                                        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
                                    ];
                                @endphp
                                @forelse($exams as $index => $exam)
                                    @php
                                        $eligibility = Auth::user()->getExamEligibility($exam);
                                        $allowedAttempts = $exam->getAllowedAttemptsForUser(Auth::id());
                                        $extraAttempts = $exam->getExtraAttemptsForUser(Auth::id());
                                        $completedAttempts = $attempts->where('exam_id', $exam->id)->where('status', 'completed');
                                        $completedAttemptsCount = $completedAttempts->count();
                                        $hasInProgress = $attempts->where('exam_id', $exam->id)->where('status', 'in_progress')->first();
                                        $hasPassed = $completedAttempts->where('is_passed', true)->isNotEmpty();
                                        $latestCompleted = $completedAttempts->first();
                                        $isPendingGrading = $latestCompleted && $latestCompleted->isPendingGrading();
                                        $dayName = $exam->starts_at ? ($thaiDays[$exam->starts_at->format('l')] ?? '-') : '-';
                                        $canRetake = ($completedAttemptsCount < $allowedAttempts) && (($completedAttemptsCount > 0 && !$hasPassed) || ($extraAttempts > 0));
                                        $isExpiredStatus = $exam->isExpired() && !$hasInProgress && !$isPendingGrading && !($hasPassed && $extraAttempts === 0) && !$canRetake;
                                    @endphp
                                    @if($isExpiredStatus)
                                        @continue
                                    @endif
                                    <tr>
                                        <td class="text-center align-middle">{{ $index + 1 }}</td>
                                        <td class="text-center align-middle text-nowrap">
                                            @if($exam->starts_at)
                                                <span class="font-weight-bold text-dark">
                                                    {{ (int) $exam->starts_at->format('j') }} {{ $thaiMonths[(int) $exam->starts_at->format('n')] ?? '' }} {{ (int) $exam->starts_at->format('Y') + 543 }}
                                                </span>
                                            @else
                                                <span class="text-muted text-sm">ไม่ระบุวัน</span>
                                            @endif
                                        </td>
                                        <td class="text-center align-middle text-sm text-nowrap">
                                            @if($exam->starts_at && $exam->ends_at)
                                                {{ $exam->starts_at->format('H:i') }} - {{ $exam->ends_at->format('H:i') }} น.
                                            @elseif($exam->starts_at)
                                                ตั้งแต่ {{ $exam->starts_at->format('H:i') }} น.
                                            @else
                                                ไม่จำกัดเวลา
                                            @endif
                                        </td>
                                        <td class="align-middle text-dark font-weight-bold">
                                            {{ $exam->subject->code }} {{ $exam->subject->name }}
                                        </td>
                                        <td class="align-middle">
                                            <div class="font-weight-bold text-dark mb-1">{{ $exam->title }}</div>
                                            <div class="text-muted text-xs">
                                                เวลาทำสอบ: {{ $exam->duration_minutes }} นาที | จำนวนข้อสอบ:
                                                {{ $exam->questions_count }} ข้อ
                                            </div>
                                        </td>
                                        <td class="text-center align-middle text-sm text-nowrap">
                                            @if(!$eligibility['eligible'])
                                                <span class="badge badge-danger px-2 py-1 font-weight-bold"
                                                    title="{{ $eligibility['reason'] }}">
                                                    <i class="fas fa-ban mr-1"></i>ถูกระงับสิทธิ์สอบ
                                                </span>
                                            @elseif($hasInProgress)
                                                <span class="text-warning font-weight-bold">กำลังทำข้อสอบ</span>
                                            @elseif($isPendingGrading)
                                                <span class="text-warning font-weight-bold"><i
                                                        class="fas fa-clock mr-1"></i>รอตรวจข้อเขียน</span>
                                            @elseif($hasPassed && $extraAttempts === 0)
                                                <span class="text-success font-weight-bold">สอบผ่านแล้ว</span>
                                            @elseif($exam->isUpcoming())
                                                <span class="text-warning font-weight-bold">ยังไม่ถึงเวลาสอบ</span>
                                            @elseif($canRetake)
                                                <span class="text-info font-weight-bold">สอบซ่อมได้</span>
                                            @elseif($exam->isExpired())
                                                <span class="text-danger font-weight-bold">หมดเวลาสอบแล้ว</span>
                                            @elseif($completedAttemptsCount >= $allowedAttempts)
                                                <span class="text-success font-weight-bold">สอบแล้ว</span>
                                            @elseif($completedAttemptsCount > 0)
                                                <span class="text-info font-weight-bold">สอบซ่อมได้</span>
                                            @else
                                                <span class="text-success font-weight-bold">พร้อมสอบ</span>
                                            @endif
                                        </td>
                                        <td class="text-center align-middle text-nowrap">
                                            @if(!$eligibility['eligible'])
                                                <button type="button" class="btn btn-sm btn-secondary font-weight-bold" disabled
                                                    title="ถูกระงับสิทธิ์การสอบ: {{ $eligibility['reason'] }}">
                                                    <i class="fas fa-ban mr-1"></i>ถูกระงับสิทธิ์
                                                </button>
                                            @elseif($hasInProgress)
                                                <a href="{{ route('student.exam.take', [$exam->id, $hasInProgress->id]) }}"
                                                    class="btn btn-sm btn-warning text-white font-weight-bold">
                                                    ทำต่อ
                                                </a>
                                            @elseif($isPendingGrading)
                                                @if($latestCompleted && $exam->allow_review)
                                                    <a href="{{ route('student.exam.result', $latestCompleted->id) }}"
                                                        class="btn btn-sm btn-outline-warning font-weight-bold">
                                                        ดูผลเบื้องต้น
                                                    </a>
                                                @else
                                                    <span class="text-muted text-sm"><i class="fas fa-clock mr-1"></i>รอผลตรวจ</span>
                                                @endif
                                            @elseif($canRetake)
                                                <a href="{{ route('student.exam.lobby', $exam->id) }}"
                                                    class="btn btn-sm btn-warning text-white font-weight-bold">
                                                    สอบซ่อม
                                                </a>
                                            @elseif($hasPassed)
                                                @if($latestCompleted && $exam->allow_review)
                                                    <a href="{{ route('student.exam.result', $latestCompleted->id) }}"
                                                        class="btn btn-sm btn-success font-weight-bold">
                                                        แสดงรายละเอียด
                                                    </a>
                                                @else
                                                    <span class="text-muted text-sm">-</span>
                                                @endif
                                            @elseif($exam->isUpcoming())
                                                <span class="text-muted text-sm">ยังไม่เปิด</span>
                                            @elseif($exam->isExpired())
                                                <span class="text-muted text-sm">หมดเวลา</span>
                                            @elseif($completedAttemptsCount > 0 && $completedAttemptsCount < $allowedAttempts)
                                                <a href="{{ route('student.exam.lobby', $exam->id) }}"
                                                    class="btn btn-sm btn-warning text-white font-weight-bold">
                                                    สอบซ่อม
                                                </a>
                                            @elseif($completedAttemptsCount == 0)
                                                <a href="{{ route('student.exam.lobby', $exam->id) }}"
                                                    class="btn btn-sm btn-success font-weight-bold">
                                                    เข้าสอบ
                                                </a>
                                            @else
                                                @if($latestCompleted && $exam->allow_review)
                                                    <a href="{{ route('student.exam.result', $latestCompleted->id) }}"
                                                        class="btn btn-sm btn-success font-weight-bold">
                                                        แสดงรายละเอียด
                                                    </a>
                                                @else
                                                    <span class="text-muted text-sm">-</span>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-5">
                                            ยังไม่มีตารางสอบสำหรับรายวิชาที่คุณลงทะเบียนในขณะนี้
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Timetable View (< 768px) -->
                    <div class="d-block d-md-none">
                        @php
                            $thaiDaysMobile = [
                                'Sunday' => 'อาทิตย์',
                                'Monday' => 'จันทร์',
                                'Tuesday' => 'อังคาร',
                                'Wednesday' => 'พุธ',
                                'Thursday' => 'พฤหัสบดี',
                                'Friday' => 'ศุกร์',
                                'Saturday' => 'เสาร์'
                            ];
                            $thaiMonthsMobile = [
                                1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
                                5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
                                9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
                            ];
                        @endphp
                        @forelse($exams as $index => $exam)
                            @php
                                $eligibility = Auth::user()->getExamEligibility($exam);
                                $allowedAttempts = $exam->getAllowedAttemptsForUser(Auth::id());
                                $extraAttempts = $exam->getExtraAttemptsForUser(Auth::id());
                                $completedAttempts = $attempts->where('exam_id', $exam->id)->where('status', 'completed');
                                $completedAttemptsCount = $completedAttempts->count();
                                $hasInProgress = $attempts->where('exam_id', $exam->id)->where('status', 'in_progress')->first();
                                $hasPassed = $completedAttempts->where('is_passed', true)->isNotEmpty();
                                $latestCompleted = $completedAttempts->first();
                                $isPendingGrading = $latestCompleted && $latestCompleted->isPendingGrading();
                                $dayNameMobile = $exam->starts_at ? ($thaiDaysMobile[$exam->starts_at->format('l')] ?? '-') : '-';
                                $canRetake = ($completedAttemptsCount < $allowedAttempts) && (($completedAttemptsCount > 0 && !$hasPassed) || ($extraAttempts > 0));
                                $isExpiredStatus = $exam->isExpired() && !$hasInProgress && !$isPendingGrading && !($hasPassed && $extraAttempts === 0) && !$canRetake;
                            @endphp
                            @if($isExpiredStatus)
                                @continue
                            @endif
                            <div class="p-3 border-bottom {{ $loop->even ? 'bg-light' : 'bg-white' }}">
                                <div class="text-xs text-muted mb-1">
                                    ลำดับที่ {{ $index + 1 }}
                                </div>
                                <div class="mb-1 text-sm font-weight-bold text-dark">
                                    @if($exam->starts_at)
                                        วัน{{ $dayNameMobile }}ที่ {{ (int) $exam->starts_at->format('j') }} {{ $thaiMonthsMobile[(int) $exam->starts_at->format('n')] ?? '' }} {{ (int) $exam->starts_at->format('Y') + 543 }}
                                        เวลา
                                        {{ $exam->starts_at && $exam->ends_at ? $exam->starts_at->format('H:i') . ' - ' . $exam->ends_at->format('H:i') . ' น.' : ($exam->starts_at ? 'ตั้งแต่ ' . $exam->starts_at->format('H:i') . ' น.' : 'ไม่จำกัดเวลา') }}
                                    @else
                                        ไม่ระบุวันและเวลาสอบ
                                    @endif
                                </div>
                                <div class="mb-1 text-sm text-dark">
                                    <span class="text-muted">รายวิชา:</span> <span
                                        class="font-weight-bold">{{ $exam->subject->code }}
                                        {{ $exam->subject->name }}</span>
                                </div>
                                <div class="mb-2 text-sm text-dark">
                                    <span class="text-muted">รายละเอียดข้อสอบ:</span> {{ $exam->title }} (เวลาทำสอบ
                                    {{ $exam->duration_minutes }} นาที, {{ $exam->questions_count }} ข้อ)
                                </div>
                                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                    <div class="text-sm">
                                        <span class="text-muted">สถานะ:</span>
                                        @if(!$eligibility['eligible'])
                                            <span class="badge badge-danger px-2 py-1 font-weight-bold text-xs"
                                                title="{{ $eligibility['reason'] }}">
                                                <i class="fas fa-ban mr-1"></i>ถูกระงับสิทธิ์สอบ
                                            </span>
                                        @elseif($hasInProgress)
                                            <span class="text-warning font-weight-bold text-sm">กำลังทำข้อสอบ</span>
                                        @elseif($isPendingGrading)
                                            <span class="text-warning font-weight-bold text-sm"><i
                                                    class="fas fa-clock mr-1"></i>รอตรวจข้อเขียน</span>
                                        @elseif($hasPassed && $extraAttempts === 0)
                                            <span class="text-success font-weight-bold text-sm">สอบผ่านแล้ว</span>
                                        @elseif($exam->isUpcoming())
                                            <span class="text-warning font-weight-bold text-sm">ยังไม่ถึงเวลาสอบ</span>
                                        @elseif($canRetake)
                                            <span class="text-info font-weight-bold text-sm">สอบซ่อมได้</span>
                                        @elseif($exam->isExpired())
                                            <span class="text-danger font-weight-bold text-sm">หมดเวลาสอบแล้ว</span>
                                        @elseif($completedAttemptsCount >= $allowedAttempts)
                                            <span class="text-success font-weight-bold text-sm">สอบแล้ว</span>
                                        @elseif($completedAttemptsCount > 0)
                                            <span class="text-info font-weight-bold text-sm">สอบซ่อมได้</span>
                                        @else
                                            <span class="text-success font-weight-bold text-sm">พร้อมสอบ</span>
                                        @endif
                                    </div>
                                    <div>
                                        @if(!$eligibility['eligible'])
                                            <button type="button" class="btn btn-sm btn-secondary font-weight-bold px-3" disabled
                                                title="ถูกระงับสิทธิ์การสอบ: {{ $eligibility['reason'] }}">
                                                <i class="fas fa-ban mr-1"></i>ถูกระงับสิทธิ์
                                            </button>
                                        @elseif($hasInProgress)
                                            <a href="{{ route('student.exam.take', [$exam->id, $hasInProgress->id]) }}"
                                                class="btn btn-sm btn-warning text-white font-weight-bold px-3">
                                                ทำต่อ
                                            </a>
                                        @elseif($isPendingGrading)
                                            @if($latestCompleted && $exam->allow_review)
                                                <a href="{{ route('student.exam.result', $latestCompleted->id) }}"
                                                    class="btn btn-sm btn-outline-warning font-weight-bold px-3">
                                                    ดูผลเบื้องต้น
                                                </a>
                                            @else
                                                <span class="text-muted text-sm"><i class="fas fa-clock mr-1"></i>รอผลตรวจ</span>
                                            @endif
                                        @elseif($canRetake)
                                            <a href="{{ route('student.exam.lobby', $exam->id) }}"
                                                class="btn btn-sm btn-warning text-white font-weight-bold px-3">
                                                สอบซ่อม
                                            </a>
                                        @elseif($hasPassed)
                                            @if($latestCompleted && $exam->allow_review)
                                                <a href="{{ route('student.exam.result', $latestCompleted->id) }}"
                                                    class="btn btn-sm btn-success font-weight-bold px-3">
                                                    แสดงรายละเอียด
                                                </a>
                                            @else
                                                <span class="text-muted text-sm">-</span>
                                            @endif
                                        @elseif($exam->isUpcoming())
                                            <span class="text-muted text-sm">ยังไม่เปิด</span>
                                        @elseif($exam->isExpired())
                                            <span class="text-muted text-sm">หมดเวลา</span>
                                        @elseif($completedAttemptsCount > 0 && $completedAttemptsCount < $allowedAttempts)
                                            <a href="{{ route('student.exam.lobby', $exam->id) }}"
                                                class="btn btn-sm btn-warning text-white font-weight-bold px-3">
                                                สอบซ่อม
                                            </a>
                                        @elseif($completedAttemptsCount == 0)
                                            <a href="{{ route('student.exam.lobby', $exam->id) }}"
                                                class="btn btn-sm btn-success font-weight-bold px-3">
                                                เข้าสอบ
                                            </a>
                                        @else
                                            @if($latestCompleted && $exam->allow_review)
                                                <a href="{{ route('student.exam.result', $latestCompleted->id) }}"
                                                    class="btn btn-sm btn-success font-weight-bold px-3">
                                                    แสดงรายละเอียด
                                                </a>
                                            @else
                                                <span class="text-muted text-sm">-</span>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-5 px-3">
                                ยังไม่มีตารางสอบสำหรับรายวิชาที่คุณลงทะเบียนในขณะนี้
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
@stop

@section('css')
<style>
    html,
    body {
        touch-action: pan-x pan-y;
        -webkit-text-size-adjust: 100%;
        -ms-text-size-adjust: 100%;
    }

    @media (max-width: 768px) {

        input,
        textarea,
        select,
        .form-control {
            font-size: 16px !important;
        }
    }

    .student-avatar {
        width: 120px;
        height: 120px;
        object-fit: cover;
        box-shadow: none;
    }

    @media (max-width: 575.98px) {
        .student-avatar {
            width: 85px;
            height: 85px;
        }

        .table th,
        .table td {
            padding: 0.5rem !important;
            font-size: 0.85rem !important;
        }

        .table td div.text-xs {
            font-size: 0.75rem !important;
        }
    }
</style>
@stop

@section('js')
<script>
    $(document).ready(function () {
        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'แจ้งเตือน',
                text: {!! json_encode(session('error')) !!},
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'ตกลง'
            });
        @endif
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: {!! json_encode(session('success')) !!},
                confirmButtonColor: '#28a745',
                confirmButtonText: 'ตกลง'
            });
        @endif
        });
</script>
@stop