@extends('adminlte::page')

@section('title', 'อาจารย์ในหมวดวิชา ' . $department->name)

@section('css')
<style>
    #department-teachers-table th, 
    #department-teachers-table td {
        padding: 0.45rem 0.6rem !important;
        vertical-align: middle !important;
    }
    #department-teachers-table .btn-sm {
        padding: 0.2rem 0.45rem;
        font-size: 0.8rem;
    }
</style>
@stop

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="text-dark font-weight-bold">หมวดวิชา: {{ $department->name }}</h1>
            <div class="mt-2">
                @php
                    $head = $department->headTeacher();
                @endphp
                <span class="mr-3">
                    <strong>หัวหน้าสาขา:</strong>
                    @if($head)
                        <span class="badge badge-success px-2 py-1"><i class="fas fa-crown mr-1"></i> {{ $head->name }}</span>
                    @else
                        <span class="badge badge-secondary px-2 py-1"><i class="fas fa-user-slash mr-1"></i> ยังไม่ได้กำหนดหัวหน้าสาขา</span>
                    @endif
                </span>
                <span class="text-muted"><i class="fas fa-users mr-1"></i>อาจารย์ในหมวดทั้งหมด ({{ $teachers->count() }} คน)</span>
            </div>
        </div>
        <a href="{{ route('admin.departments.index') }}" class="btn btn-secondary font-weight-bold shadow-sm">
            <i class="fas fa-arrow-left mr-2"></i>กลับหน้าหมวดวิชา
        </a>
    </div>
@stop

@section('content')
    <div class="card shadow-sm mt-3">
        <div class="card-header">
            <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-user-tie mr-2"></i>รายชื่ออาจารย์ในหมวดวิชา</h3>
        </div>
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="department-teachers-table" class="table table-bordered table-striped table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th style="width: 5%">#</th>
                            <th style="width: 20%">รหัสประจำตัวครู (Username)</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th style="width: 25%">ตำแหน่ง / บทบาทหน้าที่</th>
                            <th style="width: 15%" class="text-center">จำนวนวิชาที่สอน</th>
                            <th class="text-center" style="width: 15%">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($teachers as $index => $u)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $u->teacher_code ?? '-' }}</td>
                                <td>{{ $u->name }}</td>
                                <td>
                                    @if($u->isAdmin())
                                        ผู้ดูแลระบบ
                                    @elseif($u->isTeacher())
                                        อาจารย์
                                    @else
                                        -
                                    @endif
                                    @if($u->academic_role)
                                        ({{ $u->academic_role_label }})
                                    @endif
                                </td>
                                <td class="text-center">{{ $u->enrolled_subjects_count }} วิชา</td>
                                <td class="text-center">
                                    <a href="{{ route('admin.users.edit', $u->id) }}" class="btn btn-sm btn-warning font-weight-bold text-white shadow-xs" title="แก้ไขข้อมูลอาจารย์">
                                        <i class="fas fa-edit mr-1"></i> แก้ไข
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">ยังไม่มีอาจารย์ในหมวดวิชานี้</td>
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
            $('#department-teachers-table').DataTable({
                "pageLength": -1,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "ทั้งหมด"]],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Thai.json"
                },
                "responsive": true,
                "autoWidth": false,
                "order": []
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
