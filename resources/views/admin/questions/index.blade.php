@extends('adminlte::page')

@section('title', 'จัดการคำถาม')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <!-- Left: Title & score text -->
        <div>
            <div class="text-dark text-md font-weight-bold mb-1"> {{ $exam->title }} รายวิชา {{ $exam->subject->code }} {{ $exam->subject->name }}</div>
            <div class="text-secondary text-md">
                คะแนนรวม: <span class="text-primary">{{ number_format($exam->total_score, 2) }}</span> คะแนน
            </div>
        </div>

        <!-- Middle: Action buttons -->
        <div class="d-flex align-items-center justify-content-center flex-grow-1 my-2">
            @if($exam->questions->count() > 0)
                <form id="recalculate-form" action="{{ route('admin.exams.recalculate-scores', $exam->id) }}" method="POST" class="d-inline mr-2">
                    @csrf
                    <button type="button" id="recalculate-btn" class="btn btn-warning font-weight-bold shadow-sm">
                        คำนวณคะแนน
                    </button>
                </form>
            @endif
            <button type="button" class="btn btn-outline-info font-weight-bold shadow-sm mr-2" data-toggle="modal" data-target="#examSettingsModal">
                ตั้งค่าข้อสอบ
            </button>
            <a href="{{ route('admin.exams.student-attempts.index', $exam->id) }}" class="btn btn-outline-primary font-weight-bold shadow-sm mr-2" title="จัดการเปิดให้สอบเพิ่ม/สอบซ่อมเป็นรายคน">
                เปิดสอบเพิ่มรายคน
            </a>
            <form action="{{ route('admin.exams.duplicate', $exam->id) }}" method="POST" class="d-inline mr-2 confirm-duplicate"
                  data-text="ต้องการคัดลอกข้อสอบ '{{ $exam->title }}' ใช่หรือไม่? ข้อสอบชุดใหม่จะถูกสร้างเป็น 'ฉบับร่าง' และต้องยื่นขออนุมัติใหม่ก่อนเปิดใช้งาน">
                @csrf
                <button type="submit" class="btn btn-outline-secondary font-weight-bold shadow-sm" title="คัดลอกข้อสอบ (Duplicate)">
                    คัดลอกข้อสอบ
                </button>
            </form>
            <button id="save-all-btn" class="btn btn-primary font-weight-bold shadow-sm mr-2" disabled>
                บันทึกทั้งหมด <span id="unsaved-count" class="badge badge-warning ml-1 d-none">0</span>
            </button>
            <button id="add-question-btn" class="btn btn-success font-weight-bold shadow-sm">
               เพิ่มคำถามใหม่
            </button>
        </div>

        <!-- Right: Back button -->
        <div class="d-flex align-items-center">
            <a href="{{ route('admin.exams.index') }}" class="btn btn-danger font-weight-bold shadow-sm">
                <i class="fas fa-arrow-left mr-2"></i>กลับหน้าข้อสอบ
            </a>
        </div>
    </div>
@stop

@section('content')
    <!-- Approval Status Alert Banner -->
    <div class="card mb-3 border-left-{{ $exam->isApproved() ? 'success' : ($exam->isRejected() ? 'danger' : ($exam->isPending() ? 'warning' : 'secondary')) }} shadow-sm">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <span class="mr-2" style="font-size: 1rem;">
                        {!! $exam->approval_status_badge !!}
                    </span>
                    @if($exam->isPending())
                        <span class="text-warning ml-2 small font-weight-bold">
                            <i class="fas fa-lock mr-1"></i>ข้อสอบถูกล็อกไม่สามารถแก้ไขได้ชั่วคราวระหว่างรอผลการอนุมัติ
                        </span>
                    @elseif($exam->isApproved())
                        <span class="text-success ml-2 small font-weight-bold">
                            <i class="fas fa-check-double mr-1"></i>ผ่านการอนุมัติครบทุกขั้นตอนแล้ว พร้อมเปิดสอบ
                        </span>
                    @elseif($exam->isRejected())
                        <div class="text-danger mt-1 small font-weight-bold">
                            <i class="fas fa-exclamation-triangle mr-1"></i>เหตุผลที่ส่งกลับแก้ไข: {{ $exam->rejection_reason ?? '-' }}
                        </div>
                    @else
                        <span class="text-muted ml-2 small">
                            (ร่างข้อสอบ - เมื่อแก้ไขเสร็จแล้วกรุณากด "ส่งขออนุมัติข้อสอบ" เพื่อส่งให้หัวหน้าพิจารณา)
                        </span>
                    @endif
                </div>

                <div class="mt-2 mt-md-0 d-flex align-items-center">
                    @if($exam->isDraft() || $exam->isRejected())
                        <form action="{{ route('admin.exams.submit-approval', $exam->id) }}" method="post" class="d-inline mr-2">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm font-weight-bold shadow-sm">
                                <i class="fas fa-paper-plane mr-1"></i>ส่งขออนุมัติข้อสอบ
                            </button>
                        </form>
                    @elseif($exam->isPending())
                        <form action="{{ route('admin.exams.recall-approval', $exam->id) }}" method="post" class="d-inline mr-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning btn-sm font-weight-bold">
                                <i class="fas fa-undo mr-1"></i>ดึงกลับมาแก้ไขร่าง
                            </button>
                        </form>
                    @endif

                    @if($exam->approvalLogs->count() > 0)
                        <button type="button" class="btn btn-outline-info btn-sm font-weight-bold" data-toggle="modal" data-target="#approvalHistoryModal">
                            <i class="fas fa-history mr-1"></i>ประวัติการอนุมัติ ({{ $exam->approvalLogs->count() }})
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Approval History Modal -->
    @if($exam->approvalLogs->count() > 0)
    <div class="modal fade" id="approvalHistoryModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <h5 class="modal-title text-white font-weight-bold"><i class="fas fa-history mr-2"></i>ประวัติการพิจารณาและอนุมัติข้อสอบ</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 16%;">วัน-เวลา</th>
                                    <th style="width: 20%;">ขั้นตอน</th>
                                    <th style="width: 14%;">การดำเนินการ</th>
                                    <th style="width: 18%;">ผู้ดำเนินการ</th>
                                    <th>หมายเหตุ / เหตุผล</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($exam->approvalLogs as $log)
                                    <tr>
                                        <td class="small align-middle">{{ $log->created_at->format('d/m/Y H:i น.') }}</td>
                                        <td class="align-middle text-dark">{{ $log->stage_label }}</td>
                                        <td class="align-middle font-weight-bold" style="{{ $log->action_text_style }}">{{ $log->action_label }}</td>
                                        <td class="small align-middle font-weight-bold text-dark">{{ $log->user->name ?? '-' }}</td>
                                        <td class="small align-middle text-muted">{{ $log->comment ?? $log->notes ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row">
        <!-- Left Column: Edit Exam details form -->
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4 sticky-card">
                <div class="card-header bg-light">
                    <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-edit mr-2"></i>รายละเอียดข้อสอบ</h3>
                </div>
                <form id="exam-details-form" action="{{ route('admin.exams.update', $exam->id) }}" method="post">
                    @csrf
                    @method('put')
                    <div class="card-body">
                        <!-- Redirect path back to questions index instead of exams list -->
                        <input type="hidden" name="redirect_to" value="questions">

                        <div class="form-group">
                            <label class="font-weight-bold">รายวิชา</label>
                            <select name="subject_id" class="form-control select2" required>
                                @foreach($subjects as $sub)
                                    <option value="{{ $sub->id }}" {{ $exam->subject_id == $sub->id ? 'selected' : '' }}>
                                        [{{ $sub->code }}] {{ $sub->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">ชื่อข้อสอบ</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $exam->title) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">คำชี้แจง/รายละเอียด</label>
                            <textarea name="description" class="form-control summernote-editor" rows="3">{{ old('description', $exam->description) }}</textarea>
                        </div>
                        <div class="row">
                            <div class="col-4 px-1">
                                <div class="form-group mb-2">
                                    <label class="font-weight-bold text-xs">เวลาที่ใช้ (นาที)</label>
                                    <input type="number" name="duration_minutes" class="form-control px-2" value="{{ old('duration_minutes', $exam->duration_minutes) }}" min="1" required>
                                </div>
                            </div>
                            <div class="col-4 px-1">
                                <div class="form-group mb-2">
                                    <label class="font-weight-bold text-xs">เกณฑ์ผ่าน (%)</label>
                                    <input type="number" name="passing_percentage" class="form-control px-2" value="{{ old('passing_percentage', $exam->passing_percentage) }}" min="0" max="100" required>
                                </div>
                            </div>
                            <div class="col-4 px-1">
                                <div class="form-group mb-2">
                                    <label class="font-weight-bold text-xs">คะแนนเต็ม</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="total_score" id="total_score_input" step="0.01" class="form-control px-2" value="{{ old('total_score', $exam->total_score) }}" min="0.01" required>
                                        <!-- <div class="input-group-append">
                                            <button type="button" id="auto-calc-score-btn" class="btn btn-outline-info btn-sm" title="คำนวณคะแนนรวมอัตโนมัติจากคะแนนทุกข้อ">
                                                <i class="fas fa-calculator"></i>
                                            </button>
                                        </div> -->
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6 pr-1">
                                <div class="form-group mb-2">
                                    <label class="font-weight-bold text-xs"><i class="far fa-calendar-plus text-primary mr-1"></i>เริ่มเปิดสอบ</label>
                                    <input type="datetime-local" name="starts_at" class="form-control form-control-sm px-1" value="{{ old('starts_at', $exam->starts_at ? $exam->starts_at->format('Y-m-d\TH:i') : '') }}">
                                </div>
                            </div>
                            <div class="col-6 pl-1">
                                <div class="form-group mb-2">
                                    <label class="font-weight-bold text-xs"><i class="far fa-calendar-times text-danger mr-1"></i>ปิดระบบสอบ</label>
                                    <input type="datetime-local" name="ends_at" class="form-control form-control-sm px-1" value="{{ old('ends_at', $exam->ends_at ? $exam->ends_at->format('Y-m-d\TH:i') : '') }}">
                                </div>
                            </div>
                            <div class="col-12">
                                <small class="text-muted" style="font-size: 11px;">* เว้นว่างไว้หากไม่จำกัดวันเวลาเปิด/ปิด</small>
                            </div>
                        </div>
                        <div class="form-check mt-2">
                            <input type="checkbox" name="is_active" class="form-check-input" id="is_active_toggle" value="1" {{ $exam->is_active ? 'checked' : '' }}>
                            <label class="form-check-label font-weight-bold" for="is_active_toggle">เปิดใช้งานข้อสอบนี้</label>
                        </div>
                        
                    </div>



                    <div class="card-footer bg-light text-right">
                        <button type="submit" class="btn btn-warning text-white font-weight-bold btn-block">
                            <i class="fas fa-save mr-2"></i>บันทึกการแก้ไขข้อสอบ
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Column: Quiz Builder -->
        <div class="col-lg-8">
            <!-- Alert for dynamic actions -->
            <div id="alert-container"></div>
            
            <!-- List of Questions (Quiz Builder Container) -->
            <div id="quiz-builder-container">
                @php
                    $globalQuestionIndex = 0;
                    $hasSectionScores = $sections->contains(fn($s) => $s->total_score !== null && (float)$s->total_score > 0);
                    $sumSectionScores = $sections->sum(fn($s) => (float)($s->total_score ?? 0));
                    $totalExamRaw = $sections->sum(fn($s) => (float)$s->questions->sum('score'));
                @endphp

                @if($hasSectionScores)
                    <div class="alert {{ abs($sumSectionScores - (float)$exam->total_score) < 0.01 ? 'alert-light' : 'alert-light' }} shadow-sm mb-4 border-0">
                        <div class="d-flex align-items-center justify-content-between flex-wrap">
                            <div>
                                <h6 class="font-weight-bold mb-1">
                                    การคิดคะแนนแยกตามตอน
                                </h6>
                                <div class="text-md">
                                    ผลรวมคะแนนที่คุณกำหนด คือ <strong class="font-weight-bold">{{ number_format($sumSectionScores, 2) }}</strong> คะแนน คะแนนเต็มข้อสอบคือ <strong class="font-weight-bold">{{ number_format($exam->total_score, 2) }}</strong> คะแนน
                                    @if(abs($sumSectionScores - (float)$exam->total_score) > 0.01)
                                        <span class="text-danger font-weight-bold ml-2">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>กรุณาปรับคะแนนให้ถูกต้อง
                                        </span>
                                    @else
                                        <span class="text-success font-weight-bold ml-2">
                                            <i class="fas fa-check-circle mr-1"></i>ถูกต้อง
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @foreach($sections as $sectIndex => $section)
                    <div class="card card-section mb-5 border-info shadow-sm" id="section-card-{{ $section->id }}" data-section-id="{{ $section->id }}">
                        <div class="card-header bg-light text-dark d-flex justify-content-between align-items-center py-3">
                            <h5 class="font-weight-bold mb-0">
                                 {{ $sectIndex + 1 }}. <span class="section-title-label">{{ $section->title }}</span>
                            </h5>
                            <div class="card-tools">
                                <button type="button" class="btn btn-sm btn-success text-white save-section-btn mr-1 shadow-sm font-weight-bold">
                                    <i class="fas fa-save mr-1"></i> บันทึกข้อมูลตอน
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-section-btn shadow-xs font-weight-bold bg-white text-danger border-0">
                                    <i class="fas fa-trash-alt mr-1"></i> ลบตอนนี้
                                </button>
                            </div>
                        </div>
                        <div class="card-body" style="background-color: #f8f9fa;">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold text-dark text-xs">ชื่อตอน</label>
                                        <input type="text" class="form-control section-title-input" value="{{ $section->title }}" placeholder="เช่น ตอนที่ 1: ข้อสอบปรนัย" required>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold text-dark text-xs">คำชี้แจงประจำตอน</label>
                                        <input type="text" class="form-control section-instruction-input" value="{{ $section->instruction }}" placeholder="เช่น จงเลือกคำตอบที่ถูกต้องที่สุดเพียงข้อเดียว">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold text-dark text-xs text-primary">
                                            <i class="fas fa-bullseye mr-1"></i>คะแนนเต็มตอน (เป้าหมาย)
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.5" min="0" class="form-control section-total-score-input font-weight-bold text-primary" value="{{ $section->total_score !== null ? $section->total_score : '' }}" placeholder="ตามคะแนนดิบ">
                                            <div class="input-group-append">
                                                <span class="input-group-text text-xs">คะแนน</span>
                                            </div>
                                        </div>
                                        <small class="text-muted d-block mt-1" style="font-size: 11px;">เว้นว่างเพื่อคิดตามคะแนนดิบ</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Section info badges strip -->
                            <!-- <div class="d-flex align-items-center flex-wrap mb-2 px-1">
                                <span class="text-dark text-md mr-2 py-1 px-2">
                                   ข้อสอบมีจำนวน {{ $section->questions->count() }} ข้อ
                                </span>
                                <span class="text-dark text-md mr-2 py-1 px-2 text-dark">
                                    คะแนนดิบรวมคิดเป็น <strong>{{ $section->questions->sum('score') }}</strong> คะแนน
                                </span>
                                @if($section->total_score !== null)
                                    <span class="text-dark text-md mr-2 py-1 px-2">
                                       คิดคะแนนรวมทั้งตอนเท่ากับ <strong>{{ $section->total_score }}</strong> คะแนน
                                    </span>
                                @endif
                            </div> -->

                            <!-- List of Questions inside this section -->
                            <div class="section-questions-container mt-4">
                                @forelse($section->questions as $question)
                                    @php
                                        $globalQuestionIndex++;
                                    @endphp
                                    <div class="card card-question shadow-sm mb-4" id="question-card-{{ $question->id }}" data-id="{{ $question->id }}" data-saved="true">
                                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                                            <div class="d-flex align-items-center">
                                                <span class="drag-handle text-secondary mr-2 py-1 px-1" title="คลิกลากเพื่อสลับข้อ">
                                                    <i class="fas fa-grip-vertical fa-lg"></i>
                                                </span>
                                                <button type="button" class="btn btn-xs btn-outline-secondary move-question-up mr-1" title="เลื่อนข้อขึ้น">
                                                    <i class="fas fa-chevron-up"></i>
                                                </button>
                                                <button type="button" class="btn btn-xs btn-outline-secondary move-question-down mr-2" title="เลื่อนข้อลง">
                                                    <i class="fas fa-chevron-down"></i>
                                                </button>
                                                <span class="font-weight-bold text-dark mr-3 card-index">ข้อที่ {{ $globalQuestionIndex }}</span>
                                                <span class="status-badge text-success"><i class="fas fa-check-circle mr-1"></i> บันทึกแล้ว</span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <label class="mr-2 mb-0 text-sm font-weight-bold">คะแนน:</label>
                                                <input type="number" step="0.5" class="form-control score-input text-center mr-3" value="{{ $question->score }}" style="width: 70px; height: 32px;" required>
                                                
                                                <button type="button" class="btn btn-sm btn-primary save-question-btn mr-1 shadow-xs">
                                                    <i class="fas fa-save mr-1"></i> บันทึก
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-question-btn shadow-xs">
                                                    <i class="fas fa-trash-alt mr-1"></i> ลบ
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-group mb-3">
                                                <label class="font-weight-bold text-dark text-md">โจทย์คำถาม</label>
                                                <textarea class="form-control question-text-input" rows="2" placeholder="กรอกหัวข้อคำถามของคุณที่นี่..." required>{{ $question->question_text }}</textarea>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group mb-3">
                                                        <label class="font-weight-bold text-dark text-sm">ประเภทคำถาม</label>
                                                        <select class="form-control question-type-select" style="height: 38px;">
                                                            <option value="choice" {{ ($question->type ?? 'choice') === 'choice' ? 'selected' : '' }}>คำถามแบบปรนัย (มีตัวเลือก)</option>
                                                            <option value="essay" {{ ($question->type ?? 'choice') === 'essay' ? 'selected' : '' }}>คำถามแบบอัตนัย (พิมพ์ตอบ)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-8">
                                                    <div class="form-group mb-3">
                                                        <label class="font-weight-bold text-dark text-sm">ตอนของข้อสอบ</label>
                                                        <select class="form-control question-section-id-input" style="height: 38px;">
                                                            @foreach($sections as $sect)
                                                                <option value="{{ $sect->id }}" {{ $sect->id === $section->id ? 'selected' : '' }}>{{ $sect->title }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="choices-section {{ ($question->type ?? 'choice') === 'choice' ? '' : 'd-none' }}">
                                                <label class="font-weight-bold text-dark text-sm mb-2"><i class="fas fa-list-ul mr-2 text-info"></i>ตัวเลือกคำตอบ (คลิกเฉลยข้อที่ถูกต้องด้านหน้า)</label>
                                                
                                                <div class="choices-container">
                                                    @foreach($question->choices as $choiceIndex => $choice)
                                                        <div class="choice-row mb-3">
                                                            <div class="input-group">
                                                                <div class="input-group-prepend">
                                                                    <div class="input-group-text bg-white border-right-0">
                                                                        <input type="radio" name="correct_choice_{{ $question->id }}" class="correct-radio" value="{{ $choiceIndex }}" {{ $choice->is_correct ? 'checked' : '' }} style="transform: scale(1.25); cursor:pointer;">
                                                                    </div>
                                                                </div>
                                                                <input type="text" class="form-control choice-text-input" value="{{ $choice->choice_text }}" placeholder="ตัวเลือก {{ chr(65 + $choiceIndex) }}" required>
                                                                
                                                                <!-- Hidden input to store choice image URL/path -->
                                                                <input type="hidden" class="choice-image-path" value="{{ $choice->choice_image ? asset($choice->choice_image) : '' }}">

                                                                <div class="input-group-append">
                                                                    <button type="button" class="btn btn-outline-info upload-choice-image-btn border-left-0 border-right-0" title="อัปโหลดรูปภาพ"><i class="far fa-image"></i></button>
                                                                    <button type="button" class="btn btn-outline-danger remove-choice-btn border-left-0"><i class="fas fa-times"></i></button>
                                                                </div>
                                                                
                                                                <!-- Hidden file input for uploading -->
                                                                <input type="file" class="choice-image-file" style="display: none;" accept="image/*">
                                                            </div>

                                                            <!-- Image preview container -->
                                                            <div class="choice-image-preview-container mt-1 ml-5 {{ $choice->choice_image ? '' : 'd-none' }}">
                                                                <div class="position-relative d-inline-block">
                                                                    <img src="{{ $choice->choice_image ? asset($choice->choice_image) : '' }}" class="img-thumbnail choice-image-preview" style="max-height: 80px;">
                                                                    <button type="button" class="btn btn-xs btn-danger position-absolute delete-choice-image-btn" style="top: -5px; right: -5px; border-radius: 50%; width: 20px; height: 20px; padding: 0;" title="ลบรูปภาพ">
                                                                        <i class="fas fa-times" style="font-size: 10px;"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>

                                                <button type="button" class="btn btn-sm btn-outline-info add-choice-btn mt-2 font-weight-bold">
                                                    <i class="fas fa-plus mr-1"></i> เพิ่มตัวเลือก
                                                </button>
                                            </div>

                                            <div class="essay-section {{ ($question->type ?? 'choice') === 'essay' ? '' : 'd-none' }}">
                                                <div class="form-group mb-3">
                                                    <label class="font-weight-bold text-dark text-sm">เฉลย/แนวการตอบ (มีหรือไม่ก็ได้ หากเว้นว่างไว้จะตรวจเป็นแบบอัตนัยแมนนวล)</label>
                                                    <textarea class="form-control essay-answer-input" rows="2" placeholder="กรอกข้อความคำเฉลย/คีย์เวิร์ดที่ต้องการตรวจคำตอบ (เช่น คำตอบที่ถูกต้อง หรือ คำสำคัญ)" style="resize: vertical;">{{ $question->essay_answer }}</textarea>
                                                    <small class="form-text text-muted">ระบบจะเปรียบเทียบคำตอบของนักศึกษาแบบไม่ระวังตัวอักษรใหญ่-เล็ก (Case-Insensitive) และตัดเว้นวรรคส่วนเกินของหัวท้ายข้อความให้โดยอัตโนมัติ</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center text-muted my-3 py-3 border rounded border-dashed empty-section-placeholder" style="border: 2px dashed #ccc; background-color: #fdfdfd;">
                                        <i class="fas fa-info-circle mr-1"></i> ยังไม่มีโจทย์คำถามในตอนนี้ คลิกข้อความด้านล่างเพื่อสร้างคำถามแรก
                                    </div>
                                @endforelse
                            </div>

                            <div class="text-center mt-3 py-2">
                                <span class="add-question-to-section-btn font-weight-bold text-info" data-section-id="{{ $section->id }}" style="cursor: pointer; font-size: 14px; user-select: none; transition: color 0.15s ease-in-out;" onmouseover="this.style.color='#117a8b'" onmouseout="this.style.color='#17a2b8'">
                                    <i class="fas fa-plus-circle mr-1"></i> เพิ่มโจทย์คำถามในตอนนี้ (Add Question)
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Add Section Button at the bottom -->
            <div class="text-center mb-5 mt-4 py-3 bg-white rounded shadow-xs" style="border: 2px dashed #17a2b8; cursor: pointer; transition: all 0.2s;" id="add-section-btn" onmouseover="this.style.backgroundColor='#f4fbfd'; this.style.borderColor='#117a8b';" onmouseout="this.style.backgroundColor='#ffffff'; this.style.borderColor='#17a2b8';">
                <span class="font-weight-bold text-info" style="font-size: 16px; user-select: none;">
                    <i class="fas fa-plus-circle mr-2"></i> เพิ่มตอนข้อสอบใหม่ (Add Section)
                </span>
            </div>
        </div>
    </div>

    <!-- Exam Settings Modal -->
    <div class="modal fade" id="examSettingsModal" tabindex="-1" role="dialog" aria-labelledby="examSettingsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title font-weight-bold" id="examSettingsModalLabel"><i class="fas fa-cog mr-2"></i>ตั้งค่าข้อสอบ / ความปลอดภัย</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-left">
                    <div class="form-group col-12">
                        <label class="font-weight-bold">จำนวนครั้งที่เข้าสอบได้เริ่มต้น (ครั้ง)</label>
                        <input type="number" name="max_attempts" form="exam-details-form" class="form-control" value="{{ old('max_attempts', $exam->max_attempts ?? 1) }}" min="1" required>
                        <small class="form-text text-muted">ค่าเริ่มต้นสำหรับทุกคน (ปกติคือ 1 ครั้ง) — หากต้องการเปิดให้สอบเพิ่มหรือสอบซ่อมเป็นรายคน สามารถจัดการที่เมนู "เปิดสอบเพิ่มรายคน"</small>
                        <div class="mt-2">
                            <a href="{{ route('admin.exams.student-attempts.index', $exam->id) }}" class="btn btn-xs btn-outline-primary font-weight-bold">
                                <i class="fas fa-user-clock mr-1"></i>ไปที่หน้าจัดการเปิดให้สอบเพิ่มรายคน (คลิกที่นี่)
                            </a>
                        </div>
                    </div>
                    <div class="form-group col-12">
                        <label class="font-weight-bold">รหัสผ่านเข้าห้องสอบ (Passcode)</label>
                        <input type="text" name="passcode" form="exam-details-form" class="form-control" value="{{ old('passcode', $exam->passcode) }}" placeholder="เช่น 123456 (เว้นว่างไว้หากไม่ต้องใส่รหัส)">
                        <small class="form-text text-muted">นักศึกษาต้องกรอกรหัสนี้ให้ถูกต้องเพื่อเข้าสอบ</small>
                    </div>
                    <div class="form-group col-12">
                        <label class="font-weight-bold">จำกัดการสลับแท็บ/ออกหน้าจอ (ครั้ง)</label>
                        <input type="number" name="max_focus_escapes" form="exam-details-form" class="form-control" value="{{ old('max_focus_escapes', $exam->max_focus_escapes ?? 0) }}" min="0" required>
                        <small class="form-text text-muted">0 = ไม่จำกัด (หากกรอกตัวเลขมากกว่า 0 ระบบจะตรวจจับการสลับแท็บ และจะส่งคำตอบโดยอัตโนมัติหากทำเกินจำนวนครั้งที่ระบุ)</small>
                    </div>
                    <hr class="w-100">
                    <div class="form-group col-12">
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" name="shuffle_questions" form="exam-details-form" class="custom-control-input" id="shuffle_questions_toggle" value="1" {{ ($exam->shuffle_questions ?? false) ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-bold" for="shuffle_questions_toggle">สลับลำดับข้อสอบสำหรับผู้สอบแต่ละคน</label>
                        </div>
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" name="shuffle_choices" form="exam-details-form" class="custom-control-input" id="shuffle_choices_toggle" value="1" {{ ($exam->shuffle_choices ?? false) ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-bold" for="shuffle_choices_toggle">สลับตัวเลือกคำตอบสำหรับผู้สอบแต่ละคน</label>
                        </div>
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" name="force_fullscreen" form="exam-details-form" class="custom-control-input" id="force_fullscreen_toggle" value="1" {{ ($exam->force_fullscreen ?? false) ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-bold" for="force_fullscreen_toggle">บังคับให้สอบแบบเต็มหน้าจอ (Force Fullscreen)</label>
                        </div>
                        <hr class="w-100 my-2">
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" name="show_score" form="exam-details-form" class="custom-control-input" id="show_score_toggle" value="1" {{ ($exam->show_score ?? true) ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-bold" for="show_score_toggle">แสดงคะแนนทันทีหลังส่งข้อสอบเสร็จ</label>
                        </div>
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" name="show_answers" form="exam-details-form" class="custom-control-input" id="show_answers_toggle" value="1" {{ ($exam->show_answers ?? true) ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-bold" for="show_answers_toggle">แสดงเฉลยทันทีหลังส่งข้อสอบเสร็จ</label>
                        </div>
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" name="allow_review" form="exam-details-form" class="custom-control-input" id="allow_review_toggle" value="1" {{ ($exam->allow_review ?? true) ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-bold" for="allow_review_toggle">อนุญาตให้ผู้สอบเข้ามาดูผลคะแนน/เฉลยย้อนหลังได้</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">ตกลง (ชั่วคราว)</button>
                    <button type="submit" form="exam-details-form" class="btn btn-warning text-white font-weight-bold"><i class="fas fa-save mr-1"></i>บันทึกข้อมูลข้อสอบทั้งหมด</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        .card-question {
            border-radius: 12px !important;
            transition: all 0.3s ease;
        }
        .card-question:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #007bff;
        }
        .choice-row .form-control.choice-text-input {
            border-left: none !important;
            border-right: none !important;
            border-top-left-radius: 0 !important;
            border-bottom-left-radius: 0 !important;
            border-top-right-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
        }
        .choice-row .input-group-prepend .input-group-text {
            border-top-left-radius: 8px !important;
            border-bottom-left-radius: 8px !important;
            border-top-right-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
        }
        .choice-row .input-group-append .remove-choice-btn {
            border-top-left-radius: 0 !important;
            border-bottom-left-radius: 0 !important;
            border-top-right-radius: 8px !important;
            border-bottom-right-radius: 8px !important;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
        }
        .content-header {
            position: sticky;
            top: 57px;
            z-index: 1010;
            background: #f4f6f9;
            padding-bottom: 15px;
            margin-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .sticky-card {
            position: -webkit-sticky;
            position: sticky;
            top: 130px;
            z-index: 1000;
        }
        /* Elevate parent containers stacking contexts when a child note-editor goes fullscreen */
        .sticky-card:has(.note-editor.fullscreen),
        .col-lg-4:has(.note-editor.fullscreen),
        .col-lg-8:has(.note-editor.fullscreen),
        .card:has(.note-editor.fullscreen) {
            z-index: 99999 !important;
        }
        .note-editor.note-frame.fullscreen {
            z-index: 99999 !important;
            background: #fff !important;
        }
        .drag-handle {
            cursor: grab;
            transition: color 0.15s;
        }
        .drag-handle:hover {
            color: #007bff !important;
        }
        .drag-handle:active {
            cursor: grabbing;
        }
        .sortable-ghost {
            opacity: 0.4;
            background-color: #e9ecef !important;
            border: 2px dashed #007bff !important;
        }
    </style>
@stop

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        $(document).ready(function() {
            var examId = {{ $exam->id }};
            var saveUrlBase = "{{ route('admin.exams.questions.store', $exam->id) }}";
            
            // Helper to get ASCII characters (A, B, C, D...)
            function getAlphabetLetter(index) {
                return String.fromCharCode(65 + index);
            }

            // Show Toast Alert using SweetAlert2
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });

            function showToast(message, type = 'success') {
                let iconType = type;
                if (type === 'danger') {
                    iconType = 'error';
                }
                Toast.fire({
                    icon: iconType,
                    title: message
                });
            }

            // Initialize Summernote with full options for questions
            function initQuestionSummernote(element) {
                $(element).summernote({
                    height: 200,
                    tabsize: 2,
                    toolbar: [
                        ['style', ['style']],
                        ['font', ['bold', 'underline', 'clear']],
                        ['color', ['color']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['table', ['table']],
                        ['insert', ['link', 'picture', 'video', 'hr']],
                        ['view', ['fullscreen', 'codeview']]
                    ],
                    callbacks: {
                        onImageUpload: function(files) {
                            uploadSummernoteImage(files[0], this);
                        },
                        onChange: function(contents, $editable) {
                            var textarea = $(this);
                            textarea.val(contents);
                            textarea.trigger('change');
                        }
                    }
                });
            }

            function uploadSummernoteImage(file, editor) {
                var data = new FormData();
                data.append("image", file);
                data.append("_token", "{{ csrf_token() }}");
                $.ajax({
                    url: "{{ route('admin.exams.questions.upload-image') }}",
                    cache: false,
                    contentType: false,
                    processData: false,
                    data: data,
                    type: "POST",
                    success: function(response) {
                        $(editor).summernote('insertImage', response.url);
                    },
                    error: function(err) {
                        showToast('อัปโหลดรูปภาพล้มเหลว!', 'danger');
                    }
                });
            }

            // Initialize Summernote on existing question text inputs
            $('.question-text-input').each(function() {
                initQuestionSummernote(this);
            });

            // Initialize Summernote on description editor (คำชี้แจง)
            $('.summernote-editor').summernote({
                height: 120,
                tabsize: 2,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link']],
                    ['view', ['fullscreen', 'codeview']]
                ]
            });

            // Check and update global Save All button status
            function checkUnsavedChanges() {
                var unsavedCount = $('.card-question[data-saved="false"]').length;
                if (unsavedCount > 0) {
                    $('#save-all-btn').prop('disabled', false);
                    $('#unsaved-count').text(unsavedCount).removeClass('d-none');
                } else {
                    $('#save-all-btn').prop('disabled', true);
                    $('#unsaved-count').addClass('d-none');
                }
            }

            // Auto-calculate total score from sum of all question scores
            $('#auto-calc-score-btn').on('click', function() {
                var total = 0;
                var count = 0;
                $('.card-question .score-input').each(function() {
                    var val = parseFloat($(this).val());
                    if (!isNaN(val) && val > 0) {
                        total += val;
                        count++;
                    }
                });
                if (count === 0) {
                    showToast('ยังไม่มีข้อสอบในชุดนี้', 'warning');
                    return;
                }
                $('#total_score_input').val(total.toFixed(2));
                showToast('คำนวณคะแนนรวมอัตโนมัติ: ' + total.toFixed(2) + ' คะแนน (จาก ' + count + ' ข้อ)', 'success');
            });

            var sectionOptionsHtml = `
                @foreach($sections as $sect)
                    <option value="{{ $sect->id }}">{{ $sect->title }}</option>
                @endforeach
            `;

            // Update indices on cards
            function updateCardIndices() {
                var idxOverall = 1;
                $('.card-section').each(function() {
                    $(this).find('.card-question').each(function() {
                        $(this).find('.card-index').text('ข้อที่ ' + idxOverall);
                        idxOverall++;
                        var cardId = $(this).attr('data-id');
                        $(this).find('.correct-radio').attr('name', 'correct_choice_' + cardId);
                    });
                });
            }

            // Trigger Unsaved status change
            function markAsUnsaved(card) {
                if (card.attr('data-saved') === 'true') {
                    card.attr('data-saved', 'false');
                    card.find('.status-badge')
                        .removeClass('text-success text-danger')
                        .addClass('text-warning')
                        .html('<i class="fas fa-exclamation-circle mr-1"></i> ยังไม่ได้บันทึก');
                    checkUnsavedChanges();
                }
            }

            // Handle typing or change inside input to toggle unsaved status
            $(document).on('input change', '.question-text-input, .score-input, .choice-text-input, .correct-radio, .question-type-select, .essay-answer-input, .question-section-id-input', function() {
                var card = $(this).closest('.card-question');
                markAsUnsaved(card);
            });

            // Toggle choice vs essay section
            $(document).on('change', '.question-type-select', function() {
                var card = $(this).closest('.card-question');
                var type = $(this).val();
                if (type === 'essay') {
                    card.find('.choices-section').addClass('d-none');
                    card.find('.essay-section').removeClass('d-none');
                } else {
                    card.find('.choices-section').removeClass('d-none');
                    card.find('.essay-section').addClass('d-none');
                }
            });

            // Add new Blank Question Card
            function createBlankQuestionCard(sectionId) {
                var sectionCard = $('#section-card-' + sectionId);
                sectionCard.find('.empty-section-placeholder').remove();
                
                var tempId = 'temp-' + Date.now();
                var newCardHtml = `
                    <div class="card card-question shadow-sm mb-4" id="question-card-${tempId}" data-id="${tempId}" data-saved="false">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                            <div class="d-flex align-items-center">
                                <span class="drag-handle text-secondary mr-2 py-1 px-1" title="คลิกลากเพื่อสลับข้อ">
                                    <i class="fas fa-grip-vertical fa-lg"></i>
                                </span>
                                <button type="button" class="btn btn-xs btn-outline-secondary move-question-up mr-1" title="เลื่อนข้อขึ้น">
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-secondary move-question-down mr-2" title="เลื่อนข้อลง">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                <span class="font-weight-bold text-dark mr-3 card-index">ข้อที่ --</span>
                                <span class="status-badge text-warning"><i class="fas fa-exclamation-circle mr-1"></i> ยังไม่ได้บันทึก</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <label class="mr-2 mb-0 text-sm font-weight-bold">คะแนน:</label>
                                <input type="number" step="0.5" class="form-control score-input text-center mr-3" value="1.0" style="width: 70px; height: 32px;" required>
                                
                                <button type="button" class="btn btn-sm btn-primary save-question-btn mr-1 shadow-xs">
                                    <i class="fas fa-save mr-1"></i> บันทึก
                                </button>
                                <button type="button" class="btn btn-sm btn-danger delete-question-btn shadow-xs">
                                    <i class="fas fa-trash-alt mr-1"></i> ลบ
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold text-dark text-md">โจทย์คำถาม</label>
                                <textarea class="form-control question-text-input" rows="2" placeholder="กรอกหัวข้อคำถามของคุณที่นี่..." required></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold text-dark text-sm">ประเภทคำถาม</label>
                                        <select class="form-control question-type-select" style="height: 38px;">
                                            <option value="choice" selected>คำถามแบบปรนัย (มีตัวเลือก)</option>
                                            <option value="essay">คำถามแบบอัตนัย (พิมพ์ตอบ)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold text-dark text-sm">ตอนของข้อสอบ</label>
                                        <select class="form-control question-section-id-input" style="height: 38px;">
                                            ${sectionOptionsHtml}
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="choices-section">
                                <label class="font-weight-bold text-dark text-sm mb-2"><i class="fas fa-list-ul mr-2 text-info"></i>ตัวเลือกคำตอบ (คลิกเฉลยข้อที่ถูกต้องด้านหน้า)</label>
                                
                                <div class="choices-container">
                                    ${[0,1,2,3].map(i => `
                                        <div class="choice-row mb-3">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text bg-white border-right-0">
                                                        <input type="radio" name="correct_choice_${tempId}" class="correct-radio" value="${i}" ${i === 0 ? 'checked' : ''} style="transform: scale(1.25); cursor:pointer;">
                                                    </div>
                                                </div>
                                                <input type="text" class="form-control choice-text-input" placeholder="ตัวเลือก ${getAlphabetLetter(i)}" required>
                                                <input type="hidden" class="choice-image-path" value="">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-info upload-choice-image-btn border-left-0 border-right-0" title="อัปโหลดรูปภาพ"><i class="far fa-image"></i></button>
                                                    <button type="button" class="btn btn-outline-danger remove-choice-btn border-left-0"><i class="fas fa-times"></i></button>
                                                </div>
                                                <input type="file" class="choice-image-file" style="display: none;" accept="image/*">
                                            </div>
                                            <div class="choice-image-preview-container mt-1 ml-5 d-none">
                                                <div class="position-relative d-inline-block">
                                                    <img src="" class="img-thumbnail choice-image-preview" style="max-height: 80px;">
                                                    <button type="button" class="btn btn-xs btn-danger position-absolute delete-choice-image-btn" style="top: -5px; right: -5px; border-radius: 50%; width: 20px; height: 20px; padding: 0;" title="ลบรูปภาพ">
                                                        <i class="fas fa-times" style="font-size: 10px;"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>

                                <button type="button" class="btn btn-sm btn-outline-info add-choice-btn mt-2 font-weight-bold">
                                    <i class="fas fa-plus mr-1"></i> เพิ่มตัวเลือก
                                </button>
                            </div>

                            <div class="essay-section d-none">
                                <div class="form-group mb-3">
                                    <label class="font-weight-bold text-dark text-sm">เฉลย/แนวการตอบ (มีหรือไม่ก็ได้ หากเว้นว่างไว้จะตรวจเป็นแบบอัตนัยแมนนวล)</label>
                                    <textarea class="form-control essay-answer-input" rows="2" placeholder="กรอกข้อความคำเฉลย/คีย์เวิร์ดที่ต้องการตรวจคำตอบ (เช่น คำตอบที่ถูกต้อง หรือ คำสำคัญ)" style="resize: vertical;"></textarea>
                                    <small class="form-text text-muted">ระบบจะเปรียบเทียบคำตอบของนักศึกษาแบบไม่ระวังตัวอักษรใหญ่-เล็ก (Case-Insensitive) และตัดเว้นวรรคส่วนเกินของหัวท้ายข้อความให้โดยอัตโนมัติ</small>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                var card = $(newCardHtml).appendTo(sectionCard.find('.section-questions-container'));
                card.find('.question-section-id-input').val(sectionId);
                
                initQuestionSummernote(card.find('.question-text-input'));
                updateCardIndices();
                checkUnsavedChanges();
                
                // Scroll smoothly to the new card
                $('html, body').animate({
                    scrollTop: card.offset().top - 100
                }, 500);
            }

            $('#add-question-btn').click(function() {
                var lastSection = $('.card-section').last();
                if (lastSection.length === 0) {
                    showToast('กรุณาสร้างตอนข้อสอบก่อน!', 'warning');
                    return;
                }
                var sectionId = lastSection.attr('data-section-id');
                createBlankQuestionCard(sectionId);
            });

            $(document).on('click', '.add-question-to-section-btn', function() {
                var sectionId = $(this).attr('data-section-id');
                createBlankQuestionCard(sectionId);
            });

            // Dynamic Option Addition within Card
            $(document).on('click', '.add-choice-btn', function() {
                var card = $(this).closest('.card-question');
                var container = card.find('.choices-container');
                var cardId = card.attr('data-id');
                var newIndex = container.find('.choice-row').length;
                
                var letter = getAlphabetLetter(newIndex);
                var rowHtml = `
                    <div class="choice-row mb-3">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-white border-right-0">
                                    <input type="radio" name="correct_choice_${cardId}" class="correct-radio" value="${newIndex}" style="transform: scale(1.25); cursor:pointer;">
                                </div>
                            </div>
                            <input type="text" class="form-control choice-text-input" placeholder="ตัวเลือก ${letter}" required>
                            <input type="hidden" class="choice-image-path" value="">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-info upload-choice-image-btn border-left-0 border-right-0" title="อัปโหลดรูปภาพ"><i class="far fa-image"></i></button>
                                <button type="button" class="btn btn-outline-danger remove-choice-btn border-left-0"><i class="fas fa-times"></i></button>
                            </div>
                            <input type="file" class="choice-image-file" style="display: none;" accept="image/*">
                        </div>
                        <div class="choice-image-preview-container mt-1 ml-5 d-none">
                            <div class="position-relative d-inline-block">
                                <img src="" class="img-thumbnail choice-image-preview" style="max-height: 80px;">
                                <button type="button" class="btn btn-xs btn-danger position-absolute delete-choice-image-btn" style="top: -5px; right: -5px; border-radius: 50%; width: 20px; height: 20px; padding: 0;" title="ลบรูปภาพ">
                                    <i class="fas fa-times" style="font-size: 10px;"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;

                $(rowHtml).appendTo(container);
                markAsUnsaved(card);
            });

            // Option Removal inside Card
            $(document).on('click', '.remove-choice-btn', function() {
                var card = $(this).closest('.card-question');
                var row = $(this).closest('.choice-row');
                var container = card.find('.choices-container');
                
                if (container.find('.choice-row').length <= 2) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'คำเตือน!',
                        text: 'คำถามต้องมีตัวเลือกอย่างน้อย 2 ตัวเลือก!',
                        confirmButtonText: 'ตกลง'
                    });
                    return;
                }
                
                row.remove();
                
                // Re-align values and radio indices
                container.find('.choice-row').each(function(i) {
                    $(this).find('.correct-radio').val(i);
                    $(this).find('.choice-text-input').attr('placeholder', 'ตัวเลือก ' + getAlphabetLetter(i));
                });
                
                markAsUnsaved(card);
            });

            // Save Question Action (AJAX Store/Update)
            $(document).on('click', '.save-question-btn', function() {
                var card = $(this).closest('.card-question');
                var id = card.attr('data-id');
                var isNew = id.toString().indexOf('temp-') !== -1;
                
                var type = card.find('.question-type-select').val() || 'choice';
                var questionText = card.find('.question-text-input').val();
                var score = card.find('.score-input').val();
                var examSectionId = card.find('.question-section-id-input').val();
                
                var choices = [];
                var choiceImages = [];
                var correctChoice = undefined;
                var essayAnswer = '';

                if (type === 'choice') {
                    correctChoice = card.find('.correct-radio:checked').val();
                    var hasEmptyChoices = false;
                    card.find('.choice-row').each(function() {
                        var val = $(this).find('.choice-text-input').val().trim();
                        if (val === '') {
                            hasEmptyChoices = true;
                        }
                        choices.push(val);
                        choiceImages.push($(this).find('.choice-image-path').val() || '');
                    });

                    if (hasEmptyChoices) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'คำเตือน!',
                            text: 'กรุณากรอกเนื้อหาของตัวเลือกให้ครบถ้วน!',
                            confirmButtonText: 'ตกลง'
                        });
                        return;
                    }
                    if (correctChoice === undefined) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'คำเตือน!',
                            text: 'กรุณาเลือกตัวเลือกเฉลยที่ถูกต้องข้อข้อสอบ!',
                            confirmButtonText: 'ตกลง'
                        });
                        return;
                    }
                } else {
                    essayAnswer = card.find('.essay-answer-input').val();
                }

                if (questionText.trim() === '') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'คำเตือน!',
                        text: 'กรุณากรอกโจทย์คำถาม!',
                        confirmButtonText: 'ตกลง'
                    });
                    return;
                }

                var url = isNew ? saveUrlBase : `{{ url('admin/exams') }}/${examId}/questions/${id}`;
                var method = isNew ? 'POST' : 'PUT';

                $.ajax({
                    url: url,
                    method: method,
                    data: {
                        _token: "{{ csrf_token() }}",
                        question_text: questionText,
                        score: score,
                        type: type,
                        exam_section_id: examSectionId,
                        choices: choices,
                        choice_images: choiceImages,
                        correct_choice: correctChoice,
                        essay_answer: essayAnswer
                    },
                    success: function(response) {
                        if (response.success) {
                            showToast(response.message, 'success');
                            
                            if (isNew) {
                                card.attr('data-id', response.question.id);
                                card.attr('id', 'question-card-' + response.question.id);
                                card.find('.correct-radio').attr('name', 'correct_choice_' + response.question.id);
                            }

                            var container = card.find('.choices-container');
                            container.empty();
                            
                            if (response.question.choices && response.question.choices.length > 0) {
                                response.question.choices.forEach(function(choice, idx) {
                                    var choiceImgUrl = choice.choice_image ? `{{ asset('') }}${choice.choice_image}` : '';
                                    var hasImg = choice.choice_image ? '' : 'd-none';
                                    var rowHtml = `
                                        <div class="choice-row mb-3">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text bg-white border-right-0">
                                                        <input type="radio" name="correct_choice_${response.question.id}" class="correct-radio" value="${idx}" ${choice.is_correct ? 'checked' : ''} style="transform: scale(1.25); cursor:pointer;">
                                                    </div>
                                                </div>
                                                <input type="text" class="form-control choice-text-input" value="${choice.choice_text}" placeholder="ตัวเลือก ${getAlphabetLetter(idx)}" required>
                                                <input type="hidden" class="choice-image-path" value="${choiceImgUrl}">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-info upload-choice-image-btn border-left-0 border-right-0" title="อัปโหลดรูปภาพ"><i class="far fa-image"></i></button>
                                                    <button type="button" class="btn btn-outline-danger remove-choice-btn border-left-0"><i class="fas fa-times"></i></button>
                                                </div>
                                                <input type="file" class="choice-image-file" style="display: none;" accept="image/*">
                                            </div>
                                            <div class="choice-image-preview-container mt-1 ml-5 ${hasImg}">
                                                <div class="position-relative d-inline-block">
                                                    <img src="${choiceImgUrl}" class="img-thumbnail choice-image-preview" style="max-height: 80px;">
                                                    <button type="button" class="btn btn-xs btn-danger position-absolute delete-choice-image-btn" style="top: -5px; right: -5px; border-radius: 50%; width: 20px; height: 20px; padding: 0;" title="ลบรูปภาพ">
                                                        <i class="fas fa-times" style="font-size: 10px;"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                    container.append(rowHtml);
                                });
                            }

                            // If the section ID was changed, move card in DOM to target section's questions container
                            var targetSectionContainer = $('#section-card-' + examSectionId).find('.section-questions-container');
                            if (card.parent()[0] !== targetSectionContainer[0]) {
                                card.appendTo(targetSectionContainer);
                            }

                            card.attr('data-saved', 'true');
                            card.find('.status-badge')
                                .removeClass('text-warning text-danger')
                                .addClass('text-success')
                                .html('<i class="fas fa-check-circle mr-1"></i> บันทึกแล้ว');
                            
                            updateCardIndices();
                            checkUnsavedChanges();
                        }
                    },
                    error: function(xhr) {
                        var err = JSON.parse(xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด!',
                            text: 'เกิดข้อผิดพลาดในการเซฟคำถาม: ' + (err.message || xhr.statusText),
                            confirmButtonText: 'ตกลง'
                        });
                    }
                });
            });

            // Save All Unsaved Questions (Sequential with Async/Await)
            $('#save-all-btn').click(async function() {
                var unsavedCards = $('.card-question[data-saved="false"]');
                if (unsavedCards.length === 0) return;

                var total = unsavedCards.length;
                var savedCount = 0;
                var hasError = false;
                var btn = $(this);

                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> กำลังบันทึก...');
                showToast(`เริ่มบันทึกคำถาม ${total} ข้อตามลำดับ...`, 'info');

                for (var i = 0; i < unsavedCards.length; i++) {
                    var card = $(unsavedCards[i]);
                    var id = card.attr('data-id');
                    var isNew = id.toString().indexOf('temp-') !== -1;
                    
                    var questionText = card.find('.question-text-input').val();
                    var score = card.find('.score-input').val();
                    var type = card.find('.question-type-select').val() || 'choice';
                    var examSectionId = card.find('.question-section-id-input').val();
                    var essayAnswer = type === 'essay' ? card.find('.essay-answer-input').val() : null;

                    var choices = [];
                    var choiceImages = [];
                    var correctChoice = undefined;
                    var hasEmptyChoices = false;

                    if (type === 'choice') {
                        correctChoice = card.find('.correct-radio:checked').val();
                        card.find('.choice-row').each(function() {
                            var val = $(this).find('.choice-text-input').val().trim();
                            if (val === '') hasEmptyChoices = true;
                            choices.push(val);
                            choiceImages.push($(this).find('.choice-image-path').val() || '');
                        });
                    }

                    // Validate
                    var isInvalid = questionText.trim() === '' ||
                        (type === 'choice' && (hasEmptyChoices || correctChoice === undefined));

                    if (isInvalid) {
                        card.find('.status-badge')
                            .removeClass('text-warning text-success')
                            .addClass('text-danger')
                            .html('<i class="fas fa-exclamation-triangle mr-1"></i> กรอกข้อมูลไม่ครบ');
                        hasError = true;
                        continue;
                    }

                    var url = isNew ? saveUrlBase : `{{ url('admin/exams') }}/${examId}/questions/${id}`;
                    var method = isNew ? 'POST' : 'PUT';

                    try {
                        var response = await $.ajax({
                            url: url,
                            method: method,
                            data: {
                                _token: "{{ csrf_token() }}",
                                question_text: questionText,
                                score: score,
                                type: type,
                                exam_section_id: examSectionId,
                                choices: choices,
                                choice_images: choiceImages,
                                correct_choice: correctChoice,
                                essay_answer: essayAnswer
                            }
                        });

                        if (response.success) {
                            savedCount++;
                            if (isNew) {
                                card.attr('data-id', response.question.id);
                                card.attr('id', 'question-card-' + response.question.id);
                                card.find('.correct-radio').attr('name', 'correct_choice_' + response.question.id);
                            }

                            var container = card.find('.choices-container');
                            if (response.question.type === 'choice' && response.question.choices) {
                                container.empty();
                                response.question.choices.forEach(function(choice, idx) {
                                    var choiceImgUrl = choice.choice_image ? `{{ asset('') }}${choice.choice_image}` : '';
                                    var hasImg = choice.choice_image ? '' : 'd-none';
                                    var rowHtml = `
                                        <div class="choice-row mb-3">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text bg-white border-right-0">
                                                        <input type="radio" name="correct_choice_${response.question.id}" class="correct-radio" value="${idx}" ${choice.is_correct ? 'checked' : ''} style="transform: scale(1.25); cursor:pointer;">
                                                    </div>
                                                </div>
                                                <input type="text" class="form-control choice-text-input" value="${choice.choice_text}" placeholder="ตัวเลือก ${getAlphabetLetter(idx)}" required>
                                                <input type="hidden" class="choice-image-path" value="${choiceImgUrl}">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-info upload-choice-image-btn border-left-0 border-right-0" title="อัปโหลดรูปภาพ"><i class="far fa-image"></i></button>
                                                    <button type="button" class="btn btn-outline-danger remove-choice-btn border-left-0"><i class="fas fa-times"></i></button>
                                                </div>
                                                <input type="file" class="choice-image-file" style="display: none;" accept="image/*">
                                            </div>
                                            <div class="choice-image-preview-container mt-1 ml-5 ${hasImg}">
                                                <div class="position-relative d-inline-block">
                                                    <img src="${choiceImgUrl}" class="img-thumbnail choice-image-preview" style="max-height: 80px;">
                                                    <button type="button" class="btn btn-xs btn-danger position-absolute delete-choice-image-btn" style="top: -5px; right: -5px; border-radius: 50%; width: 20px; height: 20px; padding: 0;" title="ลบรูปภาพ">
                                                        <i class="fas fa-times" style="font-size: 10px;"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                    container.append(rowHtml);
                                });
                            }

                            card.attr('data-saved', 'true');
                            card.find('.status-badge')
                                .removeClass('text-warning text-danger')
                                .addClass('text-success')
                                .html('<i class="fas fa-check-circle mr-1"></i> บันทึกแล้ว');
                        }
                    } catch (err) {
                        hasError = true;
                        card.find('.status-badge')
                            .removeClass('text-warning text-success')
                            .addClass('text-danger')
                            .html('<i class="fas fa-exclamation-triangle mr-1"></i> ผิดพลาด');
                    }
                }

                btn.prop('disabled', false).html('บันทึกทั้งหมด <span id="unsaved-count" class="badge badge-warning ml-1 d-none">0</span>');
                updateCardIndices();
                checkUnsavedChanges();

                if (hasError) {
                    showToast(`บันทึกแล้ว ${savedCount}/${total} ข้อ (มีบางข้อไม่สมบูรณ์)`, 'warning');
                } else {
                    showToast(`บันทึกคำถามครบทั้ง ${savedCount} ข้อเรียบร้อยแล้ว`, 'success');
                }
            });

            // Initialize SortableJS on question containers
            function initSortableContainers() {
                if (typeof Sortable === 'undefined') return;
                $('.section-questions-container').each(function() {
                    if ($(this).data('sortable-initialized')) return;
                    $(this).data('sortable-initialized', true);

                    Sortable.create(this, {
                        handle: '.drag-handle',
                        animation: 150,
                        ghostClass: 'sortable-ghost',
                        onEnd: function(evt) {
                            var card = $(evt.item);
                            var newSectionCard = card.closest('.card-section');
                            var newSectionId = newSectionCard.attr('data-section-id');
                            card.find('.question-section-id-input').val(newSectionId);

                            saveQuestionsOrder();
                        }
                    });
                });
            }

            // Move Question Up Button
            $(document).on('click', '.move-question-up', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var card = $(this).closest('.card-question');
                var prev = card.prev('.card-question');
                if (prev.length > 0) {
                    card.insertBefore(prev);
                    saveQuestionsOrder();
                } else {
                    showToast('ข้อนี้อยู่ลำดับแรกของตอนนี้แล้ว', 'info');
                }
            });

            // Move Question Down Button
            $(document).on('click', '.move-question-down', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var card = $(this).closest('.card-question');
                var next = card.next('.card-question');
                if (next.length > 0) {
                    card.insertAfter(next);
                    saveQuestionsOrder();
                } else {
                    showToast('ข้อนี้อยู่ลำดับสุดท้ายของตอนนี้แล้ว', 'info');
                }
            });

            // Save Questions Order to Server
            function saveQuestionsOrder() {
                updateCardIndices();

                var orderData = [];
                var currentSort = 1;

                $('.card-section').each(function() {
                    var sectionId = $(this).attr('data-section-id');
                    $(this).find('.card-question').each(function() {
                        var qId = $(this).attr('data-id');
                        if (qId && qId.toString().indexOf('temp-') === -1) {
                            orderData.push({
                                id: parseInt(qId),
                                sort_order: currentSort++,
                                exam_section_id: parseInt(sectionId)
                            });
                        }
                    });
                });

                if (orderData.length === 0) return;

                $.ajax({
                    url: "{{ route('admin.exams.questions.reorder', $exam->id) }}",
                    method: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        order: orderData
                    },
                    success: function(res) {
                        if (res.success) {
                            showToast(res.message, 'success');
                        }
                    },
                    error: function() {
                        showToast('เกิดข้อผิดพลาดในการจัดลำดับข้อสอบ', 'danger');
                    }
                });
            }

            initSortableContainers();

            // Delete Question Action (AJAX Delete)
            $(document).on('click', '.delete-question-btn', function() {
                var card = $(this).closest('.card-question');
                var id = card.attr('data-id');
                var isNew = id.toString().indexOf('temp-') !== -1;

                Swal.fire({
                    title: 'คุณแน่ใจหรือไม่?',
                    text: 'คุณต้องการลบคำถามข้อนี้ใช่หรือไม่?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'ใช่, ต้องการลบ!',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (isNew) {
                            card.slideUp(400, function() {
                                $(this).remove();
                                updateCardIndices();
                                checkUnsavedChanges();
                            });
                        } else {
                            $.ajax({
                                url: `{{ url('admin/exams') }}/${examId}/questions/${id}`,
                                method: 'DELETE',
                                data: {
                                    _token: "{{ csrf_token() }}"
                                },
                                success: function(response) {
                                    if (response.success) {
                                        showToast(response.message, 'success');
                                        card.slideUp(400, function() {
                                            $(this).remove();
                                            updateCardIndices();
                                            checkUnsavedChanges();
                                            
                                            if ($('.card-question').length === 0) {
                                                location.reload(); // Reload to show empty placeholder
                                            }
                                        });
                                    }
                                },
                                error: function(xhr) {
                                    showToast('เกิดข้อผิดพลาดในการลบคำถาม!', 'danger');
                                }
                             });
                        }
                    }
                });
            });

            // Trigger hidden file input click for choice image
            $(document).on('click', '.upload-choice-image-btn', function() {
                $(this).closest('.choice-row').find('.choice-image-file').click();
            });

            // Handle choice image file selection and upload
            $(document).on('change', '.choice-image-file', function() {
                var fileInput = $(this);
                var choiceRow = fileInput.closest('.choice-row');
                var card = fileInput.closest('.card-question');
                var file = fileInput[0].files[0];
                
                if (!file) return;

                var data = new FormData();
                data.append("image", file);
                data.append("_token", "{{ csrf_token() }}");

                showToast('กำลังอัปโหลดรูปภาพ...', 'info');

                $.ajax({
                    url: "{{ route('admin.exams.questions.upload-image') }}",
                    cache: false,
                    contentType: false,
                    processData: false,
                    data: data,
                    type: "POST",
                    success: function(response) {
                        choiceRow.find('.choice-image-path').val(response.url);
                        choiceRow.find('.choice-image-preview').attr('src', response.url);
                        choiceRow.find('.choice-image-preview-container').removeClass('d-none');
                        markAsUnsaved(card);
                        showToast('อัปโหลดรูปภาพตัวเลือกสำเร็จ!', 'success');
                    },
                    error: function(err) {
                        showToast('อัปโหลดรูปภาพล้มเหลว!', 'danger');
                    }
                });
            });

            // Delete choice image
            $(document).on('click', '.delete-choice-image-btn', function() {
                var btn = $(this);
                var choiceRow = btn.closest('.choice-row');
                var card = btn.closest('.card-question');
                
                choiceRow.find('.choice-image-path').val('');
                choiceRow.find('.choice-image-file').val('');
                choiceRow.find('.choice-image-preview-container').addClass('d-none');
                choiceRow.find('.choice-image-preview').attr('src', '');
                
                markAsUnsaved(card);
                showToast('ลบรูปภาพตัวเลือกแล้ว', 'info');
            });

            // Add Exam Section (With Dialog for Title, Instruction & Total Score)
            $('#add-section-btn').click(function() {
                var nextSectionNum = $('.card-section').length + 1;
                Swal.fire({
                    title: '<i class="fas fa-layer-group text-info mr-2"></i>เพิ่มตอนข้อสอบใหม่',
                    html: `
                        <div class="text-left mt-3">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold text-dark text-sm">ชื่อตอน <span class="text-danger">*</span></label>
                                <input type="text" id="swal-section-title" class="form-control" value="ตอนที่ ${nextSectionNum}: " placeholder="เช่น ตอนที่ ${nextSectionNum}: ข้อสอบปรนัย" required>
                            </div>
                            <div class="form-group mb-3">
                                <label class="font-weight-bold text-dark text-sm">คำชี้แจงประจำตอน</label>
                                <textarea id="swal-section-instruction" class="form-control" rows="2" placeholder="เช่น จงเลือกคำตอบที่ถูกต้องที่สุดเพียงข้อเดียว"></textarea>
                            </div>
                            <div class="form-group mb-0">
                                <label class="font-weight-bold text-dark text-sm">คะแนนเต็มตอน (เป้าหมาย) <small class="text-muted">(ถ้ามี)</small></label>
                                <input type="number" step="0.5" min="0" id="swal-section-total-score" class="form-control" placeholder="เว้นว่างไว้หากต้องการคิดตามคะแนนดิบ">
                                <small class="text-muted">เช่น กำหนด 15 คะแนน สำหรับปรนัย หรือ 5 คะแนน สำหรับอัตนัย</small>
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fas fa-plus-circle mr-1"></i> สร้างตอนข้อสอบ',
                    cancelButtonText: 'ยกเลิก',
                    focusConfirm: false,
                    preConfirm: () => {
                        const title = document.getElementById('swal-section-title').value.trim();
                        const instruction = document.getElementById('swal-section-instruction').value.trim();
                        const totalScoreVal = document.getElementById('swal-section-total-score').value.trim();
                        const totalScore = totalScoreVal !== '' ? parseFloat(totalScoreVal) : null;
                        if (!title) {
                            Swal.showValidationMessage('กรุณากรอกชื่อตอน');
                            return false;
                        }
                        return { title: title, instruction: instruction, total_score: totalScore };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        var data = result.value;
                        var url = "{{ route('admin.exams.sections.store', $exam->id) }}";
                        $.ajax({
                            url: url,
                            method: 'POST',
                            data: {
                                _token: "{{ csrf_token() }}",
                                title: data.title,
                                instruction: data.instruction,
                                total_score: data.total_score
                            },
                            success: function(response) {
                                if (response.success) {
                                    showToast(response.message, 'success');
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 600);
                                }
                            },
                            error: function(xhr) {
                                showToast('ไม่สามารถสร้างตอนข้อสอบได้!', 'danger');
                            }
                        });
                    }
                });
            });

            // Save Exam Section
            $(document).on('click', '.save-section-btn', function() {
                var btn = $(this);
                var card = btn.closest('.card-section');
                var sectionId = card.attr('data-section-id');
                var title = card.find('.section-title-input').val();
                var instruction = card.find('.section-instruction-input').val();
                var totalScoreVal = card.find('.section-total-score-input').val();
                var totalScore = (totalScoreVal !== undefined && totalScoreVal.trim() !== '') ? parseFloat(totalScoreVal) : null;

                if (title.trim() === '') {
                    showToast('กรุณากรอกชื่อตอน!', 'warning');
                    return;
                }

                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> กำลังบันทึก...');

                var url = `{{ url('admin/exams') }}/${examId}/sections/${sectionId}`;
                $.ajax({
                    url: url,
                    method: 'PUT',
                    data: {
                        _token: "{{ csrf_token() }}",
                        title: title,
                        instruction: instruction,
                        total_score: totalScore
                    },
                    success: function(response) {
                        if (response.success) {
                            showToast(response.message, 'success');
                            card.find('.section-title-label').text(title);
                            setTimeout(function() {
                                window.location.reload();
                            }, 600);
                        }
                    },
                    error: function(xhr) {
                        showToast('ไม่สามารถบันทึกข้อมูลตอนได้!', 'danger');
                    },
                    complete: function() {
                        btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> บันทึกข้อมูลตอน');
                    }
                });
            });

            // Press Enter inside section title, instruction, or total score to save
            $(document).on('keypress', '.section-title-input, .section-instruction-input, .section-total-score-input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $(this).closest('.card-section').find('.save-section-btn').click();
                }
            });

            // Delete Exam Section
            $(document).on('click', '.delete-section-btn', function() {
                var card = $(this).closest('.card-section');
                var sectionId = card.attr('data-section-id');

                if ($('.card-section').length <= 1) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'ไม่สามารถดำเนินการได้!',
                        text: 'ข้อสอบต้องมีอย่างน้อย 1 ตอน!',
                        confirmButtonText: 'ตกลง'
                    });
                    return;
                }

                Swal.fire({
                    title: 'ยืนยันการลบตอนข้อสอบ?',
                    text: 'คำถามที่สังกัดในตอนนี้จะไม่ถูกลบ แต่จะยังคงอยู่ในระบบเพื่อนำไปสังกัดในตอนที่เหลือได้',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'ใช่, ลบตอนเลย!',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        var url = `{{ url('admin/exams') }}/${examId}/sections/${sectionId}`;
                        $.ajax({
                            url: url,
                            method: 'DELETE',
                            data: {
                                _token: "{{ csrf_token() }}"
                            },
                            success: function(response) {
                                if (response.success) {
                                    showToast(response.message, 'success');
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 800);
                                }
                            },
                            error: function(xhr) {
                                showToast('ไม่สามารถลบตอนข้อสอบได้!', 'danger');
                            }
                        });
                    }
                });
            });

            // SweetAlert Confirm for Recalculate Scores
            $('#recalculate-btn').on('click', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'คำนวณคะแนนอัตโนมัติ?',
                    text: 'ระบบจะคำนวณคะแนนเฉลี่ยต่อข้อให้อัตโนมัติ (หากคะแนนเต็มหารจำนวนข้อไม่ลงตัว ระบบจะตั้งเป็น 1.0 คะแนนดิบต่อข้อ และใช้สูตรแปลงสัดส่วนคะแนนเต็มให้อัตโนมัติเมื่อนักเรียนส่งข้อสอบ)',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'ตกลง, คำนวณเลย!',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#recalculate-form').submit();
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

            // SweetAlert Flash Recalculate Info (ค้างไว้จนกว่าจะกดตกลง)
            @if(session('recalculate_info'))
                Swal.fire({
                    icon: '{{ session('recalculate_info')['icon'] ?? 'info' }}',
                    title: '{{ session('recalculate_info')['title'] ?? 'ผลการคำนวณคะแนน' }}',
                    text: {!! json_encode(session('recalculate_info')['message'] ?? session('success')) !!},
                    confirmButtonText: 'รับทราบ / ตกลง',
                    confirmButtonColor: '#3085d6',
                    allowOutsideClick: true
                });
            @elseif(session('success'))
                @php
                    $isLongMessage = mb_strlen(session('success')) > 60;
                @endphp
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ!',
                    text: {!! json_encode(session('success')) !!},
                    confirmButtonText: 'ตกลง',
                    @if(!$isLongMessage)
                        timer: 3000,
                        timerProgressBar: true
                    @endif
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
