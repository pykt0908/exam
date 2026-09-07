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
    <div class="card shadow-sm mt-3">
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="exams-table" class="table table-bordered table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 4%" class="text-center align-middle">#</th>
                            <th style="width: 16%" class="align-middle">รายวิชา</th>
                            <th style="width: 20%" class="align-middle">ชื่อข้อสอบ</th>
                            <th style="width: 13%" class="align-middle">เวลา / กำหนดการ</th>
                            <th style="width: 11%" class="text-center align-middle">คะแนน / เกณฑ์ผ่าน</th>
                            <th style="width: 7%" class="text-center align-middle">จำนวนข้อ</th>
                            <th style="width: 11%" class="text-center align-middle">สถานะอนุมัติ</th>
                            <th style="width: 8%" class="text-center align-middle">เปิดสอบ</th>
                            <th style="width: 10%" class="text-center align-middle" style="white-space: nowrap;">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($exams as $index => $exam)
                            <tr>
                                <td class="text-center align-middle text-muted">{{ $index + 1 }}</td>
                                <td class="align-middle">
                                    <div class="font-weight-bold text-dark">{{ $exam->subject->code }}</div>
                                    <div class="text-secondary text-sm">{{ $exam->subject->name }}</div>
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
                                    <div class="text-xs text-muted">เกณฑ์ผ่าน {{ $exam->passing_percentage }}% ({{ floatval($exam->total_score * $exam->passing_percentage / 100) }})</div>
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
                                        <form action="{{ route('admin.exams.toggle-status', $exam->id) }}" method="post" class="mb-1">
                                            @csrf
                                            <button type="submit" class="btn btn-xs {{ $exam->is_active ? 'btn-success' : 'btn-secondary' }} px-2 font-weight-bold" title="คลิกเพื่อสลับเปิด/ปิดสอบ">
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
                                        <form action="{{ route('admin.exams.submit-approval', $exam->id) }}" method="post" class="d-inline mr-1">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-primary font-weight-bold shadow-xs" title="ส่งขออนุมัติข้อสอบ">
                                                <i class="fas fa-paper-plane mr-1"></i>ขออนุมัติ
                                            </button>
                                        </form>
                                    @elseif($exam->isPending())
                                        <form action="{{ route('admin.exams.recall-approval', $exam->id) }}" method="post" class="d-inline mr-1">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-outline-warning font-weight-bold" title="ดึงกลับมาแก้ไข">
                                                <i class="fas fa-undo mr-1"></i>ดึงกลับ
                                            </button>
                                        </form>
                                    @endif

                                    <a href="{{ route('admin.exams.student-attempts.index', $exam->id) }}" class="btn btn-xs btn-outline-primary font-weight-bold shadow-xs mr-1" title="เปิดให้สอบเพิ่มรายคน / สิทธิ์สอบซ่อม">
                                        <i class="fas fa-user-clock mr-1"></i>สิทธิ์สอบ
                                    </a>
                                    <a href="{{ route('admin.exams.questions.index', $exam->id) }}" class="btn btn-xs btn-info font-weight-bold shadow-xs mr-1" title="จัดการโจทย์ ({{ $exam->questions_count }} ข้อ)">
                                        <i class="fas fa-edit mr-1"></i>โจทย์ ({{ $exam->questions_count }})
                                    </a>
                                    <form action="{{ route('admin.exams.duplicate', $exam->id) }}" method="post" class="d-inline mr-1 confirm-duplicate"
                                          data-text="ต้องการคัดลอกข้อสอบ '{{ $exam->title }}' ใช่หรือไม่? ข้อสอบชุดใหม่จะถูกสร้างเป็น 'ฉบับร่าง' และต้องยื่นขออนุมัติใหม่ก่อนเปิดใช้งาน">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-outline-secondary font-weight-bold shadow-xs" title="คัดลอกข้อสอบ (Duplicate)">
                                            <i class="fas fa-copy mr-1"></i>คัดลอก
                                        </button>
                                    </form>
                                    @if(auth()->user()->isAdmin() || $exam->canBeEdited())
                                    <form action="{{ route('admin.exams.destroy', $exam->id) }}" method="post" class="d-inline confirm-delete"
                                          data-text="คุณแน่ใจหรือไม่ที่จะลบข้อสอบนี้? ข้อมูลคำถาม คำตอบ และผลการสอบทั้งหมดของวิชานี้จะถูกลบไปด้วย!">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="btn btn-xs btn-outline-danger font-weight-bold shadow-xs" title="ลบข้อสอบ">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">ไม่มีข้อมูลข้อสอบในระบบ</td>
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
        $(document).ready(function() {
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
            $(document).on('submit', '.confirm-delete', function(e) {
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
            $(document).on('submit', '.confirm-duplicate', function(e) {
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
