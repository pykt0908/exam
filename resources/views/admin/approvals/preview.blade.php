@extends('adminlte::page')

@section('title', 'ตรวจสอบข้อสอบ - ' . $exam->title)

@section('css')
<style>
    .question-paper-item .question-text-content,
    .question-paper-item .question-text-content>p:first-child,
    .question-paper-item .choice-text-content,
    .question-paper-item .choice-text-content>p:first-child {
        display: inline;
        margin: 0;
        padding: 0;
    }

    .question-paper-item .question-text-content>p:not(:first-child),
    .question-paper-item .choice-text-content>p:not(:first-child) {
        display: block;
        margin-top: 0.5rem;
        margin-bottom: 0;
    }
</style>
@stop

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap py-2">
    <div>
        <h1 class="text-dark font-weight-bold" style="font-size: 1.75rem;">
            ตรวจสอบข้อสอบ: {{ $exam->title }}
        </h1>
        <div class="text-muted mt-1" style="font-size: 1.05rem;">
            วิชา: <strong class="text-dark">[{{ $exam->subject->code }}] {{ $exam->subject->name }}</strong>
            @if($exam->subject->department)
                | หมวด/แผนก: <strong class="text-dark">{{ $exam->subject->department->name }}</strong>
            @endif
            | ผู้สร้าง: <strong class="text-primary">{{ $exam->creator_name }}</strong>
        </div>
    </div>
    <div class="mt-2 mt-md-0">
        <a href="{{ route('admin.approvals.index') }}" class="btn btn-secondary font-weight-bold shadow-sm mr-2">
            <i class="fas fa-arrow-left mr-1"></i>กลับหน้ารายการ
        </a>

        @if($canApprove)
            <button type="button" class="btn btn-danger font-weight-bold shadow-sm mr-2" data-toggle="modal"
                data-target="#rejectModal">
                <i class="fas fa-times-circle mr-1"></i>ส่งกลับแก้ไข
            </button>
            <button type="button" class="btn btn-success font-weight-bold shadow-sm" data-toggle="modal"
                data-target="#approveModal">
                <i class="fas fa-check-circle mr-1"></i>อนุมัติข้อสอบ
            </button>
        @endif
    </div>
</div>
@stop

@section('content')
<div class="row">
    <!-- Exam Summary Card -->
    <div class="col-md-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light py-3">
                <h5 class="card-title font-weight-bold text-dark mb-0">ข้อมูลการสอบ</h5>
            </div>
            <div class="card-body p-3 text-dark" style="font-size: 1rem; line-height: 2;">
                <div class="d-flex justify-content-between border-bottom py-1">
                    <span class="text-secondary">สถานะปัจจุบัน:</span>
                    <span class="font-weight-bold">{{ $exam->approval_status_label }}</span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-1">
                    <span class="text-secondary">จำนวนข้อสอบ:</span>
                    <span class="font-weight-bold">{{ $exam->questions->count() }} ข้อ</span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-1">
                    <span class="text-secondary">คะแนนเต็ม:</span>
                    <span class="font-weight-bold text-dark">{{ floatval($exam->total_score) }} คะแนน</span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-1">
                    <span class="text-secondary">เวลาทำข้อสอบ:</span>
                    <span class="font-weight-bold">{{ $exam->duration_minutes }} นาที</span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-1">
                    <span class="text-secondary">เกณฑ์ผ่าน:</span>
                    <span class="font-weight-bold">{{ $exam->passing_percentage }}%
                        ({{ floatval($exam->total_score * $exam->passing_percentage / 100) }} คะแนน)</span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-1">
                    <span class="text-secondary">สลับลำดับข้อสอบ:</span>
                    <span class="font-weight-bold">{{ $exam->shuffle_questions ? 'สลับข้อสอบ' : 'ไม่สลับข้อ' }}</span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-secondary">สลับตัวเลือก:</span>
                    <span
                        class="font-weight-bold">{{ $exam->shuffle_choices ? 'สลับตัวเลือก' : 'ไม่สลับตัวเลือก' }}</span>
                </div>
            </div>
        </div>


        <!-- Approval Progress Workflow -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h3 class="card-title font-weight-bold"><i class="fas fa-tasks mr-2"></i>ขั้นตอนการอนุมัติ (Workflow)
                </h3>
            </div>
            <div class="card-body p-3">
                <div class="timeline timeline-inverse mb-0">
                    <!-- Step 1: Department Head -->
                    <div class="time-label">
                        <span
                            class="bg-{{ $exam->dept_approved_by ? 'success' : ($exam->approval_status === 'pending_dept' ? 'warning' : 'secondary') }} px-2 py-1">
                            ขั้นที่ 1: หัวหน้าหมวดวิชา
                        </span>
                    </div>
                    <div>
                        <i class="fas {{ $exam->dept_approved_by ? 'fa-check bg-success' : 'fa-clock bg-gray' }}"></i>
                        <div class="timeline-item">
                            <h3 class="timeline-header no-border small">
                                @if($exam->dept_approved_by)
                                    <strong class="text-success">อนุมัติแล้ว</strong> โดย
                                    {{ $exam->deptApprover->name ?? '-' }}
                                    <br><span
                                        class="text-muted text-xs">{{ $exam->dept_approved_at ? $exam->dept_approved_at->format('d/m/Y H:i น.') : '' }}</span>
                                @elseif($exam->approval_status === 'pending_dept')
                                    <span class="text-warning font-weight-bold">กำลังรอการตรวจสอบจากหัวหน้าหมวด</span>
                                @else
                                    <span class="text-muted">ยังไม่เริ่มดำเนินการ</span>
                                @endif
                            </h3>
                        </div>
                    </div>

                    <!-- Step 2: Evaluation Head -->
                    <div class="time-label">
                        <span
                            class="bg-{{ $exam->eval_approved_by ? 'success' : ($exam->approval_status === 'pending_eval' ? 'warning' : 'secondary') }} px-2 py-1">
                            ขั้นที่ 2: หัวหน้างานวัดผล
                        </span>
                    </div>
                    <div>
                        <i class="fas {{ $exam->eval_approved_by ? 'fa-check bg-success' : 'fa-clock bg-gray' }}"></i>
                        <div class="timeline-item">
                            <h3 class="timeline-header no-border small">
                                @if($exam->eval_approved_by)
                                    <strong class="text-success">อนุมัติแล้ว</strong> โดย
                                    {{ $exam->evalApprover->name ?? '-' }}
                                    <br><span
                                        class="text-muted text-xs">{{ $exam->eval_approved_at ? $exam->eval_approved_at->format('d/m/Y H:i น.') : '' }}</span>
                                @elseif($exam->approval_status === 'pending_eval')
                                    <span class="text-warning font-weight-bold">กำลังรอการตรวจสอบจากหัวหน้างานวัดผล</span>
                                @else
                                    <span class="text-muted">รอขั้นตอนก่อนหน้า</span>
                                @endif
                            </h3>
                        </div>
                    </div>

                    <!-- Step 3: Academic Deputy -->
                    <div class="time-label">
                        <span
                            class="bg-{{ $exam->academic_approved_by ? 'success' : ($exam->approval_status === 'pending_academic' ? 'warning' : 'secondary') }} px-2 py-1">
                            ขั้นที่ 3: รองฝ่ายวิชาการ
                        </span>
                    </div>
                    <div>
                        <i
                            class="fas {{ $exam->academic_approved_by ? 'fa-check bg-success' : 'fa-clock bg-gray' }}"></i>
                        <div class="timeline-item">
                            <h3 class="timeline-header no-border small">
                                @if($exam->academic_approved_by)
                                    <strong class="text-success">อนุมัติเรียบร้อย</strong> โดย
                                    {{ $exam->academicApprover->name ?? '-' }}
                                    <br><span
                                        class="text-muted text-xs">{{ $exam->academic_approved_at ? $exam->academic_approved_at->format('d/m/Y H:i น.') : '' }}</span>
                                @elseif($exam->approval_status === 'pending_academic')
                                    <span class="text-warning font-weight-bold">กำลังรอการอนุมัติขั้นสุดท้าย</span>
                                @else
                                    <span class="text-muted">รอขั้นตอนก่อนหน้า</span>
                                @endif
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approval Logs History -->
        @if($exam->approvalLogs->count() > 0)
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h3 class="card-title font-weight-bold"><i class="fas fa-history mr-2"></i>ประวัติบันทึกการพิจารณา</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>เวลา</th>
                                    <th>การกระทำ</th>
                                    <th>ผู้ทำรายการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($exam->approvalLogs as $log)
                                    <tr>
                                        <td class="small text-muted">{{ $log->created_at->format('d/m H:i') }}</td>
                                        <td>
                                            <span class="font-weight-bold" style="{{ $log->action_text_style }}">{{ $log->action_label }}</span>
                                            @if($log->comment ?? $log->notes)
                                                <div class="text-xs text-secondary mt-1">{{ $log->comment ?? $log->notes }}</div>
                                            @endif
                                        </td>
                                        <td class="small font-weight-bold">{{ $log->user->name ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Right Column: Authentic Exam Paper Preview -->
    <div class="col-md-8">
        <div class="card shadow-sm border-0"
            style="background-color: #fff; box-shadow: 0 0 15px rgba(0,0,0,0.08) !important;">
            <!-- Exam Paper Header -->
            <div class="card-header bg-white border-bottom text-center py-4">
                <h4 class="font-weight-bold text-dark mb-1">{{ $exam->title }}</h4>
                <div class="text-dark" style="font-size: 1.05rem;">
                    <strong>รายวิชา {{ $exam->subject->code }}
                        {{ $exam->subject->name }}</strong>
                    @if($exam->subject->department)
                        <strong>สาขาวิชา: {{ $exam->subject->department->name }}</strong>
                    @endif
                </div>
                <div class="text-dark text-md ">
                    <span>ข้อสอบมีจำนวน <strong>{{ $exam->questions->count() }}
                            ข้อ</strong></span>

                    <span>เวลาทำข้อสอบ <strong>{{ $exam->duration_minutes }}
                            นาที</strong></span>

                    <span>คะแนนเต็ม <strong>{{ number_format($exam->total_score) }}
                            คะแนน</strong></span><br>
                    <span>อาจารย์ผู้สอน
                        <strong>{{ $exam->creator_name }}</strong></span>
                </div>
                @if($exam->description)
                    <div class="text-left bg-light p-3 rounded mt-3 border text-dark" style="font-size: 0.95rem;">
                        <strong><u>คำชี้แจง</u>:</strong>
                        <div class="mt-1">{!! $exam->description !!}</div>
                    </div>
                @endif
            </div>

            <!-- Exam Paper Body -->
            <div class="card-body p-4" style="line-height: 1.7; font-size: 1.05rem; color: #212529;">
                @php
                    $currentSection = null;
                    $qIndex = 1;
                    $thaiChoices = ['ก', 'ข', 'ค', 'ง', 'จ', 'ฉ', 'ช', 'ซ'];
                @endphp

                @forelse($exam->questions as $question)
                    {{-- Section Header (Plain Paper Style) --}}
                    @if($question->exam_section_id !== $currentSection)
                        @php $currentSection = $question->exam_section_id; @endphp
                        @if($question->examSection)
                            <div class="my-4 pt-2">
                                <h5 class="font-weight-bold text-dark mb-1" style="font-size: 1.15rem;">
                                    <u>{{ $question->examSection->title }}</u>
                                </h5>
                                @if($question->examSection->instructions)
                                    <div class="text-secondary mt-1">{!! $question->examSection->instructions !!}</div>
                                @endif
                            </div>
                        @endif
                    @endif

                    <!-- Single Question Item (Paper Style) -->
                    <div class="question-paper-item pb-3 mb-4 border-bottom">
                        <div class="d-flex justify-content-between align-items-baseline mb-2">
                            <div class="font-weight-bold text-dark flex-grow-1"
                                style="font-size: 1.1rem; line-height: 1.6;">
                                <span class="mr-1 d-inline">{{ $qIndex++ }}.</span>
                                <span class="question-text-content d-inline">{!! $question->question_text !!}</span>
                            </div>
                            <div class="ml-3 text-nowrap align-self-start">
                                <span class="text-muted font-weight-normal" style="font-size: 0.95rem;">
                                    ({{ number_format($question->score, 2) }} คะแนน)
                                </span>
                            </div>
                        </div>

                        @if($question->question_image)
                            <div class="my-3 text-center">
                                <img src="{{ asset('storage/' . $question->question_image) }}"
                                    class="img-fluid rounded border shadow-xs" style="max-height: 280px;"
                                    alt="รูปภาพประกอบคำถาม">
                            </div>
                        @endif

                        @if($question->type === 'essay')
                            <div class="mt-2 ml-4 p-3 bg-light rounded border">
                                <div class="font-weight-bold text-success mb-1" style="font-size: 0.95rem;">
                                    <i class="fas fa-check-circle mr-1"></i><u>เฉลยคำตอบ (อัตนัย)</u>:
                                </div>
                                <div class="text-dark">
                                    {{ $question->essay_answer ?: '(ไม่ได้ระบุเฉลยข้อความ)' }}
                                </div>
                            </div>
                        @else
                            <!-- Multiple Choices (Paper Style) -->
                            <div class="choices-paper-list ml-4 mt-2">
                                <div class="row">
                                    @foreach($question->choices as $cIdx => $choice)
                                        <div class="col-12 mb-2">
                                            <div
                                                class="d-flex align-items-start {{ $choice->is_correct ? 'text-success font-weight-bold' : 'text-dark' }}">
                                                <span class="mr-2" style="min-width: 25px;">
                                                    {{ $thaiChoices[$cIdx] ?? ($cIdx + 1) }}.
                                                </span>
                                                <div class="flex-grow-1 choice-text-content">
                                                    <span>{!! $choice->choice_text !!}</span>
                                                    @if($choice->is_correct)
                                                        <span class="badge badge-success ml-2 px-2 py-1" style="font-size: 0.75rem;">
                                                            <i class="fas fa-check mr-1"></i>(เฉลยข้อที่ถูกต้อง)
                                                        </span>
                                                    @endif
                                                    @if($choice->choice_image)
                                                        <div class="mt-2">
                                                            <img src="{{ asset('storage/' . $choice->choice_image) }}"
                                                                class="img-thumbnail" style="max-height: 140px;" alt="รูปตัวเลือก">
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-file-alt fa-3x mb-3 text-secondary d-block"></i>
                        ยังไม่มีคำถามในข้อสอบชุดนี้
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@if($canApprove)
    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success">
                    <h5 class="modal-title text-white font-weight-bold">
                        <i class="fas fa-check-circle mr-2"></i>ยืนยันการอนุมัติข้อสอบ
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.approvals.approve', $exam->id) }}" method="post">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="approve_notes" class="font-weight-bold">หมายเหตุ / ความเห็นเพิ่มเติม
                                (ถ้ามี):</label>
                            <textarea name="notes" id="approve_notes" class="form-control" rows="3"
                                placeholder="ระบุความเห็นหรือคำแนะนำเพิ่มเติม..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary font-weight-bold"
                            data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-success font-weight-bold">
                            <i class="fas fa-check mr-1"></i>ยืนยันอนุมัติ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title text-white font-weight-bold">
                        <i class="fas fa-undo-alt mr-2"></i>ส่งกลับให้ผู้สร้างแก้ไขข้อสอบ
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.approvals.reject', $exam->id) }}" method="post">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-warning small">
                            <i class="fas fa-exclamation-triangle mr-1"></i> เมื่อส่งกลับแก้ไข ข้อสอบจะกลับสู่สถานะร่าง
                            และผู้สร้างข้อสอบจะสามารถแก้ไขโจทย์และตัวเลือกได้
                        </div>
                        <div class="form-group">
                            <label for="reject_reason" class="font-weight-bold text-danger">เหตุผล / จุดที่ต้องแก้ไข <span
                                    class="text-danger">*</span>:</label>
                            <textarea name="reason" id="reject_reason" class="form-control" rows="4"
                                placeholder="ระบุสิ่งที่ต้องแก้ไขอย่างละเอียด เพื่อให้ผู้สร้างข้อสอบปรับปรุง..."
                                required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary font-weight-bold"
                            data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-danger font-weight-bold">
                            <i class="fas fa-paper-plane mr-1"></i>ยืนยันการส่งกลับแก้ไข
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@stop

@section('js')
<script>
    $(document).ready(function () {
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