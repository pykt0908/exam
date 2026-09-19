@extends('adminlte::page')

@section('title', 'ตรวจข้อสอบ')

@section('content_header')
@php
    $gradedPercentage = $totalCount > 0 ? round(($gradedCount / $totalCount) * 100) : 100;
    $currentStatus = request('grading_status', ($pendingCount > 0 ? 'pending_grading' : 'all'));
    $firstPending = $attempts->first(fn($a) => $a->isPendingGrading());
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center pb-3 border-bottom mb-3">
    <div>
        <h1 class="font-weight-bold text-dark mb-1 h3">
            ตรวจข้อสอบ
        </h1>
        <div class="text-secondary mt-2 d-flex flex-wrap align-items-center summary-text-line">
            <span class="mr-3">
                รอตรวจ: <strong
                    class="text-dark font-weight-bold text-lg-number">{{ number_format($pendingCount) }}</strong> ฉบับ
            </span>
            <span class="text-muted mr-3 d-none d-sm-inline font-weight-bold">|</span>
            <span class="mr-3">
                ตรวจเสร็จสิ้นแล้ว: <strong
                    class="text-dark font-weight-bold text-lg-number">{{ number_format($gradedCount) }}</strong> ฉบับ
                ({{ $gradedPercentage }}%)
            </span>
            <span class="text-muted mr-3 d-none d-sm-inline font-weight-bold">|</span>
            <span>
                ส่งข้อสอบทั้งหมด: <strong
                    class="text-dark font-weight-bold text-lg-number">{{ number_format($totalCount) }}</strong> ฉบับ
            </span>
        </div>
    </div>
    <div class="mt-3 mt-md-0 d-flex align-items-center">
        <ol class="breadcrumb bg-transparent p-0 m-0 text-md">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">หน้าหลัก</a></li>
            <li class="breadcrumb-item active">ตรวจข้อสอบ</li>
        </ol>
    </div>
</div>
@stop

@section('content')

<!-- 1. Search & Filter Card -->
<div class="card card-outline card-primary shadow-sm mb-4 border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="card-title font-weight-bold text-dark text-md mb-0">
            ตัวกรองและค้นหากระดาษคำตอบ
        </h3>
        <!-- Quick Status Switcher Tabs -->
        <div class="card-tools">
            <div class="btn-group">
                <a href="{{ route('admin.grading.index', array_merge(request()->except('grading_status', 'page'), ['grading_status' => 'all'])) }}"
                    class="btn btn-sm px-3 py-2 text-md {{ $currentStatus === 'all' ? 'btn-primary font-weight-bold' : 'btn-outline-secondary' }}">
                    ทั้งหมด ({{ $totalCount }})
                </a>
                <a href="{{ route('admin.grading.index', array_merge(request()->except('grading_status', 'page'), ['grading_status' => 'pending_grading'])) }}"
                    class="btn btn-sm px-3 py-2 text-md {{ $currentStatus === 'pending_grading' ? 'btn-warning text-dark font-weight-bold' : 'btn-outline-secondary' }}">
                    รอตรวจ ({{ $pendingCount }})
                </a>
                <a href="{{ route('admin.grading.index', array_merge(request()->except('grading_status', 'page'), ['grading_status' => 'graded'])) }}"
                    class="btn btn-sm px-3 py-2 text-md {{ $currentStatus === 'graded' ? 'btn-success font-weight-bold' : 'btn-outline-secondary' }}">
                    ตรวจแล้ว ({{ $gradedCount }})
                </a>
            </div>
        </div>
    </div>
    <div class="card-body bg-light p-3">
        <form action="{{ route('admin.grading.index') }}" method="GET" id="filterForm">
            <input type="hidden" name="grading_status" value="{{ $currentStatus }}">
            <div class="row align-items-end">
                <!-- 1. รายวิชา -->
                <div class="col-lg-3 col-md-6 mb-2">
                    <label class="font-weight-bold text-dark text-md mb-1">
                        รายวิชา
                    </label>
                    <select name="subject_id" id="filter_subject_id" class="form-control bg-white text-md">
                        <option value="">-- เลือกรายวิชา --</option>
                        @foreach($subjects as $subj)
                            <option value="{{ $subj->id }}" {{ (string)($subjectId ?? '') === (string)$subj->id ? 'selected' : '' }}>
                                [{{ $subj->code }}] {{ $subj->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- 2. เลือกข้อสอบในวิชา (ปลดล็อคเมื่อเลือกรายวิชาแล้ว) -->
                <div class="col-lg-3 col-md-6 mb-2">
                    <label class="font-weight-bold text-dark text-md mb-1">
                        เลือกข้อสอบในวิชา
                    </label>
                    <select name="exam_id" id="filter_exam_id" class="form-control bg-white text-md" {{ empty($subjectId) ? 'disabled' : '' }}>
                        @if(empty($subjectId))
                            <option value="">-- กรุณาเลือกรายวิชาก่อน --</option>
                        @else
                            <option value="">-- เลือกชุดข้อสอบ (มี {{ $exams->count() }} ชุด) --</option>
                            @foreach($exams as $exam)
                                <option value="{{ $exam->id }}" {{ (string)($examId ?? '') === (string)$exam->id ? 'selected' : '' }}>
                                    {{ $exam->title }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- 3. ห้องเรียนที่มีสิทธิ์สอบ (ปลดล็อคเมื่อเลือกข้อสอบแล้ว) -->
                <div class="col-lg-3 col-md-6 mb-2">
                    <label class="font-weight-bold text-dark text-md mb-1">
                        กลุ่มเรียน
                    </label>
                    <select name="classroom_id" id="filter_classroom_id" class="form-control bg-white text-md" {{ empty($examId) ? 'disabled' : '' }}>
                        @if(empty($examId))
                            <option value="">-- กรุณาเลือกข้อสอบก่อน --</option>
                        @else
                            <option value="">-- ทุกห้องเรียนที่มีสิทธิ์สอบ ({{ $classrooms->count() }} ห้อง) --</option>
                            @foreach($classrooms as $room)
                                <option value="{{ $room->id }}" {{ (string)($classroomId ?? '') === (string)$room->id ? 'selected' : '' }}>
                                    {{ $room->name }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- 4. ค้นหาผู้สอบ -->
                <div class="col-lg-3 col-md-6 mb-2">
                    <label class="font-weight-bold text-dark text-md mb-1">
                        ค้นหา
                    </label>
                    <div class="input-group">
                        <input type="text" name="q" id="filter_q" class="form-control bg-white text-md"
                            placeholder="ระบุชื่อ หรือ รหัสนักศึกษา..." value="{{ request('q') }}">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary px-3 text-md" title="ค้นหา">
                                <i class="fas fa-search"></i>
                            </button>
                            @if(!empty($subjectId) || !empty($examId) || !empty($classroomId) || request('q'))
                                <a href="{{ route('admin.grading.index') }}"
                                    class="btn btn-outline-secondary text-md" title="ล้างตัวกรองทั้งหมด">
                                    <i class="fas fa-undo"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                @if(!empty($subjectId) || !empty($examId) || !empty($classroomId) || request('q'))
                    <div class="col-12 mt-2 pt-2 border-top d-flex flex-wrap align-items-center text-sm">
                        <span class="text-muted mr-2"><i class="fas fa-filter mr-1"></i>ตัวกรองที่เลือก:</span>
                        @if(!empty($subjectId))
                            @php $activeSub = $subjects->firstWhere('id', $subjectId); @endphp
                            @if($activeSub)
                                <span class="badge badge-primary mr-2 px-2 py-1 font-weight-normal">
                                    วิชา: [{{ $activeSub->code }}] {{ $activeSub->name }}
                                </span>
                            @endif
                        @endif
                        @if(!empty($examId))
                            @php $activeExam = $exams->firstWhere('id', $examId); @endphp
                            @if($activeExam)
                                <span class="badge badge-info mr-2 px-2 py-1 font-weight-normal">
                                    ข้อสอบ: {{ $activeExam->title }}
                                </span>
                            @endif
                        @endif
                        @if(!empty($classroomId))
                            @php $activeRoom = $classrooms->firstWhere('id', $classroomId); @endphp
                            @if($activeRoom)
                                <span class="badge badge-secondary mr-2 px-2 py-1 font-weight-normal">
                                    ห้อง: {{ $activeRoom->name }}
                                </span>
                            @endif
                        @endif
                        @if(request('q'))
                            <span class="badge badge-light border mr-2 px-2 py-1 font-weight-normal">
                                ค้นหา: "{{ request('q') }}"
                            </span>
                        @endif
                        <a href="{{ route('admin.grading.index') }}"
                            class="text-danger ml-auto font-weight-bold text-sm">
                            <i class="fas fa-times-circle mr-1"></i>ล้างตัวกรองทั้งหมด
                        </a>
                    </div>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- 2. Attempt List Table -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h3 class="card-title font-weight-bold text-dark text-md mb-0">
            รายการกระดาษคำตอบนักศึกษา
            <span class="badge badge-light border ml-2 text-sm">{{ $attempts->total() }} รายการ</span>
        </h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead
                    class="bg-light border-bottom text-secondary text-uppercase font-weight-bold table-header-custom">
                    <tr>
                        <th class="text-center py-3" style="width: 60px;">ลำดับ</th>
                        <th class="py-3">ชื่อ-นามสกุล / รหัสนักศึกษา</th>
                        <th class="py-3">ห้องเรียน</th>
                        <th class="py-3">ชุดข้อสอบ / วิชา</th>
                        <th class="text-center py-3">คะแนนที่ได้</th>
                        <th class="text-center py-3">วัน-เวลาที่ส่ง</th>
                        <th class="text-center py-3" style="width: 140px;">สถานะ</th>
                        <th class="text-center py-3" style="width: 150px;">การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attempts as $index => $attempt)
                            @php
                                $totalExamScore = (float) ($attempt->exam->total_score ?? $attempt->exam->questions()->sum('score'));
                                $isPending = $attempt->isPendingGrading();
                                $scorePercent = $totalExamScore > 0 ? round((($attempt->score ?? 0) / $totalExamScore) * 100) : 0;
                                $isScaled = $attempt->isScaled() || ($attempt->raw_score !== null && $attempt->total_raw_score > 0 && abs($attempt->total_raw_score - $totalExamScore) > 0.001);
                            @endphp
                            <tr class="{{ $isPending ? 'row-pending-grading' : '' }}">
                                <td class="text-center align-middle text-muted font-weight-bold text-md">
                                    {{ $attempts->firstItem() + $index }}
                                </td>
                                <td class="align-middle">
                                    <div class="font-weight-bold text-dark text-md">{{ $attempt->user->name }}</div>
                                    <div class="text-muted text-md">
                                        รหัสนักศึกษา: {{ $attempt->user->student_code ?? '-' }}
                                    </div>
                                </td>
                                <td class="align-middle text-dark text-md">
                                    {{ $attempt->user->classroom->name ?? '-' }}
                                </td>
                                <td class="align-middle">
                                    <div class="font-weight-bold text-dark text-md">{{ $attempt->exam->title }}</div>
                                    <div class="text-muted text-sm">
                                        {{ $attempt->exam->subject->code ?? '' }} {{ $attempt->exam->subject->name ?? '' }}
                                    </div>
                                </td>
                                <td class="text-center align-middle">
                                    <div
                                        class="font-weight-bold text-md {{ $isPending ? 'text-warning-dark' : 'text-primary' }}">
                                        {{ number_format($attempt->score ?? 0, 2) }} / {{ number_format($totalExamScore, 2) }}
                                    </div>
                                    @if(!$isPending)
                                        <div class="text-muted text-xs">
                                            @if($isScaled && $attempt->raw_score !== null)
                                                ({{ floatval($attempt->raw_score) }}/{{ floatval($attempt->total_raw_score) }} ข้อ
                                                &bull; {{ $scorePercent }}%)
                                            @else
                                                ({{ $scorePercent }}%)
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center align-middle text-md text-secondary">
                                    {{ $attempt->completed_at ? $attempt->completed_at->format('d/m/Y H:i น.') : '-' }}
                                </td>
                                <td class="text-center align-middle">
                                    @if($isPending)
                                        <span class="badge badge-warning px-3 py-2 text-sm font-weight-bold">
                                            รอตรวจ
                                        </span>
                                    @else
                                        <span class="badge badge-success px-3 py-2 text-sm font-weight-bold">
                                            ตรวจแล้ว
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center align-middle">
                                    <a href="{{ route('admin.grading.show', $attempt->id) }}"
                                        class="btn btn-sm {{ $isPending ? 'btn-warning text-dark font-weight-bold shadow-xs' : 'btn-outline-primary' }} px-3 py-2 text-md">
                                        {{ $isPending ? 'ตรวจข้อสอบ' : 'ดู / ปรับคะแนน' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <div class="py-4">
                                        <h5 class="font-weight-bold text-dark">ไม่พบรายการกระดาษคำตอบ</h5>
                                        <p class="text-muted text-md mb-0">ไม่มีข้อมูลตามเงื่อนไขตัวกรองที่คุณเลือก
                                            กรุณาปรับเปลี่ยนเงื่อนไขการค้นหาหรือเลือกห้องเรียนอื่น</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($attempts->hasPages())
        <div class="card-footer bg-white border-top py-3 d-flex flex-column flex-md-row justify-content-between align-items-center">
            <div class="text-muted text-md mb-2 mb-md-0">
                แสดง {{ $attempts->firstItem() }} ถึง {{ $attempts->lastItem() }} จาก {{ $attempts->total() }} รายการ
            </div>
            <div class="pagination-container">
                {{ $attempts->appends(request()->query())->links('pagination::bootstrap-4') }}
            </div>
        </div>
    @endif
</div>
@stop

@section('css')
<style>
    .pagination-container .pagination {
        margin-bottom: 0 !important;
    }

    .pagination-container .page-link {
        padding: 0.4rem 0.8rem;
        font-size: 0.95rem;
        font-weight: 600;
        color: #007bff;
        border-radius: 4px;
        margin: 0 2px;
    }

    .pagination-container .page-item.active .page-link {
        background-color: #007bff;
        border-color: #007bff;
        color: #ffffff;
    }

    .pagination-container .page-item.disabled .page-link {
        color: #6c757d;
        background-color: #f8f9fa;
    }

    .summary-text-line {
        font-size: 1.05rem;
        line-height: 1.6;
    }

    .text-lg-number {
        font-size: 1.2rem;
    }

    .text-md {
        font-size: 0.98rem !important;
    }

    .table-header-custom th {
        font-size: 0.95rem;
        letter-spacing: 0.3px;
    }

    .text-warning-dark {
        color: #b45309 !important;
    }

    .row-pending-grading {
        background-color: #fffdf5 !important;
    }

    .row-pending-grading:hover {
        background-color: #fef9e7 !important;
    }

    .shadow-xs {
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }
</style>
@stop

@section('js')
<script>
    $(document).ready(function () {
        // เมื่อเปลี่ยนรายวิชา ให้รีเซ็ตข้อสอบและห้องเรียน แล้วส่งฟอร์มทันที
        $('#filter_subject_id').on('change', function () {
            $('#filter_exam_id').val('');
            $('#filter_classroom_id').val('');
            $('#filterForm').submit();
        });

        // เมื่อเลือกข้อสอบในวิชา ให้รีเซ็ตห้องเรียน แล้วส่งฟอร์ม
        $('#filter_exam_id').on('change', function () {
            $('#filter_classroom_id').val('');
            $('#filterForm').submit();
        });

        // เมื่อเลือกห้องเรียนที่มีสิทธิ์สอบ ให้ส่งฟอร์มทันที
        $('#filter_classroom_id').on('change', function () {
            $('#filterForm').submit();
        });
    });
</script>
@stop