@extends('adminlte::page')

@section('title', 'จัดการรายวิชาและข้อสอบ')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="text-dark font-weight-bold mb-1">รายวิชาและข้อสอบ</h1>
        <p class="text-muted text-sm mb-0">จัดการข้อมูลรายวิชา ครูผู้สอน นักศึกษา และข้อสอบในแต่ละวิชา</p>
    </div>
    <div class="mt-2 mt-md-0">
        @if(auth()->user()->isStaff())
            <a href="{{ route('admin.exams.create', array_filter(['subject_id' => request('subject_id')])) }}"
                class="btn btn-success font-weight-bold shadow-sm mr-2">
                <i class="fas fa-plus mr-1"></i>สร้างข้อสอบใหม่
            </a>
            <button type="button" class="btn btn-primary font-weight-bold shadow-sm" data-toggle="modal"
                data-target="#addSubjectModal">
                <i class="fas fa-book-medical mr-1"></i>เพิ่มรายวิชาใหม่
            </button>
        @endif
    </div>
</div>
@stop

@section('content')
<!-- Filter Card -->
<div class="card shadow-sm mt-3 mb-3">
    <div class="card-header bg-light py-2">
        <h3 class="card-title font-weight-bold text-dark mb-0">
            ตัวกรองค้นหา
        </h3>
    </div>
    <div class="card-body p-3">
        <form action="{{ route('admin.subjects.index') }}" method="GET" id="subjectFilterForm">
            <div class="row align-items-end">
                <!-- 1. หมวดวิชา / สาขาวิชา -->
                <div class="col-lg-3 col-md-6 col-sm-12 mb-2">
                    <label class="font-weight-bold text-dark text-md mb-1">
                        หมวดวิชา / สาขาวิชา
                    </label>
                    <select name="department_id" id="filter_department_id" class="form-control"
                        onchange="filterByDepartment();">
                        <option value="">-- ทุกหมวดวิชา / สาขาวิชา --</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ (string) ($selectedDepartmentId ?? request('department_id')) === (string) $dept->id ? 'selected' : '' }}>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- 2. ครูผู้สอน (Admin only) -->
                @if(auth()->user()->isAdmin())
                    <div class="col-lg-3 col-md-6 col-sm-12 mb-2">
                        <label class="font-weight-bold text-dark text-md mb-1">
                            ครูผู้สอน
                        </label>
                        <select name="teacher_id" id="filter_teacher_id" class="form-control" onchange="filterByTeacher();">
                            <option value="">-- {{ !empty($selectedDepartmentId) ? 'ครูทุกคนในหมวดนี้' : 'คุณครูทุกคน' }} --
                            </option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}" {{ (string) ($selectedTeacherId ?? request('teacher_id')) === (string) $teacher->id ? 'selected' : '' }}>
                                    {{ $teacher->name }} {{ $teacher->teacher_code ? '(' . $teacher->teacher_code . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <!-- 3. รายวิชา -->
                <div class="col-lg-{{ auth()->user()->isAdmin() ? '3' : '3' }} col-md-6 col-sm-12 mb-2">
                    <label class="font-weight-bold text-dark text-md mb-1">
                        รายวิชา
                    </label>
                    <select name="subject_id" id="filter_subject_id" class="form-control" onchange="filterBySubject();">
                        <option value="">--
                            {{ request('teacher_id') ? 'ทุกวิชาของครูท่านนี้' : (!empty($selectedDepartmentId) ? 'ทุกวิชาในหมวดนี้' : 'ทุกรายวิชา') }}
                            --
                        </option>
                        @foreach($subjects as $subj)
                            <option value="{{ $subj->id }}" {{ (string) ($selectedSubjectId ?? request('subject_id')) === (string) $subj->id ? 'selected' : '' }}>
                                [{{ $subj->code }}] {{ $subj->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- 4. สถานะข้อสอบ -->
                <div class="col-lg-3 col-md-6 col-sm-12 mb-2">
                    <label class="font-weight-bold text-dark text-md mb-1">
                        สถานะข้อสอบ
                    </label>
                    <select name="exam_status" id="filter_exam_status" class="form-control"
                        onchange="$('#subjectFilterForm').submit();">
                        <option value="">-- สถานะข้อสอบทั้งหมด --</option>
                        <option value="no_exams" {{ (string) request('exam_status') === 'no_exams' ? 'selected' : '' }}>
                            ⚠️ ยังไม่สร้างข้อสอบ
                        </option>
                        <option value="has_exams" {{ (string) request('exam_status') === 'has_exams' ? 'selected' : '' }}>
                            ✅ มีข้อสอบแล้ว
                        </option>
                    </select>
                </div>

                <!-- 5. ค้นหาข้อสอบ / รายวิชา -->
                <div class="col-lg-12 col-md-12 col-sm-12 mb-2 mt-1">
                    <label class="font-weight-bold text-dark text-md mb-1">
                        ค้นหา
                    </label>
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" value="{{ request('q') }}"
                            placeholder="ค้นหารหัสวิชา, ชื่อรายวิชา หรือชื่อข้อสอบ...">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary font-weight-bold" title="ค้นหา">
                                <i class="fas fa-search mr-1"></i>ค้นหา
                            </button>
                            @if(request('department_id') || request('teacher_id') || request('subject_id') || request('exam_status') || request('q'))
                                <a href="{{ route('admin.subjects.index') }}" class="btn btn-outline-secondary"
                                    title="ล้างค่าตัวกรอง">
                                    <i class="fas fa-undo mr-1"></i>ล้างตัวกรอง
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Badges -->
            @if(request('department_id') || request('teacher_id') || request('subject_id') || request('exam_status') || request('q'))
                <div class="row mt-2 pt-2 border-top">
                    <div class="col-12 d-flex flex-wrap align-items-center text-sm">
                        <span class="text-muted mr-2"><i class="fas fa-filter mr-1"></i>ตัวกรองที่เลือก:</span>
                        @if(request('department_id'))
                            @php $filterDept = $departments->firstWhere('id', request('department_id')); @endphp
                            @if($filterDept)
                                <span class="badge badge-secondary mr-2 px-2 py-1 font-weight-normal">
                                    หมวดวิชา: {{ $filterDept->name }}
                                </span>
                            @endif
                        @endif
                        @if(request('teacher_id'))
                            @php $filterTeach = $teachers->firstWhere('id', request('teacher_id')) ?? \App\Models\User::find(request('teacher_id')); @endphp
                            @if($filterTeach)
                                <span class="badge badge-info mr-2 px-2 py-1 font-weight-normal">
                                    ครูผู้สอน: {{ $filterTeach->name }}
                                </span>
                            @endif
                        @endif
                        @if(request('subject_id'))
                            @php $filterSubj = $subjects->firstWhere('id', request('subject_id')) ?? \App\Models\Subject::find(request('subject_id')); @endphp
                            @if($filterSubj)
                                <span class="badge badge-primary mr-2 px-2 py-1 font-weight-normal">
                                    รายวิชา: [{{ $filterSubj->code }}] {{ $filterSubj->name }}
                                </span>
                            @endif
                        @endif
                        @if(request('exam_status') === 'no_exams')
                            <span class="badge badge-warning text-dark mr-2 px-2 py-1 font-weight-normal">
                                <i class="fas fa-exclamation-triangle mr-1"></i>ยังไม่สร้างข้อสอบ
                            </span>
                        @elseif(request('exam_status') === 'has_exams')
                            <span class="badge badge-success mr-2 px-2 py-1 font-weight-normal">
                                <i class="fas fa-check-circle mr-1"></i>มีข้อสอบแล้ว
                            </span>
                        @endif
                        @if(request('q'))
                            <span class="badge badge-light border mr-2 px-2 py-1 font-weight-normal">
                                ค้นหา: "{{ request('q') }}"
                            </span>
                        @endif
                        <a href="{{ route('admin.subjects.index') }}" class="text-danger ml-auto font-weight-bold text-sm">
                            <i class="fas fa-times-circle mr-1"></i>ล้างตัวกรองทั้งหมด
                        </a>
                    </div>
                </div>
            @endif
        </form>
    </div>
</div>

<!-- Active Subject Banner if subject_id is selected -->
@if(request('subject_id'))
    @php $activeSubj = $subjects->firstWhere('id', request('subject_id')) ?? \App\Models\Subject::find(request('subject_id')); @endphp
    @if($activeSubj)
        <div class="card card-outline card-primary shadow-sm mb-3">
            <div class="card-body py-2 px-3 d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <span class="badge badge-primary font-weight-bold mr-2 text-md px-2 py-1">{{ $activeSubj->code }}</span>
                    <strong class="text-dark h6 mb-0">{{ $activeSubj->name }}</strong>
                    <span
                        class="text-muted ml-2">({{ $activeSubj->department ? $activeSubj->department->name : 'ไม่มีหมวด' }})</span>
                </div>
                <div class="mt-2 mt-md-0 d-flex flex-wrap align-items-center">
                    <a href="{{ route('admin.subjects.students.index', $activeSubj->id) }}"
                        class="btn btn-sm btn-info font-weight-bold shadow-xs mr-1">
                        <i class="fas fa-user-graduate mr-1"></i>นักศึกษา
                        ({{ $activeSubj->students_count ?? $activeSubj->students->count() }})
                    </a>
                    @if(auth()->user()->isAdmin() || $activeSubj->teachers->contains(auth()->id()))
                        <button type="button" class="btn btn-sm btn-warning font-weight-bold text-white shadow-xs mr-1"
                            data-toggle="modal" data-target="#editSubjectModal{{ $activeSubj->id }}">
                            <i class="fas fa-edit mr-1"></i>แก้ไขวิชา
                        </button>
                        <form action="{{ route('admin.subjects.destroy', $activeSubj->id) }}" method="post"
                            class="d-inline confirm-delete-subject mr-1"
                            data-subject-name="[{{ $activeSubj->code }}] {{ $activeSubj->name }}"
                            data-exams-count="{{ $activeSubj->exams_count ?? $activeSubj->exams->count() }}">
                            @csrf
                            @method('delete')
                            <button type="submit" class="btn btn-sm btn-danger font-weight-bold shadow-xs" title="ลบรายวิชานี้">
                                <i class="fas fa-trash-alt mr-1"></i>ลบวิชา
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('admin.exams.create', ['subject_id' => $activeSubj->id]) }}"
                        class="btn btn-sm btn-success font-weight-bold shadow-xs">
                        <i class="fas fa-plus mr-1"></i>สร้างข้อสอบในวิชานี้
                    </a>
                </div>
            </div>
        </div>
    @endif
@endif

@php
    $noExamsCount = $subjects->where('exams_count', 0)->count();
    $hasExamsCount = $subjects->where('exams_count', '>', 0)->count();
@endphp

<!-- Navigation Tabs / Pills -->
<ul class="nav nav-pills mb-3" id="subjectExamsTab" role="tablist">
    <li class="nav-item">
        <a class="nav-link {{ request('tab', 'subjects') === 'subjects' ? 'active' : '' }} font-weight-bold shadow-xs"
            id="tab-subjects-btn" data-toggle="pill" href="#tab-subjects" role="tab" aria-controls="tab-subjects"
            aria-selected="{{ request('tab', 'subjects') === 'subjects' ? 'true' : 'false' }}">
            รายวิชาทั้งหมด ({{ $subjects->count() }} วิชา)
            @if($noExamsCount > 0)
                <span class="badge badge-warning text-dark ml-1 font-weight-bold"
                    title="{{ $noExamsCount }} รายวิชาที่ยังไม่ได้สร้างข้อสอบ">
                    <i class="fas fa-exclamation-circle mr-1"></i>{{ $noExamsCount }} ยังไม่มีข้อสอบ
                </span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request('tab') === 'exams' ? 'active' : '' }} font-weight-bold shadow-xs"
            id="tab-exams-btn" data-toggle="pill" href="#tab-exams" role="tab" aria-controls="tab-exams"
            aria-selected="{{ request('tab') === 'exams' ? 'true' : 'false' }}">
            รายการข้อสอบ ({{ $exams->count() }} ชุด)
        </a>
    </li>
</ul>

<div class="tab-content" id="subjectExamsTabContent">
    <!-- ========================================================================= -->
    <!-- TAB 1: ตารางรายวิชาทั้งหมด                                                -->
    <!-- ========================================================================= -->
    <div class="tab-pane fade {{ request('tab', 'subjects') === 'subjects' ? 'show active' : '' }}" id="tab-subjects"
        role="tabpanel" aria-labelledby="tab-subjects-btn">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="card-title font-weight-bold text-dark mb-0">
                    ตารางรายวิชา
                </h3>
                <div class="d-flex align-items-center mt-1 mt-md-0">
                    @if($noExamsCount > 0)
                        <a href="{{ route('admin.subjects.index', array_merge(request()->query(), ['exam_status' => 'no_exams'])) }}"
                            class="badge badge-warning text-dark p-2 font-weight-bold mr-2 text-decoration-none shadow-xs"
                            title="คลิกเพื่อกรองดูเฉพาะวิชาที่ยังไม่มีข้อสอบ">
                            <i class="fas fa-exclamation-triangle mr-1"></i>ดูวิชาที่ยังไม่สร้างข้อสอบ ({{ $noExamsCount }})
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table id="subjects-table" class="table table-bordered table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 4%" class="text-center align-middle">#</th>
                                <th style="width: 14%" class="align-middle">รหัสวิชา</th>
                                <th style="width: 26%" class="align-middle">ชื่อรายวิชา</th>
                                <th style="width: 15%" class="align-middle">หมวดวิชา / สาขา</th>
                                <th style="width: 13%" class="align-middle">ครูผู้สอน</th>
                                <th style="width: 8%" class="text-center align-middle">นักศึกษา</th>
                                <th style="width: 10%" class="text-center align-middle">ข้อสอบในวิชา</th>
                                <th style="width: 10%" class="text-center align-middle" style="white-space: nowrap;">
                                    การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($subjects as $index => $subject)
                                <tr
                                    class="{{ (string) $subject->id === (string) request('subject_id') ? 'table-primary font-weight-bold' : '' }}">
                                    <td class="text-center font-weight-bold align-middle">{{ $index + 1 }}</td>
                                    <td class="align-middle">
                                        <span
                                            class="text-dark font-weight-bold px-2 py-1 text-md">{{ $subject->code }}</span>
                                    </td>
                                    <td class="align-middle">
                                        <div class="font-weight-bold text-dark" style="font-size: 0.98rem;">
                                            {{ $subject->name }}
                                        </div>
                                    </td>
                                    <td class="align-middle text-secondary">
                                        {{ $subject->department ? $subject->department->name : '-' }}
                                    </td>
                                    <td class="align-middle">
                                        @if($subject->teachers->count() > 0)
                                            <div class="text-dark small">
                                                {{ $subject->teachers->pluck('name')->join(', ') }}
                                            </div>
                                        @else
                                            <span class="text-muted small">ยังไม่มีผู้สอน</span>
                                        @endif
                                    </td>
                                    <td class="text-center align-middle">
                                        <a href="{{ route('admin.subjects.students.index', $subject->id) }}"
                                            class="btn btn-xs btn-outline-info font-weight-bold shadow-xs"
                                            title="จัดการนักศึกษาในรายวิชา">
                                            <i class="fas fa-user-graduate mr-1"></i>{{ $subject->students_count }} คน
                                        </a>
                                    </td>
                                    <td class="text-center align-middle">
                                        @if($subject->exams_count == 0)
                                            <span class="badge badge-warning text-dark font-weight-bold px-2 py-1"
                                                title="ยังไม่มีข้อสอบในวิชานี้">
                                                <i class="fas fa-exclamation-circle mr-1"></i>ยังไม่สร้างข้อสอบ
                                            </span>
                                            <div class="mt-1">
                                                <a href="{{ route('admin.exams.create', ['subject_id' => $subject->id]) }}"
                                                    class="btn btn-xs btn-success font-weight-bold shadow-xs"
                                                    title="สร้างข้อสอบแรกในวิชานี้">
                                                    <i class="fas fa-plus mr-1"></i>สร้างข้อสอบ
                                                </a>
                                            </div>
                                        @else
                                            <a href="{{ route('admin.exams.index', ['subject_id' => $subject->id]) }}"
                                                class="btn btn-xs btn-outline-primary font-weight-bold shadow-xs"
                                                title="ดูข้อสอบทั้งหมดในวิชานี้">
                                                <i class="fas fa-copy mr-1"></i>{{ $subject->exams_count }} ชุด
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-center align-middle" style="white-space: nowrap;">
                                        <!-- ปุ่มสร้างข้อสอบ -->
                                        <a href="{{ route('admin.exams.create', ['subject_id' => $subject->id]) }}"
                                            class="btn btn-xs btn-outline-success font-weight-bold shadow-xs mr-1"
                                            title="สร้างข้อสอบในวิชานี้">
                                            <i class="fas fa-plus"></i> ข้อสอบ
                                        </a>



                                        <!-- ปุ่มแก้ไขวิชา -->
                                        @if(auth()->user()->isAdmin() || $subject->teachers->contains(auth()->id()))
                                            <button type="button"
                                                class="btn btn-xs btn-outline-warning font-weight-bold shadow-xs mr-1"
                                                data-toggle="modal" data-target="#editSubjectModal{{ $subject->id }}"
                                                title="แก้ไขรายวิชา">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        @endif

                                        <!-- ปุ่มลบรายวิชา -->
                                        @if(auth()->user()->isAdmin() || $subject->teachers->contains(auth()->id()))
                                            <form action="{{ route('admin.subjects.destroy', $subject->id) }}" method="post"
                                                class="d-inline confirm-delete-subject"
                                                data-subject-name="[{{ $subject->code }}] {{ $subject->name }}"
                                                data-exams-count="{{ $subject->exams_count }}">
                                                @csrf
                                                @method('delete')
                                                <button type="submit"
                                                    class="btn btn-xs btn-outline-danger font-weight-bold shadow-xs"
                                                    title="ลบรายวิชานี้">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="fas fa-book-open fa-3x d-block mb-3 text-secondary"></i>
                                        @if(request('department_id') || request('teacher_id') || request('subject_id') || request('exam_status') || request('q'))
                                            <h6 class="font-weight-bold text-dark mb-1">ไม่พบรายวิชาที่ตรงกับเงื่อนไขการค้นหา
                                            </h6>
                                            <p class="text-muted text-sm mb-3">
                                                ลองเปลี่ยนหรือล้างเงื่อนไขตัวกรองเพื่อดูรายวิชาอื่น</p>
                                            <a href="{{ route('admin.subjects.index') }}"
                                                class="btn btn-sm btn-outline-primary font-weight-bold">
                                                <i class="fas fa-undo mr-1"></i>ล้างตัวกรองทั้งหมด
                                            </a>
                                        @else
                                            <h6 class="font-weight-bold text-dark mb-1">ไม่มีข้อมูลรายวิชาในระบบ</h6>
                                            <p class="text-muted text-sm">คลิกปุ่ม "เพิ่มรายวิชาใหม่"
                                                ด้านบนเพื่อเริ่มต้นสร้างรายวิชา</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 2: ตารางรายการข้อสอบ                                                  -->
    <!-- ========================================================================= -->
    <div class="tab-pane fade {{ request('tab') === 'exams' ? 'show active' : '' }}" id="tab-exams" role="tabpanel"
        aria-labelledby="tab-exams-btn">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="card-title font-weight-bold text-dark mb-0">
                    รายการข้อสอบ ({{ $exams->count() }} ชุด)
                </h3>

            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table id="exams-table" class="table table-bordered table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 4%" class="text-center align-middle">#</th>
                                <th style="width: 20%" class="align-middle">รายวิชา</th>
                                <th style="width: 22%" class="align-middle">ชื่อข้อสอบ</th>
                                <th style="width: 10%" class="align-middle">ครูผู้สอน</th>
                                <th style="width: 8%" class="align-middle">เวลา</th>
                                <th style="width: 11%" class="text-center align-middle">คะแนน / เกณฑ์ผ่าน</th>
                                <th style="width: 7%" class="text-center align-middle">จำนวนข้อ</th>
                                <th style="width: 8%" class="text-center align-middle">สถานะ</th>
                                <th style="width: 10%" class="text-center align-middle" style="white-space: nowrap;">
                                    การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($exams as $index => $exam)
                                <tr>
                                    <td class="text-center font-weight-bold align-middle">{{ $index + 1 }}</td>
                                    <td class="align-middle">
                                        @if($exam->subject)
                                            <div class="font-weight-bold text-dark">
                                                [{{ $exam->subject->code }}] {{ $exam->subject->name }}
                                            </div>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        <div class="font-weight-bold text-dark" style="font-size: 0.98rem;">
                                            {{ $exam->title }}
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        <span class="text-dark">{{ $exam->creator_name ?? '-' }}</span>
                                    </td>
                                    <td class="align-middle">
                                        <span class="badge badge-light border text-dark font-weight-normal px-2 py-1">
                                            <i class="far fa-clock mr-1 text-secondary"></i>{{ $exam->duration_minutes }}
                                            นาที
                                        </span>
                                    </td>
                                    <td class="text-center align-middle">
                                        <span
                                            class="font-weight-bold text-primary">{{ number_format($exam->total_score, 2) }}</span>
                                        <div class="text-xs text-muted">เกณฑ์ผ่าน {{ $exam->passing_percentage }}%</div>
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge badge-info font-weight-bold px-2 py-1">
                                            {{ $exam->questions_count }} ข้อ
                                        </span>
                                    </td>
                                    <td class="text-center align-middle">
                                        @if($exam->approval_status === 'approved')
                                            <span class="badge badge-success px-2 py-1">อนุมัติแล้ว</span>
                                        @elseif($exam->approval_status === 'rejected')
                                            <span class="badge badge-danger px-2 py-1">ส่งกลับแก้ไข</span>
                                        @elseif($exam->approval_status === 'pending_academic' || $exam->approval_status === 'pending_dept' || $exam->approval_status === 'pending_eval')
                                            <span class="badge badge-warning text-dark px-2 py-1">รออนุมัติ</span>
                                        @else
                                            <span class="badge badge-secondary px-2 py-1">ฉบับร่าง</span>
                                        @endif

                                        @if($exam->is_active)
                                            <div class="text-xs text-success font-weight-bold mt-1"><i
                                                    class="fas fa-check-circle mr-1"></i>เปิดสอบ</div>
                                        @else
                                            <div class="text-xs text-muted mt-1"><i class="fas fa-times-circle mr-1"></i>ปิดสอบ
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-center align-middle" style="white-space: nowrap;">
                                        {{-- ปุ่มตัวอย่าง (2 มุมมอง) --}}
                                        <div class="btn-group mr-1">
                                            <button type="button"
                                                class="btn btn-xs btn-outline-info font-weight-bold shadow-xs dropdown-toggle"
                                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                                title="ดูตัวอย่างข้อสอบ">
                                                <i class="fas fa-eye mr-1"></i>ตัวอย่าง
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right shadow border-0 py-1"
                                                style="min-width: 250px;">
                                                <h6
                                                    class="dropdown-header font-weight-bold text-dark border-bottom pb-2 mb-1">
                                                    <i class="fas fa-eye mr-1 text-info"></i>เลือกมุมมองดูตัวอย่างข้อสอบ
                                                </h6>
                                                <a class="dropdown-item py-2"
                                                    href="{{ route('admin.exams.preview', [$exam->id, 'mode' => 'approval']) }}"
                                                    target="_blank">
                                                    <i class="fas fa-clipboard-check text-primary mr-2"></i>
                                                    <strong>1. เหมือนตอนอนุมัติ</strong>
                                                    <div class="text-xs text-muted pl-4">กระดาษข้อสอบ พร้อมเฉลยและเกณฑ์คะแนน
                                                    </div>
                                                </a>
                                                <div class="dropdown-divider my-1"></div>
                                                <a class="dropdown-item py-2"
                                                    href="{{ route('admin.exams.preview', [$exam->id, 'mode' => 'take']) }}"
                                                    target="_blank">
                                                    <i class="fas fa-user-graduate text-success mr-2"></i>
                                                    <strong>2. มุมมองตอนทำข้อสอบ</strong>
                                                    <div class="text-xs text-muted pl-4">ทดลองทำข้อสอบจริงได้ (ไม่เก็บผลสอบ)
                                                    </div>
                                                </a>
                                            </div>
                                        </div>

                                        <a href="{{ route('admin.subjects.students.index', $exam->subject->id) }}"
                                            class="btn btn-xs btn-info font-weight-bold shadow-xs mr-1"
                                            title="ดูรายชื่อนักศึกษาในวิชานี้">
                                            นักศึกษา
                                            ({{ $exam->subject->students_count ?? $exam->subject->students->count() }})
                                        </a>

                                        <a href="{{ route('admin.exams.questions.index', $exam->id) }}"
                                            class="btn btn-xs btn-info font-weight-bold shadow-xs mr-1"
                                            title="จัดการโจทย์ ({{ $exam->questions_count }} ข้อ)">
                                            <i class="fas fa-edit mr-1"></i>โจทย์
                                        </a>

                                        <a href="{{ route('admin.exams.student-attempts.index', $exam->id) }}"
                                            class="btn btn-xs btn-outline-primary font-weight-bold shadow-xs mr-1"
                                            title="สิทธิ์สอบ">
                                            <i class="fas fa-user-clock"></i>
                                        </a>

                                        <form action="{{ route('admin.exams.duplicate', $exam->id) }}" method="post"
                                            class="d-inline mr-1 confirm-duplicate"
                                            data-text="ต้องการคัดลอกข้อสอบ '{{ $exam->title }}' ใช่หรือไม่? ข้อสอบชุดใหม่จะถูกสร้างเป็น 'ฉบับร่าง'">
                                            @csrf
                                            <button type="submit"
                                                class="btn btn-xs btn-outline-secondary font-weight-bold shadow-xs"
                                                title="คัดลอกข้อสอบ">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </form>

                                        @if(auth()->user()->isAdmin() || $exam->canBeEdited())
                                            <form action="{{ route('admin.exams.destroy', $exam->id) }}" method="post"
                                                class="d-inline confirm-delete"
                                                data-text="คุณแน่ใจหรือไม่ที่จะลบข้อสอบนี้? ข้อมูลคำถาม คำตอบ และผลการสอบทั้งหมดจะถูกลบไปด้วย!">
                                                @csrf
                                                @method('delete')
                                                <button type="submit"
                                                    class="btn btn-xs btn-outline-danger font-weight-bold shadow-xs"
                                                    title="ลบข้อสอบ">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">
                                        <i class="fas fa-folder-open fa-3x d-block mb-3 text-secondary"></i>
                                        @if(request('department_id') || request('teacher_id') || request('subject_id') || request('exam_status') || request('q'))
                                            <h6 class="font-weight-bold text-dark mb-1">ไม่พบข้อสอบที่ตรงกับเงื่อนไขการค้นหา
                                            </h6>
                                            <p class="text-muted text-sm mb-3">
                                                ลองเปลี่ยนหรือล้างเงื่อนไขตัวกรองเพื่อดูข้อสอบชุดอื่น</p>
                                            <a href="{{ route('admin.subjects.index') }}"
                                                class="btn btn-sm btn-outline-primary font-weight-bold">
                                                <i class="fas fa-undo mr-1"></i>ล้างตัวกรองทั้งหมด
                                            </a>
                                        @else
                                            <h6 class="font-weight-bold text-dark mb-1">ไม่มีข้อมูลข้อสอบในระบบ</h6>
                                            <p class="text-muted text-sm">คลิกปุ่ม "สร้างข้อสอบใหม่"
                                                ด้านบนเพื่อเริ่มต้นสร้างข้อสอบ</p>
                                        @endif
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

@if(auth()->user()->isStaff())
    <!-- Add Subject Modal -->
    <div class="modal fade" id="addSubjectModal" tabindex="-1" role="dialog" aria-labelledby="addSubjectModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white font-weight-bold" id="addSubjectModalLabel"><i
                            class="fas fa-plus mr-2"></i>เพิ่มรายวิชาใหม่</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.subjects.store') }}" method="post">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="code" class="font-weight-bold">รหัสวิชา</label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                value="{{ old('code') }}" placeholder="เช่น BC-301" required>
                            @error('code')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="name" class="font-weight-bold">ชื่อรายวิชา</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name') }}" placeholder="เช่น การพัฒนาเว็บแอปพลิเคชัน" required>
                            @error('name')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="department_id" class="font-weight-bold">หมวดวิชา / แผนก</label>
                            <select name="department_id" class="form-control">
                                <option value="">-- ไม่ระบุ (สังกัดหมวดวิชาของผู้สร้าง) --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ (string) old('department_id', request('department_id')) === (string) $dept->id ? 'selected' : '' }}>
                                        {{ $dept->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary font-weight-bold"
                            data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary font-weight-bold">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Subject Modals for filtered subjects -->
    @foreach($subjects as $subjItem)
        @if(auth()->user()->isAdmin() || $subjItem->teachers->contains(auth()->id()))
            <div class="modal fade" id="editSubjectModal{{ $subjItem->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-warning">
                            <h5 class="modal-title text-white font-weight-bold"><i class="fas fa-edit mr-2"></i>แก้ไขรายวิชา</h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form action="{{ route('admin.subjects.update', $subjItem->id) }}" method="post">
                            @csrf
                            @method('put')
                            <div class="modal-body">
                                <div class="form-group">
                                    <label for="code" class="font-weight-bold">รหัสวิชา</label>
                                    <input type="text" name="code" class="form-control" value="{{ old('code', $subjItem->code) }}"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label for="name" class="font-weight-bold">ชื่อรายวิชา</label>
                                    <input type="text" name="name" class="form-control" value="{{ old('name', $subjItem->name) }}"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label for="department_id" class="font-weight-bold">หมวดวิชา / แผนก</label>
                                    <select name="department_id" class="form-control">
                                        <option value="">-- ไม่ระบุ --</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}" {{ (string) old('department_id', $subjItem->department_id) === (string) $dept->id ? 'selected' : '' }}>
                                                {{ $dept->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary font-weight-bold"
                                    data-dismiss="modal">ยกเลิก</button>
                                <button type="submit" class="btn btn-warning text-white font-weight-bold">บันทึกการแก้ไข</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@endif
@stop

@section('js')
<script>
    function filterByDepartment() {
        $('#filter_teacher_id').val('');
        $('#filter_subject_id').val('');
        $('#subjectFilterForm').submit();
    }

    function filterByTeacher() {
        $('#filter_subject_id').val('');
        $('#subjectFilterForm').submit();
    }

    function filterBySubject() {
        $('#subjectFilterForm').submit();
    }

    $(document).ready(function () {
        $('#subjects-table').DataTable({
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "ทั้งหมด"]],
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Thai.json"
            },
            "responsive": true,
            "autoWidth": false
        });

        $('#exams-table').DataTable({
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "ทั้งหมด"]],
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Thai.json"
            },
            "responsive": true,
            "autoWidth": false
        });

        // Auto adjust table column widths on tab switch
        $('a[data-toggle="pill"]').on('shown.bs.tab', function (e) {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        });

        // SweetAlert Flash Success
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: {!! json_encode(session('success')) !!},
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
                html: {!! json_encode(implode("<br>", $errors->all())) !!},
                confirmButtonText: 'ตกลง'
            });
        @endif

        // Confirm Delete Subject Handler
        $(document).on('submit', '.confirm-delete-subject', function (e) {
            e.preventDefault();
            var form = this;
            var subjectName = $(this).attr('data-subject-name') || "รายวิชานี้";
            var examsCount = parseInt($(this).attr('data-exams-count')) || 0;

            var text = "คุณแน่ใจหรือไม่ที่จะลบรายวิชา " + subjectName + " ?";
            if (examsCount > 0) {
                text += " คำเตือน: มีข้อสอบในวิชานี้จำนวน " + examsCount + " ชุด ข้อมูลข้อสอบ โจทย์ และผลสอบทั้งหมดจะถูกลบไปด้วย!";
            } else {
                text += " ข้อมูลรายวิชาและข้อมูลนักศึกษาที่ลงทะเบียนในวิชานี้จะถูกลบออกจากระบบ";
            }

            Swal.fire({
                title: 'ยืนยันการลบรายวิชา?',
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ใช่, ต้องการลบรายวิชา!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

        // Confirm Delete Exam Handler
        $(document).on('submit', '.confirm-delete', function (e) {
            e.preventDefault();
            var form = this;
            var text = $(this).attr('data-text') || "ข้อมูลนี้จะถูกลบและไม่สามารถกู้คืนกลับมาได้!";
            Swal.fire({
                title: 'คุณแน่ใจหรือไม่?',
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ใช่, ต้องการลบ!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

        // Confirm Duplicate Handler
        $(document).on('submit', '.confirm-duplicate', function (e) {
            e.preventDefault();
            var form = this;
            var text = $(this).attr('data-text') || "ข้อสอบชุดใหม่จะถูกสร้างเป็น 'ฉบับร่าง' และต้องยื่นขออนุมัติใหม่ก่อนเปิดใช้งาน";
            Swal.fire({
                title: 'ยืนยันการคัดลอกข้อสอบ?',
                text: text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ใช่, คัดลอกข้อสอบ!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
</script>
@stop