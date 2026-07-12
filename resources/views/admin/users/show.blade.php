@extends('adminlte::page')

@section('title', 'ประวัติผู้ใช้งาน')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="text-dark font-weight-bold">ข้อมูลประวัติผู้ใช้งาน</h1>
            <h5 class="text-muted mt-1">ชื่อ: <strong>{{ $user->name }}</strong> 
                @if($user->isStudent())
                    (รหัสนักศึกษา: <code>{{ $user->student_code }}</code>)
                @else
                    (รหัสประจำตัวครู: <code>{{ $user->teacher_code ?? '-' }}</code>)
                @endif
            </h5>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary font-weight-bold shadow-sm">
            <i class="fas fa-arrow-left mr-2"></i>กลับหน้ารายชื่อผู้ใช้งาน
        </a>
    </div>
@stop

@section('content')
    <div class="row">
        <!-- User Info Card -->
        <div class="col-md-4">
            <div class="card shadow-sm text-center py-4">
                <div class="card-body">
                    <div class="mb-4">
                        @if($user->isAdmin())
                            <i class="fas fa-user-shield fa-6x text-danger"></i>
                        @elseif($user->isTeacher())
                            <i class="fas fa-user-tie fa-6x text-warning"></i>
                        @else
                            <i class="fas fa-user-graduate fa-6x text-success"></i>
                        @endif
                    </div>
                    <h4 class="font-weight-bold text-dark mb-1">{{ $user->name }}</h4>
                    <p class="text-muted mb-3">บทบาท: 
                        @if($user->isAdmin())
                            <span class="badge badge-danger">ผู้ดูแลระบบ (Admin)</span>
                        @elseif($user->isTeacher())
                            <span class="badge badge-warning text-white">อาจารย์ (Teacher)</span>
                        @else
                            <span class="badge badge-success">นักศึกษา (Student)</span>
                        @endif
                    </p>
                    <ul class="list-group list-group-unbordered mb-3 text-left">
                        @if($user->isStudent())
                            <li class="list-group-item">
                                <b>รหัสนักศึกษา</b> <a class="float-right text-dark"><code>{{ $user->student_code }}</code></a>
                            </li>
                            <li class="list-group-item">
                                <b>ห้องเรียน / ระดับชั้น</b> <a class="float-right text-dark"><span class="badge badge-info">{{ $user->classroom ? $user->classroom->name : 'ไม่ได้ระบุ' }}</span></a>
                            </li>
                            <li class="list-group-item">
                                <b>เลขบัตรประชาชน (Password)</b> <a class="float-right text-dark"><code>{{ $user->citizen_id }}</code></a>
                            </li>
                            <li class="list-group-item">
                                <b>จำนวนสอบทั้งหมด</b> <a class="float-right text-primary font-weight-bold">{{ $attempts->count() }} ครั้ง</a>
                            </li>
                        @else
                            <li class="list-group-item">
                                <b>รหัสประจำตัวครู</b> <a class="float-right text-dark"><code>{{ $user->teacher_code ?? '-' }}</code></a>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>

        <!-- Student Attempts History -->
        @if($user->isStudent())
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-history mr-2"></i>ประวัติผลการเข้าสอบทั้งหมด</h3>
                    </div>
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="attempts-table" class="table table-bordered table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>วิชา/ข้อสอบ</th>
                                        <th class="text-center">เวลาเริ่มสอบ</th>
                                        <th class="text-center">เวลาเสร็จสิ้น</th>
                                        <th class="text-center">คะแนน</th>
                                        <th class="text-center">ผลสอบ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($attempts as $index => $attempt)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                <span class="badge badge-secondary mb-1">{{ $attempt->exam->subject->code }}</span><br>
                                                <strong>{{ $attempt->exam->title }}</strong>
                                            </td>
                                            <td class="text-center text-sm">{{ $attempt->started_at->format('d/m/Y H:i') }} น.</td>
                                            <td class="text-center text-sm">
                                                @if($attempt->completed_at)
                                                    {{ $attempt->completed_at->format('d/m/Y H:i') }} น.
                                                @else
                                                    <span class="text-danger">ไม่สมบูรณ์</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($attempt->status === 'completed')
                                                    <strong class="text-primary">{{ $attempt->score }}</strong> / {{ $attempt->exam->questions()->sum('score') }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($attempt->status === 'completed')
                                                    @if($attempt->is_passed)
                                                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> ผ่านเกณฑ์</span>
                                                    @else
                                                        <span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i> ไม่ผ่าน</span>
                                                    @endif
                                                @else
                                                    <span class="badge badge-warning px-2 py-1">กำลังดำเนินการ</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">ยังไม่มีประวัติการสอบของนักศึกษาคนนี้</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body py-5 text-center text-muted">
                        <i class="fas fa-info-circle fa-4x mb-3 text-secondary"></i>
                        <h4 class="font-weight-bold">ข้อมูลไม่มีประวัติการสอบ</h4>
                        <p>บัญชีประเภทผู้ดูแลระบบและอาจารย์จะไม่มีข้อมูลประวัติผลการทำข้อสอบในระบบ</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            $('#attempts-table').DataTable({
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
                    text: '{{ session('success') }}',
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
                    html: '{!! implode("<br>", $errors->all()) !!}',
                    confirmButtonText: 'ตกลง'
                });
            @endif
        });
    </script>
@stop
