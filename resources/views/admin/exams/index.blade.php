@extends('adminlte::page')

@section('title', 'จัดการข้อสอบ')

@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <h1 class="text-dark font-weight-bold">ข้อสอบ</h1>
    <a href="{{ route('admin.exams.create') }}" class="btn btn-primary font-weight-bold shadow-sm">
        <i class="fas fa-plus mr-2"></i>สร้างข้อสอบใหม่
    </a>
</div>
@stop

@section('content')
<!-- Filter Card -->
<div class="card shadow-sm mt-3 mb-3">
    <div class="card-header bg-light py-2">
        <div class="d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold text-dark mb-0">
                ค้นหาข้อสอบ
            </h3>
        </div>
    </div>
    <div class="card-body p-3">
        <form action="{{ route('admin.exams.index') }}" method="GET" id="examFilterForm">
            <div class="row align-items-end">
                @if(auth()->user()->isAdmin())
                    <div class="col-lg-3 col-md-6 col-sm-12 mb-2">
                        <label class="font-weight-bold text-dark text-md mb-1">
                            สาขาวิชา
                        </label>
                        <select name="department_id" id="filter_department_id" class="form-control"
                            onchange="filterByDepartment();">
                            <option value="">-- ทุกสาขาวิชา --</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ (string) ($selectedDepartmentId ?? request('department_id')) === (string) $dept->id ? 'selected' : '' }}>
                                    {{ $dept->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6 col-sm-12 mb-2">
                        <label class="font-weight-bold text-dark text-md mb-1">
                            ครูผู้สอน
                        </label>
                        <select name="teacher_id" id="filter_teacher_id" class="form-control" onchange="filterByTeacher();">
                            <option value="">-- {{ !empty($selectedDepartmentId) ? 'ครูทุกคนในสาขานี้' : 'คุณครูทุกคน' }} --
                            </option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}" {{ (string) request('teacher_id') === (string) $teacher->id ? 'selected' : '' }}>
                                    {{ $teacher->name }} {{ $teacher->teacher_code ? '(' . $teacher->teacher_code . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-lg-{{ auth()->user()->isAdmin() ? '3' : '6' }} col-md-6 col-sm-12 mb-2">
                    <label class="font-weight-bold text-dark text-md mb-1">
                        รายวิชา
                    </label>
                    <select name="subject_id" id="filter_subject_id" class="form-control"
                        onchange="$('#examFilterForm').submit();">
                        <option value="">--
                            {{ request('teacher_id') ? 'ทุกวิชาของครูท่านนี้' : (!empty($selectedDepartmentId) ? 'ทุกวิชาในสาขานี้' : 'ทุกรายวิชา') }}
                            --
                        </option>
                        @foreach($subjects as $subj)
                            <option value="{{ $subj->id }}" {{ (string) request('subject_id') === (string) $subj->id ? 'selected' : '' }}>
                                [{{ $subj->code }}] {{ $subj->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-{{ auth()->user()->isAdmin() ? '3' : '6' }} col-md-6 col-sm-12 mb-2">
                    <label class="font-weight-bold text-dark text-md mb-1">
                        ค้นหาข้อสอบ
                    </label>
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" value="{{ request('q') }}"
                            placeholder="ค้นหาชื่อข้อสอบ หรือรายละเอียด...">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary font-weight-bold" title="ค้นหา">
                                <i class="fas fa-search"></i>
                            </button>
                            @if(request('department_id') || request('subject_id') || request('teacher_id') || request('classroom_id') || request('q'))
                                <a href="{{ route('admin.exams.index') }}" class="btn btn-outline-secondary"
                                    title="ล้างค่าตัวกรอง">
                                    <i class="fas fa-undo"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                @if(request('subject_id') || request('department_id') || request('teacher_id') || request('q'))
                    <div class="col-12 mt-2 pt-2 border-top d-flex flex-wrap align-items-center text-sm">
                        <span class="text-muted mr-2"><i class="fas fa-filter mr-1"></i>ตัวกรองที่เลือก:</span>
                        @if(request('subject_id'))
                            @php $filterSubj = $subjects->firstWhere('id', request('subject_id')); @endphp
                            @if($filterSubj)
                                <span class="badge badge-primary mr-2 px-2 py-1 font-weight-normal">
                                    รายวิชา: [{{ $filterSubj->code }}] {{ $filterSubj->name }}
                                </span>
                            @endif
                        @endif
                        @if(request('department_id'))
                            @php $filterDept = $departments->firstWhere('id', request('department_id')); @endphp
                            @if($filterDept)
                                <span class="badge badge-secondary mr-2 px-2 py-1 font-weight-normal">
                                    สาขาวิชา: {{ $filterDept->name }}
                                </span>
                            @endif
                        @endif
                        @if(request('teacher_id'))
                            @php $filterTeach = $teachers->firstWhere('id', request('teacher_id')); @endphp
                            @if($filterTeach)
                                <span class="badge badge-info mr-2 px-2 py-1 font-weight-normal">
                                    ครูผู้สอน: {{ $filterTeach->name }}
                                </span>
                            @endif
                        @endif
                        @if(request('q'))
                            <span class="badge badge-light border mr-2 px-2 py-1 font-weight-normal">
                                ค้นหา: "{{ request('q') }}"
                            </span>
                        @endif
                        <a href="{{ route('admin.exams.index') }}" class="text-danger ml-auto font-weight-bold text-sm">
                            <i class="fas fa-times-circle mr-1"></i>ล้างตัวกรองทั้งหมด
                        </a>
                    </div>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mt-3">
    <div class="card-body p-3">
        <div class="table-responsive">
            <table id="exams-table" class="table table-bordered table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 4%" class="text-center align-middle">#</th>
                        <th style="width: 19%" class="align-middle">รายวิชา</th>
                        <th style="width: 20%" class="align-middle">ชื่อข้อสอบ</th>
                        <th style="width: 10%" class="align-middle">เวลา</th>
                        <th style="width: 11%" class="text-center align-middle">คะแนน / เกณฑ์ผ่าน</th>
                        <th style="width: 7%" class="text-center align-middle">จำนวนข้อ</th>
                        <th style="width: 11%" class="text-center align-middle">สถานะอนุมัติ</th>
                        <th style="width: 8%" class="text-center align-middle">เปิดสอบ</th>
                        <th style="width: 10%" class="text-center align-middle" style="white-space: nowrap;">การจัดการ
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($exams as $index => $exam)
                        <tr>
                            <td class="text-center align-middle text-muted">{{ $index + 1 }}</td>
                            <td class="align-middle">
                                <div class="font-weight-bold text-dark">{{ $exam->subject->code }} <br>
                                    {{ $exam->subject->name }}
                                </div>
                                <div class="text-muted text-xs mt-1">
                                    <span class="text-dark font-weight-normal" title="ครูผู้สอน / ผู้รับผิดชอบ">
                                        อาจารย์ผู้สอน: <br>{{ $exam->creator_name }}
                                    </span>
                                </div>
                            </td>
                            <td class="align-middle">
                                <span class="font-weight-bold text-dark">{{ $exam->title }}</span>
                            </td>
                            <td class="align-middle">
                                <div class="font-weight-bold text-dark">{{ $exam->duration_minutes }} นาที</div>
                                @if($exam->starts_at || $exam->ends_at)
                                    <div class="text-xs text-muted mt-1" style="line-height: 1.3;">
                                        <div>{{ $exam->starts_at ? $exam->starts_at->format('d/m/y H:i') : 'เริ่มทันที' }}</div>
                                        <div>{{ $exam->ends_at ? $exam->ends_at->format('d/m/y H:i') : 'ไม่จำกัด' }}</div>
                                    </div>
                                @endif
                            </td>
                            <td class="text-center align-middle">
                                <div class="font-weight-bold text-dark">{{ floatval($exam->total_score) }} คะแนน</div>
                                <div class="text-xs text-muted">เกณฑ์ผ่าน {{ $exam->passing_percentage }}%
                                    ({{ floatval($exam->total_score * $exam->passing_percentage / 100) }})</div>
                            </td>
                            <td class="text-center align-middle font-weight-bold">
                                {{ $exam->questions_count }} ข้อ
                            </td>
                            <td class="text-center align-middle" style="white-space: nowrap;">
                                @if($exam->approval_status === 'draft')
                                    <span class="text-secondary font-weight-bold">ฉบับร่าง</span>
                                @elseif($exam->approval_status === 'pending_dept')
                                    <span class="text-warning font-weight-bold">รอหัวหน้าหมวด</span>
                                @elseif($exam->approval_status === 'pending_eval')
                                    <span class="text-primary font-weight-bold">รอหัวหน้างานวัดผล</span>
                                @elseif($exam->approval_status === 'pending_academic')
                                    <span class="text-info font-weight-bold">รอรองฝ่ายวิชาการ</span>
                                @elseif($exam->approval_status === 'approved')
                                    <span class="text-success font-weight-bold">อนุมัติแล้ว</span>
                                @elseif($exam->approval_status === 'rejected')
                                    <span class="text-danger font-weight-bold">ส่งกลับแก้ไข</span>
                                @else
                                    <span class="text-muted">{{ $exam->approval_status }}</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" style="white-space: nowrap;">
                                @if($exam->isApproved())
                                    <form action="{{ route('admin.exams.toggle-status', $exam->id) }}" method="post"
                                        class="mb-1">
                                        @csrf
                                        <button type="submit"
                                            class="btn btn-xs {{ $exam->is_active ? 'btn-success' : 'btn-secondary' }} px-2 font-weight-bold"
                                            title="คลิกเพื่อสลับเปิด/ปิดสอบ">
                                            {{ $exam->is_active ? 'เปิดสอบ' : 'ปิดสอบ' }}
                                        </button>
                                    </form>
                                    @if($exam->is_active)
                                        @if($exam->isUpcoming())
                                            <span class="text-warning small d-block font-weight-bold">ยังไม่ถึงเวลา</span>
                                        @elseif($exam->isExpired())
                                            <span class="text-danger small d-block font-weight-bold">หมดเวลา</span>
                                        @else
                                            <span class="text-success small font-weight-bold d-block">พร้อมสอบ</span>
                                        @endif
                                    @endif
                                @else
                                    <span class="text-secondary small font-weight-bold">ยังไม่อนุมัติ</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" style="white-space: nowrap;">
                                @if($exam->isDraft() || $exam->isRejected())
                                    <form action="{{ route('admin.exams.submit-approval', $exam->id) }}" method="post"
                                        class="d-inline mr-1">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-primary font-weight-bold shadow-xs"
                                            title="ส่งขออนุมัติข้อสอบ">
                                            <i class="fas fa-paper-plane mr-1"></i>ขออนุมัติ
                                        </button>
                                    </form>
                                @elseif($exam->isPending())
                                    <form action="{{ route('admin.exams.recall-approval', $exam->id) }}" method="post"
                                        class="d-inline mr-1">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-outline-warning font-weight-bold"
                                            title="ดึงกลับมาแก้ไข">
                                            <i class="fas fa-undo mr-1"></i>ดึงกลับ
                                        </button>
                                    </form>
                                @endif

                                <a href="{{ route('admin.exams.student-attempts.index', $exam->id) }}"
                                    class="btn btn-xs btn-outline-primary font-weight-bold shadow-xs mr-1"
                                    title="เปิดให้สอบเพิ่มรายคน / สิทธิ์สอบซ่อม">
                                    <i class="fas fa-user-clock mr-1"></i>สิทธิ์สอบ
                                </a>
                                <a href="{{ route('admin.exams.questions.index', $exam->id) }}"
                                    class="btn btn-xs btn-info font-weight-bold shadow-xs mr-1"
                                    title="จัดการโจทย์ ({{ $exam->questions_count }} ข้อ)">
                                    <i class="fas fa-edit mr-1"></i>โจทย์ ({{ $exam->questions_count }})
                                </a>
                                <div class="btn-group mr-1">
                                    <button type="button" class="btn btn-xs btn-outline-info font-weight-bold shadow-xs dropdown-toggle"
                                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="ดูตัวอย่างข้อสอบ">
                                        <i class="fas fa-eye mr-1"></i>ตัวอย่าง
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right shadow border-0 py-1" style="min-width: 250px;">
                                        <h6 class="dropdown-header font-weight-bold text-dark border-bottom pb-2 mb-1">
                                            <i class="fas fa-eye mr-1 text-info"></i>เลือกมุมมองดูตัวอย่างข้อสอบ
                                        </h6>
                                        <a class="dropdown-item py-2" href="{{ route('admin.exams.preview', [$exam->id, 'mode' => 'approval']) }}" target="_blank">
                                            <i class="fas fa-clipboard-check text-primary mr-2"></i>
                                            <strong>1. เหมือนตอนอนุมัติ</strong>
                                            <div class="text-xs text-muted pl-4">กระดาษข้อสอบ พร้อมเฉลยและเกณฑ์คะแนน</div>
                                        </a>
                                        <div class="dropdown-divider my-1"></div>
                                        <a class="dropdown-item py-2" href="{{ route('admin.exams.preview', [$exam->id, 'mode' => 'take']) }}" target="_blank">
                                            <i class="fas fa-user-graduate text-success mr-2"></i>
                                            <strong>2. มุมมองตอนทำข้อสอบ</strong>
                                            <div class="text-xs text-muted pl-4">ทดลองทำข้อสอบจริงได้ (ไม่เก็บผลสอบ)</div>
                                        </a>
                                    </div>
                                </div>
                                <form action="{{ route('admin.exams.duplicate', $exam->id) }}" method="post"
                                    class="d-inline mr-1 confirm-duplicate"
                                    data-text="ต้องการคัดลอกข้อสอบ '{{ $exam->title }}' ใช่หรือไม่? ข้อสอบชุดใหม่จะถูกสร้างเป็น 'ฉบับร่าง' และต้องยื่นขออนุมัติใหม่ก่อนเปิดใช้งาน">
                                    @csrf
                                    <button type="submit"
                                        class="btn btn-xs btn-outline-secondary font-weight-bold shadow-xs"
                                        title="คัดลอกข้อสอบ (Duplicate)">
                                        <i class="fas fa-copy mr-1"></i>คัดลอก
                                    </button>
                                </form>
                                @if(auth()->user()->isAdmin() || $exam->canBeEdited())
                                    <form action="{{ route('admin.exams.destroy', $exam->id) }}" method="post"
                                        class="d-inline confirm-delete"
                                        data-text="คุณแน่ใจหรือไม่ที่จะลบข้อสอบนี้? ข้อมูลคำถาม คำตอบ และผลการสอบทั้งหมดของวิชานี้จะถูกลบไปด้วย!">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="btn btn-xs btn-outline-danger font-weight-bold shadow-xs"
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
                                @if(request('department_id') || request('subject_id') || request('teacher_id') || request('classroom_id') || request('q'))
                                    <h6 class="font-weight-bold text-dark mb-1">ไม่พบข้อสอบที่ตรงกับเงื่อนไขการค้นหา</h6>
                                    <p class="text-muted text-sm mb-3">ลองเปลี่ยนหรือล้างเงื่อนไขตัวกรองเพื่อดูข้อสอบชุดอื่น</p>
                                    <a href="{{ route('admin.exams.index') }}"
                                        class="btn btn-sm btn-outline-primary font-weight-bold">
                                        <i class="fas fa-undo mr-1"></i>ล้างตัวกรองทั้งหมด
                                    </a>
                                @else
                                    <h6 class="font-weight-bold text-dark mb-1">ไม่มีข้อมูลข้อสอบในระบบ</h6>
                                    <p class="text-muted text-sm">คลิกปุ่ม "สร้างข้อสอบใหม่" ด้านบนเพื่อเริ่มต้นสร้างข้อสอบ</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
    function filterByDepartment() {
        $('#filter_teacher_id').val('');
        $('#filter_subject_id').val('');
        $('#examFilterForm').submit();
    }

    function filterByTeacher() {
        $('#filter_subject_id').val('');
        $('#examFilterForm').submit();
    }

    $(document).ready(function () {
        $('#exams-table').DataTable({
            "pageLength": -1,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "ทั้งหมด"]],
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Thai.json"
            },
            "responsive": true,
            "autoWidth": false
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

        // Confirm Delete Handler
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