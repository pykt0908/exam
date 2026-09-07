@extends('adminlte::page')

@section('title', isset($selectedClassroom) ? 'นักศึกษาห้อง ' . $selectedClassroom->name . ' - ' . $subject->code : 'ห้องเรียนในรายวิชา ' . $subject->code)

@section('plugins.Select2', true)
@section('plugins.Datatables', true)

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
            <a href="{{ route('admin.subjects.students.index', $subject->id) }}"
                class="btn btn-secondary font-weight-bold shadow-sm">
                <i class="fas fa-arrow-left mr-2"></i>กลับหน้ารายการห้องเรียน
            </a>
        @else
            <button type="button" class="btn btn-success font-weight-bold shadow-sm mr-2" data-toggle="modal"
                data-target="#addClassroomModal">
                <i class="fas fa-plus mr-2"></i>เพิ่มกลุ่มเรียนเข้ารายวิชา
            </button>
            <a href="{{ route('admin.subjects.index') }}" class="btn btn-secondary font-weight-bold shadow-sm">
                <i class="fas fa-arrow-left mr-2"></i>กลับหน้ารายวิชา
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
                กลุ่มเรียนที่มีในรายวิชา ({{ $enrolledClassrooms->count() }} ห้อง)
            </h3>
        </div>
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="classrooms-table" class="table table-bordered table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 6%" class="text-center">#</th>
                            <th>ชื่อห้องเรียน / ระดับชั้น</th>
                            <th style="width: 25%" class="text-center">จำนวนนักศึกษาในวิชา</th>
                            <th class="text-center" style="width: 1%; white-space: nowrap;">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($enrolledClassrooms as $index => $room)
                            <tr>
                                <td class="text-center align-middle text-muted">{{ $index + 1 }}</td>
                                <td class="align-middle">
                                    <a href="{{ route('admin.subjects.students.index', ['subject' => $subject->id, 'classroom_id' => $room->id]) }}"
                                        class="font-weight-bold text-primary text-md">
                                        <i class="fas fa-school mr-2 text-info"></i>{{ $room->name }}
                                    </a>
                                </td>
                                <td class="text-center align-middle">
                                    <span class="badge badge-info px-3 py-1 font-weight-bold text-sm">
                                        <i class="fas fa-users mr-1"></i>{{ $room->subject_students_count }} คน
                                    </span>
                                </td>
                                <td class="text-center align-middle" style="white-space: nowrap;">
                                    <a href="{{ route('admin.subjects.students.index', ['subject' => $subject->id, 'classroom_id' => $room->id]) }}"
                                        class="btn btn-sm btn-info font-weight-bold shadow-xs mr-1" title="ดูรายชื่อนักศึกษา">
                                        <i class="fas fa-users mr-1"></i> ดูรายชื่อ
                                    </a>
                                    <form action="{{ route('admin.subjects.classrooms.destroy', [$subject->id, $room->id]) }}"
                                        method="post" class="d-inline confirm-delete"
                                        data-text="คุณแน่ใจหรือไม่ที่จะนำห้องเรียน {{ $room->name }} ออกจากรายวิชานี้? (นักศึกษาทั้งหมดในห้องนี้จะถูกนำออกจากวิชา)">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="btn btn-sm btn-outline-danger font-weight-bold shadow-xs"
                                            title="นำห้องเรียนออกจากรายวิชา">
                                            <i class="fas fa-trash-alt mr-1"></i> นำห้องเรียนออก
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
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
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success">
                    <h5 class="modal-title text-white font-weight-bold" id="addClassroomModalLabel">
                        <i class="fas fa-school mr-2"></i>เพิ่มกลุ่มเรียนเข้าร่วมรายวิชา
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.subjects.students.store', $subject->id) }}" method="post"
                    id="add-classroom-form">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group mb-0">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="font-weight-bold mb-0">
                                    เลือกห้องเรียน / ระดับชั้น <span class="text-danger">*</span>
                                </label>
                                <div>
                                    <button type="button" class="btn btn-xs btn-outline-success font-weight-bold mr-1"
                                        id="btn-select-all-rooms">
                                        เลือกทั้งหมด
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-danger font-weight-bold"
                                        id="btn-deselect-all-rooms">
                                        ล้างที่เลือก
                                    </button>
                                </div>
                            </div>
                            @php
                                $enrolledIds = isset($enrolledClassrooms) ? $enrolledClassrooms->pluck('id')->toArray() : [];
                            @endphp
                            <select name="classroom_ids[]" id="classroom_select" class="form-control select2"
                                multiple="multiple" data-placeholder="คลิกเพื่อเลือกห้องเรียน (เลือกได้หลายห้อง)..."
                                required style="width: 100%;">
                                @foreach($allClassrooms as $room)
                                    @php
                                        $isEnrolled = in_array($room->id, $enrolledIds);
                                    @endphp
                                    <option value="{{ $room->id }}" data-enrolled="{{ $isEnrolled ? '1' : '0' }}">
                                        {{ $room->name }} ({{ $room->users_count }} คน)
                                        {{ $isEnrolled ? '— [อยู่ในวิชานี้แล้ว]' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary font-weight-bold"
                            data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-success font-weight-bold" id="btn-submit-add-rooms">
                            <i class="fas fa-plus mr-2"></i>เพิ่ม
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@stop

@section('js')
<script>
    $(document).ready(function () {
        $('#classroom_select').select2({
            dropdownParent: $('#addClassroomModal'),
            placeholder: "คลิกเพื่อเลือกห้องเรียน (เลือกได้หลายห้อง)...",
            allowClear: true,
            width: '100%'
        });

        // Quick select all rooms
        $('#btn-select-all-rooms').on('click', function () {
            var allVals = [];
            $('#classroom_select option').each(function () {
                allVals.push($(this).val());
            });
            $('#classroom_select').val(allVals).trigger('change');
        });

        // Quick deselect all
        $('#btn-deselect-all-rooms').on('click', function () {
            $('#classroom_select').val([]).trigger('change');
        });

        // Form submit loading
        $('#add-classroom-form').on('submit', function () {
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