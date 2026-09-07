@extends('adminlte::page')

@section('title', 'จัดการสิทธิ์สอบเพิ่มรายคน - ' . $exam->title)

@section('plugins.Datatables', true)
@section('plugins.Sweetalert2', true)

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="text-dark font-weight-bold">
            จัดการสิทธิ์เปิดให้สอบเพิ่มรายคน
        </h1>
        <div class="text-dark text-md mt-1">
            ข้อสอบ: <strong>{{ $exam->title }}</strong> |
            รายวิชา: <strong>{{ $exam->subject->code }} {{ $exam->subject->name }}</strong>
            @if($exam->subject->department)
                ({{ $exam->subject->department->name }})
            @endif
        </div>
    </div>
    <div class="mt-2 mt-md-0">
        <a href="{{ route('admin.exams.questions.index', $exam->id) }}"
            class="btn btn-outline-info font-weight-bold shadow-sm mr-2">
            <i class="fas fa-edit mr-1"></i>จัดการโจทย์ข้อสอบ
        </a>
        <a href="{{ route('admin.exams.index') }}" class="btn btn-secondary font-weight-bold shadow-sm">
            <i class="fas fa-arrow-left mr-1"></i>กลับหน้าข้อสอบ
        </a>
    </div>
</div>
@stop

@section('content')
<!-- Filter & Status Tabs -->
<div class="card shadow-sm mb-3">
    <div class="card-body p-3">
        <form action="{{ route('admin.exams.student-attempts.index', $exam->id) }}" method="GET"
            class="form-row align-items-center">
            <input type="hidden" name="status" value="{{ $statusFilter }}">

            <div class="form-group col-md-4 mb-2 mb-md-0">
                <label class="font-weight-bold small text-muted mb-1">กรองตามห้องเรียน</label>
                <select name="classroom_id" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">-- ทุกห้องเรียน (ทั้งหมด) --</option>
                    @foreach($classrooms as $c)
                        <option value="{{ $c->id }}" {{ request('classroom_id') == $c->id ? 'selected' : '' }}>
                            {{ $c->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group col-md-5 mb-2 mb-md-0">
                <label class="font-weight-bold small text-muted mb-1">ค้นหานักศึกษา</label>
                <div class="input-group input-group-sm">
                    <input type="text" name="q" class="form-control" value="{{ request('q') }}"
                        placeholder="ค้นหารหัสนักศึกษา หรือ ชื่อ-นามสกุล...">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-primary font-weight-bold">
                            <i class="fas fa-search mr-1"></i>ค้นหา
                        </button>
                    </div>
                </div>
            </div>

            <div class="form-group col-md-3 mb-0 text-md-right">
                <label class="font-weight-bold small text-muted mb-1 d-block">&nbsp;</label>
                @if(request('classroom_id') || request('q') || request('status', 'all') !== 'all')
                    <a href="{{ route('admin.exams.student-attempts.index', $exam->id) }}"
                        class="btn btn-sm btn-outline-secondary font-weight-bold">
                        <i class="fas fa-redo mr-1"></i>ล้างตัวกรอง
                    </a>
                @endif
            </div>
        </form>

        <hr class="my-3">

        <!-- Status Tabs -->
        <div class="d-flex flex-wrap align-items-center justify-content-start">
            <a href="{{ route('admin.exams.student-attempts.index', [$exam->id, 'classroom_id' => request('classroom_id'), 'q' => request('q'), 'status' => 'all']) }}"
                class="btn btn-sm {{ $statusFilter === 'all' ? 'btn-dark' : 'btn-outline-secondary' }} font-weight-bold mr-2 mb-1">
                ทั้งหมด ({{ $stats['total'] }})
            </a>
            <a href="{{ route('admin.exams.student-attempts.index', [$exam->id, 'classroom_id' => request('classroom_id'), 'q' => request('q'), 'status' => 'failed']) }}"
                class="btn btn-sm {{ $statusFilter === 'failed' ? 'btn-danger' : 'btn-outline-danger' }} font-weight-bold mr-2 mb-1">
                <i class="fas fa-times-circle mr-1"></i>สอบไม่ผ่าน ({{ $stats['failed'] }})
            </a>
            <a href="{{ route('admin.exams.student-attempts.index', [$exam->id, 'classroom_id' => request('classroom_id'), 'q' => request('q'), 'status' => 'passed']) }}"
                class="btn btn-sm {{ $statusFilter === 'passed' ? 'btn-success' : 'btn-outline-success' }} font-weight-bold mr-2 mb-1">
                <i class="fas fa-check-circle mr-1"></i>สอบผ่านแล้ว ({{ $stats['passed'] }})
            </a>
            <a href="{{ route('admin.exams.student-attempts.index', [$exam->id, 'classroom_id' => request('classroom_id'), 'q' => request('q'), 'status' => 'not_attempted']) }}"
                class="btn btn-sm {{ $statusFilter === 'not_attempted' ? 'btn-secondary' : 'btn-outline-secondary' }} font-weight-bold mr-2 mb-1">
                <i class="fas fa-clock mr-1"></i>ยังไม่เคยสอบ ({{ $stats['not_attempted'] }})
            </a>
            <a href="{{ route('admin.exams.student-attempts.index', [$exam->id, 'classroom_id' => request('classroom_id'), 'q' => request('q'), 'status' => 'has_extra']) }}"
                class="btn btn-sm {{ $statusFilter === 'has_extra' ? 'btn-primary' : 'btn-outline-primary' }} font-weight-bold mr-2 mb-1">
                <i class="fas fa-user-plus mr-1"></i>ได้รับสิทธิ์เพิ่มแล้ว ({{ $stats['has_extra'] }})
            </a>
        </div>
    </div>
</div>

<!-- Main Student Attempts Table with Bulk Actions -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div class="d-flex align-items-center mb-2 mb-md-0">
                <h3 class="card-title font-weight-bold text-dark mr-3 mb-0">
                    <i class="fas fa-users mr-1"></i>รายชื่อนักศึกษา ({{ $students->count() }} คน)
                </h3>
            </div>

            <!-- Bulk Actions Toolbar -->
            <div class="d-flex align-items-center flex-wrap">
                <span class="text-muted text-sm mr-2">เลือกด่วน:</span>
                <button type="button" id="select-failed-btn"
                    class="btn btn-xs btn-outline-danger font-weight-bold mr-1 mb-1">
                    <i class="fas fa-times-circle mr-1"></i>เลือกคนที่ตกทั้งหมด
                </button>
                <button type="button" id="select-not-attempted-btn"
                    class="btn btn-xs btn-outline-secondary font-weight-bold mr-3 mb-1">
                    <i class="fas fa-clock mr-1"></i>เลือกคนที่ยังไม่สอบ
                </button>

                <button type="button" id="bulk-add-btn"
                    class="btn btn-sm btn-success font-weight-bold shadow-xs mr-2 mb-1" disabled>
                    <i class="fas fa-plus-circle mr-1"></i>เปิดให้สอบเพิ่ม (+1 ครั้ง) (<span
                        class="selected-count">0</span> คน)
                </button>
                <button type="button" id="bulk-reset-btn"
                    class="btn btn-sm btn-outline-secondary font-weight-bold mr-1 mb-1" disabled>
                    <i class="fas fa-undo mr-1"></i>รีเซ็ตสิทธิ์ (<span class="selected-count">0</span> คน)
                </button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <!-- Form for Bulk Action -->
        <form id="bulk-form" action="{{ route('admin.exams.student-attempts.bulk', $exam->id) }}" method="POST">
            @csrf
            <input type="hidden" name="action" id="bulk-action-input" value="add_one">
            <input type="hidden" name="bulk_reason" id="bulk-reason-input" value="">

            <div class="table-responsive">
                <table id="students-table" class="table table-bordered table-striped table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 4%" class="text-center align-middle">
                                <input type="checkbox" id="select-all">
                            </th>
                            <th style="width: 5%" class="text-center align-middle">#</th>
                            <th style="width: 14%" class="align-middle">รหัสนักศึกษา</th>
                            <th style="width: 18%" class="align-middle">ชื่อ-นามสกุล</th>
                            <th style="width: 10%" class="align-middle text-center">ห้องเรียน</th>
                            <th style="width: 10%" class="text-center align-middle">ทำไปแล้ว</th>
                            <th style="width: 13%" class="text-center align-middle">คะแนนล่าสุด / สถานะ</th>
                            <th style="width: 12%" class="text-center align-middle">สิทธิ์สอบทั้งหมด</th>
                            <th style="width: 14%" class="text-center align-middle" style="white-space: nowrap;">
                                การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($students as $index => $student)
                            <tr data-status="{{ $student->status_group }}">
                                <td class="text-center align-middle">
                                    <input type="checkbox" name="student_ids[]" value="{{ $student->id }}"
                                        class="student-select">
                                </td>
                                <td class="text-center align-middle text-muted">{{ $loop->iteration }}</td>
                                <td class="align-middle font-weight-bold text-dark">
                                    <code>{{ $student->student_code }}</code>
                                </td>
                                <td class="align-middle">
                                    <span class="font-weight-bold">{{ $student->name }}</span>
                                </td>
                                <td class="align-middle text-center">
                                    <span
                                        class="badge badge-light border text-dark">{{ $student->classroom ? $student->classroom->name : '-' }}</span>
                                </td>
                                <td class="text-center align-middle">
                                    <span
                                        class="font-weight-bold {{ $student->student_attempts_count >= $student->allowed_attempts ? 'text-muted' : 'text-primary' }}">
                                        {{ $student->student_attempts_count }} / {{ $student->allowed_attempts }} ครั้ง
                                    </span>
                                    @if($student->remaining_attempts > 0)
                                        <div class="text-xs text-success font-weight-bold mt-1">
                                            (เหลือ {{ $student->remaining_attempts }} ครั้ง)
                                        </div>
                                    @else
                                        <div class="text-xs text-muted mt-1">(สิทธิ์หมดแล้ว)</div>
                                    @endif
                                </td>
                                <td class="text-center align-middle">
                                    @if($student->student_attempts_count === 0)
                                        <span class="badge badge-secondary px-2 py-1 font-weight-normal"><i
                                                class="fas fa-minus mr-1"></i>ยังไม่เข้าสอบ</span>
                                    @elseif($student->has_passed)
                                        <div>
                                            <span class="badge badge-success px-2 py-1 font-weight-bold"><i
                                                    class="fas fa-check-circle mr-1"></i>ผ่านเกณฑ์</span>
                                        </div>
                                        <div class="small font-weight-bold text-success mt-1">
                                            {{ $student->latest_attempt ? floatval($student->latest_attempt->score) : '-' }} /
                                            {{ floatval($exam->total_score) }}
                                            @if($student->latest_attempt && $student->latest_attempt->isScaled() && $student->latest_attempt->raw_score !== null)
                                                <div class="text-xs text-muted font-weight-normal">
                                                    ({{ floatval($student->latest_attempt->raw_score) }}/{{ floatval($student->latest_attempt->total_raw_score) }} ข้อ)
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <div>
                                            <span class="badge badge-danger px-2 py-1 font-weight-bold"><i
                                                    class="fas fa-times-circle mr-1"></i>ไม่ผ่าน</span>
                                        </div>
                                        <div class="small font-weight-bold text-danger mt-1">
                                            {{ $student->latest_attempt ? floatval($student->latest_attempt->score) : '-' }} /
                                            {{ floatval($exam->total_score) }}
                                            @if($student->latest_attempt && $student->latest_attempt->isScaled() && $student->latest_attempt->raw_score !== null)
                                                <div class="text-xs text-muted font-weight-normal">
                                                    ({{ floatval($student->latest_attempt->raw_score) }}/{{ floatval($student->latest_attempt->total_raw_score) }} ข้อ)
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center align-middle">
                                    <div class="font-weight-bold">
                                        {{ $student->allowed_attempts }} ครั้ง
                                        @if($student->extra_attempts > 0)
                                            <span class="badge badge-primary ml-1"
                                                title="เปิดให้สอบเพิ่มพิเศษ +{{ $student->extra_attempts }} ครั้ง">
                                                +{{ $student->extra_attempts }} ครั้ง
                                            </span>
                                        @endif
                                    </div>
                                    @if($student->override && $student->override->reason)
                                        <div class="text-xs text-muted mt-1 text-truncate"
                                            style="max-width: 140px; margin: 0 auto;" title="{{ $student->override->reason }}">
                                            <i class="fas fa-info-circle mr-1"></i>{{ $student->override->reason }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center align-middle" style="white-space: nowrap;">
                                    <!-- Quick Add +1 Button -->
                                    <button type="button"
                                        class="btn btn-xs btn-success font-weight-bold shadow-xs mr-1 quick-add-btn"
                                        data-url="{{ route('admin.exams.student-attempts.quick-add', [$exam->id, $student->id]) }}"
                                        data-name="[{{ $student->student_code }}] {{ $student->name }}"
                                        data-current="{{ $student->allowed_attempts }}"
                                        title="เปิดให้สอบเพิ่ม +1 ครั้งทันที">
                                        <i class="fas fa-plus mr-1"></i>+1 ครั้ง
                                    </button>

                                    <!-- Edit Modal Trigger -->
                                    <button type="button"
                                        class="btn btn-xs btn-outline-primary font-weight-bold shadow-xs mr-1 edit-override-btn"
                                        data-id="{{ $student->id }}" data-code="{{ $student->student_code }}"
                                        data-name="{{ $student->name }}"
                                        data-classroom="{{ $student->classroom ? $student->classroom->name : '-' }}"
                                        data-attempts="{{ $student->student_attempts_count }}"
                                        data-base="{{ $exam->max_attempts }}" data-extra="{{ $student->extra_attempts }}"
                                        data-reason="{{ $student->override ? $student->override->reason : '' }}"
                                        data-update-url="{{ route('admin.exams.student-attempts.update', [$exam->id, $student->id]) }}"
                                        title="กำหนดจำนวนครั้งและระบุเหตุผล">
                                        <i class="fas fa-cog mr-1"></i>ตั้งค่า
                                    </button>

                                    <!-- Reset Button -->
                                    @if($student->extra_attempts > 0)
                                        <button type="button"
                                            class="btn btn-xs btn-outline-danger font-weight-bold shadow-xs reset-btn mr-1"
                                            data-url="{{ route('admin.exams.student-attempts.reset', [$exam->id, $student->id]) }}"
                                            data-name="[{{ $student->student_code }}] {{ $student->name }}"
                                            title="รีเซ็ตสิทธิ์กลับเป็นค่าเริ่มต้น">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                    @endif

                                    <!-- Delete Attempts Button (Admin only) -->
                                    @if(auth()->user()->isAdmin() && $student->student_attempts_count > 0)
                                        <button type="button"
                                            class="btn btn-xs btn-outline-danger font-weight-bold shadow-xs delete-student-attempts-btn"
                                            data-url="{{ route('admin.exams.student-attempts.destroy-all', [$exam->id, $student->id]) }}"
                                            data-name="[{{ $student->student_code }}] {{ $student->name }}"
                                            data-count="{{ $student->student_attempts_count }}"
                                            title="ลบผลการสอบทั้งหมดของนักศึกษาคนนี้">
                                            <i class="fas fa-trash-alt mr-1"></i>ลบผลสอบ
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle fa-2x mb-2 text-secondary d-block"></i>
                                    ไม่พบรายชื่อนักศึกษาตามเงื่อนไขที่เลือก
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Form for Single Actions -->
<form id="action-form" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="_method" id="action-method" value="POST">
</form>

<!-- Modal: Edit Student Override -->
<div class="modal fade" id="editOverrideModal" tabindex="-1" role="dialog" aria-labelledby="editOverrideModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="modal-override-form" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title font-weight-bold" id="editOverrideModalLabel">
                        <i class="fas fa-user-clock mr-2"></i>กำหนดสิทธิ์สอบเพิ่มรายบุคคล
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="bg-light p-3 rounded mb-3 border">
                        <div class="font-weight-bold text-dark" id="modal-student-name">-</div>
                        <div class="text-sm text-muted">
                            รหัสนักศึกษา: <span id="modal-student-code">-</span> | ห้อง: <span
                                id="modal-student-classroom">-</span>
                        </div>
                        <div class="text-sm text-muted mt-1">
                            จำนวนครั้งที่เข้าสอบไปแล้ว: <strong class="text-primary" id="modal-attempts-used">0</strong>
                            ครั้ง
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold text-dark">
                            จำนวนครั้งที่เปิดให้สอบเพิ่มพิเศษ (ครั้ง) <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="extra_attempts" id="modal-extra-attempts"
                            class="form-control font-weight-bold text-center" min="0" max="50" required>
                        <small class="form-text text-muted">
                            ค่าเริ่มต้นของข้อสอบคือ <strong>{{ $exam->max_attempts }} ครั้ง</strong>
                            + เพิ่มพิเศษ <span id="modal-extra-preview" class="text-primary font-weight-bold">0</span>
                            ครั้ง
                            = รวมสอบได้ทั้งหมด <strong id="modal-total-preview"
                                class="text-success">{{ $exam->max_attempts }}</strong> ครั้ง
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold text-dark">เหตุผล / หมายเหตุ (แสดงให้ครูและแอดมินเห็น)</label>
                        <input type="text" name="reason" id="modal-reason" class="form-control"
                            placeholder="เช่น สอบซ่อมรอบที่ 1, คอมพิวเตอร์ดับ">
                        <div class="mt-2">
                            <span class="text-xs text-muted mr-1">แท็กด่วน:</span>
                            <button type="button" class="badge badge-light border text-secondary quick-tag"
                                data-text="สอบซ่อมรอบที่ 1">สอบซ่อมรอบ 1</button>
                            <button type="button" class="badge badge-light border text-secondary quick-tag"
                                data-text="สอบซ่อมรอบที่ 2">สอบซ่อมรอบ 2</button>
                            <button type="button" class="badge badge-light border text-secondary quick-tag"
                                data-text="มีปัญหาทางเทคนิค/เครื่องดับ">ปัญหาเทคนิค</button>
                            <button type="button" class="badge badge-light border text-secondary quick-tag"
                                data-text="ได้รับอนุญาตเป็นกรณีพิเศษ">กรณีพิเศษ</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary font-weight-bold"
                        data-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary font-weight-bold shadow-sm">
                        <i class="fas fa-save mr-1"></i>บันทึกสิทธิ์การสอบ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
    $(document).ready(function () {
        // Checkbox multi-select logic
        function updateSelectedCount() {
            var count = $('.student-select:checked').length;
            $('.selected-count').text(count);
            if (count > 0) {
                $('#bulk-add-btn').prop('disabled', false);
                $('#bulk-reset-btn').prop('disabled', false);
            } else {
                $('#bulk-add-btn').prop('disabled', true);
                $('#bulk-reset-btn').prop('disabled', true);
            }
        }

        $('#select-all').on('change', function () {
            $('.student-select').prop('checked', $(this).prop('checked'));
            updateSelectedCount();
        });

        $('.student-select').on('change', function () {
            updateSelectedCount();
            var allChecked = $('.student-select:checked').length === $('.student-select').length;
            $('#select-all').prop('checked', allChecked);
        });

        // Quick Select Buttons
        $('#select-failed-btn').on('click', function () {
            $('.student-select').prop('checked', false);
            $('tr[data-status="failed"] .student-select').prop('checked', true);
            updateSelectedCount();
            var count = $('.student-select:checked').length;
            if (count === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'ไม่พบนักศึกษาที่สอบไม่ผ่าน',
                    text: 'ไม่มีนักศึกษาที่สอบตกตามตัวกรองปัจจุบัน',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });

        $('#select-not-attempted-btn').on('click', function () {
            $('.student-select').prop('checked', false);
            $('tr[data-status="not_attempted"] .student-select').prop('checked', true);
            updateSelectedCount();
            var count = $('.student-select:checked').length;
            if (count === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'ไม่พบนักศึกษาที่ยังไม่เคยสอบ',
                    text: 'ไม่มีนักศึกษาที่ยังไม่สอบตามตัวกรองปัจจุบัน',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });

        // Quick Add +1 Action
        $('.quick-add-btn').on('click', function () {
            var url = $(this).data('url');
            var name = $(this).data('name');
            var current = $(this).data('current');

            Swal.fire({
                title: 'ยืนยันการเปิดให้สอบเพิ่ม?',
                html: `คุณต้องการเปิดสิทธิ์ให้ <strong>${name}</strong> สอบเพิ่มอีก 1 ครั้งใช่หรือไม่?<br><span class="text-muted">(สิทธิ์รวมจะเพิ่มจาก ${current} เป็น ${current + 1} ครั้ง)</span>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-check mr-1"></i> ยืนยันเปิดสิทธิ์',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#action-form').attr('action', url);
                    $('#action-method').val('POST');
                    $('#action-form').submit();
                }
            });
        });

        // Reset Action
        $('.reset-btn').on('click', function () {
            var url = $(this).data('url');
            var name = $(this).data('name');

            Swal.fire({
                title: 'ยืนยันการรีเซ็ตสิทธิ์?',
                html: `คุณต้องการรีเซ็ตสิทธิ์สอบเพิ่มของ <strong>${name}</strong> กลับเป็นค่าเริ่มต้นใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ยืนยันรีเซ็ต',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#action-form').attr('action', url);
                    $('#action-method').val('DELETE');
                    $('#action-form').submit();
                }
            });
        });

        // Delete Student Attempts Action
        $('.delete-student-attempts-btn').on('click', function () {
            var url = $(this).data('url');
            var name = $(this).data('name');
            var count = $(this).data('count');

            Swal.fire({
                title: 'ยืนยันการลบผลสอบ?',
                html: `คุณต้องการลบผลการสอบทั้งหมด (${count} ครั้ง) ของ <strong>${name}</strong> ใช่หรือไม่?<br><span class="text-danger text-sm font-weight-bold"><i class="fas fa-exclamation-triangle mr-1"></i> ข้อมูลคะแนนและคำตอบทั้งหมดจะถูกลบและไม่สามารถกู้คืนได้</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i> ยืนยันลบผลสอบ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#action-form').attr('action', url);
                    $('#action-method').val('DELETE');
                    $('#action-form').submit();
                }
            });
        });

        // Bulk Add Action
        $('#bulk-add-btn').on('click', function () {
            var count = $('.student-select:checked').length;
            if (count === 0) return;

            Swal.fire({
                title: 'เปิดสิทธิ์สอบเพิ่มแบบกลุ่ม',
                html: `ต้องการเปิดให้สอบเพิ่ม (+1 ครั้ง) สำหรับนักศึกษาที่เลือกจำนวน <strong>${count} คน</strong> ใช่หรือไม่?<br><br>
                           <input type="text" id="swal-bulk-reason" class="form-control form-control-sm" placeholder="ระบุเหตุผล (เช่น สอบซ่อมรอบ 1) - ไม่บังคับ">`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `<i class="fas fa-check mr-1"></i> เปิดให้สอบเพิ่ม ${count} คน`,
                cancelButtonText: 'ยกเลิก',
                preConfirm: () => {
                    return document.getElementById('swal-bulk-reason').value;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#bulk-action-input').val('add_one');
                    $('#bulk-reason-input').val(result.value);
                    $('#bulk-form').submit();
                }
            });
        });

        // Bulk Reset Action
        $('#bulk-reset-btn').on('click', function () {
            var count = $('.student-select:checked').length;
            if (count === 0) return;

            Swal.fire({
                title: 'ยืนยันการรีเซ็ตสิทธิ์แบบกลุ่ม?',
                html: `ต้องการรีเซ็ตสิทธิ์สอบเพิ่มของนักศึกษาที่เลือกจำนวน <strong>${count} คน</strong> กลับเป็นค่าเริ่มต้นใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ยืนยันรีเซ็ต',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#bulk-action-input').val('reset');
                    $('#bulk-form').submit();
                }
            });
        });

        // Edit Modal Handler
        var baseAttempts = {{ $exam->max_attempts }};
        $('.edit-override-btn').on('click', function () {
            var btn = $(this);
            $('#modal-student-name').text(btn.data('name'));
            $('#modal-student-code').text(btn.data('code'));
            $('#modal-student-classroom').text(btn.data('classroom'));
            $('#modal-attempts-used').text(btn.data('attempts'));
            $('#modal-extra-attempts').val(btn.data('extra'));
            $('#modal-reason').val(btn.data('reason'));
            $('#modal-override-form').attr('action', btn.data('update-url'));

            updateModalPreview();
            $('#editOverrideModal').modal('show');
        });

        function updateModalPreview() {
            var extra = parseInt($('#modal-extra-attempts').val()) || 0;
            $('#modal-extra-preview').text(extra);
            $('#modal-total-preview').text(baseAttempts + extra);
        }

        $('#modal-extra-attempts').on('input change', function () {
            updateModalPreview();
        });

        $('.quick-tag').on('click', function () {
            $('#modal-reason').val($(this).data('text'));
        });

        // Flash Messages
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: '{{ session('success') }}',
                timer: 3000,
                showConfirmButton: false
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: '{{ session('error') }}',
            });
        @endif
        });
</script>
@stop