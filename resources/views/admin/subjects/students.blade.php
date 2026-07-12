@extends('adminlte::page')

@section('title', 'นักศึกษาในรายวิชา')

@section('plugins.Select2', true)
@section('plugins.Datatables', true)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="text-dark font-weight-bold">นักศึกษาในรายวิชา: {{ $subject->code }} {{ $subject->name }}</h1>
        </div>
        <a href="{{ route('admin.subjects.index') }}" class="btn btn-secondary font-weight-bold shadow-sm">
            <i class="fas fa-arrow-left mr-2"></i>กลับหน้ารายวิชา
        </a>
    </div>
@stop

@section('content')


    <div class="row">
        <!-- Enrolled Students List (Left Column) -->
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h3 class="card-title font-weight-bold text-dark">
                        นักศึกษาที่เข้าร่วมเรียนในรายวิชานี้ ({{ $enrolledStudents->count() }} คน)
                    </h3>
                </div>
                <div class="card-body p-3">
                    <!-- HTML5 forms for individual delete actions (not nested in the main bulk form) -->
                    @foreach($enrolledStudents as $student)
                        <form id="delete-form-{{ $student->id }}" action="{{ route('admin.subjects.students.destroy', [$subject->id, $student->id]) }}" method="post" class="confirm-delete"
                              data-text="คุณแน่ใจหรือไม่ที่จะนำนักศึกษาคนนี้ออกจากรายวิชา?">
                            @csrf
                            @method('delete')
                        </form>
                    @endforeach

                    <!-- Bulk Actions Form wrapper -->
                    <form id="bulk-remove-form" action="{{ route('admin.subjects.students.bulk-destroy', $subject->id) }}" method="post">
                        @csrf
                        <div class="mb-3">
                            <button type="button" id="bulk-remove-btn" class="btn btn-danger btn-sm font-weight-bold shadow-sm" disabled>
                                <i class="fas fa-user-minus mr-1"></i> นำผู้ที่เลือกออก (0)
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table id="enrolled-table" class="table table-bordered table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 5%" class="text-center">
                                            <input type="checkbox" id="select-all">
                                        </th>
                                        <th>รหัสนักศึกษา</th>
                                        <th>ชื่อ-นามสกุล</th>
                                        <th>ระดับชั้น/ห้องเรียน</th>
                                        <th class="text-center" style="width: 1%; white-space: nowrap;">การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($enrolledStudents as $index => $student)
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" class="student-select">
                                            </td>
                                            <td>{{ $student->student_code }}</td>
                                            <td>{{ $student->name }}</td>
                                            <td>{{ $student->classroom ? $student->classroom->name : 'ไม่ได้ระบุ' }}</td>
                                            <td class="text-center" style="white-space: nowrap;">
                                                <!-- Link to outer HTML5 form using form attribute -->
                                                <button type="submit" form="delete-form-{{ $student->id }}" class="btn btn-sm btn-danger font-weight-bold shadow-xs" title="นำออก">
                                                    <i class="fas fa-user-minus mr-1"></i> นำออก
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">ยังไม่มีนักศึกษาในรายวิชานี้</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add Students Section (Right Column) -->
        <div class="col-lg-5">
            <!-- Add by Classroom Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-school mr-2 text-success"></i>เพิ่มนักศึกษาตามห้องเรียน
                    </h3>
                </div>
                <form action="{{ route('admin.subjects.students.store', $subject->id) }}" method="post">
                    @csrf
                    <input type="hidden" name="enroll_type" value="classroom">
                    <div class="card-body">
                        <div class="form-group mb-0">
                            <label class="font-weight-bold">เลือกห้องเรียน / ระดับชั้น</label>
                            <select name="classroom_id" class="form-control select2" required style="width: 100%;">
                                <option value="">-- เลือกห้องเรียน --</option>
                                @foreach($classrooms as $room)
                                    <option value="{{ $room->id }}">{{ $room->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="card-footer bg-light text-right">
                        <button type="submit" class="btn btn-success font-weight-bold btn-block">
                            <i class="fas fa-plus mr-2"></i>เพิ่มนักศึกษาทั้งห้องเรียน
                        </button>
                    </div>
                </form>
            </div>

            <!-- Add Individually Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-user-plus mr-2 text-info"></i>เพิ่มนักศึกษารายคน
                    </h3>
                </div>
                <form action="{{ route('admin.subjects.students.store', $subject->id) }}" method="post">
                    @csrf
                    <input type="hidden" name="enroll_type" value="individual">
                    <div class="card-body">
                        <div class="form-group mb-0">
                            <label class="font-weight-bold">เลือกนักศึกษา (เลือกได้หลายคน)</label>
                            <select name="student_ids[]" class="form-control select2" multiple="multiple" data-placeholder="ค้นหาชื่อหรือรหัสนักศึกษา..." required style="width: 100%;">
                                @foreach($availableStudents as $student)
                                    <option value="{{ $student->id }}">
                                        [{{ $student->student_code }}] {{ $student->name }} ({{ $student->classroom ? $student->classroom->name : 'ไม่มีห้อง' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="card-footer bg-light text-right">
                        <button type="submit" class="btn btn-info font-weight-bold btn-block text-white">
                            <i class="fas fa-plus mr-2"></i>เพิ่มนักศึกษาที่เลือก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            $('.select2').select2();

            var table = $('#enrolled-table').DataTable({
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
                // Find all checked boxes, including those on other Datatable pages
                var checkedCount = table.$('.student-select:checked').length;
                if (checkedCount > 0) {
                    $('#bulk-remove-btn').prop('disabled', false).text(`นำผู้ที่เลือกออก (${checkedCount})`);
                } else {
                    $('#bulk-remove-btn').prop('disabled', true).text('นำผู้ที่เลือกออก (0)');
                }
            }

            // Handle bulk remove click with SweetAlert confirmation
            $('#bulk-remove-btn').on('click', function(e) {
                e.preventDefault();
                var checkedCount = table.$('.student-select:checked').length;
                
                // Collect selected student IDs from all pages
                var selectedIds = [];
                table.$('.student-select:checked').each(function() {
                    selectedIds.push($(this).val());
                });

                if (selectedIds.length === 0) return;

                Swal.fire({
                    title: 'ยืนยันการทำรายการ',
                    text: `คุณต้องการนำนักศึกษาจำนวน ${checkedCount} คนที่เลือก ออกจากรายวิชานี้ใช่หรือไม่?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'ตกลง, นำออกทั้งหมด!',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Clear any old hidden inputs in the form
                        $('#bulk-remove-form').find('input[name="student_ids[]"]').remove();
                        
                        // Append selected IDs as hidden inputs to ensure we submit all pages' selections
                        selectedIds.forEach(function(id) {
                            $('#bulk-remove-form').append(
                                $('<input>')
                                    .attr('type', 'hidden')
                                    .attr('name', 'student_ids[]')
                                    .val(id)
                            );
                        });

                        $('#bulk-remove-form').submit();
                    }
                });
            });

            // SweetAlert Confirm Delete/Remove (Individual)
            $(document).on('submit', '.confirm-delete', function(e) {
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
                    confirmButtonText: 'ตกลง, นำออก!',
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
