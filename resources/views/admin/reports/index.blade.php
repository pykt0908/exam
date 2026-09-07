@extends('adminlte::page')

@section('title', 'รายงานผลคะแนน')

@section('plugins.Datatables', true)
@section('plugins.Sweetalert2', true)

@section('css')
<style>
    /* Gradebook Vertical Headers */
    .table-gradebook thead th {
        vertical-align: bottom !important;
        padding-bottom: 8px;
    }

    .th-exam-vertical {
        height: 220px;
        vertical-align: bottom !important;
        padding: 8px 4px 8px 4px !important;
    }

    .th-exam-vertical-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-end;
        height: 100%;
    }

    .th-exam-title-vertical {
        writing-mode: vertical-rl;
        transform: rotate(180deg);
        white-space: nowrap;
        text-align: left;
        max-height: 200px;
        font-weight: 700;
        font-size: 0.95rem;
        letter-spacing: 0.3px;
        color: #2c3e50;
        padding: 0 4px;
    }
</style>
@stop

@section('content_header')
<h1 class="text-dark font-weight-bold">รายงานผลคะแนนสอบ</h1>
@stop

@section('content')
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h3 class="card-title font-weight-bold text-dark mb-0"><i class="fas fa-filter mr-2"></i>ตัวกรองรายงานผลคะแนน
        </h3>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.reports.index') }}" method="GET" class="row align-items-end">
            <div class="form-group col-md-5 mb-2">
                <label class="font-weight-bold text-dark text-md mb-1">
                    รายวิชา
                </label>
                <select name="subject_id" class="form-control font-weight-bold" onchange="this.form.submit()">
                    <option value="">
                        {{ (auth()->user()->isTeacher() && $subjects->isEmpty()) ? '-- ไม่มีรายวิชาที่รับผิดชอบ --' : '-- เลือกรายวิชาเพื่อดูผลคะแนน --' }}
                    </option>
                    @foreach($subjects as $sub)
                        <option value="{{ $sub->id }}" {{ (isset($selectedSubject) && $selectedSubject && $selectedSubject->id == $sub->id) ? 'selected' : '' }}>
                            [{{ $sub->code }}] {{ $sub->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group col-md-4 mb-2">
                <label class="font-weight-bold text-dark text-md mb-1">
                    ห้องเรียน
                </label>
                <select name="classroom_id" class="form-control" onchange="this.form.submit()">
                    <option value="">-- ทุกห้องเรียน (แสดงทั้งหมด) --</option>
                    @foreach($classrooms as $c)
                        <option value="{{ $c->id }}" {{ request('classroom_id') == $c->id ? 'selected' : '' }}>
                            {{ $c->name }}
                        </option>
                    @endforeach
                </select>
            </div>



            <div class="form-group col-md-3 mb-2">
                <label class="font-weight-bold text-dark text-md mb-1">
                    ค้นหา
                </label>
                <div class="input-group">
                    <input type="text" name="q" class="form-control" value="{{ request('q') }}"
                        placeholder="ชื่อ หรือ รหัสนักศึกษา...">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-primary font-weight-bold" title="ค้นหา">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>

            @if(request('subject_id') || request('classroom_id') || request('q'))
                <div class="col-12 mt-1">
                    <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times mr-1"></i>ล้างตัวกรองทั้งหมด
                    </a>
                </div>
            @endif
        </form>
    </div>
</div>

@if(!$hasSearchedOrSelected)
    <div class="card shadow-sm py-5">
        <div class="card-body text-center">
            @if(auth()->user()->isTeacher() && $subjects->isEmpty())
                <i class="fas fa-book-open text-muted fa-3x mb-3"></i>
                <h5 class="text-secondary font-weight-bold">ไม่พบรายวิชาที่รับผิดชอบ</h5>
                <p class="text-muted text-md">คุณยังไม่มีรายวิชาที่รับผิดชอบการสอน กรุณาติดต่อผู้ดูแลระบบเพื่อมอบหมายรายวิชา</p>
            @else
                <i class="fas fa-book text-muted fa-3x mb-3"></i>
                <h5 class="text-secondary font-weight-bold">ยังไม่ได้เลือกรายวิชา</h5>
                <p class="text-muted text-md">กรุณาเลือกรายวิชาเพื่อดูรายงานสรุปผลคะแนนและสถิติ</p>
            @endif
        </div>
    </div>
@else
    <!-- Matrix Gradebook Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-2 px-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="d-flex align-items-center flex-wrap my-1">
                    @if($selectedSubject)
                        <span
                            class="font-weight-bold text-dark text-md px-2 py-1">{{ $selectedSubject->code }}</span>
                        <span class="font-weight-bold text-dark text-md px-2 py-1">{{ $selectedSubject->name }}</span>
                    @else
                        <h3 class="card-title font-weight-bold text-dark mb-0">
                            ตารางคะแนนสอบรายวิชา
                        </h3>
                    @endif
                </div>
                <div class="d-flex align-items-center flex-wrap my-1">
                    @if(request('classroom_id'))
                        <span class="text-dark text-md px-2 py-1 font-weight-bold">ห้อง {{ $classrooms->firstWhere('id', request('classroom_id'))->name ?? 'ที่เลือก' }}</span>
                    @else
                        <span class="text-dark text-md font-weight-bold">แสดงทุกห้องเรียน</span>
                    @endif
                    <a href="{{ route('admin.reports.export', request()->query()) }}" class="btn btn-sm btn-success font-weight-bold ml-2 shadow-sm" title="ส่งออกผลคะแนนเป็นไฟล์ Excel (.xlsx)">
                        <i class="fas fa-file-excel mr-1"></i> ส่งออก Excel
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="reportsTable" class="table table-bordered table-hover mb-0 text-center table-gradebook">
                    <thead class="bg-light text-dark">
                        <tr>
                            <th style="width: 50px;" class="align-bottom pb-3">#</th>
                            <th style="width: 140px;" class="align-bottom text-left pb-3">รหัสนักศึกษา</th>
                            <th style="width: 180px;" class="align-bottom text-left pb-3">ชื่อ-นามสกุล</th>
                            <th style="width: 110px;" class="align-bottom pb-3">กลุ่มเรียน</th>
                            @forelse($subjectExams as $exam)
                                <th class="th-exam-vertical text-center" style="min-width: 130px; width: 150px;">
                                    <div class="th-exam-vertical-content">
                                        <div class="th-exam-title-vertical" title="{{ $exam->title }}">
                                            {{ $exam->title }}
                                        </div>
                                    </div>
                                </th>
                            @empty
                                <th class="align-bottom text-center text-muted pb-3" style="min-width: 200px;">
                                    ยังไม่มีชุดข้อสอบในวิชานี้
                                </th>
                            @endforelse

                        </tr>
                    </thead>
                    <tbody>
                        @forelse($students as $index => $student)
                            <tr>
                                <td class="align-middle text-center">{{ $index + 1 }}</td>
                                <td class="align-middle text-left">{{ $student->student_code ?? '-' }}</td>
                                <td class="align-middle text-left">{{ $student->name ?? '-' }}</td>
                                <td class="align-middle text-center">{{ $student->classroom->name ?? '-' }}</td>

                                @forelse($subjectExams as $exam)
                                    @php
                                        $examAttempts = $attemptsMatrix->get($student->id . '_' . $exam->id, collect());
                                        $totalRoundsCount = $examAttempts->count();
                                        $totalScore = floatval($exam->total_score);
                                    @endphp
                                    <td class="align-middle text-center">
                                        @if($totalRoundsCount === 0)
                                            -
                                        @else
                                            @php
                                                $bestAttempt = $examAttempts->sortByDesc('score')->first();
                                                $percentage = ($bestAttempt->total_raw_score > 0 && $bestAttempt->raw_score !== null)
                                                    ? round(($bestAttempt->raw_score / $bestAttempt->total_raw_score) * 100, 2)
                                                    : ($totalScore > 0 ? round(($bestAttempt->score / $totalScore) * 100, 2) : 0);
                                                $roundsJson = json_encode($examAttempts->map(fn($r) => [
                                                    'id' => $r->id,
                                                    'round' => $r->attempt_number,
                                                    'score' => floatval($r->score),
                                                    'raw_score' => $r->raw_score !== null ? floatval($r->raw_score) : null,
                                                    'total_raw_score' => $r->total_raw_score !== null ? floatval($r->total_raw_score) : null,
                                                    'is_passed' => (bool) $r->is_passed,
                                                    'completed_at' => $r->completed_at ? $r->completed_at->format('d/m/Y H:i น.') : '-'
                                                ]));
                                                $roundsTooltip = 'คะแนนแต่ละรอบ: ';
                                                foreach ($examAttempts as $r) {
                                                    $roundsTooltip .= 'รอบที่ ' . $r->attempt_number . ': ' . floatval($r->score) . ' (' . ($r->is_passed ? 'ผ่าน' : 'ไม่ผ่าน') . ') ';
                                                }
                                                $roundsTooltip .= '(ประวัติ ' . $totalRoundsCount . ' รอบ)';
                                            @endphp

                                            <span class="view-rounds-btn" style="cursor: pointer;"
                                                title="{{ $roundsTooltip }}" data-student="{{ $student->name ?? '-' }}"
                                                data-code="{{ $student->student_code ?? '-' }}" data-student-id="{{ $student->id }}"
                                                data-classroom="{{ $student->classroom->name ?? '-' }}"
                                                data-exam="{{ $exam->title ?? '-' }}" data-exam-id="{{ $exam->id }}"
                                                data-total="{{ floatval($totalScore) }}"
                                                data-is-passed="{{ $bestAttempt->is_passed ? '1' : '0' }}"
                                                data-rounds="{{ $roundsJson }}">
                                                {{ floatval($bestAttempt->score) }}
                                            </span>
                                        @endif
                                    </td>
                                @empty
                                    <td class="align-middle text-center">-</td>
                                @endforelse
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 4 + $subjectExams->count() }}"
                                    class="text-center py-4 text-muted">
                                    ไม่พบข้อมูลนักศึกษาในเงื่อนไขที่เลือก
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: Rounds History -->
    <div class="modal fade" id="roundsHistoryModal" tabindex="-1" role="dialog" aria-labelledby="roundsHistoryModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title font-weight-bold" id="roundsHistoryModalLabel">
                        <i class="fas fa-history mr-2"></i>ประวัติคะแนนการสอบแต่ละรอบ
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="bg-light p-3 rounded mb-3 border">
                        <div class="mb-1">นักศึกษา: <strong class="text-dark" id="modal-rh-student"></strong> (<code
                                id="modal-rh-code"></code>) <span id="modal-rh-classroom" class="text-muted text-sm ml-1"></span></div>
                        <div>ชุดข้อสอบ: <strong class="text-primary" id="modal-rh-exam"></strong></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped text-center mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 15%;">รอบที่</th>
                                    <th>คะแนนที่ได้</th>
                                    <th>ผลการสอบ</th>
                                    <th>วัน-เวลาที่ส่งข้อสอบ</th>
                                    @if(auth()->user()->isAdmin())
                                        <th style="width: 12%;">จัดการ</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody id="modal-rh-body">
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between align-items-center">
                    <div id="modal-rh-actions"></div>
                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Form for Attempt Action -->
    <form id="action-form" method="POST" style="display: none;">
        @csrf
        <input type="hidden" name="_method" id="action-method" value="POST">
    </form>
@endif
@stop

@section('js')
<script>
    $(document).ready(function () {
        // Initialize DataTables
        if ($('#reportsTable').length) {
            $('#reportsTable').DataTable({
                "pageLength": -1,
                "lengthMenu": [[-1, 10, 25, 50], ["ทั้งหมด", 10, 25, 50]],
                "responsive": false,
                "scrollX": true,
                "lengthChange": true,
                "autoWidth": false,
                "order": [],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Thai.json"
                }
            });
        }

        var isAdmin = {{ auth()->user()->isAdmin() ? 'true' : 'false' }};

        // Modal: View Student Rounds History
        $(document).on('click', '.view-rounds-btn', function () {
            var btn = $(this);
            var student = btn.data('student');
            var code = btn.data('code');
            var exam = btn.data('exam');
            var total = btn.data('total');
            var rounds = btn.data('rounds');
            var studentId = btn.data('student-id');
            var examId = btn.data('exam-id');
            var isPassed = btn.data('is-passed');
            var classroom = btn.data('classroom');

            if (typeof rounds === 'string') {
                try { rounds = JSON.parse(rounds); } catch (e) { rounds = []; }
            }

            $('#modal-rh-student').text(student);
            $('#modal-rh-code').text(code);
            $('#modal-rh-exam').text(exam);
            if (classroom && classroom !== '-') {
                $('#modal-rh-classroom').text('ห้อง ' + classroom);
            } else {
                $('#modal-rh-classroom').text('');
            }

            if (studentId && examId) {
                var manageUrl = "{{ url('admin/exams') }}/" + examId + "/student-attempts";
                var quickAddUrl = "{{ url('admin/exams') }}/" + examId + "/student-attempts/" + studentId + "/quick-add";
                var token = '{{ csrf_token() }}';
                var actionsHtml = '<a href="' + manageUrl + '" class="btn btn-sm btn-outline-primary mr-2" target="_blank"><i class="fas fa-user-clock mr-1"></i>จัดการสิทธิ์สอบ</a>';
                if (isPassed != '1') {
                    actionsHtml += '<form action="' + quickAddUrl + '" method="POST" class="d-inline mr-2">' +
                        '<input type="hidden" name="_token" value="' + token + '">' +
                        '<button type="submit" class="btn btn-sm btn-success font-weight-bold"><i class="fas fa-plus mr-1"></i>+1 สอบซ่อม</button>' +
                        '</form>';
                }
                if (isAdmin && Array.isArray(rounds) && rounds.length > 0) {
                    var deleteAllUrl = "{{ url('admin/exams') }}/" + examId + "/students/" + studentId + "/attempts";
                    actionsHtml += '<button type="button" class="btn btn-sm btn-outline-danger delete-all-attempts-btn" data-url="' + deleteAllUrl + '" data-student="' + student + '" data-count="' + rounds.length + '">' +
                        '<i class="fas fa-trash-alt mr-1"></i>ลบประวัติสอบทั้งหมด' +
                        '</button>';
                }
                $('#modal-rh-actions').html(actionsHtml);
            } else {
                $('#modal-rh-actions').empty();
            }

            var rows = '';
            if (Array.isArray(rounds) && rounds.length > 0) {
                rounds.forEach(function (r) {
                    var badgeClass = r.is_passed ? 'badge-success' : 'badge-danger';
                    var badgeText = r.is_passed ? '<i class="fas fa-check-circle mr-1"></i>ผ่าน' : '<i class="fas fa-times-circle mr-1"></i>ไม่ผ่าน';
                    var rawScoreHtml = '';
                    if (r.raw_score !== null && r.total_raw_score !== null) {
                        rawScoreHtml = '<div class="text-xs text-muted">(' + r.raw_score + '/' + r.total_raw_score + ' ข้อ)</div>';
                    }
                    var deleteActionTd = '';
                    if (isAdmin && r.id) {
                        var deleteUrl = "{{ url('admin/exam-attempts') }}/" + r.id;
                        deleteActionTd = '<td class="align-middle">' +
                            '<button type="button" class="btn btn-xs btn-outline-danger font-weight-bold delete-attempt-btn" data-url="' + deleteUrl + '" data-round="' + r.round + '" data-student="' + student + '" title="ลบผลการสอบรอบที่ ' + r.round + '">' +
                            '<i class="fas fa-trash-alt mr-1"></i>ลบ' +
                            '</button>' +
                            '</td>';
                    }

                    rows += '<tr>' +
                        '<td class="align-middle font-weight-bold"><span class="badge badge-info px-2 py-1">รอบที่ ' + r.round + '</span></td>' +
                        '<td class="align-middle font-weight-bold"><span class="text-primary h6 mb-0">' + r.score + '</span> / ' + total + rawScoreHtml + '</td>' +
                        '<td class="align-middle"><span class="badge ' + badgeClass + ' px-2 py-1">' + badgeText + '</span></td>' +
                        '<td class="align-middle text-muted text-sm">' + r.completed_at + '</td>' +
                        deleteActionTd +
                        '</tr>';
                });
            } else {
                rows = '<tr><td colspan="' + (isAdmin ? '5' : '4') + '" class="text-muted">ไม่พบข้อมูลประวัติรอบสอบ</td></tr>';
            }

            $('#modal-rh-body').html(rows);
            $('#roundsHistoryModal').modal('show');
        });

        // Delete Single Attempt Action
        $(document).on('click', '.delete-attempt-btn', function () {
            var url = $(this).data('url');
            var round = $(this).data('round');
            var student = $(this).data('student');

            Swal.fire({
                title: 'ยืนยันการลบผลสอบ?',
                html: `คุณต้องการลบผลการสอบ<strong>รอบที่ ${round}</strong> ของ <strong>${student}</strong> ใช่หรือไม่?<br><span class="text-danger text-sm font-weight-bold"><i class="fas fa-exclamation-triangle mr-1"></i> ข้อมูลคะแนนและคำตอบในรอบนี้จะถูกลบและไม่สามารถกู้คืนได้</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i> ยืนยันลบ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#action-form').attr('action', url);
                    $('#action-method').val('DELETE');
                    $('#action-form').submit();
                }
            });
        });

        // Delete All Student Attempts Action
        $(document).on('click', '.delete-all-attempts-btn', function () {
            var url = $(this).data('url');
            var student = $(this).data('student');
            var count = $(this).data('count');

            Swal.fire({
                title: 'ยืนยันการลบประวัติสอบทั้งหมด?',
                html: `คุณต้องการลบผลการสอบทั้งหมด (${count} รอบ) ของ <strong>${student}</strong> ใช่หรือไม่?<br><span class="text-danger text-sm font-weight-bold"><i class="fas fa-exclamation-triangle mr-1"></i> ประวัติการสอบทั้งหมดจะถูกรีเซ็ตเหมือนยังไม่เคยเข้าสอบ</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i> ยืนยันลบทั้งหมด',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#action-form').attr('action', url);
                    $('#action-method').val('DELETE');
                    $('#action-form').submit();
                }
            });
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