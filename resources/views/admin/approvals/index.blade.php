@extends('adminlte::page')

@section('title', 'พิจารณาอนุมัติข้อสอบ')

@section('plugins.Datatables', true)
@section('plugins.Sweetalert2', true)

@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h1 class="text-dark font-weight-bold">พิจารณาอนุมัติข้อสอบ</h1>
        <p class="text-dark text-md mb-0">ระบบพิจารณาและตรวจสอบข้อสอบตามลำดับขั้น (หัวหน้าหมวด &rarr; หัวหน้างานวัดผล
            &rarr; รองผู้อำนวยการฝ่ายวิชาการ)</p>
    </div>
</div>
@stop

@section('content')
<!-- Filter Tabs -->
<div class="card card-primary card-outline card-outline-tabs shadow-sm">
    <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs" id="approvalTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'my_pending' ? 'active' : '' }} font-weight-bold"
                    href="{{ route('admin.approvals.index', ['tab' => 'my_pending']) }}">
                    รอการพิจารณาของฉัน
                    @if($myPendingCount > 0)
                        <span class="badge badge-warning ml-1">{{ $myPendingCount }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'approved' ? 'active' : '' }} font-weight-bold"
                    href="{{ route('admin.approvals.index', ['tab' => 'approved']) }}">
                    อนุมัติแล้ว
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'rejected' ? 'active' : '' }} font-weight-bold"
                    href="{{ route('admin.approvals.index', ['tab' => 'rejected']) }}">
                    ส่งกลับแก้ไข
                </a>
            </li>
        </ul>
    </div>
    <div class="card-body p-3">
        <div class="table-responsive">
            <table id="approvals-table" class="table table-bordered table-striped table-hover mb-0"
                style="width: 100%;">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 5%" class="text-center align-middle">#</th>
                        <th style="width: 22%" class="align-middle">รายวิชา / แผนก</th>
                        <th style="width: 24%" class="align-middle">ชื่อข้อสอบ</th>
                        <th style="width: 15%" class="align-middle">ผู้สร้างข้อสอบ</th>
                        <th style="width: 14%" class="text-center align-middle">สถานะการอนุมัติ</th>
                        <th style="width: 10%" class="text-center align-middle">จำนวนข้อ / คะแนน</th>
                        <th style="width: 10%" class="text-center align-middle">การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($exams as $index => $exam)
                        <tr>
                            <td class="text-center align-middle text-muted">{{ $loop->iteration }}</td>
                            <td class="align-middle">
                                <div class="font-weight-bold text-dark">{{ $exam->subject->code }}
                                    {{ $exam->subject->name }}</div>
                                @if($exam->subject->department)
                                    <div class="text-secondary text-sm">{{ $exam->subject->department->name }}</div>
                                @endif
                            </td>
                            <td class="align-middle">
                                <div class="font-weight-bold text-dark">{{ $exam->title }}</div>
                                <div class="text-secondary text-sm">{{ $exam->duration_minutes }} นาที (เกณฑ์ผ่าน
                                    {{ $exam->passing_percentage }}%)</div>
                            </td>
                            <td class="align-middle">
                                <div class="text-dark">{{ $exam->creator_name ?? '-' }}</div>
                                <div class="text-secondary text-xs">
                                    {{ $exam->created_at ? $exam->created_at->format('d/m/Y H:i น.') : '-' }}</div>
                            </td>
                            <td class="text-center align-middle">
                                <div class="font-weight-bold text-dark">{{ $exam->approval_status_label }}</div>
                                @if($exam->rejection_reason)
                                    <div class="text-danger text-xs mt-1 text-left" title="{{ $exam->rejection_reason }}">
                                        {{ \Illuminate\Support\Str::limit($exam->rejection_reason, 40) }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-center align-middle">
                                <div>{{ $exam->questions_count }} ข้อ</div>
                                <div class="text-secondary text-sm">{{ floatval($exam->total_score) }} คะแนน</div>
                            </td>
                            <td class="text-center align-middle" style="white-space: nowrap;">
                                <a href="{{ route('admin.approvals.preview', $exam->id) }}"
                                    class="btn btn-sm btn-warning font-weight-bold">
                                    ตรวจข้อสอบ
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                @if($tab === 'my_pending')
                                    ไม่มีรายการข้อสอบที่รอให้คุณพิจารณาในขณะนี้
                                @elseif($tab === 'approved')
                                    ยังไม่มีรายการข้อสอบที่ผ่านการอนุมัติ
                                @else
                                    ไม่มีรายการข้อสอบที่ถูกส่งกลับแก้ไข
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
    $(document).ready(function () {
        $('#approvals-table').DataTable({
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "ทั้งหมด"]],
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Thai.json"
            },
            "responsive": true,
            "autoWidth": false,
            "columnDefs": [
                { "orderable": false, "targets": [6] }
            ]
        });

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