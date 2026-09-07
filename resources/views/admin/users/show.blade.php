@extends('adminlte::page')

@section('title', 'ประวัติผู้ใช้งาน: ' . $user->name)

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-2 border-bottom mb-3">
        <div>
            <div class="d-flex align-items-center">
                
                <div>
                    <h1 class="font-weight-bold text-dark mb-0 h4">
                        ข้อมูลประวัติผู้ใช้งาน
                    </h1>
                </div>
            </div>
        </div>
        <div class="mt-2 mt-md-0 d-flex align-items-center">
           <a href="{{ route('admin.users.index', ['role' => 'student']) }}" class="btn btn-sm btn-danger font-weight-bold shadow-sm mr-3">
    <i class="fas fa-arrow-left mr-1"></i> กลับหน้ารายชื่อผู้ใช้งาน
</a>
        </div>
    </div>
@stop

@section('content')
    @if($user->isStudent())
        @php
            $totalAttempts = $attempts->count();
            $passedCount = $attempts->where('status', 'completed')->where('is_passed', true)->count();
            $failedCount = $attempts->where('status', 'completed')->where('is_passed', false)->where('grading_status', '!=', 'pending_grading')->count();
            $pendingCount = $attempts->where('status', 'completed')->where('grading_status', 'pending_grading')->count();
            $inProgressCount = $attempts->where('status', '!=', 'completed')->count();
            
            $uniqueSubjects = $attempts->pluck('exam.subject')->filter()->unique('id')->sortBy('code');
        @endphp

        <div class="row">
            <!-- 1. Left Column: Student Identity & Details (Name, Code, Group, Citizen ID) -->
            <div class="col-lg-3 col-md-4 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center pt-4 pb-3">
                        <div class="position-relative d-inline-block mb-3">
                            @if($user->photo)
                                <img src="{{ asset('storage/' . $user->photo) }}" class="rounded-circle border shadow-xs" alt="User Image" style="width: 105px; height: 105px; object-fit: cover;" onerror="this.onerror=null; this.src='{{ asset('images/default-avatar.png') }}';">
                            @else
                                <img src="{{ asset('images/default-avatar.png') }}" class="rounded-circle border shadow-xs" alt="Student Placeholder" style="width: 105px; height: 105px; object-fit: cover;">
                            @endif
                            @if($user->is_exam_eligible === false)
                                <span class="badge badge-danger position-absolute shadow-xs" style="bottom: 2px; right: 2px; border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center;" title="ถูกระงับสิทธิ์สอบภาพรวม">
                                    <i class="fas fa-ban text-xs"></i>
                                </span>
                            @endif
                        </div>

                        <ul class="list-group list-group-unbordered text-left small mb-0">
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-1">
                                <span class="text-muted"><i class="fas fa-user mr-1 text-secondary"></i>ชื่อ-นามสกุล</span>
                                <span class="font-weight-bold text-dark text-truncate" style="max-width: 135px;" title="{{ $user->name }}">{{ $user->name }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-1">
                                <span class="text-muted"><i class="fas fa-id-badge mr-1 text-secondary"></i>รหัส</span>
                                <code class="font-weight-bold text-primary">{{ $user->student_code ?? '-' }}</code>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-1">
                                <span class="text-muted"><i class="fas fa-users mr-1 text-secondary"></i>กลุ่มเรียน</span>
                                <span class="font-weight-bold text-dark text-truncate" style="max-width: 135px;" title="{{ $user->classroom ? $user->classroom->name : 'ไม่ได้ระบุ' }}">{{ $user->classroom ? $user->classroom->name : 'ไม่ได้ระบุ' }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-1">
                                <span class="text-muted"><i class="fas fa-id-card mr-1 text-secondary"></i>เลขบัตร</span>
                                <code class="font-weight-bold text-dark">{{ $user->citizen_id ?? '-' }}</code>
                            </li>
                        </ul>

                        @if($user->is_exam_eligible === false)
                            <div class="alert alert-danger p-2 text-left small mt-3 mb-0">
                                <strong><i class="fas fa-exclamation-circle mr-1"></i> ระงับสิทธิ์สอบ:</strong>
                                {{ $user->ineligible_reason ?: 'ระงับสิทธิ์สอบระดับผู้ใช้งาน' }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- 2. Right Column: Exam History with Filter Toolbar -->
            <div class="col-lg-9 col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h3 class="card-title font-weight-bold text-dark mb-0">
                                <i class="fas fa-history text-primary mr-2"></i>ประวัติการสอบ
                                <span class="badge badge-secondary ml-1" id="badge-total-count">{{ $totalAttempts }} ครั้ง</span>
                            </h3>
                        </div>
                    </div>

                    @if($totalAttempts > 0)
                        <!-- Filter Toolbar -->
                        <div class="card-body bg-light border-bottom p-3">
                            <div class="row align-items-center">
                                <!-- Status Filter Buttons -->
                                <div class="col-lg-7 col-md-12 mb-2 mb-lg-0">
                                    <div class="d-flex flex-wrap align-items-center">
                                        <span class="text-muted small font-weight-bold mr-2 mb-1"><i class="fas fa-filter mr-1"></i>สถานะ:</span>
                                        <button type="button" class="btn btn-sm btn-dark font-weight-bold filter-status-btn mr-1 mb-1" data-status="all" data-active-class="btn-dark">
                                            ทั้งหมด ({{ $totalAttempts }})
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-success font-weight-bold filter-status-btn mr-1 mb-1" data-status="passed" data-active-class="btn-success">
                                            <i class="fas fa-check-circle mr-1"></i>ผ่านเกณฑ์ ({{ $passedCount }})
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger font-weight-bold filter-status-btn mr-1 mb-1" data-status="failed" data-active-class="btn-danger">
                                            <i class="fas fa-times-circle mr-1"></i>ไม่ผ่านเกณฑ์ ({{ $failedCount }})
                                        </button>
                                        @if($pendingCount > 0)
                                            <button type="button" class="btn btn-sm btn-outline-warning text-dark font-weight-bold filter-status-btn mr-1 mb-1" data-status="pending_grading" data-active-class="btn-warning">
                                                <i class="fas fa-clock mr-1"></i>รอตรวจ ({{ $pendingCount }})
                                            </button>
                                        @endif
                                        @if($inProgressCount > 0)
                                            <button type="button" class="btn btn-sm btn-outline-secondary font-weight-bold filter-status-btn mr-1 mb-1" data-status="in_progress" data-active-class="btn-secondary">
                                                <i class="fas fa-spinner mr-1"></i>กำลังสอบ ({{ $inProgressCount }})
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                <!-- Subject Filter Dropdown -->
                                <div class="col-lg-5 col-md-12">
                                    <div class="d-flex align-items-center">
                                        <label for="filter-subject" class="text-muted small font-weight-bold mr-2 mb-0 text-nowrap"><i class="fas fa-book mr-1"></i>รายวิชา:</label>
                                        <select id="filter-subject" class="form-control form-control-sm bg-white">
                                            <option value="">-- ทุกรายวิชา (ทั้งหมด) --</option>
                                            @foreach($uniqueSubjects as $subj)
                                                <option value="{{ $subj->id }}">{{ $subj->code }} - {{ $subj->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" id="btn-reset-filters" class="btn btn-sm btn-outline-secondary ml-2 font-weight-bold text-nowrap" title="ล้างตัวกรอง">
                                            <i class="fas fa-redo"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="attempts-table" class="table table-hover table-striped align-middle mb-0" style="width: 100%;">
                                <thead class="bg-light text-secondary small font-weight-bold text-uppercase">
                                    <tr>
                                        <th style="width: 35px;" class="text-center">#</th>
                                        <th>ชุดข้อสอบ / รายวิชา</th>
                                        <th class="text-center">เวลา / ระยะเวลา</th>
                                        <th class="text-center">คะแนนที่ได้</th>
                                        <th class="text-center">สลับจอ</th>
                                        <th class="text-center">ผลการสอบ</th>
                                        <th style="width: 75px;" class="text-center">ดูผล</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($attempts as $index => $attempt)
                                        @php
                                            $totalScorePossible = (float)($attempt->exam->total_score ?? $attempt->exam->questions->sum('score'));
                                            $percent = $totalScorePossible > 0 ? round((($attempt->score ?? 0) / $totalScorePossible) * 100, 1) : 0;
                                            $isScaled = $attempt->isScaled() || ($attempt->raw_score !== null && $attempt->total_raw_score > 0 && abs($attempt->total_raw_score - $totalScorePossible) > 0.001);
                                            
                                            if ($attempt->status !== 'completed') {
                                                $statusKey = 'in_progress';
                                            } elseif ($attempt->grading_status === 'pending_grading') {
                                                $statusKey = 'pending_grading';
                                            } elseif ($attempt->is_passed) {
                                                $statusKey = 'passed';
                                            } else {
                                                $statusKey = 'failed';
                                            }

                                            // Duration
                                            $durationText = '-';
                                            if ($attempt->started_at && $attempt->completed_at) {
                                                $diffSeconds = $attempt->started_at->diffInSeconds($attempt->completed_at);
                                                $mins = floor($diffSeconds / 60);
                                                $secs = $diffSeconds % 60;
                                                $hrs = floor($mins / 60);
                                                $remMins = $mins % 60;
                                                if ($hrs > 0) {
                                                    $durationText = $hrs . ' ชม. ' . $remMins . ' นาที';
                                                } else {
                                                    $durationText = ($mins > 0 ? $mins . ' น. ' : '') . $secs . ' วิ';
                                                }
                                            }
                                        @endphp
                                        <tr data-status="{{ $statusKey }}" data-subject-id="{{ $attempt->exam->subject_id ?? '' }}">
                                            <td class="text-center align-middle font-weight-bold text-muted">{{ $index + 1 }}</td>
                                            <td class="align-middle">
                                                <div class="d-flex align-items-center mb-1">
                                                    <span class="badge badge-secondary mr-1">{{ $attempt->exam->subject->code ?? '-' }}</span>
                                                    <span class="badge badge-light border text-muted">รอบที่ {{ $attempt->attempt_number }}</span>
                                                </div>
                                                <div class="font-weight-bold text-dark text-md">{{ $attempt->exam->title }}</div>
                                                <small class="text-muted">{{ $attempt->exam->subject->name ?? '' }}</small>
                                            </td>
                                            <td class="text-center align-middle">
                                                <div class="small text-dark font-weight-bold">{{ $attempt->started_at ? $attempt->started_at->format('d/m/Y H:i') : '-' }} น.</div>
                                                @if($attempt->completed_at)
                                                    <div class="small text-muted">ถึง {{ $attempt->completed_at->format('H:i') }} น.</div>
                                                    <span class="badge badge-light border text-secondary mt-1">
                                                        <i class="far fa-clock mr-1"></i>{{ $durationText }}
                                                    </span>
                                                @else
                                                    <span class="badge badge-warning text-dark text-xs mt-1">ยังไม่ส่งข้อสอบ</span>
                                                @endif
                                            </td>
                                            <td class="text-center align-middle">
                                                @if($attempt->status === 'completed')
                                                    <div class="font-weight-bold text-primary text-md">
                                                        {{ number_format($attempt->score ?? 0, 2) }}
                                                        <small class="text-muted font-weight-normal">/ {{ number_format($totalScorePossible, 2) }}</small>
                                                    </div>
                                                    <div class="text-xs text-muted">
                                                        คิดเป็น <strong>{{ $percent }}%</strong>
                                                        @if($isScaled && $attempt->raw_score !== null)
                                                            <div>(ดิบ: {{ number_format($attempt->raw_score, 1) }}/{{ number_format($attempt->total_raw_score, 1) }})</div>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center align-middle">
                                                @if($attempt->focus_escape_count > 0)
                                                    <span class="text-danger font-weight-bold small" title="ตรวจพบการสลับหน้าจอระหว่างทำข้อสอบ">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>{{ $attempt->focus_escape_count }} ครั้ง
                                                    </span>
                                                @else
                                                    <span class="text-muted small">0 ครั้ง</span>
                                                @endif
                                            </td>
                                            <td class="text-center align-middle">
                                                @if($attempt->status !== 'completed')
                                                    <span class="badge badge-warning text-dark px-2 py-1">กำลังดำเนินการ</span>
                                                @elseif($attempt->grading_status === 'pending_grading')
                                                    <span class="text-warning-dark font-weight-bold small d-block">
                                                        <i class="fas fa-clock mr-1"></i>รอตรวจข้อเขียน
                                                    </span>
                                                @elseif($attempt->is_passed)
                                                    <span class="text-success font-weight-bold small d-block">
                                                        <i class="fas fa-check-circle mr-1"></i>ผ่านเกณฑ์
                                                    </span>
                                                    <small class="text-muted">(≥{{ $attempt->exam->passing_percentage }}%)</small>
                                                @else
                                                    <span class="text-danger font-weight-bold small d-block">
                                                        <i class="fas fa-times-circle mr-1"></i>ไม่ผ่านเกณฑ์
                                                    </span>
                                                    <small class="text-muted">(<{{ $attempt->exam->passing_percentage }}%)</small>
                                                @endif
                                            </td>
                                            <td class="text-center align-middle">
                                                @if($attempt->status === 'completed')
                                                    <a href="{{ route('admin.grading.show', $attempt->id) }}" 
                                                       class="btn btn-xs btn-outline-primary shadow-xs font-weight-bold" 
                                                       title="เปิดดูใบบันทึกผลและคำตอบ">
                                                        <i class="fas fa-file-alt mr-1"></i>ดูผล
                                                    </a>
                                                @else
                                                    <span class="text-muted small">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-5">
                                                <i class="fas fa-file-signature fa-3x mb-3 text-secondary"></i>
                                                <h6 class="font-weight-bold text-dark">ยังไม่มีประวัติการเข้าสอบ</h6>
                                                <p class="small text-muted mb-0">นักศึกษาคนนี้ยังไม่เคยเข้าทำข้อสอบใดๆ ในระบบ</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- Teacher / Admin Profile View -->
        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm text-center py-4 border-0">
                    <div class="card-body">
                        <div class="mb-4">
                            @if($user->photo)
                                <img src="{{ asset('storage/' . $user->photo) }}" class="rounded-circle border shadow-xs" alt="User Image" style="width: 120px; height: 120px; object-fit: cover;" onerror="this.onerror=null; this.src='{{ asset('images/default-avatar.png') }}';">
                            @elseif($user->isAdmin())
                                <i class="fas fa-user-shield fa-6x text-danger"></i>
                            @elseif($user->isTeacher())
                                <i class="fas fa-user-tie fa-6x text-warning"></i>
                            @else
                                <img src="{{ asset('images/default-avatar.png') }}" class="rounded-circle border shadow-xs" alt="User Image" style="width: 120px; height: 120px; object-fit: cover;">
                            @endif
                        </div>
                        <h4 class="font-weight-bold text-dark mb-1">{{ $user->name }}</h4>
                        <p class="text-muted mb-3">
                            บทบาท: 
                            @if($user->isAdmin())
                                <span class="badge badge-danger">ผู้ดูแลระบบ (Admin)</span>
                                @if($user->academic_role)
                                    <br><span class="badge badge-info mt-1">{{ $user->academic_role_label }}</span>
                                @endif
                            @elseif($user->isTeacher())
                                <span class="badge badge-warning text-white">อาจารย์ (Teacher)</span>
                                @if($user->academic_role)
                                    <br><span class="badge badge-info mt-1">{{ $user->academic_role_label }}</span>
                                @endif
                            @else
                                <span class="badge badge-secondary">{{ $user->role }}</span>
                            @endif
                        </p>
                        <ul class="list-group list-group-unbordered mb-3 text-left small">
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <b>รหัสประจำตัวครู</b> <code class="font-weight-bold text-dark">{{ $user->teacher_code ?? '-' }}</code>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <b>หมวดวิชา / แผนกวิชา</b> 
                                <span>
                                    @if($user->department)
                                        <a href="{{ route('admin.departments.show', $user->department->id) }}" class="badge badge-info">
                                            <i class="fas fa-layer-group mr-1"></i>{{ $user->department->name }}
                                        </a>
                                    @else
                                        <span class="text-muted">ไม่ได้ระบุ</span>
                                    @endif
                                </span>
                            </li>
                            @if($user->isStaff() && $user->academic_role)
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                    <b>บทบาทหน้าที่เสริม</b> 
                                    <span>
                                        @if($user->academic_role === 'department_head')
                                            <span class="badge badge-success"><i class="fas fa-crown mr-1"></i>{{ $user->academic_role_label }}</span>
                                        @else
                                            <span class="badge badge-info">{{ $user->academic_role_label }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endif
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <b>อีเมล</b> <span class="text-dark">{{ $user->email }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <b>วันที่สร้างบัญชี</b> <span class="text-dark">{{ $user->created_at ? $user->created_at->format('d/m/Y H:i น.') : '-' }}</span>
                            </li>
                        </ul>

                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-outline-warning btn-block font-weight-bold shadow-xs">
                                <i class="fas fa-user-edit mr-1"></i> แก้ไขข้อมูลผู้ใช้งาน
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body py-5 text-center text-muted">
                        <i class="fas fa-info-circle fa-4x mb-3 text-secondary"></i>
                        <h4 class="font-weight-bold text-dark">ข้อมูลผู้ใช้งานระดับอาจารย์ / ผู้ดูแลระบบ</h4>
                        <p class="text-muted">บัญชีประเภทนี้ทำหน้าที่จัดการระบบ ออกข้อสอบ และประเมินผล จึงไม่มีข้อมูลประวัติการทำข้อสอบของนักเรียน</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
@stop

@section('css')
    <style>
        .shadow-xs {
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .text-warning-dark {
            color: #b45309 !important;
        }
        .nav-pills .nav-link.active {
            background-color: #1e293b !important;
            color: #ffffff !important;
        }
        .nav-pills .nav-link {
            color: #475569;
        }
        .nav-pills .nav-link:hover {
            color: #1e293b;
            background-color: #f1f5f9;
        }
    </style>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            var currentStatus = 'all';
            var currentSubject = '';

            // Custom DataTable filter function
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                if (settings.nTable.id !== 'attempts-table') return true;

                var rowNode = settings.aoData[dataIndex].nTr;
                if (!rowNode) return true;

                var $row = $(rowNode);
                var rowStatus = $row.data('status');
                var rowSubject = String($row.data('subject-id') || '');

                if (currentStatus !== 'all' && rowStatus !== currentStatus) {
                    return false;
                }
                if (currentSubject !== '' && rowSubject !== currentSubject) {
                    return false;
                }
                return true;
            });

            // Initialize Attempts DataTable
            var attemptsTable = null;
            if ($('#attempts-table').length) {
                attemptsTable = $('#attempts-table').DataTable({
                    "pageLength": 10,
                    "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "ทั้งหมด"]],
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Thai.json"
                    },
                    "responsive": true,
                    "autoWidth": false,
                    "order": []
                });
            }

            // Status Filter Click
            $('.filter-status-btn').on('click', function() {
                var targetStatus = $(this).data('status');
                var activeClass = $(this).data('active-class');

                // Reset all status buttons
                $('.filter-status-btn').each(function() {
                    var btnActiveClass = $(this).data('active-class');
                    if (btnActiveClass === 'btn-dark') {
                        $(this).removeClass('btn-dark').addClass('btn-outline-dark');
                    } else if (btnActiveClass === 'btn-success') {
                        $(this).removeClass('btn-success').addClass('btn-outline-success');
                    } else if (btnActiveClass === 'btn-danger') {
                        $(this).removeClass('btn-danger').addClass('btn-outline-danger');
                    } else if (btnActiveClass === 'btn-warning') {
                        $(this).removeClass('btn-warning').addClass('btn-outline-warning text-dark');
                    } else {
                        $(this).removeClass('btn-secondary').addClass('btn-outline-secondary');
                    }
                });

                // Activate selected button
                $(this).removeClass('btn-outline-dark btn-outline-success btn-outline-danger btn-outline-warning btn-outline-secondary text-dark')
                       .addClass(activeClass);

                currentStatus = targetStatus;
                if (attemptsTable) {
                    attemptsTable.draw();
                }
            });

            // Subject Filter Change
            $('#filter-subject').on('change', function() {
                currentSubject = $(this).val();
                if (attemptsTable) {
                    attemptsTable.draw();
                }
            });

            // Reset Filters Button
            $('#btn-reset-filters').on('click', function() {
                currentStatus = 'all';
                currentSubject = '';
                $('#filter-subject').val('');

                // Reset button states
                $('.filter-status-btn').each(function() {
                    var btnStatus = $(this).data('status');
                    var btnActiveClass = $(this).data('active-class');
                    if (btnStatus === 'all') {
                        $(this).removeClass('btn-outline-dark btn-outline-secondary').addClass('btn-dark');
                    } else if (btnActiveClass === 'btn-success') {
                        $(this).removeClass('btn-success').addClass('btn-outline-success');
                    } else if (btnActiveClass === 'btn-danger') {
                        $(this).removeClass('btn-danger').addClass('btn-outline-danger');
                    } else if (btnActiveClass === 'btn-warning') {
                        $(this).removeClass('btn-warning').addClass('btn-outline-warning text-dark');
                    } else {
                        $(this).removeClass('btn-secondary').addClass('btn-outline-secondary');
                    }
                });

                if (attemptsTable) {
                    attemptsTable.search('').draw();
                }
            });

            // SweetAlert Flash Success
            @if(session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ!',
                    text: '{{ session('success') }}',
                    confirmButtonText: 'ตกลง',
                    timer: 3000,
                    timerProgressBar: true
                });
            @endif

            // SweetAlert Flash Errors
            @if($errors->any())
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด!',
                    html: '{!! implode("<br>", $errors->all()) !!}',
                    confirmButtonText: 'ตกลง'
                });
            @endif
        });
    </script>
@stop
