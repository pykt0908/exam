@extends('adminlte::page')

@section('title', 'ตรวจข้อสอบ: ' . $attempt->user->name)

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center pb-2 border-bottom mb-3">
        <div>
            <div class="d-flex align-items-center">
                <!-- <a href="{{ route('admin.grading.index', ['exam_id' => $attempt->exam_id]) }}" class="btn btn-sm btn-outline-danger mr-2" title="กลับหน้ารายการ">
                    <i class="fas fa-arrow-left"></i>
                </a> -->
                <div>
                    <h1 class="font-weight-bold text-dark mb-0 h4">
                        ใบบันทึกผลการตรวจประเมินข้อสอบ
                    </h1>
                    <div class="text-dark text-md mt-1">
                        <span class="mr-2"><strong> {{ $attempt->exam->title }} รายวิชา  {{ $attempt->exam->subject->code ?? '' }} {{ $attempt->exam->subject->name ?? '' }}</strong></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-2 mt-md-0 d-flex flex-wrap align-items-center">
            <!-- Student Navigation Controls -->
            <div class="btn-group btn-group-sm mr-2 shadow-xs" role="group">
                @if($prevAttempt)
                    <a href="{{ route('admin.grading.show', $prevAttempt->id) }}" class="btn btn-outline-secondary" title="คนก่อนหน้า: {{ $prevAttempt->user->name }}">
                        <i class="fas fa-chevron-left mr-1"></i> ก่อนหน้า
                    </a>
                @else
                    <button class="btn btn-outline-secondary" disabled>
                        <i class="fas fa-chevron-left mr-1"></i> ก่อนหน้า
                    </button>
                @endif

                @if($allAttempts->count() > 0)
                    <div class="btn-group btn-group-sm dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle font-weight-bold px-3" type="button" data-toggle="dropdown">
                            ฉบับที่ {{ ($currentIndex !== false ? $currentIndex + 1 : 1) }} / {{ $allAttempts->count() }}
                        </button>
                        <div class="dropdown-menu dropdown-menu-right shadow-sm p-0" style="max-height: 380px; overflow-y: auto; min-width: 320px;">
                            <div class="dropdown-header font-weight-bold text-dark border-bottom py-2 bg-light">
                                รายชื่อผู้ส่งข้อสอบทั้งหมด ({{ $allAttempts->count() }} คน)
                            </div>
                            @foreach($allAttempts as $sIndex => $sibling)
                                @php
                                    $isCurrent = ($sibling->id === $attempt->id);
                                @endphp
                                <a class="dropdown-item attempt-dropdown-item d-flex justify-content-between align-items-center py-2 px-3 {{ $isCurrent ? 'current-attempt' : '' }}" 
                                   href="{{ route('admin.grading.show', $sibling->id) }}">
                                    <div class="text-truncate mr-2">
                                        <div class="d-flex align-items-center">
                                            <span class="mr-1 text-muted small">{{ $sIndex + 1 }}.</span>
                                            <span class="font-weight-bold text-dark text-truncate">{{ $sibling->user->name }}</span>
                                            @if($isCurrent)
                                                <span class="badge badge-primary ml-2 px-2 py-0" style="font-size: 0.68rem; font-weight: normal;">กำลังตรวจ</span>
                                            @endif
                                        </div>
                                        <small class="d-block text-muted">รหัส: {{ $sibling->user->student_code ?? '-' }}</small>
                                    </div>
                                    <div class="text-right flex-shrink-0 ml-2">
                                        @if($sibling->isPendingGrading())
                                            <span class="text-warning-dark font-weight-bold small">รอตรวจ</span>
                                        @else
                                            <span class="text-success font-weight-bold small">{{ $sibling->score }} คะแนน</span>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($nextInListAttempt)
                    <a href="{{ route('admin.grading.show', $nextInListAttempt->id) }}" class="btn btn-outline-secondary" title="คนถัดไป: {{ $nextInListAttempt->user->name }}">
                        ถัดไป <i class="fas fa-chevron-right ml-1"></i>
                    </a>
                @else
                    <button class="btn btn-outline-secondary" disabled>
                        ถัดไป <i class="fas fa-chevron-right ml-1"></i>
                    </button>
                @endif
            </div>

            <a href="{{ route('admin.grading.index', ['exam_id' => $attempt->exam_id]) }}" class="btn btn-sm btn-danger">
                กลับหน้ารายการ
            </a>
        </div>
    </div>
@stop

@section('content')
    @php
        $totalRawScore = (float)$attempt->exam->questions->sum('score');
        $examTargetScore = (float)($attempt->exam->total_score ?? $totalRawScore);
        $isScaled = $attempt->isScaled() || ($totalRawScore > 0 && abs($totalRawScore - $examTargetScore) > 0.001);
        $totalPossibleScore = $examTargetScore;
        $essayQuestions = $attempt->exam->questions->where('type', 'essay');
        $choiceQuestions = $attempt->exam->questions->where('type', 'choice');
        $choiceTotalScore = (float)$choiceQuestions->sum('score');
        $essayTotalScore = (float)$essayQuestions->sum('score');
        $isPending = $attempt->isPendingGrading();

        // Calculate auto-graded choice score awarded (raw)
        $choiceScoreAwarded = 0;
        foreach($choiceQuestions as $q) {
            $ans = $studentAnswers->get($q->id);
            if ($ans && $ans->is_correct) {
                $choiceScoreAwarded += (float)$q->score;
            }
        }
        $passingScore = round(($examTargetScore * ($attempt->exam->passing_percentage ?? 50)) / 100, 2);
    @endphp

    <!-- 1. Formal Student & Evaluation Profile Header Card -->
    <div class="card card-outline card-navy shadow-sm mb-4 border-0">
        <div class="card-body p-4 bg-white">
            <div class="row align-items-center">
                <!-- Left: Student Profile Information -->
                <div class="col-lg-4 col-md-12 border-right-lg pr-lg-4 mb-3 mb-lg-0">
                    <div class="d-flex align-items-center">
                        <div class="avatar-circle bg-primary text-white font-weight-bold mr-3 shadow-xs">
                            {{ mb_substr($attempt->user->name, 0, 1) }}
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="font-weight-bold text-dark mb-1">{{ $attempt->user->name }}</h5>
                            <div class="text-secondary text-md line-height-md">
                                <div >รหัสนักศึกษา: <code class="text-dark font-weight-bold">{{ $attempt->user->student_code ?? '-' }}</code></div>
                                <div>กลุ่มเรียน: <span class="text-dark font-weight-bold">{{ $attempt->user->classroom->name ?? '-' }}</span></div>
                                <div>ส่งข้อสอบเมื่อ: <span class="text-dark font-weight-bold">{{ $attempt->completed_at ? $attempt->completed_at->format('d/m/Y H:i น.') : '-' }}</span></div>
                            </div>
                            @if($attempt->focus_escape_count > 0)
                                <div class="mt-2">
                                    <span class="text-danger font-weight-bold small" title="ระบบตรวจพบการสลับหน้าจอระหว่างทำข้อสอบ">
                                        สลับหน้าจอ {{ $attempt->focus_escape_count }} ครั้ง
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Center: Score & Evaluation Metrics -->
                <div class="col-lg-5 col-md-7 border-right-lg px-lg-4 mb-3 mb-lg-0">
                    <div class="row text-center">
                        <div class="col-6">
                            <span class="text-dark text-uppercase small font-weight-bold d-block mb-1">คะแนนรวมปัจจุบัน</span>
                            <div class="h3 font-weight-bold text-dark mb-0">
                                <span id="header-total-score">{{ number_format($attempt->score ?? 0, 2) }}</span>
                                <small class="text-muted font-weight-normal text-sm">/ {{ number_format($totalPossibleScore, 2) }}</small>
                            </div>
                            <div class="small text-muted mt-1">
                                คิดเป็น <span id="header-percentage" class="font-weight-bold text-dark">
                                    {{ $totalPossibleScore > 0 ? number_format((($attempt->score ?? 0) / $totalPossibleScore) * 100, 1) : 0 }}%
                                </span>
                            </div>
                            @if($isScaled)
                                <div class="text-xs text-muted mt-1">
                                    (คะแนนดิบ: <span id="header-raw-score">{{ number_format($attempt->raw_score ?? 0, 1) }}</span> / {{ number_format($totalRawScore, 1) }})
                                </div>
                            @endif
                        </div>
                        <div class="col-6">
                            <span class="text-dark text-uppercase small font-weight-bold d-block mb-1">ผลการประเมิน</span>
                            <div class="mt-1" id="header-pass-badge-container">
                                @if($isPending)
                                    <span class="text-warning-dark font-weight-bold">
                                        รอตรวจข้อเขียน
                                    </span>
                                @elseif($attempt->is_passed)
                                    <span class="text-success font-weight-bold">
                                        ผ่านเกณฑ์ (≥{{ $attempt->exam->passing_percentage }}%)
                                    </span>
                                @else
                                    <span class="text-danger font-weight-bold">
                                        ไม่ผ่านเกณฑ์ (<{{ $attempt->exam->passing_percentage }}%)
                                    </span>
                                @endif
                            </div>
                            <div class="small text-muted mt-1">
                                เกณฑ์ผ่าน: {{ $attempt->exam->passing_percentage }}% ({{ $passingScore }} คะแนน)
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Status and Question Structure -->
                <div class="col-lg-3 col-md-5 pl-lg-4 text-center text-md-left">
                    <span class="text-muted text-uppercase small font-weight-bold d-block mb-1">สัดส่วนข้อสอบ</span>
                    <div class="p-2 rounded bg-light border small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">ปรนัย</span>
                            <span class="font-weight-bold text-dark">{{ $choiceQuestions->count() }} ข้อ ({{ number_format($choiceTotalScore, 1) }} คะแนน)</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">อัตนัย</span>
                            <span class="font-weight-bold text-warning-dark">{{ $essayQuestions->count() }} ข้อ ({{ number_format($essayTotalScore, 1) }} คะแนน)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Sticky Quick Jump & Filter Toolbar -->
    <div class="sticky-nav-card card shadow-sm mb-4 border bg-white">
        <div class="card-body py-2 px-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <!-- Left: Question Quick Jump Track -->
                <div class="d-flex align-items-center flex-wrap mr-2 py-1">
                    <span class="text-dark small font-weight-bold mr-2 text-uppercase d-none d-sm-inline">
                        สารบัญข้อสอบ:
                    </span>
                    <div class="d-flex flex-wrap align-items-center question-nav-track">
                        @php $qIndex = 0; @endphp
                        @foreach($attempt->exam->questions as $qItem)
                            @php
                                $qIndex++;
                                $isItemEssay = ($qItem->type === 'essay');
                                $qAns = $studentAnswers->get($qItem->id);
                                $itemScore = $qAns ? ($qAns->score_awarded ?? ($qAns->is_correct ? (float)$qItem->score : 0)) : 0;
                            @endphp
                            <a href="#question-card-{{ $qItem->id }}" 
                               class="question-pill-btn btn btn-xs m-1 {{ $isItemEssay ? 'btn-outline-warning text-dark font-weight-bold' : ($qAns && $qAns->is_correct ? 'btn-outline-success' : 'btn-outline-secondary') }}"
                               id="pill-q-{{ $qItem->id }}"
                               title="ข้อ {{ $qIndex }} ({{ $isItemEssay ? 'อัตนัย' : 'ปรนัย' }})">

                                ข้อ {{ $qIndex }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Right: View & Filter Toggles -->
                <div class="d-flex align-items-center py-1">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary active" id="btn-filter-all">
                            ทั้งหมด ({{ $attempt->exam->questions->count() }})
                        </button>
                        <button type="button" class="btn btn-outline-warning text-dark" id="btn-filter-essay">
                            เฉพาะข้อเขียน ({{ $essayQuestions->count() }})
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="btn-toggle-choices" title="ย่อ/ขยายข้อสอบปรนัยทั้งหมด">
                            ย่อปรนัย
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Main Grading Form -->
    <form action="{{ route('admin.grading.update', $attempt->id) }}" method="POST" id="gradingForm">
        @csrf
        @method('PUT')
        <input type="hidden" name="action" id="formAction" value="save">

        @php
            $currentSectionId = -1;
            $questionNumber = 0;
        @endphp

        @foreach($attempt->exam->questions as $question)
            @php
                $questionNumber++;
                $ans = $studentAnswers->get($question->id);
                $isEssay = ($question->type === 'essay');
                $maxScore = (float)$question->score;
                $currentScore = $ans ? ($ans->score_awarded ?? ($ans->is_correct ? $maxScore : 0)) : 0;
                $currentFeedback = $ans ? $ans->teacher_feedback : '';

                $sectVal = $question->exam_section_id;
                $sectTitleVal = $question->examSection ? $question->examSection->title : null;
                $sectInstructionVal = $question->examSection ? $question->examSection->instruction : null;
            @endphp

            @if($sectVal !== null && $currentSectionId !== $sectVal)
                @php $currentSectionId = $sectVal; @endphp
                <div class="section-divider-card card shadow-sm mt-4 mb-3 border-0">
                    <div class="card-body py-3 px-4 text-dark">
                        <div class="d-flex align-items-center">
                            <div>
                                <h6 class="font-weight-bold mb-0 text-uppercase letter-spacing-sm">{{ $sectTitleVal }}</h6>
                                @if($sectInstructionVal)
                                    <div class="text-md  mt-1">{{ $sectInstructionVal }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if($isEssay)
                <!-- ============================================== -->
                <!-- ESSAY QUESTION CARD (Main Grading Interface)    -->
                <!-- ============================================== -->
                <div class="card card-outline card-warning shadow-sm mb-4 border question-card essay-question-card" 
                     id="question-card-{{ $question->id }}"
                     data-question-id="{{ $question->id }}"
                     data-max-score="{{ $maxScore }}">
                    <!-- Card Header -->
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center flex-wrap">
                            <span class="font-weight-bold text-dark mr-2 text-sm">
                                ข้อที่ {{ $questionNumber }} <span class="text-warning-dark font-weight-bold">(ข้อเขียน / อัตนัย)</span>
                            </span>
                            <span class="text-muted small">
                                คะแนนเต็ม {{ number_format($maxScore, 2) }} คะแนน
                            </span>
                        </div>
                        <div class="card-tools">
                            <span id="status-badge-{{ $question->id }}" class="small">
                                @if($currentScore > 0)
                                    <span class="text-success font-weight-bold">ให้คะแนนแล้ว: {{ number_format($currentScore, 1) }} / {{ number_format($maxScore, 1) }}</span>
                                @else
                                    <span class="text-warning-dark font-weight-bold">ยังไม่ได้ให้คะแนน</span>
                                @endif
                            </span>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <!-- 1. Question Prompt Area -->
                        <div class="question-prompt-box mb-4 p-3 bg-light rounded border">
                            <div class="d-flex align-items-center mb-2">
                                <span class="text-secondary font-weight-bold small mr-2">
                                    โจทย์คำถาม
                                </span>
                            </div>
                            <div class="question-text text-dark font-weight-bold" style="font-size: 1.08rem; line-height: 1.6;">
                                {!! $question->question_text !!}
                            </div>
                            @if($question->question_image)
                                <div class="mt-3">
                                    <a href="{{ asset($question->question_image) }}" target="_blank" class="d-inline-block">
                                        <img src="{{ asset($question->question_image) }}" 
                                             class="img-fluid img-thumbnail hover-zoom" 
                                             style="max-height: 240px; border-radius: 6px;" 
                                             alt="รูปประกอบคำถามข้อที่ {{ $questionNumber }}">
                                    </a>
                                    <div class="text-muted small mt-1">คลิกที่รูปเพื่อเปิดดูขนาดเต็ม</div>
                                </div>
                            @endif
                        </div>

                        <!-- 2. Official Rubrics & Expected Model Answer -->
                        <div class="rubrics-box mb-4 rounded border" style="background-color: #f0fdf4; border-color: #bbf7d0 !important;">
                            <div class="rubrics-header px-3 py-2 border-bottom d-flex justify-content-between align-items-center" style="background-color: #dcfce7; border-color: #bbf7d0 !important;">
                                <div class="font-weight-bold text-success text-sm">
                                    แนวคำตอบและเกณฑ์การตรวจเฉลย (Model Answer & Rubrics)
                                </div>
                                <button type="button" class="btn btn-xs btn-link text-success p-0 font-weight-bold" data-toggle="collapse" data-target="#rubric-body-{{ $question->id }}">
                                    ซ่อน/แสดง <i class="fas fa-chevron-down ml-1"></i>
                                </button>
                            </div>
                            <div class="collapse show p-3" id="rubric-body-{{ $question->id }}">
                                <div class="text-dark line-height-md" style="white-space: pre-wrap; font-size: 0.95rem;">{{ $question->essay_answer ?: '(ไม่ได้ระบุแนวคำตอบไว้ในระบบ)' }}</div>
                            </div>
                        </div>

                        <!-- 3. Student's Submission Answer Sheet -->
                        <div class="student-answer-sheet mb-4 rounded border shadow-xs" style="background-color: #ffffff; border-color: #cbd5e1 !important;">
                            <div class="answer-sheet-header px-3 py-2 border-bottom d-flex justify-content-between align-items-center bg-light">
                                <div class="font-weight-bold text-dark text-sm">
                                    คำตอบจากผู้เข้าสอบ: <span class="text-primary">{{ $attempt->user->name }}</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    @if(empty($ans) || empty($ans->answer_text))
                                        <span class="text-danger font-weight-bold small mr-2">ไม่ได้กรอกคำตอบ</span>
                                    @else
                                        <span class="text-muted small mr-2">
                                            {{ mb_strlen($ans->answer_text) }} ตัวอักษร
                                        </span>
                                        <button type="button" class="btn btn-xs btn-outline-secondary btn-copy-answer" data-target="student-ans-text-{{ $question->id }}" title="คัดลอกคำตอบ">
                                            คัดลอก
                                        </button>
                                    @endif
                                </div>
                            </div>
                            <div class="answer-sheet-body p-3">
                                @if(empty($ans) || empty($ans->answer_text))
                                    <div class="text-danger font-italic py-3 text-center bg-light rounded border border-danger-light">
                                        (นักศึกษาไม่ได้พิมพ์ตอบในข้อนี้)
                                    </div>
                                @else
                                    <div class="student-answer-content text-dark p-3 rounded" 
                                         id="student-ans-text-{{ $question->id }}"
                                         style="background-color: #fcfcfc; border: 1px solid #e2e8f0; font-size: 1.02rem; line-height: 1.8; white-space: pre-wrap;">{{ $ans->answer_text }}</div>
                                @endif
                            </div>
                        </div>

                        <!-- 4. Academic Evaluation & Score Input Panel -->
                        <div class="evaluation-panel p-3 rounded border" style="background-color: #f8fafc; border-color: #e2e8f0 !important;">
                            <div class="row align-items-center">
                                <!-- Score Input Column -->
                                <div class="col-lg-5 col-md-12 mb-3 mb-lg-0 border-right-lg pr-lg-4">
                                    <label class="font-weight-bold text-dark small mb-2 d-flex justify-content-between align-items-center">
                                        <span>ให้คะแนนข้อนี้ (เต็ม {{ number_format($maxScore, 2) }} คะแนน):</span>
                                        <span class="text-muted small" id="score-percentage-{{ $question->id }}">
                                            {{ $maxScore > 0 ? round(($currentScore / $maxScore) * 100) : 0 }}%
                                        </span>
                                    </label>
                                    <div class="input-group input-group-lg mb-2 shadow-xs">
                                        <input type="number" 
                                               name="scores[{{ $question->id }}]" 
                                               id="score-input-{{ $question->id }}"
                                               class="form-control text-center font-weight-bold score-field text-primary" 
                                               min="0" 
                                               max="{{ $maxScore }}" 
                                               step="0.1" 
                                               value="{{ $currentScore }}"
                                               data-question-id="{{ $question->id }}"
                                               data-max="{{ $maxScore }}"
                                               style="font-size: 1.35rem;"
                                               required>
                                        <div class="input-group-append">
                                            <span class="input-group-text bg-light font-weight-bold text-muted">/ {{ number_format($maxScore, 1) }}</span>
                                        </div>
                                    </div>

                                    <!-- Quick Score Presets -->
                                    <div class="d-flex flex-wrap quick-score-toolbar">
                                        <div class="btn-group btn-group-sm w-100 shadow-xs" role="group">
                                            <button type="button" class="btn btn-outline-secondary btn-quick-score" 
                                                    data-target="score-input-{{ $question->id }}" 
                                                    data-score="0" title="0 คะแนน (0%)">
                                                0
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-quick-score" 
                                                    data-target="score-input-{{ $question->id }}" 
                                                    data-score="{{ round($maxScore * 0.25, 2) }}" title="25%">
                                                25%
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-quick-score" 
                                                    data-target="score-input-{{ $question->id }}" 
                                                    data-score="{{ round($maxScore * 0.5, 2) }}" title="50% (ครึ่งหนึ่ง)">
                                                50%
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-quick-score" 
                                                    data-target="score-input-{{ $question->id }}" 
                                                    data-score="{{ round($maxScore * 0.75, 2) }}" title="75%">
                                                75%
                                            </button>
                                            <button type="button" class="btn btn-outline-success font-weight-bold btn-quick-score" 
                                                    data-target="score-input-{{ $question->id }}" 
                                                    data-score="{{ $maxScore }}" title="คะแนนเต็ม (100%)">
                                                เต็ม ({{ $maxScore }})
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Feedback & Academic Remarks Column -->
                                <div class="col-lg-7 col-md-12 pl-lg-4">
                                    <label class="font-weight-bold text-dark small mb-1 d-flex justify-content-between align-items-center">
                                        <span>ข้อเสนอแนะ / คำแนะนำทางวิชาการแก่นักศึกษา:</span>
                                        <span class="text-muted font-weight-normal text-xs">(ไม่บังคับ)</span>
                                    </label>
                                    <textarea name="comments[{{ $question->id }}]" 
                                              id="comment-input-{{ $question->id }}"
                                              class="form-control feedback-textarea" 
                                              rows="3" 
                                              placeholder="ระบุข้อเสนอแนะ ข้อคิดเห็น หรือจุดที่ควรปรับปรุงเพื่อให้นักศึกษาเข้าใจเหตุผลของคะแนน...">{{ $currentFeedback }}</textarea>
                                    
                                    <!-- Quick Feedback Chips -->
                                    <div class="mt-2 d-flex flex-wrap align-items-center quick-feedback-chips">
                                        <span class="text-muted small mr-1 font-weight-bold">ข้อความด่วน:</span>
                                        <button type="button" class="btn btn-xs btn-light border text-secondary m-1 btn-quick-feedback" 
                                                data-target="comment-input-{{ $question->id }}" 
                                                data-text="ตอบได้ถูกต้อง ครบถ้วน ตรงประเด็น">
                                            + ครบถ้วนตรงประเด็น
                                        </button>
                                        <button type="button" class="btn btn-xs btn-light border text-secondary m-1 btn-quick-feedback" 
                                                data-target="comment-input-{{ $question->id }}" 
                                                data-text="แนวคิดถูกต้อง แต่อธิบายเหตุผลยังไม่สมบูรณ์">
                                            + แนวคิดถูกแต่ยังไม่อธิบายครบ
                                        </button>
                                        <button type="button" class="btn btn-xs btn-light border text-secondary m-1 btn-quick-feedback" 
                                                data-target="comment-input-{{ $question->id }}" 
                                                data-text="ตอบไม่ตรงประเด็นตามที่โจทย์กำหนด">
                                            + ไม่ตรงประเด็น
                                        </button>
                                        <button type="button" class="btn btn-xs btn-light border text-secondary m-1 btn-quick-feedback" 
                                                data-target="comment-input-{{ $question->id }}" 
                                                data-text="ขาดการยกตัวอย่างหรือข้อมูลอ้างอิงประกอบ">
                                            + ขาดตัวอย่างประกอบ
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <!-- ============================================== -->
                <!-- CHOICE QUESTION CARD (Auto-Graded Reference)   -->
                <!-- ============================================== -->
                @php
                    $isCorrect = $ans ? $ans->is_correct : false;
                    $studentChoiceId = $ans ? $ans->choice_id : null;
                @endphp
                <div class="card card-outline {{ $isCorrect ? 'card-success' : 'card-secondary' }} shadow-xs mb-3 question-card choice-question-card" 
                     id="question-card-{{ $question->id }}"
                     data-question-id="{{ $question->id }}"
                     data-score="{{ $isCorrect ? $maxScore : 0 }}">
                    <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center flex-wrap">
                            <span class="font-weight-bold text-dark text-sm mr-2">
                                ข้อที่ {{ $questionNumber }} (ปรนัย / เลือกตอบ)
                            </span>
                            @if($isCorrect)
                                <span class="text-success font-weight-bold small">
                                    ถูกต้อง (+{{ number_format($maxScore, 2) }} คะแนน)
                                </span>
                            @else
                                <span class="text-danger font-weight-bold small">
                                    ไม่ถูกต้อง (0.00 / {{ number_format($maxScore, 2) }} คะแนน)
                                </span>
                            @endif
                        </div>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="ย่อ/ขยาย">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body py-3 px-3 choice-card-body">
                        <div class="font-weight-bold text-dark mb-3 text-sm">{!! $question->question_text !!}</div>
                        @if($question->question_image)
                            <div class="mb-3">
                                <img src="{{ asset($question->question_image) }}" class="img-fluid img-thumbnail" style="max-height: 180px; border-radius: 6px;">
                            </div>
                        @endif
                        <div class="row">
                            @foreach($question->choices as $choiceIndex => $choice)
                                @php
                                    $isStudentPick = ($studentChoiceId == $choice->id);
                                    $itemClass = 'bg-light';
                                    $choiceLetterClass = 'text-muted';
                                    if ($choice->is_correct) {
                                        $choiceLetterClass = 'text-success font-weight-bold';
                                        $itemClass = 'choice-item-correct';
                                    } elseif ($isStudentPick && !$choice->is_correct) {
                                        $choiceLetterClass = 'text-danger font-weight-bold';
                                        $itemClass = 'choice-item-wrong';
                                    }
                                @endphp
                                <div class="col-md-6 mb-2">
                                    <div class="p-2 border rounded small d-flex align-items-center {{ $itemClass }}">
                                        <span class="mr-2 {{ $choiceLetterClass }}" style="min-width: 22px;">{{ chr(65 + $choiceIndex) }}.</span>
                                        <span class="flex-grow-1 text-dark">{{ $choice->choice_text }}</span>
                                        @if($isStudentPick)
                                            <span class="{{ $choice->is_correct ? 'text-success' : 'text-danger' }} font-weight-bold ml-auto small">
                                                คำตอบนักศึกษา
                                            </span>
                                        @endif
                                        @if($choice->is_correct && !$isStudentPick)
                                            <span class="text-success font-weight-bold ml-auto small">
                                                เฉลย
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endforeach

        <!-- 4. Bottom Sticky Action Toolbar -->
        <div class="sticky-bottom-bar bg-white border-top shadow-lg p-3 rounded mt-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div class="mb-2 mb-md-0">
                    <a href="{{ route('admin.grading.index', ['exam_id' => $attempt->exam_id]) }}" class="btn font-weight-bold btn-danger">
                        กลับหน้ารายการ
                    </a>
                </div>

                <!-- Live Score Summary Badge in Bar -->
                <div class="d-flex align-items-center mx-md-auto mb-2 mb-md-0 px-3 py-1 bg-light rounded border">
                    <div class="mr-3 text-center">
                        <small class="text-muted font-weight-bold d-block">คะแนนรวมสุทธิ</small>
                        <span class="h5 font-weight-bold text-primary mb-0" id="live-total-score">
                            {{ number_format($attempt->score ?? 0, 2) }}
                        </span>
                        <small class="text-muted font-weight-normal">/ {{ number_format($totalPossibleScore, 2) }}</small>
                    </div>
                    <div class="border-left pl-3 text-center">
                        <small class="text-muted font-weight-bold d-block">ผลการประเมิน</small>
                        <span id="live-pass-badge" class="{{ $isPending ? 'text-warning-dark' : ($attempt->is_passed ? 'text-success' : 'text-danger') }} font-weight-bold">
                            @if($isPending)
                                รอตรวจข้อเขียน
                            @else
                                {{ $attempt->is_passed ? 'ผ่านเกณฑ์' : 'ไม่ผ่านเกณฑ์' }} ({{ $totalPossibleScore > 0 ? round((($attempt->score ?? 0) / $totalPossibleScore) * 100) : 0 }}%)
                            @endif
                        </span>
                    </div>
                </div>

                <div class="d-flex align-items-center">
                    <button type="submit" class="btn btn-success font-weight-bold px-4 mr-2 shadow-xs" onclick="$('#formAction').val('save');">
                        บันทึกผลการตรวจ
                    </button>
                    @if($nextAttempt)
                        <button type="submit" class="btn btn-primary font-weight-bold px-4 shadow-xs" onclick="$('#formAction').val('save_and_next');" title="บันทึกและเปิดชุดของ {{ $nextAttempt->user->name }}">
                            บันทึกแล้วตรวจคนถัดไป ({{ $nextAttempt->user->name }})
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </form>
@stop

@section('css')
    <style>
        .letter-spacing-sm {
            letter-spacing: 0.5px;
        }
        .bg-navy {
            background-color: #1e293b !important;
        }
        .text-warning-dark {
            color: #b45309 !important;
        }
        .avatar-circle {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        .shadow-xs {
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        @media (min-width: 992px) {
            .border-right-lg {
                border-right: 1px solid #e2e8f0 !important;
            }
        }
        .line-height-md {
            line-height: 1.5;
        }
        .sticky-nav-card {
            position: sticky;
            top: 56px;
            z-index: 1020;
            border-radius: 8px;
        }
        .question-nav-track {
            flex-wrap: wrap;
        }
        .question-card {
            scroll-margin-top: 120px;
            border-radius: 8px;
        }
        .hover-zoom {
            transition: transform 0.2s ease;
        }
        .hover-zoom:hover {
            transform: scale(1.02);
        }
        .choice-item-correct {
            background-color: #f0fdf4 !important;
            border-color: #86efac !important;
            color: #166534 !important;
            font-weight: 600;
        }
        .choice-item-wrong {
            background-color: #fef2f2 !important;
            border-color: #fca5a5 !important;
            color: #991b1b !important;
            font-weight: 600;
        }

        .sticky-bottom-bar {
            position: sticky;
            bottom: 15px;
            z-index: 1010;
            backdrop-filter: blur(8px);
            background-color: rgba(255, 255, 255, 0.96) !important;
            border: 1px solid #cbd5e1 !important;
        }
        .border-danger-light {
            border-color: #fecaca !important;
        }
        .attempt-dropdown-item {
            border-bottom: 1px solid #f1f5f9;
            transition: background-color 0.15s ease;
            white-space: normal;
            color: #1e293b;
        }
        .attempt-dropdown-item:last-child {
            border-bottom: none;
        }
        .attempt-dropdown-item:hover {
            background-color: #f8fafc;
            color: #1e293b;
        }
        .attempt-dropdown-item.current-attempt {
            background-color: #eff6ff !important;
            border-left: 4px solid #2563eb !important;
            color: #1e293b !important;
        }
        .attempt-dropdown-item.current-attempt:hover,
        .attempt-dropdown-item:active {
            background-color: #dbeafe !important;
            color: #1e293b !important;
        }
    </style>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            var totalRawScore = {{ $totalRawScore }};
            var examTargetScore = {{ $examTargetScore }};
            var isScaled = {{ $isScaled ? 'true' : 'false' }};
            var passingPercentage = {{ $attempt->exam->passing_percentage ?? 50 }};
            var autoChoiceScore = {{ $choiceScoreAwarded }};
            var isPending = {{ $isPending ? 'true' : 'false' }};
            var hasUserEdited = false;

            // Calculate live totals on input changes
            function recalculateTotals() {
                var essayTotal = 0;
                $('.score-field').each(function() {
                    var val = parseFloat($(this).val()) || 0;
                    var max = parseFloat($(this).data('max')) || 0;
                    if (val > max) {
                        val = max;
                        $(this).val(max);
                    } else if (val < 0) {
                        val = 0;
                        $(this).val(0);
                    }
                    essayTotal += val;

                    // Update question specific text & percentage
                    var qId = $(this).data('question-id');
                    var percent = max > 0 ? Math.round((val / max) * 100) : 0;
                    $('#score-percentage-' + qId).text(percent + '%');

                    var statusBadge = $('#status-badge-' + qId);
                    if (val > 0) {
                        statusBadge.html('<span class="text-success font-weight-bold">ให้คะแนนแล้ว: ' + val.toFixed(1) + ' / ' + max.toFixed(1) + '</span>');
                    } else {
                        statusBadge.html('<span class="text-warning-dark font-weight-bold">ยังไม่ได้ให้คะแนน</span>');
                    }
                });

                var currentRawTotal = autoChoiceScore + essayTotal;
                var currentFinalScore = (totalRawScore > 0 && examTargetScore > 0)
                    ? (currentRawTotal / totalRawScore) * examTargetScore
                    : currentRawTotal;

                var totalFormatted = currentFinalScore.toFixed(2);
                var percentage = examTargetScore > 0 ? ((currentFinalScore / examTargetScore) * 100).toFixed(1) : 0;
                var isPassed = parseFloat(percentage) >= passingPercentage;

                // Update Header Summary
                $('#header-total-score').text(totalFormatted);
                $('#header-percentage').text(percentage + '%');
                if (isScaled) {
                    $('#header-raw-score').text(currentRawTotal.toFixed(1));
                }

                var badgeHtml = '';
                var liveBadge = $('#live-pass-badge');
                if (isPending && !hasUserEdited) {
                    badgeHtml = '<span class="text-warning-dark font-weight-bold">รอตรวจข้อเขียน</span>';
                    liveBadge.removeClass('text-danger text-success text-secondary').addClass('text-warning-dark font-weight-bold').text('รอตรวจข้อเขียน');
                } else if (isPassed) {
                    badgeHtml = '<span class="text-success font-weight-bold">ผ่านเกณฑ์ (≥' + passingPercentage + '%)</span>';
                    liveBadge.removeClass('text-danger text-warning-dark text-secondary').addClass('text-success font-weight-bold').text('ผ่านเกณฑ์ (' + Math.round(percentage) + '%)');
                } else {
                    badgeHtml = '<span class="text-danger font-weight-bold">ไม่ผ่านเกณฑ์ (<' + passingPercentage + '%)</span>';
                    liveBadge.removeClass('text-success text-warning-dark text-secondary').addClass('text-danger font-weight-bold').text('ไม่ผ่านเกณฑ์ (' + Math.round(percentage) + '%)');
                }
                $('#header-pass-badge-container').html(badgeHtml);

                // Update Bottom Bar Live Stats
                $('#live-total-score').text(totalFormatted);
            }

            // Quick score button click handler
            $('.btn-quick-score').on('click', function() {
                hasUserEdited = true;
                var targetId = $(this).data('target');
                var score = $(this).data('score');
                $('#' + targetId).val(score).trigger('input');
            });

            // Quick feedback chip click handler
            $('.btn-quick-feedback').on('click', function() {
                var targetId = $(this).data('target');
                var textToAdd = $(this).data('text');
                var textarea = $('#' + targetId);
                var currentText = textarea.val().trim();
                if (currentText.length > 0) {
                    textarea.val(currentText + '\n' + textToAdd);
                } else {
                    textarea.val(textToAdd);
                }
                textarea.trigger('input');
            });

            // Score input event
            $('.score-field').on('input change', function() {
                hasUserEdited = true;
                recalculateTotals();
            });

            // Copy Student Answer to Clipboard
            $('.btn-copy-answer').on('click', function() {
                var targetId = $(this).data('target');
                var text = $('#' + targetId).text().trim();
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function() {
                        toastr.success('คัดลอกคำตอบของนักศึกษาแล้ว');
                    });
                } else {
                    var tempInput = $('<textarea>');
                    $('body').append(tempInput);
                    tempInput.val(text).select();
                    document.execCommand('copy');
                    tempInput.remove();
                    toastr.success('คัดลอกคำตอบของนักศึกษาแล้ว');
                }
            });

            // Filter Toolbar Buttons
            $('#btn-filter-all').on('click', function() {
                $(this).addClass('active').siblings().removeClass('active');
                $('.question-card').show();
                $('.section-divider-card').show();
            });

            $('#btn-filter-essay').on('click', function() {
                $(this).addClass('active').siblings().removeClass('active');
                $('.choice-question-card').hide();
                $('.essay-question-card').show();
            });

            $('#btn-toggle-choices').on('click', function() {
                $('.choice-question-card .choice-card-body').collapse('toggle');
            });

            // Keyboard Shortcuts: Ctrl+S / Cmd+S to Save
            $(document).on('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    $('#formAction').val('save');
                    $('#gradingForm').submit();
                }
            });

            // Initial calculation
            recalculateTotals();

            @if(session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ!',
                    text: {!! json_encode(session('success')) !!},
                    timer: 2500,
                    showConfirmButton: true
                });
            @endif
        });
    </script>
@stop
