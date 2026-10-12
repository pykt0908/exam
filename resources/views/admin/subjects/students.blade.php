@extends('adminlte::page')

@section('title', isset($selectedClassroom) ? 'นักศึกษาห้อง ' . $selectedClassroom->name . ' - ' . $subject->code : 'ห้องเรียนในรายวิชา ' . $subject->code)

@section('plugins.Select2', true)
@section('plugins.Datatables', true)

@section('css')
<style>
    /* ปรับแต่งสีและรูปแบบของตัวเลือก Select2 (Multiple) ให้อ่านง่าย ชัดเจน */
    .select2-container--default .select2-selection--multiple .select2-selection__choice,
    .select2-container--default .select2-selection--multiple .select2-selection__choice span {
        color: #1e293b !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #f1f5f9 !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 4px !important;
        padding: 3px 8px !important;
        margin-top: 5px !important;
        font-size: 0.9rem !important;
        font-weight: 500 !important;
        line-height: 1.4 !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove,
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove span {
        color: #64748b !important;
        float: right !important;
        margin-left: 8px !important;
        margin-right: -2px !important;
        font-weight: bold !important;
        cursor: pointer !important;
        transition: color 0.15s ease-in-out;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover,
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover span {
        color: #ef4444 !important;
    }

    .select2-container--default .select2-selection--multiple {
        border: 1px solid #ced4da;
        border-radius: 4px;
        min-height: 38px;
        padding: 2px 4px;
    }

    .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: #28a745;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    }

    /* สไตล์สำหรับตารางรายชื่อนักศึกษาใน Modal เลือกรายคน */
    .modal-student-row {
        cursor: pointer;
        user-select: none;
        transition: background-color 0.15s ease-in-out;
    }

    .modal-student-row:hover {
        background-color: #f1f5f9 !important;
    }

    .modal-student-row.table-primary,
    .modal-student-row.table-primary td {
        background-color: #e0f2fe !important;
    }

    .modal-student-row.table-primary:hover,
    .modal-student-row.table-primary:hover td {
        background-color: #bae6fd !important;
    }

    /* สไตล์สำหรับตารางกลุ่มเรียนใน Modal เพิ่มกลุ่มเรียน */
    .modal-cr-row {
        cursor: pointer;
        user-select: none;
        transition: background-color 0.15s ease-in-out;
    }

    .modal-cr-row:hover {
        background-color: #f1f5f9 !important;
    }

    .modal-cr-row.table-success,
    .modal-cr-row.table-success td {
        background-color: #dcfce7 !important;
    }

    .modal-cr-row.table-success:hover,
    .modal-cr-row.table-success:hover td {
        background-color: #bbf7d0 !important;
    }
</style>
@stop

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        @if(isset($selectedClassroom))
            <h1 class="text-dark font-weight-bold">
                <i class="fas fa-school mr-2 text-primary"></i>ห้องเรียน: {{ $selectedClassroom->name }}
            </h1>
            <div class="text-muted text-sm mt-1">
                รายชื่อนักศึกษาในรายวิชา: <strong>{{ $subject->code }} {{ $subject->name }}</strong>
            </div>
        @else
            <h1 class="text-dark font-weight-bold">
                รายวิชา {{ $subject->code }} {{ $subject->name }}
            </h1>
        @endif
    </div>
    <div class="mt-2 mt-md-0">
        @if(isset($selectedClassroom))
            <button type="button" class="btn btn-primary font-weight-bold shadow-sm mr-2" data-toggle="modal"
                data-target="#addIndividualStudentModal">
                <i class="fas fa-user-plus mr-1"></i>เพิ่มนักศึกษารายคน
            </button>
            <a href="{{ route('admin.subjects.students.index', $subject->id) }}"
                class="btn btn-secondary font-weight-bold shadow-sm">
                <i class="fas fa-arrow-left mr-1"></i>กลับหน้ารายการห้องเรียน
            </a>
        @else
            <button type="button" class="btn btn-primary font-weight-bold shadow-sm mr-2" data-toggle="modal"
                data-target="#addIndividualStudentModal">
                <i class="fas fa-user-plus mr-1"></i>เพิ่มนักศึกษารายคน
            </button>
            <button type="button" class="btn btn-success font-weight-bold shadow-sm mr-2" data-toggle="modal"
                data-target="#addClassroomModal">
                <i class="fas fa-plus mr-1"></i>เพิ่มกลุ่มเรียนเข้ารายวิชา
            </button>
            <a href="{{ route('admin.subjects.index') }}" class="btn btn-secondary font-weight-bold shadow-sm">
                <i class="fas fa-arrow-left mr-1"></i>กลับหน้ารายวิชา
            </a>
        @endif
    </div>
</div>
@stop

@section('content')
@if(isset($selectedClassroom))
    <!-- ================= LEVEL 2: STUDENTS IN SELECTED CLASSROOM ================= -->
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap">
            <h3 class="card-title font-weight-bold text-dark mb-0">
                <i class="fas fa-user-graduate mr-2"></i>รายชื่อนักศึกษาห้อง {{ $selectedClassroom->name }}
                ({{ $students->count() }} คน)
            </h3>
        </div>
        <div class="card-body p-3">
            <!-- Individual remove forms -->
            @foreach($students as $student)
                <form id="delete-form-{{ $student->id }}"
                    action="{{ route('admin.subjects.students.destroy', [$subject->id, $student->id]) }}" method="post"
                    class="confirm-delete"
                    data-text="คุณแน่ใจหรือไม่ที่จะนำนักศึกษา [{{ $student->student_code }}] {{ $student->name }} ออกจากรายวิชานี้?">
                    @csrf
                    @method('delete')
                </form>
            @endforeach

            <!-- Bulk Actions Form wrapper -->
            <form id="bulk-action-form" action="{{ route('admin.subjects.students.bulk-destroy', $subject->id) }}"
                method="post">
                @csrf

                <div class="d-flex flex-wrap align-items-center mb-3">
                    <button type="button" id="bulk-remove-btn"
                        class="btn btn-outline-danger btn-sm font-weight-bold shadow-sm" disabled>
                        <i class="fas fa-user-minus mr-1"></i> นำออกจากรายวิชา (<span
                            class="selected-count font-weight-bold">0</span> คน)
                    </button>
                </div>

                <div class="table-responsive">
                    <table id="students-table" class="table table-bordered table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 4%" class="text-center">
                                    <input type="checkbox" id="select-all">
                                </th>
                                <th style="width: 6%" class="text-center">#</th>
                                <th style="width: 25%">รหัสนักศึกษา</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th class="text-center" style="width: 1%; white-space: nowrap;">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($students as $index => $student)
                                <tr>
                                    <td class="text-center align-middle">
                                        <input type="checkbox" name="student_ids[]" value="{{ $student->id }}"
                                            class="student-select">
                                    </td>
                                    <td class="text-center align-middle text-muted">{{ $index + 1 }}</td>
                                    <td class="align-middle font-weight-bold text-dark">{{ $student->student_code }}</td>
                                    <td class="align-middle">{{ $student->name }}</td>
                                    <td class="text-center align-middle" style="white-space: nowrap;">
                                        <button type="submit" form="delete-form-{{ $student->id }}"
                                            class="btn btn-xs btn-outline-danger font-weight-bold shadow-xs"
                                            title="นำออกจากรายวิชา">
                                            <i class="fas fa-trash-alt mr-1"></i>นำออก
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5">
                                        ไม่มีนักศึกษาในห้องเรียนนี้ที่ลงทะเบียนในรายวิชา
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

@else
    <!-- ================= LEVEL 1: CLASSROOMS LIST IN SUBJECT ================= -->
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap">
            <h3 class="card-title font-weight-bold text-dark mb-0">
                <i class="fas fa-layer-group mr-2 text-primary"></i>กลุ่มเรียนที่มีในรายวิชา
                ({{ $enrolledClassrooms->count() }} ห้อง / นักศึกษาทั้งหมด {{ $totalStudentsCount ?? 0 }} คน)
            </h3>
        </div>
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="classrooms-table" class="table table-bordered table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 6%" class="text-center">#</th>
                            <th>ชื่อห้องเรียน / ระดับชั้น</th>
                            <th style="width: 12%" class="text-center">ภาค</th>
                            <th style="width: 20%" class="text-center">จำนวนนักศึกษาในวิชา</th>
                            <th class="text-center" style="width: 1%; white-space: nowrap;">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(isset($unassignedStudentsCount) && $unassignedStudentsCount > 0)
                            <tr class="table-warning">
                                <td class="text-center align-middle text-muted">-</td>
                                <td class="align-middle">
                                    <a href="{{ route('admin.subjects.students.index', ['subject' => $subject->id, 'classroom_id' => 'unassigned']) }}"
                                        class="font-weight-bold text-dark text-md">
                                        <i class="fas fa-user-tag mr-2 text-warning"></i>นักศึกษาที่ไม่มีกลุ่มเรียน
                                    </a>
                                </td>
                                <td class="text-center align-middle text-muted">-</td>
                                <td class="text-center align-middle">
                                    <span class="px-3 py-1 font-weight-bold text-md badge badge-warning">
                                        {{ $unassignedStudentsCount }} คน
                                    </span>
                                </td>
                                <td class="text-center align-middle" style="white-space: nowrap;">
                                    <a href="{{ route('admin.subjects.students.index', ['subject' => $subject->id, 'classroom_id' => 'unassigned']) }}"
                                        class="btn btn-sm btn-primary font-weight-bold shadow-xs mr-1"
                                        title="ดูรายชื่อนักศึกษา">
                                        <i class="fas fa-users mr-1"></i> ดูรายชื่อ
                                    </a>
                                </td>
                            </tr>
                        @endif
                        @forelse($enrolledClassrooms as $index => $room)
                            <tr>
                                <td class="text-center align-middle text-muted">{{ $index + 1 }}</td>
                                <td class="align-middle">
                                    <a href="{{ route('admin.subjects.students.index', ['subject' => $subject->id, 'classroom_id' => $room->id]) }}"
                                        class="font-weight-bold text-dark text-md">
                                        {{ $room->name }}
                                    </a>
                                </td>
                                <td class="text-center align-middle">
                                    <span class="text-dark font-weight-bold">{{ $room->shift_text }}</span>
                                </td>
                                <td class="text-center align-middle">
                                    <span class=" px-3 py-1 font-weight-bold text-md">
                                        {{ $room->subject_students_count }} คน
                                    </span>
                                </td>
                                <td class="text-center align-middle" style="white-space: nowrap;">
                                    <a href="{{ route('admin.subjects.students.index', ['subject' => $subject->id, 'classroom_id' => $room->id]) }}"
                                        class="btn btn-sm btn-primary font-weight-bold shadow-xs mr-1"
                                        title="ดูรายชื่อนักศึกษา">
                                        <i class="fas fa-users mr-1"></i> ดูรายชื่อ
                                    </a>
                                    <form action="{{ route('admin.subjects.classrooms.destroy', [$subject->id, $room->id]) }}"
                                        method="post" class="d-inline confirm-delete"
                                        data-text="คุณแน่ใจหรือไม่ที่จะนำห้องเรียน {{ $room->name }} ออกจากรายวิชานี้? (นักศึกษาทั้งหมดในห้องนี้จะถูกนำออกจากวิชา)">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="btn btn-sm btn-danger font-weight-bold shadow-xs"
                                            title="นำห้องเรียนออกจากรายวิชา">
                                            <i class="fas fa-trash-alt mr-1"></i> นำห้องเรียนออก
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <span
                                        class="d-block font-weight-bold text-md text-secondary">ยังไม่มีกลุ่มเรียนในรายวิชานี้</span>
                                    <span class="text-sm text-muted d-block mt-1">กดปุ่ม
                                        <strong>"เพิ่มกลุ่มเรียนเข้ารายวิชา"</strong>
                                        เพื่อเริ่มต้นเปิดสอนรายวิชานี้ให้กับห้องเรียน</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Classroom Modal -->
    <div class="modal fade" id="addClassroomModal" tabindex="-1" role="dialog" aria-labelledby="addClassroomModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title font-weight-bold" id="addClassroomModalLabel">
                        <i class="fas fa-school mr-2"></i>เพิ่มกลุ่มเรียนเข้าร่วมรายวิชา
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.subjects.students.store', $subject->id) }}" method="post"
                    id="add-classroom-form">
                    @csrf
                    <div class="modal-body p-4">
                        <!-- Filters & Search Row -->
                        <div class="row text-dark mb-3">
                            <div class="col-md-3 mb-2 mb-md-0">
                                <label class="font-weight-bold mb-1">
                                    <i class="far fa-clock text-info mr-1"></i>ภาค
                                </label>
                                <select id="modal_cr_shift_filter" class="form-control">
                                    <option value="">-- ทุกภาค --</option>
                                    <option value="เช้า">ภาคเช้า</option>
                                    <option value="บ่าย">ภาคบ่าย</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <label class="font-weight-bold mb-1">
                                    สถานะในรายวิชา
                                </label>
                                <select id="modal_cr_status_filter" class="form-control">
                                    <option value="">-- แสดงทั้งหมด --</option>
                                    <option value="not_enrolled">ยังไม่เข้าร่วม</option>
                                    <option value="enrolled">อยู่ในวิชาแล้ว</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="font-weight-bold mb-1">
                                    ค้นหาชื่อกลุ่มเรียน หรือระดับชั้น
                                </label>
                                <div class="input-group">
                                    <input type="text" id="modal_cr_search" class="form-control"
                                        placeholder="พิมพ์ชื่อกลุ่มเรียนเพื่อค้นหา...">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" id="btn_clear_cr_search"
                                            title="ล้างการค้นหา">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions & Selection Summary Bar -->
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                            <div class="text-dark">
                                <strong>เลือกแล้ว:</strong>
                                <span id="modal_cr_selected_count" class="badge badge-success px-2 py-1 font-weight-bold ml-1"
                                    style="font-size: 0.95rem;">0</span> กลุ่มเรียน
                                <span class="text-muted ml-1">(รวมนักศึกษา <strong id="modal_cr_student_sum" class="text-success">0</strong> คน)</span>
                                <span class="text-muted ml-2">| แสดงอยู่: <span id="modal_cr_visible_count" class="font-weight-bold text-dark">{{ $allClassrooms->count() }}</span> ห้อง</span>
                            </div>
                            <div>
                                <button type="button" class="btn btn-xs btn-outline-danger font-weight-bold"
                                    id="btn_cr_clear_all">
                                    <i class="fas fa-trash-alt mr-1"></i>ล้างที่เลือกทั้งหมด
                                </button>
                            </div>
                        </div>

                        <!-- Scrollable Table of Classrooms with Checkboxes -->
                        <div class="table-responsive border rounded" style="max-height: 400px; overflow-y: auto;">
                            @php
                                $enrolledIds = isset($enrolledClassrooms) ? $enrolledClassrooms->pluck('id')->toArray() : [];
                            @endphp
                            <table class="table table-sm table-hover table-striped mb-0" id="modal_classrooms_table">
                                <thead class="thead-light"
                                    style="position: sticky; top: 0; z-index: 5; background-color: #f1f5f9;">
                                    <tr>
                                        <th style="width: 45px;" class="text-center align-middle">
                                            <input type="checkbox" id="modal_cr_check_all"
                                                title="เลือก/ยกเลิกทั้งหมดที่แสดง">
                                        </th>
                                        <th style="width: 50px;" class="text-center align-middle">#</th>
                                        <th class="align-middle">ชื่อห้องเรียน / ระดับชั้น</th>
                                        <th style="width: 100px;" class="text-center align-middle">ภาค</th>
                                        <th style="width: 140px;" class="text-center align-middle">จำนวนนักศึกษา</th>
                                        <th style="width: 150px;" class="text-center align-middle">สถานะในวิชา</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($allClassrooms as $index => $room)
                                        @php
                                            $isEnrolled = in_array($room->id, $enrolledIds);
                                            $roomShift = str_contains($room->name, 'บ่าย') ? 'บ่าย' : 'เช้า';
                                        @endphp
                                        <tr class="modal-cr-row"
                                            data-shift="{{ $roomShift }}"
                                            data-name="{{ mb_strtolower($room->name) }}"
                                            data-enrolled="{{ $isEnrolled ? '1' : '0' }}">
                                            <td class="text-center align-middle">
                                                <input type="checkbox" name="classroom_ids[]" value="{{ $room->id }}"
                                                    class="modal-cr-check"
                                                    data-count="{{ $room->users_count }}">
                                            </td>
                                            <td class="text-center align-middle text-muted modal-cr-row-num">
                                                {{ $index + 1 }}
                                            </td>
                                            <td class="align-middle">
                                                <span class="font-weight-bold text-dark">{{ $room->name }}</span>
                                            </td>
                                            <td class="text-center align-middle">
                                                <span class="text-dark font-weight-bold">{{ $room->shift_text }}</span>
                                            </td>
                                            <td class="text-center align-middle">
                                                <span class="font-weight-bold text-secondary">{{ $room->users_count }} คน</span>
                                            </td>
                                            <td class="text-center align-middle">
                                                @if($isEnrolled)
                                                    <span class="badge badge-success px-2 py-1">
                                                        <i class="fas fa-check-circle mr-1"></i>อยู่ในวิชาแล้ว
                                                    </span>
                                                @else
                                                    <span class="badge badge-light border text-muted px-2 py-1">
                                                        ยังไม่เข้าร่วม
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                ไม่พบข้อมูลห้องเรียนในระบบ
                                            </td>
                                        </tr>
                                    @endforelse
                                    <tr id="modal_cr_no_results" class="d-none">
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="fas fa-search mr-1"></i>ไม่พบกลุ่มเรียนที่ตรงกับเงื่อนไขการค้นหา
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Info note -->
                        <div class="mt-2 text-muted text-xs">
                            <i class="fas fa-info-circle mr-1 text-info"></i>
                            คลิกที่แถวเพื่อเลือกหรือยกเลิกการเลือกห้องเรียน | สามารถเลือกหลายห้องเรียนพร้อมกันได้
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary font-weight-bold"
                            data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-success font-weight-bold shadow-sm" id="btn-submit-add-rooms">
                            <i class="fas fa-plus mr-1"></i>เพิ่มกลุ่มเรียนเข้ารายวิชา (<span id="modal_cr_submit_count">0</span>)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<!-- Add Individual Student Modal -->
<div class="modal fade" id="addIndividualStudentModal" tabindex="-1" role="dialog"
    aria-labelledby="addIndividualStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="addIndividualStudentModalLabel">
                    เพิ่มนักศึกษาเข้าร่วมรายวิชา
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('admin.subjects.students.store', $subject->id) }}" method="post"
                id="add-student-form">
                @csrf
                <div class="modal-body p-4">
                    <!-- Classroom Filter & Search Row -->
                    <div class="row text-dark mb-3">
                        <div class="col-md-3 mb-2 mb-md-0">
                            <label class="font-weight-bold mb-1">
                                <i class="far fa-clock text-info mr-1"></i>ภาค
                            </label>
                            <select id="modal_student_shift_filter" class="form-control">
                                <option value="">-- ทุกภาค --</option>
                                <option value="เช้า">ภาคเช้า</option>
                                <option value="บ่าย">ภาคบ่าย</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2 mb-md-0">
                            <label class="font-weight-bold mb-1">
                                กรองตามกลุ่มเรียน / ห้องเรียน
                            </label>
                            <select id="modal_classroom_filter" class="form-control select2" style="width: 100%;">
                                <option value="">-- แสดงนักศึกษาทุกกลุ่มเรียน --</option>
                                @foreach($allClassrooms as $room)
                                    @php
                                        $roomShift = str_contains($room->name, 'บ่าย') ? 'บ่าย' : 'เช้า';
                                    @endphp
                                    <option value="{{ $room->id }}" data-shift="{{ $roomShift }}" {{ (isset($selectedClassroom) && $selectedClassroom->id == $room->id) ? 'selected' : '' }}>
                                        {{ $room->name }}
                                    </option>
                                @endforeach
                                @php
                                    $noRoomCount = $availableStudents->whereNull('classroom_id')->count();
                                @endphp
                                @if($noRoomCount > 0)
                                    <option value="unassigned" data-shift="">
                                        ไม่มีกลุ่มเรียน
                                    </option>
                                @endif
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="font-weight-bold mb-1">
                                ค้นหาชื่อ หรือรหัสนักศึกษา
                            </label>
                            <div class="input-group">
                                <input type="text" id="modal_student_search" class="form-control"
                                    placeholder="พิมพ์รหัส หรือชื่อเพื่อค้นหา...">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary" id="btn_clear_search"
                                        title="ล้างการค้นหา">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions & Selection Summary Bar -->
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                        <div class="text-dark">
                            <strong>เลือกแล้ว:</strong>
                            <span id="modal_selected_count" class="text-primary px-2 py-1 font-weight-bold ml-1"
                                style="font-size: 0.95rem;">0</span> คน
                        </div>
                        <div>
                            <button type="button" class="btn btn-xs btn-outline-primary font-weight-bold mr-1"
                                id="btn_select_all_visible">
                                <i class="fas fa-check-square mr-1"></i>เลือกทั้งหมดที่แสดง
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-danger font-weight-bold"
                                id="btn_clear_all_selection">
                                <i class="fas fa-trash-alt mr-1"></i>ล้างที่เลือกทั้งหมด
                            </button>
                        </div>
                    </div>

                    <!-- Scrollable Table of Students with Checkboxes -->
                    <div class="table-responsive border rounded" style="max-height: 380px; overflow-y: auto;">
                        <table class="table table-sm table-hover table-striped mb-0" id="modal_students_table">
                            <thead class="thead-light"
                                style="position: sticky; top: 0; z-index: 5; background-color: #f1f5f9;">
                                <tr>
                                    <th style="width: 45px;" class="text-center align-middle">
                                        <input type="checkbox" id="modal_check_all_checkbox"
                                            title="เลือก/ยกเลิกทั้งหมดที่แสดง">
                                    </th>
                                    <th style="width: 140px;" class="align-middle">รหัสนักศึกษา</th>
                                    <th class="align-middle">ชื่อ-นามสกุล</th>
                                    <th style="width: 200px;" class="align-middle">กลุ่มเรียน</th>
                                    <th style="width: 100px;" class="text-center align-middle">ภาค</th>
                                </tr>
                            </thead>
                            <tbody id="modal_students_tbody">
                                @forelse($availableStudents as $student)
                                    @php
                                        $studShift = $student->classroom ? (str_contains($student->classroom->name, 'บ่าย') ? 'บ่าย' : 'เช้า') : '';
                                    @endphp
                                    <tr class="modal-student-row"
                                        data-classroom-id="{{ $student->classroom_id ?? 'unassigned' }}"
                                        data-shift="{{ $studShift }}"
                                        data-search="{{ mb_strtolower($student->student_code . ' ' . $student->name . ' ' . ($student->classroom ? $student->classroom->name : 'ไม่มีกลุ่มเรียน')) }}">
                                        <td class="text-center align-middle" style="width: 45px;">
                                            <input type="checkbox" name="student_ids[]" value="{{ $student->id }}"
                                                class="modal-student-check">
                                        </td>
                                        <td class="align-middle font-weight-bold text-dark">{{ $student->student_code }}
                                        </td>
                                        <td class="align-middle text-dark font-weight-500">{{ $student->name }}</td>
                                        <td class="align-middle">
                                            <span class="badge badge-light text-secondary font-weight-bold px-2 py-1">
                                                {{ $student->classroom ? $student->classroom->name : 'ไม่มีกลุ่มเรียน' }}
                                            </span>
                                        </td>
                                        <td class="text-center align-middle">
                                            @if($student->classroom)
                                                <span class="text-dark font-weight-bold">{{ $student->classroom->shift_text }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="modal_empty_row">
                                        <td colspan="5" class="text-center text-muted py-5">
                                            <i class="fas fa-check-circle text-success mb-2"
                                                style="font-size: 2.2rem;"></i><br>
                                            <strong class="text-dark">นักศึกษาทุกคนเข้าร่วมรายวิชานี้แล้ว</strong><br>
                                            <span class="text-sm">ไม่มีนักศึกษาคงเหลือที่รอเพิ่มในระบบ</span>
                                        </td>
                                    </tr>
                                @endforelse
                                <tr id="modal_no_results_row" class="d-none">
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="fas fa-search text-secondary mb-2" style="font-size: 1.8rem;"></i><br>
                                        <span>ไม่พบรายชื่อนักศึกษาตามเงื่อนไขที่เลือกหรือค้นหา</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light justify-content-end">

                    <div>
                        <button type="button" class="btn btn-secondary font-weight-bold mr-1"
                            data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary font-weight-bold" id="btn-submit-add-students"
                            disabled>
                            <i class="fas fa-user-plus mr-1"></i> เพิ่มนักศึกษาเข้ารายวิชา (<span
                                class="selected-badge-count">0</span> คน)
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
    $(document).ready(function () {
        // Modal Classroom Filter Select2
        $('#modal_classroom_filter').select2({
            dropdownParent: $('#addIndividualStudentModal'),
            placeholder: "กรองตามกลุ่มเรียน...",
            allowClear: false,
            width: '100%'
        });

        // Filter function (Classroom + Search Input + Shift)
        function filterModalStudents() {
            var selectedShift = $('#modal_student_shift_filter').val();
            var selectedRoom = $('#modal_classroom_filter').val();
            var searchTerm = ($('#modal_student_search').val() || '').trim().toLowerCase();
            var visibleCount = 0;

            $('.modal-student-row').each(function () {
                var row = $(this);
                var room = row.attr('data-classroom-id');
                var shift = row.attr('data-shift') || '';
                var searchData = row.attr('data-search') || '';

                var matchShift = !selectedShift || shift === selectedShift;
                var matchRoom = !selectedRoom || room === selectedRoom;
                var matchSearch = !searchTerm || searchData.indexOf(searchTerm) !== -1;

                if (matchShift && matchRoom && matchSearch) {
                    row.removeClass('d-none');
                    visibleCount++;
                } else {
                    row.addClass('d-none');
                }
            });

            $('#modal_visible_count').text(visibleCount);

            if (visibleCount === 0 && $('.modal-student-row').length > 0) {
                $('#modal_no_results_row').removeClass('d-none');
            } else {
                $('#modal_no_results_row').addClass('d-none');
            }

            updateModalSelectionState();
        }

        // Strictly filter modal classroom filter by removing non-matching options
        var allIndividualModalClassrooms = $('#modal_classroom_filter option').clone();

        $('#modal_student_shift_filter').on('change', function () {
            var shift = $(this).val();
            var currentVal = $('#modal_classroom_filter').val();
            $('#modal_classroom_filter').empty();
            allIndividualModalClassrooms.each(function () {
                var roomShift = $(this).data('shift');
                var optVal = $(this).val();
                if (!shift || !roomShift || roomShift === shift || optVal === "" || optVal === "unassigned") {
                    $('#modal_classroom_filter').append($(this).clone());
                }
            });
            if (currentVal && $('#modal_classroom_filter option[value="' + currentVal + '"]').length > 0) {
                $('#modal_classroom_filter').val(currentVal);
            } else {
                $('#modal_classroom_filter').val('');
            }
            $('#modal_classroom_filter').trigger('change.select2');
            filterModalStudents();
        });

        $('#modal_classroom_filter').on('change', function () {
            filterModalStudents();
        });

        $('#modal_student_search').on('input', function () {
            filterModalStudents();
        });

        $('#btn_clear_search').on('click', function () {
            $('#modal_student_search').val('');
            filterModalStudents();
        });

        // Click row to toggle checkbox
        $(document).on('click', '.modal-student-row', function (e) {
            if ($(e.target).is('input[type="checkbox"]')) {
                return;
            }
            var cb = $(this).find('.modal-student-check');
            cb.prop('checked', !cb.prop('checked')).trigger('change');
        });

        // Checkbox change updates row highlight and counter
        $(document).on('change', '.modal-student-check', function () {
            var tr = $(this).closest('tr');
            if (this.checked) {
                tr.addClass('table-primary');
            } else {
                tr.removeClass('table-primary');
            }
            updateModalSelectionState();
        });

        function updateModalSelectionState() {
            var checkedCount = $('.modal-student-check:checked').length;
            $('#modal_selected_count').text(checkedCount);
            $('.selected-badge-count').text(checkedCount);

            if (checkedCount > 0) {
                $('#btn-submit-add-students').prop('disabled', false);
            } else {
                $('#btn-submit-add-students').prop('disabled', true);
            }

            // Update master checkbox based on visible rows
            var visibleRows = $('.modal-student-row:not(.d-none)');
            var visibleChecked = visibleRows.find('.modal-student-check:checked').length;

            if (visibleRows.length > 0 && visibleChecked === visibleRows.length) {
                $('#modal_check_all_checkbox').prop('checked', true).prop('indeterminate', false);
            } else if (visibleChecked > 0) {
                $('#modal_check_all_checkbox').prop('checked', false).prop('indeterminate', true);
            } else {
                $('#modal_check_all_checkbox').prop('checked', false).prop('indeterminate', false);
            }
        }

        // Master checkbox in table header
        $('#modal_check_all_checkbox').on('click', function () {
            var isChecked = this.checked;
            $('.modal-student-row:not(.d-none)').each(function () {
                var cb = $(this).find('.modal-student-check');
                cb.prop('checked', isChecked);
                if (isChecked) {
                    $(this).addClass('table-primary');
                } else {
                    $(this).removeClass('table-primary');
                }
            });
            updateModalSelectionState();
        });

        // Quick button: Select all currently visible
        $('#btn_select_all_visible').on('click', function () {
            $('.modal-student-row:not(.d-none)').each(function () {
                $(this).find('.modal-student-check').prop('checked', true);
                $(this).addClass('table-primary');
            });
            updateModalSelectionState();
        });

        // Quick button: Clear all selections
        $('#btn_clear_all_selection').on('click', function () {
            $('.modal-student-row').each(function () {
                $(this).find('.modal-student-check').prop('checked', false);
                $(this).removeClass('table-primary');
            });
            updateModalSelectionState();
        });

        // Trigger filter when opening modal
        $('#addIndividualStudentModal').on('shown.bs.modal', function () {
            filterModalStudents();
            $('#modal_student_search').focus();
        });

        // Form submit loading
        $('#add-student-form').on('submit', function (e) {
            var checkedCount = $('.modal-student-check:checked').length;
            if (checkedCount === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณาเลือกนักศึกษา',
                    text: 'กรุณาเลือกนักศึกษาที่ต้องการเพิ่มอย่างน้อย 1 คน',
                    confirmButtonText: 'ตกลง'
                });
                return false;
            }
            var btn = $('#btn-submit-add-students');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> กำลังบันทึก...');
        });

        // ================= MODAL: ADD CLASSROOMS AS TABLE =================
        function filterModalClassrooms() {
            var selectedShift = $('#modal_cr_shift_filter').val();
            var selectedStatus = $('#modal_cr_status_filter').val();
            var searchTerm = $.trim($('#modal_cr_search').val()).toLowerCase();
            var visibleCount = 0;

            $('.modal-cr-row').each(function () {
                var row = $(this);
                var shift = row.attr('data-shift') || '';
                var name = row.attr('data-name') || '';
                var isEnrolled = row.attr('data-enrolled') || '0';

                var matchShift = !selectedShift || shift === selectedShift;
                var matchStatus = !selectedStatus ||
                    (selectedStatus === 'enrolled' && isEnrolled === '1') ||
                    (selectedStatus === 'not_enrolled' && isEnrolled === '0');
                var matchSearch = !searchTerm || name.indexOf(searchTerm) !== -1;

                if (matchShift && matchStatus && matchSearch) {
                    row.removeClass('d-none');
                    visibleCount++;
                    row.find('.modal-cr-row-num').text(visibleCount);
                } else {
                    row.addClass('d-none');
                }
            });

            $('#modal_cr_visible_count').text(visibleCount);

            if (visibleCount === 0 && $('.modal-cr-row').length > 0) {
                $('#modal_cr_no_results').removeClass('d-none');
            } else {
                $('#modal_cr_no_results').addClass('d-none');
            }

            updateModalClassroomCounts();
        }

        function updateModalClassroomCounts() {
            var checkedCheckboxes = $('.modal-cr-check:checked');
            var checkedCount = checkedCheckboxes.length;
            var studentSum = 0;

            checkedCheckboxes.each(function () {
                studentSum += parseInt($(this).data('count')) || 0;
            });

            $('#modal_cr_selected_count').text(checkedCount);
            $('#modal_cr_student_sum').text(studentSum.toLocaleString());
            $('#modal_cr_submit_count').text(checkedCount);

            // Master checkbox state
            var visibleRows = $('.modal-cr-row:not(.d-none)');
            var visibleChecked = visibleRows.find('.modal-cr-check:checked').length;

            if (visibleRows.length > 0 && visibleChecked === visibleRows.length) {
                $('#modal_cr_check_all').prop('checked', true).prop('indeterminate', false);
            } else if (visibleChecked > 0) {
                $('#modal_cr_check_all').prop('checked', false).prop('indeterminate', true);
            } else {
                $('#modal_cr_check_all').prop('checked', false).prop('indeterminate', false);
            }
        }

        // Row click to toggle checkbox
        $(document).on('click', '.modal-cr-row', function (e) {
            if ($(e.target).is('input[type="checkbox"]')) {
                return;
            }
            var cb = $(this).find('.modal-cr-check');
            cb.prop('checked', !cb.prop('checked')).trigger('change');
        });

        // Checkbox change updates row highlight and counts
        $(document).on('change', '.modal-cr-check', function () {
            var tr = $(this).closest('tr');
            if (this.checked) {
                tr.addClass('table-success');
            } else {
                tr.removeClass('table-success');
            }
            updateModalClassroomCounts();
        });

        // Master checkbox in table header
        $('#modal_cr_check_all').on('click', function () {
            var isChecked = this.checked;
            $('.modal-cr-row:not(.d-none)').each(function () {
                var cb = $(this).find('.modal-cr-check');
                cb.prop('checked', isChecked);
                if (isChecked) {
                    $(this).addClass('table-success');
                } else {
                    $(this).removeClass('table-success');
                }
            });
            updateModalClassroomCounts();
        });

        // Quick button: Clear all selections
        $('#btn_cr_clear_all').on('click', function () {
            $('.modal-cr-row').each(function () {
                $(this).find('.modal-cr-check').prop('checked', false);
                $(this).removeClass('table-success');
            });
            updateModalClassroomCounts();
        });

        // Event listeners for filters
        $('#modal_cr_shift_filter, #modal_cr_status_filter').on('change', function () {
            filterModalClassrooms();
        });

        $('#modal_cr_search').on('input', function () {
            filterModalClassrooms();
        });

        $('#btn_clear_cr_search').on('click', function () {
            $('#modal_cr_search').val('');
            filterModalClassrooms();
        });

        // Trigger filter and autofocus search when modal opened
        $('#addClassroomModal').on('shown.bs.modal', function () {
            filterModalClassrooms();
            $('#modal_cr_search').focus();
        });

        // Form submit loading & validation
        $('#add-classroom-form').on('submit', function (e) {
            var checkedCount = $('.modal-cr-check:checked').length;
            if (checkedCount === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณาเลือกกลุ่มเรียน',
                    text: 'กรุณาเลือกกลุ่มเรียนที่ต้องการเพิ่มเข้ารายวิชาอย่างน้อย 1 กลุ่ม',
                    confirmButtonText: 'ตกลง'
                });
                return false;
            }
            var btn = $('#btn-submit-add-rooms');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> กำลังบันทึก...');
        });

        @if(isset($selectedClassroom))
            var table = $('#students-table').DataTable({
                "pageLength": -1,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "ทั้งหมด"]],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Thai.json"
                },
                "responsive": true,
                "autoWidth": false,
                "columnDefs": [
                    { "orderable": false, "targets": [0, 4] }
                ]
            });

            // Handle select all checkbox
            $('#select-all').on('click', function () {
                var rows = table.rows({ 'search': 'applied' }).nodes();
                $('input[type="checkbox"]', rows).prop('checked', this.checked);
                updateBulkButtonState();
            });

            // Handle individual checkbox changes
            $(document).on('change', '.student-select', function () {
                if (!this.checked) {
                    var el = $('#select-all').get(0);
                    if (el && el.checked && ('indeterminate' in el)) {
                        el.indeterminate = true;
                    }
                }
                updateBulkButtonState();
            });

            function updateBulkButtonState() {
                var checkedCount = table.$('.student-select:checked').length;
                $('.selected-count').text(checkedCount);
                if (checkedCount > 0) {
                    $('#bulk-remove-btn').prop('disabled', false);
                } else {
                    $('#bulk-remove-btn').prop('disabled', true);
                }
            }

            // --- Bulk Remove Students ---
            $('#bulk-remove-btn').on('click', function (e) {
                e.preventDefault();
                var checkedCount = table.$('.student-select:checked').length;
                if (checkedCount === 0) return;

                var selectedIds = [];
                table.$('.student-select:checked').each(function () {
                    selectedIds.push($(this).val());
                });

                Swal.fire({
                    title: 'ยืนยันการนำออก',
                    text: `คุณต้องการนำนักศึกษาจำนวน ${checkedCount} คนที่เลือก ออกจากรายวิชานี้ใช่หรือไม่?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'ตกลง, นำออกทั้งหมด',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        var form = $('#bulk-action-form');
                        form.find('input[name="student_ids[]"]').remove();
                        selectedIds.forEach(function (id) {
                            form.append($('<input>').attr('type', 'hidden').attr('name', 'student_ids[]').val(id));
                        });
                        form.submit();
                    }
                });
            });
        @else
            $('#classrooms-table').DataTable({
                "pageLength": -1,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "ทั้งหมด"]],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Thai.json"
                },
                "responsive": true,
                "autoWidth": false,
                "columnDefs": [
                    { "orderable": false, "targets": [3] }
                ]
            });
        @endif

        // SweetAlert Confirm Delete/Remove
        $(document).on('submit', '.confirm-delete', function (e) {
            e.preventDefault();
            var form = this;
            var text = $(this).data('text') || 'คุณต้องการดำเนินการนี้ใช่หรือไม่?';

            Swal.fire({
                title: 'ยืนยันการทำรายการ',
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ตกลง, ดำเนินการ!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
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
                html: `{!! implode('<br>', $errors->all()) !!}`,
                confirmButtonText: 'ตกลง'
            });
        @endif
        });
</script>
@stop