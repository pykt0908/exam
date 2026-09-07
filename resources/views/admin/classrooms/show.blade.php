@extends('adminlte::page')

@section('title', 'จัดการสิทธิ์สอบ - ห้องเรียน ' . $classroom->name)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="text-dark font-weight-bold">
                <i class="fas fa-school mr-2 text-primary"></i>ห้องเรียน: {{ $classroom->name }}
            </h1>
            <div class="text-muted text-sm mt-1">
                จัดการสิทธิ์การเข้าสอบและรายชื่อนักศึกษาในระดับห้องเรียน (มีผลกับทุกวิชาสอบ)
            </div>
        </div>
        <a href="{{ route('admin.classrooms.index') }}" class="btn btn-secondary font-weight-bold shadow-sm mt-2 mt-md-0">
            <i class="fas fa-arrow-left mr-2"></i>กลับหน้าห้องเรียน
        </a>
    </div>
@stop

@section('content')
    @php
        $eligibleCount = $students->filter(function($s) {
            return $s->is_exam_eligible !== false && (int)$s->is_exam_eligible !== 0;
        })->count();
        $ineligibleCount = $students->count() - $eligibleCount;
    @endphp

    <!-- Summary Stats Bar -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card shadow-sm border-0 bg-white">
                <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center justify-content-between">
                    <div class="d-flex align-items-center flex-wrap my-1">
                        <span class="mr-3 font-weight-bold text-dark">
                            <i class="fas fa-users text-primary mr-1"></i>นักเรียนทั้งหมด: <strong>{{ $students->count() }}</strong> คน
                        </span>
                        <span class="text-muted mr-3 d-none d-sm-inline">|</span>
                        <span class="mr-3 text-success font-weight-bold">
                            <i class="fas fa-check-circle mr-1"></i>มีสิทธิ์สอบ: <strong>{{ $eligibleCount }}</strong> คน
                        </span>
                        <span class="text-muted mr-3 d-none d-sm-inline">|</span>
                        <span class="text-danger font-weight-bold">
                            <i class="fas fa-ban mr-1"></i>ระงับสิทธิ์สอบ: <strong>{{ $ineligibleCount }}</strong> คน
                        </span>
                    </div>
                    <div class="text-muted text-xs my-1">
                        <i class="fas fa-info-circle mr-1 text-info"></i>การเปิด/ปิดสิทธิ์สอบระดับห้องเรียน จะมีผลกับการสอบทุกวิชาของนักศึกษาโดยอัตโนมัติ
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Students Card -->
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap">
            <h3 class="card-title font-weight-bold text-dark mb-0">
                <i class="fas fa-user-graduate mr-2"></i>รายชื่อนักศึกษาในห้องเรียน ({{ $students->count() }} คน)
            </h3>
            <div class="card-tools d-flex flex-wrap align-items-center mt-2 mt-sm-0">
                <button type="button" id="btn-toggle-all-enable" class="btn btn-outline-success btn-sm font-weight-bold mr-2 shadow-xs">
                    <i class="fas fa-check-double mr-1"></i>เปิดสิทธิ์ทั้งห้อง
                </button>
                <button type="button" id="btn-toggle-all-disable" class="btn btn-outline-danger btn-sm font-weight-bold shadow-xs">
                    <i class="fas fa-ban mr-1"></i>ระงับสิทธิ์ทั้งห้อง
                </button>
            </div>
        </div>
        <div class="card-body p-3">
            <!-- Bulk Action Form -->
            <form id="bulk-action-form" action="{{ route('admin.classrooms.bulk-toggle-eligibility', $classroom->id) }}" method="post">
                @csrf
                <input type="hidden" name="is_exam_eligible" id="bulk-is-eligible" value="1">
                <input type="hidden" name="ineligible_reason" id="bulk-ineligible-reason" value="">

                <div class="d-flex flex-wrap align-items-center mb-3">
                    <span class="text-muted text-sm mr-2 mb-2">จัดการผู้ที่เลือก (<span class="selected-count font-weight-bold">0</span> คน):</span>
                    <button type="button" id="bulk-enable-btn" class="btn btn-success btn-sm font-weight-bold shadow-sm mr-2 mb-2" disabled>
                        <i class="fas fa-check-circle mr-1"></i> เปิดสิทธิ์สอบ (<span class="selected-count">0</span>)
                    </button>
                    <button type="button" id="bulk-disable-btn" class="btn btn-warning btn-sm text-dark font-weight-bold shadow-sm mb-2" disabled>
                        <i class="fas fa-ban mr-1"></i> ระงับสิทธิ์สอบ (<span class="selected-count">0</span>)
                    </button>
                </div>

                <div class="table-responsive">
                    <table id="classroom-students-table" class="table table-bordered table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 4%" class="text-center">
                                    <input type="checkbox" id="select-all">
                                </th>
                                <th style="width: 5%">#</th>
                                <th style="width: 16%">รหัสนักศึกษา</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th style="width: 18%">เลขประจำตัวประชาชน</th>
                                <th class="text-center" style="width: 20%">สถานะสิทธิ์การสอบ</th>
                                <th class="text-center" style="width: 1%; white-space: nowrap;">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($students as $index => $u)
                                @php
                                    $isEligible = $u->is_exam_eligible !== false && (int)$u->is_exam_eligible !== 0;
                                    $reason = $u->ineligible_reason;
                                @endphp
                                <tr>
                                    <td class="text-center align-middle">
                                        <input type="checkbox" name="student_ids[]" value="{{ $u->id }}" class="student-select">
                                    </td>
                                    <td class="align-middle">{{ $index + 1 }}</td>
                                    <td class="align-middle font-weight-bold text-dark">{{ $u->student_code }}</td>
                                    <td class="align-middle">{{ $u->name }}</td>
                                    <td class="align-middle text-muted">{{ $u->citizen_id ?: '-' }}</td>
                                    <td class="text-center align-middle">
                                        @if($isEligible)
                                            <span class="badge badge-success px-2 py-1 font-weight-normal">
                                                <i class="fas fa-check-circle mr-1"></i>มีสิทธิ์สอบ
                                            </span>
                                        @else
                                            <span class="badge badge-danger px-2 py-1 font-weight-normal" title="เหตุผล: {{ $reason }}">
                                                <i class="fas fa-ban mr-1"></i>ระงับสิทธิ์
                                            </span>
                                            @if($reason)
                                                <div class="text-xs text-danger font-weight-bold mt-1">
                                                    {{ $reason }}
                                                </div>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="text-center align-middle" style="white-space: nowrap;">
                                        <!-- Single toggle button -->
                                        @if($isEligible)
                                            <button type="button" class="btn btn-xs btn-outline-danger font-weight-bold shadow-xs mr-1 btn-disable-student" 
                                                    data-id="{{ $u->id }}" 
                                                    data-name="{{ $u->name }}" 
                                                    data-code="{{ $u->student_code }}"
                                                    title="ระงับสิทธิ์สอบ (ค้างค่าเทอม/ยังไม่จ่ายเงิน/เวลาเรียนไม่ครบ)">
                                                <i class="fas fa-ban mr-1"></i>ระงับสิทธิ์
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-xs btn-success font-weight-bold shadow-xs mr-1 btn-enable-student" 
                                                    data-id="{{ $u->id }}" 
                                                    data-name="{{ $u->name }}" 
                                                    data-code="{{ $u->student_code }}"
                                                    title="เปิดคืนสิทธิ์สอบ">
                                                <i class="fas fa-check mr-1"></i>เปิดสิทธิ์
                                            </button>
                                        @endif

                                        <a href="{{ route('admin.users.show', $u->id) }}" class="btn btn-xs btn-info font-weight-bold shadow-xs" title="ดูประวัติการสอบ">
                                            <i class="fas fa-history mr-1"></i>ดูประวัติ
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">ไม่มีข้อมูลนักศึกษาในห้องเรียนนี้</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden Individual / Toggle-All Forms -->
    <form id="individual-toggle-form" method="post" style="display: none;">
        @csrf
        <input type="hidden" name="is_exam_eligible" id="toggle-is-eligible" value="1">
        <input type="hidden" name="ineligible_reason" id="toggle-ineligible-reason" value="">
    </form>

    <form id="toggle-all-form" action="{{ route('admin.classrooms.toggle-all-eligibility', $classroom->id) }}" method="post" style="display: none;">
        @csrf
        <input type="hidden" name="is_exam_eligible" id="all-is-eligible" value="1">
        <input type="hidden" name="ineligible_reason" id="all-ineligible-reason" value="">
    </form>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            var table = $('#classroom-students-table').DataTable({
                "pageLength": -1,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "ทั้งหมด"]],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Thai.json"
                },
                "responsive": true,
                "autoWidth": false,
                "columnDefs": [
                    { "orderable": false, "targets": [0, 5, 6] }
                ]
            });

            // Handle select all checkbox
            $('#select-all').on('click', function() {
                var rows = table.rows({ 'search': 'applied' }).nodes();
                $('input[type="checkbox"]', rows).prop('checked', this.checked);
                updateBulkButtonState();
            });

            // Handle individual checkbox changes
            $(document).on('change', '.student-select', function() {
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
                    $('#bulk-enable-btn, #bulk-disable-btn').prop('disabled', false);
                } else {
                    $('#bulk-enable-btn, #bulk-disable-btn').prop('disabled', true);
                }
            }

            // --- Single Student: Enable ---
            $(document).on('click', '.btn-enable-student', function() {
                var studentId = $(this).data('id');
                var studentName = $(this).data('name');
                var studentCode = $(this).data('code');

                Swal.fire({
                    title: 'เปิดสิทธิ์การสอบ',
                    text: `ต้องการเปิดสิทธิ์การเข้าสอบให้แก่ [${studentCode}] ${studentName} ใช่หรือไม่? (จะมีผลกับทุกวิชาสอบ)`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'ตกลง, เปิดสิทธิ์สอบ',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        var form = $('#individual-toggle-form');
                        form.attr('action', '{{ url("/admin/classrooms/{$classroom->id}/toggle-student-eligibility") }}/' + studentId);
                        $('#toggle-is-eligible').val(1);
                        $('#toggle-ineligible-reason').val('');
                        form.submit();
                    }
                });
            });

            // --- Single Student: Disable with Reason Selection ---
            $(document).on('click', '.btn-disable-student', function() {
                var studentId = $(this).data('id');
                var studentName = $(this).data('name');
                var studentCode = $(this).data('code');

                Swal.fire({
                    title: 'ระงับสิทธิ์การสอบ',
                    html: `
                        <p class="text-muted text-sm mb-3">นักศึกษา: <strong>[${studentCode}] ${studentName}</strong></p>
                        <div class="text-left mb-3">
                            <label class="font-weight-bold text-dark text-sm">เลือกสาเหตุที่ระงับสิทธิ์:</label>
                            <select id="swal-single-reason-select" class="form-control mb-2">
                                <option value="ค้างชำระค่าเทอม / ค่าเล่าเรียน">ค้างชำระค่าเทอม / ค่าเล่าเรียน</option>
                                <option value="ยังไม่ชำระเงินค่าลงทะเบียน">ยังไม่ชำระเงินค่าลงทะเบียน</option>
                                <option value="เวลาเรียนไม่ครบ (มส.)">เวลาเรียนไม่ครบ (มส.)</option>
                                <option value="ขาดคุณสมบัติในการสอบ">ขาดคุณสมบัติในการสอบ</option>
                                <option value="other">ระบุเหตุผลอื่นๆ...</option>
                            </select>
                            <input id="swal-single-reason-custom" class="form-control d-none" placeholder="กรอกเหตุผลที่ระงับสิทธิ์สอบ...">
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'ยืนยันระงับสิทธิ์',
                    cancelButtonText: 'ยกเลิก',
                    didOpen: () => {
                        const select = Swal.getHtmlContainer().querySelector('#swal-single-reason-select');
                        const customInput = Swal.getHtmlContainer().querySelector('#swal-single-reason-custom');
                        select.addEventListener('change', () => {
                            if (select.value === 'other') {
                                customInput.classList.remove('d-none');
                                customInput.focus();
                            } else {
                                customInput.classList.add('d-none');
                            }
                        });
                    },
                    preConfirm: () => {
                        const select = Swal.getHtmlContainer().querySelector('#swal-single-reason-select');
                        const customInput = Swal.getHtmlContainer().querySelector('#swal-single-reason-custom');
                        if (select.value === 'other') {
                            const val = customInput.value.trim();
                            if (!val) {
                                Swal.showValidationMessage('กรุณาระบุเหตุผล');
                                return false;
                            }
                            return val;
                        }
                        return select.value;
                    }
                }).then((result) => {
                    if (result.isConfirmed && result.value) {
                        var form = $('#individual-toggle-form');
                        form.attr('action', '{{ url("/admin/classrooms/{$classroom->id}/toggle-student-eligibility") }}/' + studentId);
                        $('#toggle-is-eligible').val(0);
                        $('#toggle-ineligible-reason').val(result.value);
                        form.submit();
                    }
                });
            });

            // --- Bulk Enable (Selected) ---
            $('#bulk-enable-btn').on('click', function(e) {
                e.preventDefault();
                var checkedCount = table.$('.student-select:checked').length;
                if (checkedCount === 0) return;

                var selectedIds = [];
                table.$('.student-select:checked').each(function() {
                    selectedIds.push($(this).val());
                });

                Swal.fire({
                    title: 'เปิดสิทธิ์การสอบ (ผู้ที่เลือก)',
                    text: `คุณต้องการเปิดสิทธิ์สอบให้นักศึกษาจำนวน ${checkedCount} คนที่เลือก ใช่หรือไม่?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'ตกลง, เปิดสิทธิ์ทั้งหมด',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        submitBulkForm(selectedIds, 1, '');
                    }
                });
            });

            // --- Bulk Disable (Selected) with Reason ---
            $('#bulk-disable-btn').on('click', function(e) {
                e.preventDefault();
                var checkedCount = table.$('.student-select:checked').length;
                if (checkedCount === 0) return;

                var selectedIds = [];
                table.$('.student-select:checked').each(function() {
                    selectedIds.push($(this).val());
                });

                Swal.fire({
                    title: 'ระงับสิทธิ์การสอบ (ผู้ที่เลือก)',
                    html: `
                        <p class="text-muted text-sm mb-3">จำนวนนักศึกษาที่เลือก: <strong>${checkedCount} คน</strong></p>
                        <div class="text-left mb-3">
                            <label class="font-weight-bold text-dark text-sm">เลือกสาเหตุที่ระงับสิทธิ์:</label>
                            <select id="swal-bulk-reason-select" class="form-control mb-2">
                                <option value="ค้างชำระค่าเทอม / ค่าเล่าเรียน">ค้างชำระค่าเทอม / ค่าเล่าเรียน</option>
                                <option value="ยังไม่ชำระเงินค่าลงทะเบียน">ยังไม่ชำระเงินค่าลงทะเบียน</option>
                                <option value="เวลาเรียนไม่ครบ (มส.)">เวลาเรียนไม่ครบ (มส.)</option>
                                <option value="ขาดคุณสมบัติในการสอบ">ขาดคุณสมบัติในการสอบ</option>
                                <option value="other">ระบุเหตุผลอื่นๆ...</option>
                            </select>
                            <input id="swal-bulk-reason-custom" class="form-control d-none" placeholder="กรอกเหตุผลที่ระงับสิทธิ์สอบ...">
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'ยืนยันระงับสิทธิ์',
                    cancelButtonText: 'ยกเลิก',
                    didOpen: () => {
                        const select = Swal.getHtmlContainer().querySelector('#swal-bulk-reason-select');
                        const customInput = Swal.getHtmlContainer().querySelector('#swal-bulk-reason-custom');
                        select.addEventListener('change', () => {
                            if (select.value === 'other') {
                                customInput.classList.remove('d-none');
                                customInput.focus();
                            } else {
                                customInput.classList.add('d-none');
                            }
                        });
                    },
                    preConfirm: () => {
                        const select = Swal.getHtmlContainer().querySelector('#swal-bulk-reason-select');
                        const customInput = Swal.getHtmlContainer().querySelector('#swal-bulk-reason-custom');
                        if (select.value === 'other') {
                            const val = customInput.value.trim();
                            if (!val) {
                                Swal.showValidationMessage('กรุณาระบุเหตุผล');
                                return false;
                            }
                            return val;
                        }
                        return select.value;
                    }
                }).then((result) => {
                    if (result.isConfirmed && result.value) {
                        submitBulkForm(selectedIds, 0, result.value);
                    }
                });
            });

            function submitBulkForm(selectedIds, isEligible, reason) {
                var form = $('#bulk-action-form');
                form.find('input[name="student_ids[]"]').remove();
                
                selectedIds.forEach(function(id) {
                    form.append($('<input>').attr('type', 'hidden').attr('name', 'student_ids[]').val(id));
                });

                $('#bulk-is-eligible').val(isEligible);
                $('#bulk-ineligible-reason').val(reason || '');
                form.submit();
            }

            // --- Toggle All: Enable All in Classroom ---
            $('#btn-toggle-all-enable').on('click', function() {
                Swal.fire({
                    title: 'เปิดสิทธิ์การสอบทั้งห้อง',
                    text: 'คุณต้องการเปิดสิทธิ์การสอบให้นักศึกษาทุกคนในห้อง {{ $classroom->name }} ใช่หรือไม่?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'ตกลง, เปิดสิทธิ์ทั้งห้อง',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#all-is-eligible').val(1);
                        $('#all-ineligible-reason').val('');
                        $('#toggle-all-form').submit();
                    }
                });
            });

            // --- Toggle All: Disable All in Classroom ---
            $('#btn-toggle-all-disable').on('click', function() {
                Swal.fire({
                    title: 'ระงับสิทธิ์การสอบทั้งห้อง',
                    html: `
                        <p class="text-muted text-sm mb-3">ห้องเรียน: <strong>{{ $classroom->name }}</strong> ({{ $students->count() }} คน)</p>
                        <div class="text-left mb-3">
                            <label class="font-weight-bold text-dark text-sm">เลือกสาเหตุที่ระงับสิทธิ์ทั้งห้อง:</label>
                            <select id="swal-all-reason-select" class="form-control mb-2">
                                <option value="ค้างชำระค่าเทอม / ค่าเล่าเรียน">ค้างชำระค่าเทอม / ค่าเล่าเรียน</option>
                                <option value="ยังไม่ชำระเงินค่าลงทะเบียน">ยังไม่ชำระเงินค่าลงทะเบียน</option>
                                <option value="เวลาเรียนไม่ครบ (มส.)">เวลาเรียนไม่ครบ (มส.)</option>
                                <option value="ระงับสิทธิ์ชั่วคราว">ระงับสิทธิ์ชั่วคราว</option>
                                <option value="other">ระบุเหตุผลอื่นๆ...</option>
                            </select>
                            <input id="swal-all-reason-custom" class="form-control d-none" placeholder="กรอกเหตุผลที่ระงับสิทธิ์สอบ...">
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'ยืนยันระงับสิทธิ์ทั้งห้อง',
                    cancelButtonText: 'ยกเลิก',
                    didOpen: () => {
                        const select = Swal.getHtmlContainer().querySelector('#swal-all-reason-select');
                        const customInput = Swal.getHtmlContainer().querySelector('#swal-all-reason-custom');
                        select.addEventListener('change', () => {
                            if (select.value === 'other') {
                                customInput.classList.remove('d-none');
                                customInput.focus();
                            } else {
                                customInput.classList.add('d-none');
                            }
                        });
                    },
                    preConfirm: () => {
                        const select = Swal.getHtmlContainer().querySelector('#swal-all-reason-select');
                        const customInput = Swal.getHtmlContainer().querySelector('#swal-all-reason-custom');
                        if (select.value === 'other') {
                            const val = customInput.value.trim();
                            if (!val) {
                                Swal.showValidationMessage('กรุณาระบุเหตุผล');
                                return false;
                            }
                            return val;
                        }
                        return select.value;
                    }
                }).then((result) => {
                    if (result.isConfirmed && result.value) {
                        $('#all-is-eligible').val(0);
                        $('#all-ineligible-reason').val(result.value);
                        $('#toggle-all-form').submit();
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
                    html: {!! json_encode(implode("<br>", $errors->all())) !!},
                    confirmButtonText: 'ตกลง'
                });
            @endif
        });
    </script>
@stop
