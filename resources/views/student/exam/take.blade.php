@extends('adminlte::page')

@section('title', 'กำลังทำข้อสอบ')

@section('content_header')
    <div>
        <!-- <-h1 class="text-dark font-weightbold">กำลังทำข้อสอบ</h1> -->
        <h5 class="text-muted mt-1">{{ $exam->title }} ({{ $exam->subject->code }})</h5>
    </div>
@stop

@section('content')
@php
    $sections = $questions->groupBy('exam_section_id');
    $sectionIds = $sections->keys()->toArray();
    $sectionCount = count($sectionIds);
    $hasSections = $sectionCount > 1 || ($sectionCount === 1 && $sectionIds[0] !== null);
@endphp

{{-- ===== REVOKED ELIGIBILITY FULLSCREEN OVERLAY ===== --}}
<div id="revokedEligibilityOverlay" class="d-none" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.96); z-index: 999999; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(8px); padding: 20px;">
    <div class="card shadow-lg border-danger text-center p-4 p-md-5" style="max-width: 520px; border-radius: 16px; border-width: 2px; background: #ffffff;">
        <div class="mb-3">
            <div class="mx-auto text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 84px; height: 84px; background-color: #fee2e2;">
                <i class="fas fa-ban fa-3x text-danger"></i>
            </div>
        </div>
        <h3 class="font-weight-bold text-dark mb-2">คุณถูกระงับสิทธิ์การเข้าสอบ!</h3>
        <div class="alert alert-danger py-2 px-3 mb-3 text-left font-weight-bold" style="font-size: 0.95rem; border-radius: 8px;">
            <i class="fas fa-exclamation-circle mr-1"></i> สาเหตุ: <span id="revokedReasonText">ระงับสิทธิ์สอบ (ติดต่อฝ่ายการเงิน/ทะเบียน)</span>
        </div>
        <p class="text-secondary mb-4" style="font-size: 0.95rem; line-height: 1.6;">
            ระบบได้ทำการปิดสิทธิ์และระงับการทำข้อสอบชุดนี้ของคุณเรียบร้อยแล้ว ไม่สามารถเลือกคำตอบ บันทึก หรือส่งข้อสอบได้อีกต่อไป กรุณาติดต่ออาจารย์ผู้สอนหรือฝ่ายการเงิน/ทะเบียน
        </p>
        <a href="{{ route('student.dashboard') }}" class="btn btn-danger btn-lg font-weight-bold px-4 shadow-sm" style="border-radius: 8px;">
            <i class="fas fa-arrow-left mr-2"></i> กลับสู่หน้าหลักนักศึกษา
        </a>
    </div>
</div>

<form id="examForm" action="{{ route('student.exam.submit', $attempt->id) }}" method="post">
    @csrf

    <div class="row">
    {{-- ===== LEFT: Questions Column ===== --}}
    <div class="col-md-9 order-2 order-md-1">

    @if($hasSections)
    {{-- ===== SECTION-BY-SECTION MODE ===== --}}
    <div id="section-progress-bar" class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <small class="text-muted font-weight-bold" id="section-progress-label">ตอนที่ 1 / {{ $sectionCount }}</small>
            <small class="text-muted" id="section-progress-pct">0%</small>
        </div>
        <div class="progress" style="height:5px; border-radius:3px;">
            <div class="progress-bar bg-info" id="section-progress-fill" style="width:0%; transition: width 0.4s ease;"></div>
        </div>
    </div>

    @foreach($sections as $sectId => $sectQuestions)
    @php
        $sectionIndex  = array_search($sectId, $sectionIds);
        $firstQ        = $sectQuestions->first();
        $sectTitle     = $firstQ && $firstQ->examSection ? $firstQ->examSection->title : 'ตอนที่ ' . ($sectionIndex + 1);
        $sectInstr     = $firstQ && $firstQ->examSection ? $firstQ->examSection->instruction : '';
        $isLast        = $sectionIndex === $sectionCount - 1;
        $isFirst       = $sectionIndex === 0;
    @endphp
    <div class="exam-section-panel {{ $isFirst ? '' : 'd-none' }}"
         data-section-index="{{ $sectionIndex }}"
         data-section-id="{{ $sectId ?? 'null' }}"
         data-section-title="{{ $sectTitle }}"
         data-is-last="{{ $isLast ? '1' : '0' }}">

        {{-- Section header --}}
        <div class="mb-4 pl-3" style="border-left: 4px solid #17a2b8;">
            <h5 class="font-weight-bold mb-1 text-dark">{{ $sectTitle }}</h5>
            @if($sectInstr)
            <p class="text-xs mb-0 text-muted">{{ $sectInstr }}</p>
            @endif
        </div>

        {{-- Questions --}}
        @foreach($sectQuestions as $question)
        @php
            $overallIndex  = $questions->search(fn($q) => $q->id === $question->id);
            $savedChoiceId = $savedAnswers[$question->id] ?? null;
        @endphp
        <div class="card card-outline card-primary shadow-sm mb-3 question-card" id="question-{{ $question->id }}">
            <div class="card-header bg-light py-2">
                <h6 class="card-title font-weight-bold text-dark mb-0">ข้อที่ {{ $overallIndex + 1 }}</h6>
            </div>
            <div class="card-body py-3">
                <div class="font-weight-bold text-dark mb-3" style="font-size:0.97rem;line-height:1.6;">{!! $question->question_text !!}</div>

                @if($question->type === 'essay')
                <div class="form-group">
                    <label class="font-weight-bold text-dark text-sm">พิมพ์คำตอบของคุณด้านล่าง <span class="text-danger">*</span></label>
                    <textarea name="text_answers[{{ $question->id }}]"
                              data-question-id="{{ $question->id }}"
                              class="form-control exam-textarea"
                              rows="4"
                              placeholder="กรอกคำตอบของคุณที่นี่..."
                              style="font-size:0.95rem;padding:9px 12px;border-radius:8px;">{{ $savedTextAnswers[$question->id] ?? '' }}</textarea>
                </div>
                @else
                <div class="row">
                    @foreach($question->choices as $choiceIndex => $choice)
                    <div class="col-12 mb-2">
                        <div class="custom-control custom-radio p-2 border rounded choice-box {{ $savedChoiceId == $choice->id ? 'choice-selected border-primary bg-primary-light' : 'border-secondary-light bg-light' }}" style="cursor:pointer;">
                            <input type="radio" id="choice_{{ $choice->id }}" name="answers[{{ $question->id }}]"
                                   value="{{ $choice->id }}" data-question-id="{{ $question->id }}"
                                   class="custom-control-input exam-radio"
                                   {{ $savedChoiceId == $choice->id ? 'checked' : '' }}>
                            <label class="custom-control-label text-dark w-100 pl-2" style="cursor:pointer;font-size:0.9rem;" for="choice_{{ $choice->id }}">
                                <span class="mr-2">{{ chr(65 + $choiceIndex) }}.</span>
                                {{ $choice->choice_text }}
                                @if($choice->choice_image)
                                <div class="choice-image-container mt-2">
                                    <img src="{{ asset($choice->choice_image) }}" class="img-fluid img-thumbnail" style="max-height:150px;border-radius:8px;">
                                </div>
                                @endif
                            </label>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
        @endforeach

        {{-- Section footer action --}}
        <div class="d-flex justify-content-end mt-4 mb-5">
            @if(!$isLast)
            <button type="button" class="btn btn-info btn-next-section font-weight-bold px-5 py-2 shadow-sm"
                    data-section-id="{{ $sectId ?? 'null' }}"
                    data-section-title="{{ $sectTitle }}"
                    data-next-index="{{ $sectionIndex + 1 }}">
                ส่วนถัดไป <i class="fas fa-chevron-right ml-2"></i>
            </button>
            @else
            <button type="submit" class="btn btn-primary font-weight-bold px-5 py-2 shadow-sm">
                <i class="fas fa-paper-plane mr-2"></i> ส่งข้อสอบ
            </button>
            @endif
        </div>
    </div>
    @endforeach

    @else
    {{-- ===== SINGLE PAGE MODE (no sections) ===== --}}
    @foreach($questions as $index => $question)
    @php $savedChoiceId = $savedAnswers[$question->id] ?? null; @endphp
    <div class="card card-outline card-primary shadow-sm mb-3 question-card" id="question-{{ $question->id }}">
        <div class="card-header bg-light py-2">
            <h6 class="card-title font-weight-bold text-dark mb-0">ข้อที่ {{ $index + 1 }}</h6>
            <div class="card-tools">
                <span class="badge badge-primary px-2 py-1">{{ $question->score }} คะแนน</span>
            </div>
        </div>
        <div class="card-body py-3">
            <div class="font-weight-bold text-dark mb-3" style="font-size:0.97rem;line-height:1.6;">{!! $question->question_text !!}</div>

            @if($question->type === 'essay')
            <div class="form-group">
                <label class="font-weight-bold text-dark text-sm">พิมพ์คำตอบของคุณด้านล่าง <span class="text-danger">*</span></label>
                <textarea name="text_answers[{{ $question->id }}]"
                          data-question-id="{{ $question->id }}"
                          class="form-control exam-textarea"
                          rows="4"
                          placeholder="กรอกคำตอบของคุณที่นี่..."
                          style="font-size:0.95rem;padding:9px 12px;border-radius:8px;">{{ $savedTextAnswers[$question->id] ?? '' }}</textarea>
            </div>
            @else
            <div class="row">
                @foreach($question->choices as $choiceIndex => $choice)
                <div class="col-12 mb-2">
                    <div class="custom-control custom-radio p-2 border rounded choice-box {{ $savedChoiceId == $choice->id ? 'choice-selected border-primary bg-primary-light' : 'border-secondary-light bg-light' }}" style="cursor:pointer;">
                        <input type="radio" id="choice_{{ $choice->id }}" name="answers[{{ $question->id }}]"
                               value="{{ $choice->id }}" data-question-id="{{ $question->id }}"
                               class="custom-control-input exam-radio"
                               {{ $savedChoiceId == $choice->id ? 'checked' : '' }}>
                        <label class="custom-control-label text-dark w-100 pl-2" style="cursor:pointer;font-size:0.9rem;" for="choice_{{ $choice->id }}">
                            <span class="mr-2">{{ chr(65 + $choiceIndex) }}.</span>
                            {{ $choice->choice_text }}
                            @if($choice->choice_image)
                            <div class="choice-image-container mt-2">
                                <img src="{{ asset($choice->choice_image) }}" class="img-fluid img-thumbnail" style="max-height:150px;border-radius:8px;">
                            </div>
                            @endif
                        </label>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
    @endforeach
    <div class="card bg-light shadow-sm mb-5 text-center py-4">
        <h5 class="font-weight-bold text-dark mb-3">เมื่อตรวจสอบความถูกต้องครบถ้วนแล้ว กรุณากดปุ่มส่งข้อสอบ</h5>
        <button type="submit" class="btn btn-primary btn-lg font-weight-bold shadow px-5">
            <i class="fas fa-paper-plane mr-2"></i> ส่งข้อสอบ
        </button>
    </div>
    @endif

    </div>{{-- end col-md-9 --}}

    {{-- ===== RIGHT: Navigator Sidebar ===== --}}
    <div class="col-md-3 navigator-column order-1 order-md-2">
        <div class="card card-outline card-primary shadow-sm navigator-card">
            <!-- Desktop header -->
            <div class="card-header bg-dark text-center py-3 d-none d-md-block">
                <h5 class="font-weight-bold text-white mb-0"><i class="fas fa-th mr-2"></i>ตัวนำทางข้อสอบ</h5>
            </div>

            <!-- Mobile toggle strip -->
            <div class="d-flex d-md-none align-items-center justify-content-between px-3 py-1 mobile-nav-toggle-bar" id="mobileNavToggle" style="cursor:pointer; border-bottom: 1px solid #e9ecef; user-select:none;">
                <span class="text-xs font-weight-bold text-dark">
                    <i class="fas fa-th-large text-info mr-1" style="font-size:11px;"></i> ตัวนำทาง
                </span>
                <i class="fas fa-chevron-down text-muted mobile-nav-chevron" style="font-size:11px; transition: transform 0.25s;"></i>
            </div>

            <div class="card-body p-2 p-md-3" id="mobileNavBody">
                <p class="text-xs text-muted mb-3 text-center d-none d-md-block">คลิกตัวเลขเพื่อเลื่อนไปยังข้อนั้นๆ</p>

                @php $navGrouped = $questions->groupBy('exam_section_id'); @endphp
                @foreach($navGrouped as $nSectId => $nSectQuestions)
                @php
                    $nFirst = $nSectQuestions->first();
                    $nTitle = $nFirst && $nFirst->examSection ? $nFirst->examSection->title : 'ไม่มีกลุ่มตอน';
                    $nIndex = array_search($nSectId, $sectionIds);
                @endphp
                <div class="mt-2 mb-1 font-weight-bold text-dark text-xs nav-section-group"
                     data-nav-section-index="{{ $nIndex }}">{{ $nTitle }}</div>
                <div class="nav-buttons-container mb-2 nav-section-btns"
                     data-nav-section-index="{{ $nIndex }}">
                    @foreach($nSectQuestions as $q)
                    @php
                        $qi = $questions->search(fn($x) => $x->id === $q->id);
                        $qAnswered = isset($savedAnswers[$q->id]) || isset($savedTextAnswers[$q->id]);
                    @endphp
                    <a href="#question-{{ $q->id }}" id="nav-btn-{{ $q->id }}"
                       class="nav-grid-btn {{ $qAnswered ? 'answered' : '' }}">
                        {{ $qi + 1 }}
                    </a>
                    @endforeach
                </div>
                @endforeach

                <div class="navigator-legend-and-action d-none d-md-block">
                    <hr class="my-3">
                    <div class="d-flex justify-content-between text-xs text-muted px-2 mb-3">
                        <div><span class="d-inline-block rounded-circle bg-success mr-1" style="width:8px;height:8px;"></span> ตอบแล้ว</div>
                        <div><span class="d-inline-block rounded-circle bg-light border mr-1" style="width:8px;height:8px;"></span> ยังไม่ตอบ</div>
                        <div><span class="d-inline-block rounded-circle bg-primary-light border border-primary mr-1" style="width:8px;height:8px;"></span> กำลังดู</div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block font-weight-bold shadow-sm py-2">
                        <i class="fas fa-paper-plane mr-2"></i>ส่งข้อสอบ
                    </button>
                </div>
            </div>
        </div>
    </div>{{-- end col-md-3 --}}

    </div>{{-- end row --}}
</form>

{{-- ===== Section Score Modal ===== --}}
<div class="modal fade" id="sectionScoreModal" tabindex="-1" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;overflow:hidden;">
            <div class="modal-body text-center p-5">
                <div class="mb-3">
                    <i class="fas fa-check-circle text-success" style="font-size:3rem;"></i>
                </div>
                <h4 class="font-weight-bold text-dark mb-1" id="modal-section-title">สิ้นสุดตอน</h4>
                <p class="text-muted mb-4" id="modal-section-answered"></p>

                <div class="p-3 mb-4 rounded" style="background:#f0fdf4;border:2px solid #bbf7d0;">
                    <div class="text-muted text-sm mb-1">คะแนนที่ได้ในตอนนี้</div>
                    <div class="font-weight-bold" style="font-size:2rem;color:#16a34a;" id="modal-score-display">—</div>
                </div>

                <button type="button" class="btn btn-info btn-block font-weight-bold py-2 shadow-sm" id="modal-next-btn">
                    ทำส่วนถัดไป <i class="fas fa-chevron-right ml-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>
@stop


@section('css')
    <style>
        /* Prevent text selection during exam */
        body {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }

        /* Hide main sidebar to maximize exam focus */
        .main-sidebar {
            display: none !important;
        }
        .content-wrapper {
            margin-left: 0 !important;
        }
        .main-header {
            margin-left: 0 !important;
        }
        .bg-primary-light {
            background-color: rgba(0, 123, 255, 0.08) !important;
        }
        .choice-selected {
            border-width: 2px !important;
        }
        .border-secondary-light {
            border-color: #e9ecef !important;
        }
        .gap-2 {
            gap: 0.5rem;
        }
        .choice-box:hover {
            border-color: #007bff !important;
        }

        /* Question Navigation Buttons styles */
        .nav-grid-btn {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            margin: 4px;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
            border: 1.5px solid #dee2e6;
            background-color: #fff;
            color: #495057;
            text-decoration: none !important;
        }
        .nav-grid-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.08);
            border-color: #007bff;
            color: #007bff;
        }
        .nav-grid-btn.answered {
            background-color: #28a745;
            border-color: #28a745;
            color: #fff;
        }
        .nav-grid-btn.answered:hover {
            background-color: #218838;
            border-color: #218838;
            color: #fff;
        }
        .nav-grid-btn.current-active {
            border-color: #007bff !important;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            background-color: #e8f0fe;
            color: #007bff;
        }
        .nav-grid-btn.current-active.answered {
            background-color: #28a745;
            color: #fff;
            border-color: #007bff !important;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.35);
        }

        /* Responsive Layout Overrides */
        .nav-buttons-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            width: 100%;
        }

        @media (min-width: 768px) {
            .navigator-card {
                position: sticky;
                top: 75px;
                z-index: 10;
            }
        }

        @media (max-width: 767.98px) {
            /* Stick the entire navigator below the navbar */
            .navigator-column {
                position: sticky;
                top: 57px;
                z-index: 1020;
                padding: 0 !important;
                background-color: #fff;
                box-shadow: 0 4px 10px rgba(0,0,0,0.08);
                margin-bottom: 15px;
                /* Critical: constrain to screen width, never overflow */
                max-width: 100vw;
                overflow: hidden;
            }
            .navigator-card {
                border-top: none !important;
                border-radius: 0 !important;
                margin-bottom: 0 !important;
                box-shadow: none !important;
                border: none !important;
                background-color: transparent !important;
                overflow: hidden;
            }
            .navigator-card .card-body {
                padding: 2px 6px !important;
                overflow: hidden;
            }
            /* Section label on mobile: plain small text */
            .navigator-card .mt-2.mb-1 {
                display: block;
                font-size: 9px;
                color: #6c757d;
                padding: 1px 0;
                margin: 3px 0 1px 0;
                white-space: nowrap;
            }
            /* Wrap buttons into rows — no horizontal overflow */
            .nav-buttons-container {
                display: flex;
                flex-wrap: wrap;
                justify-content: flex-start;
                padding: 2px 0 4px;
                max-width: 100%;
                overflow: visible;
            }
            .nav-grid-btn {
                width: 26px;
                height: 26px;
                font-size: 0.7rem;
                margin: 2px;
                border-radius: 6px;
                flex-shrink: 0;
            }
            /* Collapsed state */
            #mobileNavBody.nav-collapsed {
                display: none !important;
            }
            .mobile-nav-chevron.rotated {
                transform: rotate(180deg);
            }
            /* Toggle bar hover */
            .mobile-nav-toggle-bar:active {
                background-color: #f8f9fa;
            }
        }        
    </style>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            // Mobile navigator collapse toggle
            var $navToggle = $('#mobileNavToggle');
            var $navBody   = $('#mobileNavBody');
            var $chevron   = $navToggle.find('.mobile-nav-chevron');

            // Start collapsed on mobile
            if (window.innerWidth < 768) {
                $navBody.addClass('nav-collapsed');
                $chevron.addClass('rotated');
            }

            $navToggle.on('click', function() {
                $navBody.toggleClass('nav-collapsed');
                $chevron.toggleClass('rotated');
            });

            // Auto-expand when a nav button is clicked (so user can see it)
            $(document).on('click', '.nav-grid-btn', function() {
                if (window.innerWidth < 768 && !$navBody.hasClass('nav-collapsed')) {
                    // Collapse after navigating so user sees the question
                    setTimeout(function() {
                        $navBody.addClass('nav-collapsed');
                        $chevron.addClass('rotated');
                    }, 400);
                }
            });

            // ====================================================
            // SECTION-BY-SECTION NAVIGATION
            // ====================================================
            var sectionCount = {{ $sectionCount ?? 1 }};
            var hasSections  = {{ ($hasSections ?? false) ? 'true' : 'false' }};
            var pendingNextIndex = null;

            function updateSectionProgress(currentIndex) {
                var pct = Math.round(((currentIndex) / sectionCount) * 100);
                $('#section-progress-fill').css('width', pct + '%');
                $('#section-progress-pct').text(pct + '%');
                $('#section-progress-label').text('ตอนที่ ' + (currentIndex + 1) + ' / ' + sectionCount);
            }

            // "ส่วนถัดไป" button click
            $(document).on('click', '.btn-next-section', function() {
                var btn        = $(this);
                var sectionId  = btn.data('section-id');
                var sectionTitle = btn.data('section-title');
                var nextIndex  = btn.data('next-index');

                pendingNextIndex = nextIndex;

                // Disable button while loading
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> กำลังโหลด...');

                $.ajax({
                    url: "{{ route('student.exam.sectionScore', $attempt->id) }}",
                    method: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        section_id: sectionId
                    },
                    success: function(res) {
                        // Populate modal
                        $('#modal-section-title').text('สิ้นสุด ' + sectionTitle);
                        $('#modal-section-answered').text('คุณตอบแล้ว ' + res.answered + ' / ' + res.question_count + ' ข้อ');
                        $('#modal-score-display').text(res.earned + ' / ' + res.total + ' คะแนน');
                        $('#sectionScoreModal').modal('show');
                    },
                    error: function() {
                        // Even on error, allow proceeding
                        pendingNextIndex = nextIndex;
                        advanceToNextSection(nextIndex);
                        btn.prop('disabled', false).html('ส่วนถัดไป <i class="fas fa-chevron-right ml-2"></i>');
                    }
                });
            });

            // Modal "ทำส่วนถัดไป" button
            $('#modal-next-btn').on('click', function() {
                $('#sectionScoreModal').modal('hide');
                if (pendingNextIndex !== null) {
                    advanceToNextSection(pendingNextIndex);
                    pendingNextIndex = null;
                }
            });

            function advanceToNextSection(nextIndex) {
                // Hide all panels, show target
                var panels = $('.exam-section-panel');
                panels.addClass('d-none');
                var $next = panels.filter('[data-section-index="' + nextIndex + '"]');
                $next.removeClass('d-none');

                // Update progress bar
                updateSectionProgress(nextIndex);

                // Scroll to top
                $('html, body').animate({ scrollTop: 0 }, 350);

                // Re-enable any "next" button
                $('.btn-next-section').prop('disabled', false)
                    .html('ส่วนถัดไป <i class="fas fa-chevron-right ml-2"></i>');
            }

            // Init progress on page load
            if (hasSections) {
                updateSectionProgress(0);
            }

            // Count down logic
            var timeRemaining = {{ $timeRemainingSeconds }};
            var countdownEl = $('#navbar-clock');

            function updateTimer() {
                if (timeRemaining <= 0) {
                    clearInterval(timerInterval);
                    if (countdownEl.length) {
                        countdownEl.html('<i class="far fa-clock mr-1"></i> เวลาที่เหลือ 00:00');
                    }
                    Swal.fire({
                        icon: 'warning',
                        title: 'หมดเวลาแล้ว!',
                        text: 'หมดเวลาทำข้อสอบแล้ว! ระบบจะทำการส่งข้อสอบของคุณโดยอัตโนมัติ',
                        confirmButtonText: 'ตกลง',
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(() => {
                        isSubmitted = true;
                        $('#examForm').submit();
                    });
                    return;
                }

                timeRemaining--;

                var minutes = Math.floor(timeRemaining / 60);
                var seconds = timeRemaining % 60;

                minutes = minutes < 10 ? '0' + minutes : minutes;
                seconds = seconds < 10 ? '0' + seconds : seconds;

                if (countdownEl.length) {
                    countdownEl.html('<i class="far fa-clock mr-1"></i> เวลาที่เหลือ ' + minutes + ':' + seconds);
                    
                    // Warning when less than 2 minutes (120 seconds)
                    if (timeRemaining < 120) {
                        countdownEl.removeClass('text-white').addClass('text-danger');
                        // Simple flash effect
                        countdownEl.fadeOut(500).fadeIn(500);
                    }
                }
            }

            // Init and start timer
            updateTimer();
            var timerInterval = setInterval(updateTimer, 1000);

            // ====================================================
            // REVOKED EXAM ELIGIBILITY HANDLER & HEARTBEAT
            // ====================================================
            var isRevoked = false;

            function showRevokedEligibility(reason) {
                if (isRevoked) return;
                isRevoked = true;
                isSubmitted = true; // Prevent anti-cheat focus escapes or timeout auto-submit

                if (typeof timerInterval !== 'undefined') {
                    clearInterval(timerInterval);
                }
                if (typeof heartbeatInterval !== 'undefined') {
                    clearInterval(heartbeatInterval);
                }

                // Exit fullscreen if active
                if (document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement) {
                    if (document.exitFullscreen) {
                        document.exitFullscreen().catch(function() {});
                    } else if (document.webkitExitFullscreen) {
                        document.webkitExitFullscreen();
                    }
                }

                // Lock and disable all exam inputs and submission
                $('input, textarea, button, select').prop('disabled', true);
                $('.choice-box').css('pointer-events', 'none').css('opacity', '0.5');
                $('#examForm').off('submit').on('submit', function(e) { e.preventDefault(); return false; });

                var displayReason = reason || 'ระงับสิทธิ์สอบระดับผู้ใช้งาน (ติดต่อฝ่ายการเงิน/ทะเบียน)';
                $('#revokedReasonText').text(displayReason);
                $('#revokedEligibilityOverlay').removeClass('d-none').css('display', 'flex');

                // Display modal notice
                Swal.fire({
                    icon: 'error',
                    title: 'คุณถูกระงับสิทธิ์การสอบ!',
                    html: '<div class="text-left py-2 px-3 mb-3 font-weight-bold text-danger" style="background:#fee2e2; border:1px solid #f87171; border-radius:8px;"><i class="fas fa-ban mr-1"></i> สาเหตุ: ' + $('<div>').text(displayReason).html() + '</div><p class="text-muted text-sm mb-0">ระบบได้ทำการปิดสิทธิ์และระงับการทำข้อสอบของคุณในวิชานี้ทันที กรุณาติดต่ออาจารย์ผู้สอนหรือฝ่ายการเงิน/ทะเบียน</p>',
                    confirmButtonText: 'รับทราบ และกลับสู่หน้าหลัก',
                    confirmButtonColor: '#dc3545',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showCancelButton: false
                }).then(function() {
                    window.location.href = "{{ route('student.dashboard') }}";
                });
            }

            // Real-time Heartbeat Polling (Checks eligibility every 8 seconds)
            var heartbeatInterval = setInterval(function() {
                if (isRevoked || isSubmitted) {
                    clearInterval(heartbeatInterval);
                    return;
                }

                $.ajax({
                    url: "{{ route('student.exam.checkEligibility', $attempt->id) }}",
                    method: "POST",
                    data: {
                        _token: "{{ csrf_token() }}"
                    },
                    dataType: "json",
                    success: function(res) {
                        if (res && res.eligible === false && !res.completed) {
                            showRevokedEligibility(res.reason);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 403 && xhr.responseJSON && xhr.responseJSON.ineligible) {
                            showRevokedEligibility(xhr.responseJSON.reason);
                        }
                    }
                });
            }, 8000);

            // Click behavior for Choice Box
            $('.choice-box').click(function(e) {
                if (!$(e.target).is('input')) {
                    $(this).find('input[type=radio]').prop('checked', true).trigger('change');
                }
            });

            // Ajax saving logic for radio options
            $('.exam-radio').change(function() {
                var radio = $(this);
                var questionId = radio.attr('data-question-id');
                var choiceId = radio.val();

                // Style update for options inside the question card
                var card = radio.closest('.question-card');
                card.find('.choice-box').removeClass('choice-selected border-primary bg-primary-light').addClass('border-secondary-light bg-light');
                radio.closest('.choice-box').addClass('choice-selected border-primary bg-primary-light').removeClass('border-secondary-light bg-light');

                // Highlight navigator button
                var navBtn = $('#nav-btn-' + questionId);
                navBtn.addClass('answered');

                // Save answer in database
                $.ajax({
                    url: "{{ route('student.exam.saveAnswer', $attempt->id) }}",
                    method: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        question_id: questionId,
                        choice_id: choiceId
                    },
                    success: function(response) {
                        console.log("Saved answer for question " + questionId);
                    },
                    error: function(xhr) {
                        console.error("Failed to save answer", xhr);
                    }
                });
            });

            // Ajax saving logic for textareas (essay questions)
            $('.exam-textarea').on('change blur', function() {
                var textarea = $(this);
                var questionId = textarea.attr('data-question-id');
                var answerText = textarea.val();

                // Highlight navigator button
                var navBtn = $('#nav-btn-' + questionId);
                if (answerText.trim() !== '') {
                    navBtn.addClass('answered');
                } else {
                    navBtn.removeClass('answered');
                }

                // Save answer in database
                $.ajax({
                    url: "{{ route('student.exam.saveAnswer', $attempt->id) }}",
                    method: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        question_id: questionId,
                        answer_text: answerText
                    },
                    success: function(response) {
                        console.log("Saved text answer for question " + questionId);
                    },
                    error: function(xhr) {
                        console.error("Failed to save text answer", xhr);
                    }
                });
            });

            // Smooth scroll for anchor tags
            $('a[href^="#"]').on('click', function(e) {
                e.preventDefault();
                var target = this.hash;
                var $target = $(target);
                var offset = $(window).width() < 768 ? 120 : 80;
                $('html, body').stop().animate({
                    'scrollTop': $target.offset().top - offset
                }, 500, 'swing');
            });

            // ScrollSpy highlight active question in navigator
            function updateActiveQuestionNav() {
                var scrollPosition = $(window).scrollTop() + 150; // offset
                var activeId = null;
                $('.question-card').each(function() {
                    var card = $(this);
                    var top = card.offset().top;
                    var bottom = top + card.outerHeight();
                    var id = card.attr('id').replace('question-', '');
                    
                    if (scrollPosition >= top && scrollPosition <= bottom) {
                        activeId = id;
                    }
                });
                
                if (activeId) {
                    $('.nav-grid-btn').removeClass('current-active');
                    var activeBtn = $('#nav-btn-' + activeId);
                    activeBtn.addClass('current-active');

                    // Center the active button in the mobile horizontal scroll container
                    if ($(window).width() < 768) {
                        var container = $('.nav-buttons-container');
                        if (container.length) {
                            var scrollLeft = activeBtn.position().left + container.scrollLeft() - (container.width() / 2) + (activeBtn.width() / 2);
                            container.stop().animate({ scrollLeft: scrollLeft }, 200);
                        }
                    }
                }
            }

            $(window).on('scroll resize', updateActiveQuestionNav);
            updateActiveQuestionNav(); // run on load

            // Confirm submission handler
            $('#examForm').on('submit', function(e) {
                // If timeRemaining <= 0, it means it is auto-submitted on timeout, so submit directly
                if (timeRemaining <= 0) {
                    return;
                }
                
                e.preventDefault();
                var form = this;
                Swal.fire({
                    title: 'ยืนยันการส่งข้อสอบ?',
                    text: 'คุณแน่ใจหรือไม่ว่าต้องการส่งข้อสอบ? กรุณาตรวจสอบให้แน่ใจว่าทำครบทุกข้อแล้ว!',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#007bff',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'ตกลง, ส่งข้อสอบ!',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        isSubmitted = true;
                        form.submit();
                    }
                });
            });

            // Anti-Cheating & Fullscreen Enforcement
            var forceFullscreen = {{ $exam->force_fullscreen ? 'true' : 'false' }};
            var maxFocusEscapes = {{ $exam->max_focus_escapes }};
            // Load persisted count from server so refresh does NOT reset it
            var focusEscapeCount = {{ $attempt->focus_escape_count ?? 0 }};
            var isSubmitted = false;
            var isWarningOpen = false;
            var lastEscapeReason = '';
            var isWarningModalShown = false;

            function enterFullscreen() {
                var elem = document.documentElement;
                if (elem.requestFullscreen) {
                    elem.requestFullscreen().catch(function(err) {});
                } else if (elem.webkitRequestFullscreen) {
                    elem.webkitRequestFullscreen().catch(function(err) {});
                } else if (elem.msRequestFullscreen) {
                    elem.msRequestFullscreen().catch(function(err) {});
                }
            }

            function handleFocusEscape(reason) {
                if (isSubmitted || isWarningOpen) {
                    return;
                }
                isWarningOpen = true;
                lastEscapeReason = reason;

                // Immediately increment count and show warning modal without waiting for network lag
                focusEscapeCount++;
                showFocusEscapeWarning(reason);

                // Persist to database in background (with keepalive: true so iOS suspend does not drop it)
                var token = "{{ csrf_token() }}";
                var url = "{{ route('student.exam.focusEscape', $attempt->id) }}";

                if (window.fetch) {
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: '_token=' + encodeURIComponent(token),
                        keepalive: true
                    }).then(function(res) {
                        return res.json();
                    }).then(function(res) {
                        if (res && res.focus_escape_count !== undefined) {
                            focusEscapeCount = res.focus_escape_count;
                        }
                    }).catch(function() {});
                } else {
                    $.ajax({
                        url: url,
                        method: "POST",
                        data: { _token: token },
                        success: function(res) {
                            if (res && res.focus_escape_count !== undefined) {
                                focusEscapeCount = res.focus_escape_count;
                            }
                        }
                    });
                }
            }

            function showFocusEscapeWarning(reason) {
                isWarningModalShown = true;
                
                if (maxFocusEscapes > 0 && focusEscapeCount >= maxFocusEscapes) {
                    isSubmitted = true;
                    Swal.fire({
                        title: 'ทำผิดกฎการสอบ!',
                        text: reason + ` เนื่องจากคุณออกจากหน้าจอข้อสอบหรือยกเลิกโหมดเต็มหน้าจอเกินจำนวนครั้งที่กำหนด (${maxFocusEscapes} ครั้ง) ระบบจะส่งข้อสอบโดยอัตโนมัติทันที!`,
                        icon: 'error',
                        confirmButtonText: 'ตกลง',
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(() => {
                        if (document.exitFullscreen) {
                            document.exitFullscreen().catch(() => {});
                        }
                        timeRemaining = 0; // Bypass confirm prompt
                        $('#examForm').submit();
                    });
                } else {
                    var remainingMsg = maxFocusEscapes > 0 
                        ? ` (ทำผิดแล้ว ${focusEscapeCount} / ${maxFocusEscapes} ครั้ง หากครบจะส่งข้อสอบอัตโนมัติ)` 
                        : '';
                    Swal.fire({
                        title: 'คำเตือน!',
                        text: reason + ` ห้ามสลับหน้าจอหรือยกเลิกโหมดเต็มหน้าจอเด็ดขาด!` + remainingMsg,
                        icon: 'warning',
                        confirmButtonText: 'ตกลง, กลับเข้าสู่การสอบ',
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(() => {
                        isWarningOpen = false;
                        isWarningModalShown = false;
                        lastEscapeReason = '';
                        lastHeartbeat = Date.now();
                        lastTickTime = Date.now();
                        unfocusedDuration = 0;
                        touchFromBottom = false;
                        pageGracePeriodUntil = Date.now() + 1000;
                        if (forceFullscreen) {
                            enterFullscreen();
                        }
                    });
                }
            }

            var lastTickTime = Date.now();
            var pageGracePeriodUntil = Date.now() + 2500;
            var unfocusedDuration = 0;
            var touchFromBottom = false;
            var touchStartTime = 0;

            @if(!empty($wasRefreshed))
                isWarningOpen = true;
                Swal.fire({
                    title: 'คำเตือน!',
                    text: 'คุณได้ทำการรีเฟรชหน้าจอข้อสอบ! ห้ามรีเฟรชหรือออกจากหน้าจอข้อสอบเด็ดขาด! (ทำผิดแล้ว {{ $attempt->focus_escape_count }} / {{ $exam->max_focus_escapes }} ครั้ง หากครบจะส่งข้อสอบอัตโนมัติ)',
                    icon: 'warning',
                    confirmButtonText: 'ตกลง, กลับเข้าสู่การสอบ',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then(() => {
                    isWarningOpen = false;
                    lastTickTime = Date.now();
                    unfocusedDuration = 0;
                    touchFromBottom = false;
                    pageGracePeriodUntil = Date.now() + 1000;
                    if (forceFullscreen) {
                        enterFullscreen();
                    }
                });
            @elseif($exam->force_fullscreen)
                // Show fullscreen prompt modal immediately on page load
                Swal.fire({
                    title: 'คำชี้แจงความปลอดภัย',
                    text: 'ข้อสอบนี้บังคับให้ทำในโหมดเต็มหน้าจอ (Fullscreen) เท่านั้น ห้ามสลับหน้าจอหรือปิดโหมดเต็มหน้าจอเด็ดขาด!',
                    icon: 'warning',
                    confirmButtonText: 'เข้าสู่โหมดเต็มหน้าจอเพื่อเริ่มทำข้อสอบ',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then((result) => {
                    lastTickTime = Date.now();
                    unfocusedDuration = 0;
                    touchFromBottom = false;
                    pageGracePeriodUntil = Date.now() + 1000;
                    enterFullscreen();
                });
            @endif

            if (forceFullscreen) {
                $(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange MSFullscreenChange', function() {
                    if (!document.fullscreenElement && !document.webkitIsFullScreen && !document.mozFullScreen && !document.msFullscreenElement) {
                        if (!isSubmitted && !isWarningOpen) {
                            handleFocusEscape('คุณกดยกเลิกโหมดเต็มหน้าจอ!');
                        }
                    }
                });
            }

            var isPageUnloading = false;
            window.addEventListener('beforeunload', function() {
                isPageUnloading = true;
            });

            if (maxFocusEscapes > 0) {
                // 1. Desktop & Window blur
                $(window).on('blur', function() {
                    if (isPageUnloading) return;
                    if (!isSubmitted && !isWarningOpen && !(typeof Swal !== 'undefined' && Swal.isVisible())) {
                        handleFocusEscape('คุณสลับหน้าจอหรือเปิดแท็บใหม่!');
                    }
                });

                // 2. Mobile Visibility Change
                document.addEventListener('visibilitychange', function() {
                    if (isPageUnloading) return;
                    if (document.visibilityState === 'hidden' || document.hidden) {
                        if (!isSubmitted && !isWarningOpen && !(typeof Swal !== 'undefined' && Swal.isVisible())) {
                            handleFocusEscape('คุณสลับหน้าจอหรือเปิดแอปพลิเคชันอื่น!');
                        }
                    }
                });

                // 3. iOS App Switcher Gesture Detection (Bottom swipe-up canceled by OS)
                window.addEventListener('touchstart', function(e) {
                    if (e.touches && e.touches.length > 0) {
                        var y = e.touches[0].clientY;
                        // iOS Home indicator zone is bottom ~85px
                        if (y >= window.innerHeight - 85) {
                            touchFromBottom = true;
                            touchStartTime = Date.now();
                        } else {
                            touchFromBottom = false;
                        }
                    }
                }, { passive: true });

                window.addEventListener('touchcancel', function(e) {
                    if (touchFromBottom && (Date.now() - touchStartTime < 3000)) {
                        touchFromBottom = false;
                        if (!isSubmitted && !isWarningOpen && !(typeof Swal !== 'undefined' && Swal.isVisible())) {
                            handleFocusEscape('คุณเปิดหน้าสลับแอป (App Switcher) หรือออกจากหน้าจอสอบ!');
                        }
                    }
                }, { passive: true });

                window.addEventListener('touchend', function() {
                    touchFromBottom = false;
                }, { passive: true });

                // 4. Returning to page from background / App Switcher
                function checkBackgroundGap() {
                    var now = Date.now();
                    var delta = now - lastTickTime;
                    lastTickTime = now;
                    if (now > pageGracePeriodUntil && delta > 750) {
                        if (!isSubmitted && !isWarningOpen && !(typeof Swal !== 'undefined' && Swal.isVisible())) {
                            handleFocusEscape('คุณเปิดหน้าสลับแอป (App Switcher) หรือออกจากหน้าจอสอบ!');
                        }
                    }
                }

                window.addEventListener('pageshow', checkBackgroundGap);
                window.addEventListener('focus', checkBackgroundGap);

                // 5. Suspension gap and continuous document focus polling
                setInterval(function() {
                    var now = Date.now();
                    var delta = now - lastTickTime;
                    lastTickTime = now;

                    if (now < pageGracePeriodUntil) {
                        return;
                    }

                    if (isSubmitted || isWarningOpen || (typeof Swal !== 'undefined' && Swal.isVisible())) {
                        unfocusedDuration = 0;
                        return;
                    }

                    // A: Thread suspension gap (browser paused/backgrounded by iOS)
                    if (delta > 750) {
                        handleFocusEscape('คุณเปิดหน้าสลับแอป (App Switcher) หรือออกจากหน้าจอสอบ!');
                        return;
                    }

                    // B: document.hasFocus() check (SpringBoard or other window holds focus)
                    if (typeof document.hasFocus === 'function' && !document.hasFocus()) {
                        unfocusedDuration += 150;
                        if (unfocusedDuration >= 450) {
                            handleFocusEscape('คุณเปิดหน้าสลับแอป (App Switcher) หรือออกจากหน้าจอสอบ!');
                        }
                    } else {
                        unfocusedDuration = 0;
                    }
                }, 150);
            }

            // Disable copy, cut, paste and context menu (right click) to prevent cheating
            $(document).on('contextmenu', function(e) {
                e.preventDefault();
            });
            $(document).on('cut copy paste', function(e) {
                e.preventDefault();
            });
            $(document).on('selectstart', function(e) {
                e.preventDefault();
            });
        });
    </script>
@stop
