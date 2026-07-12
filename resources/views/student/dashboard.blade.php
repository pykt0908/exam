@extends('adminlte::page')

@section('title', 'หน้าแรกนักศึกษา')

@section('content_header')
    <h1 class="text-dark font-weight-bold">ระบบสอบออนไลน์ (สำหรับนักศึกษา)</h1>
@stop

@section('content')
    <!-- Student Profile Card -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-id-card mr-2"></i>ข้อมูลส่วนตัวนักศึกษา</h3>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <!-- Profile Image -->
                        <div class="col-md-2 text-center mb-3 mb-md-0">
                            @if(Auth::user()->photo)
                                <img src="{{ asset('storage/' . Auth::user()->photo) }}" class="elevation-2" alt="User Image" style="width: 140px; height: 140px; object-fit: cover; border-radius: 12px;">
                            @else
                                <img src="{{ asset('vendor/adminlte/dist/img/avatar5.png') }}" class="elevation-2" alt="User Image" style="width: 140px; height: 140px; object-fit: cover; border-radius: 12px;">
                            @endif
                        </div>
                        <!-- Profile Info Details -->
                        <div class="col-md-10">
                            <div class="row">
                                <div class="col-sm-6 col-md-4 mb-3">
                                    <span class="text-muted d-block text-xs">ชื่อ-นามสกุล</span>
                                    <strong class="text-md text-dark">{{ Auth::user()->name }}</strong>
                                </div>
                                <div class="col-sm-6 col-md-4 mb-3">
                                    <span class="text-muted d-block text-xs">รหัสนักศึกษา</span>
                                    <strong class="text-md text-dark">{{ Auth::user()->student_code }}</strong>
                                </div>
                                <div class="col-sm-6 col-md-4 mb-3">
                                    <span class="text-muted d-block text-xs">ห้องเรียน / ระดับชั้น</span>
                                    <strong class="text-md text-dark">{{ Auth::user()->classroom ? Auth::user()->classroom->name : 'ไม่ได้ระบุห้องเรียน' }}</strong>
                                </div>
                                <div class="col-sm-6 col-md-4 mb-3 mb-md-0">
                                    <span class="text-muted d-block text-xs">เลขประจำตัวประชาชน</span>
                                    <span class="text-dark font-weight-bold">{{ Auth::user()->citizen_id ?: '-' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <!-- Available Exams list -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-file-alt mr-2"></i>รายการข้อสอบที่เปิดให้สอบ</h3>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($exams as $exam)
                            @php
                                $completedAttemptsCount = $attempts->where('exam_id', $exam->id)->where('status', 'completed')->count();
                                $hasInProgress = $attempts->where('exam_id', $exam->id)->where('status', 'in_progress')->first();
                                $hasPassed = $attempts->where('exam_id', $exam->id)->where('status', 'completed')->where('is_passed', true)->isNotEmpty();
                            @endphp
                            <li class="list-group-item p-3">
                                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center">
                                    <div class="mb-3 mb-sm-0">
                                         <div class="text-muted text-sm font-weight-bold mb-1">
                                             รายวิชา: {{ $exam->subject->code }} {{ $exam->subject->name }}
                                         </div>
                                         <h5 class="font-weight-bold mb-1 text-dark">{{ $exam->title }}</h5>
                                         <p class="text-sm text-muted mb-0">
                                             <i class="fas fa-clock mr-1"></i> เวลาทำข้อสอบ: {{ $exam->duration_minutes }} นาที | 
                                             <i class="fas fa-question-circle mr-1"></i> จำนวนข้อสอบ: {{ $exam->questions_count }} ข้อ
                                         </p>
                                    </div>
                                    <div class="text-center text-sm-right">
                                         @if($hasInProgress)
                                             <a href="{{ route('student.exam.take', [$exam->id, $hasInProgress->id]) }}" class="btn btn-warning btn-mobile-block text-white font-weight-bold shadow-xs">
                                                 <i class="fas fa-play mr-1"></i> ทำต่อ
                                             </a>
                                         @elseif($hasPassed)
                                             <span class="badge badge-success d-block d-sm-inline-block px-3 py-2"><i class="fas fa-check-circle mr-1"></i> สอบผ่านแล้ว</span>
                                         @elseif($completedAttemptsCount > 0 && $completedAttemptsCount < $exam->max_attempts)
                                             <a href="{{ route('student.exam.lobby', $exam->id) }}" class="btn btn-warning btn-mobile-block text-white font-weight-bold shadow-xs">
                                                 <i class="fas fa-redo mr-1"></i> เข้าสอบซ่อม (ครั้งที่ {{ $completedAttemptsCount + 1 }})
                                             </a>
                                         @elseif($completedAttemptsCount == 0)
                                             <a href="{{ route('student.exam.lobby', $exam->id) }}" class="btn btn-success btn-mobile-block font-weight-bold shadow-xs">
                                                 <i class="fas fa-sign-in-alt mr-1"></i> เข้าสอบ
                                             </a>
                                         @else
                                             <span class="badge badge-secondary d-block d-sm-inline-block px-3 py-2"><i class="fas fa-times-circle mr-1"></i> สอบเสร็จแล้ว</span>
                                         @endif
                                    </div>
                                </div>
                            </li>
                        @empty
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-folder-open fa-3x mb-3 text-secondary"></i>
                                <p class="mb-0">ยังไม่มีรายวิชาที่เปิดสอบในขณะนี้</p>
                            </div>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <!-- Attempt History -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-history mr-2"></i>ประวัติการเข้าสอบของคุณ</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>วิชา/ข้อสอบ</th>
                                    <th class="text-center">คะแนน</th>
                                    <th class="text-center">ผลการสอบ</th>
                                    <th class="text-center">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($attempts as $att)
                                    <tr>
                                        <td>
                                             <div class="text-muted text-xs font-weight-bold mb-1">
                                                 รายวิชา: {{ $att->exam->subject->code }} {{ $att->exam->subject->name }}
                                             </div>
                                             <strong>{{ $att->exam->title }}</strong>
                                            <div class="text-xs text-muted">สอบเมื่อ: {{ $att->completed_at ? $att->completed_at->format('d/m/Y H:i') : $att->started_at->format('d/m/Y H:i') }} น.</div>
                                        </td>
                                        <td class="text-center font-weight-bold text-primary">
                                            @if($att->status === 'completed')
                                                {{ $att->score }} / {{ $att->exam->questions()->sum('score') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($att->status === 'completed')
                                                @if($att->is_passed)
                                                    <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> ผ่านเกณฑ์</span>
                                                @else
                                                    <span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i> ไม่ผ่าน</span>
                                                @endif
                                            @else
                                                <span class="badge badge-warning px-2 py-1">กำลังดำเนินการ</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                             @if($att->status === 'completed')
                                                 @if($att->exam->allow_review)
                                                     <a href="{{ route('student.exam.result', $att->id) }}" class="btn btn-sm btn-info font-weight-bold shadow-xs">
                                                         <i class="fas fa-search mr-1"></i>ดูเฉลย
                                                     </a>
                                                 @else
                                                     <span class="badge badge-secondary px-2 py-1"><i class="fas fa-lock mr-1"></i> ปิดการดูย้อนหลัง</span>
                                                 @endif
                                             @else
                                                 <a href="{{ route('student.exam.take', [$att->exam->id, $att->id]) }}" class="btn btn-sm btn-warning text-white font-weight-bold shadow-xs">
                                                     <i class="fas fa-play mr-1"></i> ทำต่อ
                                                 </a>
                                             @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                         <td colspan="4" class="text-center text-muted py-5">คุณยังไม่มีประวัติการเข้าสอบในระบบ</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        @media (max-width: 575.98px) {
            .table th, .table td {
                padding: 0.5rem !important;
                font-size: 0.85rem !important;
            }
            .table td div.text-xs {
                font-size: 0.75rem !important;
            }
            .btn-mobile-block {
                width: 100% !important;
                display: block !important;
            }
        }
        @media (min-width: 576px) {
            .btn-mobile-block {
                width: auto !important;
                display: inline-block !important;
            }
        }
    </style>
@stop
