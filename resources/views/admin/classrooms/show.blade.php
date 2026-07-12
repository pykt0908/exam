@extends('adminlte::page')

@section('title', 'นักศึกษาในห้องเรียน ' . $classroom->name)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="text-dark font-weight-bold">ห้องเรียน / ระดับชั้น: {{ $classroom->name }}</h1>
            <h5 class="text-muted mt-1"><i class="fas fa-users mr-1"></i>รายชื่อนักศึกษาในห้องนี้ทั้งหมด ({{ $students->count() }} คน)</h5>
        </div>
        <a href="{{ route('admin.classrooms.index') }}" class="btn btn-secondary font-weight-bold shadow-sm">
            <i class="fas fa-arrow-left mr-2"></i>กลับหน้าจัดการห้องเรียน
        </a>
    </div>
@stop

@section('content')
    <div class="card shadow-sm mt-3">
        <div class="card-header">
            <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-user-graduate mr-2"></i>รายชื่อนักศึกษา</h3>
        </div>
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="classroom-students-table" class="table table-bordered table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 5%">#</th>
                            <th style="width: 20%">รหัสนักศึกษา (Username)</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th style="width: 25%">เลขประจำตัวประชาชน (Password)</th>
                            <th style="width: 15%" class="text-center">สอบเสร็จสิ้น</th>
                            <th class="text-center" style="width: 15%">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($students as $index => $u)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $u->student_code }}</td>
                                <td>{{ $u->name }}</td>
                                <td>{{ $u->citizen_id }}</td>
                                <td class="text-center">
                                    <span class="badge badge-primary px-2 py-1">{{ $u->exam_attempts_count }} ครั้ง</span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('admin.users.show', $u->id) }}" class="btn btn-sm btn-info font-weight-bold shadow-xs mr-1" title="ดูประวัติการสอบ">
                                        <i class="fas fa-history mr-1"></i> ดูประวัติ
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">ไม่มีข้อมูลนักศึกษาในห้องเรียนนี้</td>
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
            $('#classroom-students-table').DataTable({
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
        });
    </script>
@stop
