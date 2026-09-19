@extends('adminlte::page')

@section('title', 'ทดลองทำข้อสอบ (Preview Mode) - ' . $exam->title)

@section('meta_tags')
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
@stop

@section('css')
<style>
    .palette-btn {
        width: 38px;
        height: 38px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.88rem;
        border-radius: 8px;
        transition: all 0.15s ease;
    }

    .choice-item {
        border: 1.5px solid #e9ecef;
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 8px;
        cursor: pointer;
        transition: all 0.15s ease;
        background-color: #ffffff;
    }

    .choice-item:hover {
        background-color: #f8f9fa;
        border-color: #ced4da;
    }

    .choice-item.selected {
        border-color: #007bff;
        background-color: #f0f7ff;
    }

    .choice-item.reveal-correct {
        border-color: #28a745 !important;
        background-color: #e8f5e9 !important;
    }

    .choice-item.reveal-incorrect {
        border-color: #dc3545 !important;
        background-color: #ffebee !important;
    }

    .sticky-sidebar {
        position: -webkit-sticky;
        position: sticky;
        top: 20px;
        z-index: 100;
    }

    .timer-badge {
        font-family: 'Courier New', Courier, monospace;
        font-size: 1.5rem;
        letter-spacing: 1.5px;
    }
</style>
@stop

@section('content_header')
<!-- Preview Mode Notice Banner -->
<div class="shadow-sm py-2 px-3 mb-2 d-flex justify-content-between align-items-center flex-wrap" style="border-radius: 8px;">
    <div class="my-1">
         <a href="{{ route('admin.exams.index', ['subject_id' => $exam->subject_id]) }}" class="btn btn-sm btn-outline-danger font-weight-bold shadow-xs">
            <i class="fas fa-sign-out-alt mr-1"></i>ออกจากโหมดทดลอง
        </a>
        <a href="{{ route('admin.exams.preview', [$exam->id, 'mode' => 'approval']) }}" class="btn btn-sm btn-outline-primary font-weight-bold shadow-xs mr-2">
            <i class="fas fa-clipboard-check mr-1"></i>สลับไปมุมมองตรวจข้อสอบ (พร้อมเฉลย)
        </a>
       
    </div>
</div>
@stop

@section('content')
@php
    $sections = $questions->groupBy('exam_section_id');
    $sectionIds = $sections->keys()->toArray();
    $sectionCount = count($sectionIds);
    $hasSections = $sectionCount > 1 || ($sectionCount === 1 && $sectionIds[0] !== null);
    $thaiChoices = ['ก', 'ข', 'ค', 'ง', 'จ', 'ฉ', 'ช', 'ซ'];
    $totalQuestionCount = $questions->count();
@endphp

<form id="previewExamForm" onsubmit="return false;">
    <div class="row">
        {{-- ===== LEFT: Questions Column ===== --}}
        <div class="col-lg-8 col-md-7 order-2 order-md-1">
            @if($hasSections)
                {{-- Section progress bar --}}
                <div id="section-progress-bar" class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted font-weight-bold" id="section-progress-label">ตอนที่ 1 / {{ $sectionCount }}</small>
                        <small class="text-muted font-weight-bold" id="section-progress-pct">0%</small>
                    </div>
                    <div class="progress" style="height: 6px; border-radius: 3px;">
                        <div class="progress-bar bg-info" id="section-progress-fill" style="width: 0%; transition: width 0.3s ease;"></div>
                    </div>
                </div>
            @endif

            @if($totalQuestionCount === 0)
                <div class="card shadow-sm p-5 text-center text-muted">
                    <i class="fas fa-file-alt fa-3x mb-3 text-secondary"></i>
                    <h5 class="font-weight-bold">ยังไม่มีคำถามในข้อสอบชุดนี้</h5>
                    <p class="mb-0 text-sm">กรุณาเพิ่มคำถามในระบบจัดการข้อสอบก่อนทดลองทำ</p>
                </div>
            @endif

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
                     data-is-last="{{ $isLast ? '1' : '0' }}">

                    {{-- Section header --}}
                    @if($hasSections)
                        <div class="mb-3 pl-3 py-1 bg-white rounded shadow-sm border-left border-info" style="border-left-width: 4px !important;">
                            <h5 class="font-weight-bold mb-1 text-dark">{{ $sectTitle }}</h5>
                            @if($sectInstr)
                                <p class="text-xs mb-0 text-muted">{{ $sectInstr }}</p>
                            @endif
                        </div>
                    @endif

                    {{-- Questions --}}
                    @foreach($sectQuestions as $question)
                        @php
                            $overallIndex = $questions->search(fn($q) => $q->id === $question->id);
                        @endphp
                        <div class="card shadow-sm mb-3 question-card" id="question-card-{{ $question->id }}" data-qid="{{ $question->id }}" data-type="{{ $question->type }}">
                            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                <h6 class="font-weight-bold text-dark mb-0">ข้อที่ {{ $overallIndex + 1 }}</h6>
                                <span class="badge badge-light border text-muted">({{ number_format($question->score, 2) }} คะแนน)</span>
                            </div>
                            <div class="card-body py-3">
                                <div class="font-weight-bold text-dark mb-3" style="font-size: 1.05rem; line-height: 1.6;">
                                    {!! $question->question_text !!}
                                </div>

                                @if($question->question_image)
                                    <div class="mb-3 text-center">
                                        <img src="{{ (str_starts_with($question->question_image, 'storage/') || str_starts_with($question->question_image, 'uploads/') || str_starts_with($question->question_image, 'http')) ? asset($question->question_image) : asset('storage/' . $question->question_image) }}"
                                             class="img-fluid rounded border shadow-xs" style="max-height: 260px;" alt="รูปประกอบ">
                                    </div>
                                @endif

                                @if($question->type === 'essay')
                                    <div class="form-group mb-2">
                                        <label class="text-muted text-sm font-weight-bold mb-1"><i class="fas fa-pen mr-1"></i>พิมพ์คำตอบของคุณ:</label>
                                        <textarea class="form-control essay-input" rows="4" placeholder="พิมพ์คำตอบข้อเขียนที่นี่เพื่อทดสอบ..." data-qid="{{ $question->id }}"></textarea>
                                    </div>
                                    <div class="essay-reveal-box d-none mt-2 p-3 bg-light rounded border border-success">
                                        <div class="font-weight-bold text-success mb-1 small">
                                            <i class="fas fa-check-circle mr-1"></i>แนวทางเฉลยของอาจารย์:
                                        </div>
                                        <div class="text-dark small">{{ $question->essay_answer ?: '(ไม่มีเฉลยข้อความ)' }}</div>
                                    </div>
                                @else
                                    <div class="choices-container">
                                        @foreach($question->choices as $cIdx => $choice)
                                            <label class="choice-item d-flex align-items-start w-100" id="choice-label-{{ $choice->id }}">
                                                <input type="radio" name="question_{{ $question->id }}" value="{{ $choice->id }}"
                                                       class="choice-radio mt-1 mr-2"
                                                       data-qid="{{ $question->id }}"
                                                       data-is-correct="{{ $choice->is_correct ? '1' : '0' }}"
                                                       data-score="{{ $question->score }}">
                                                <div class="flex-grow-1">
                                                    <span class="font-weight-bold mr-1">{{ $thaiChoices[$cIdx] ?? ($cIdx + 1) }}.</span>
                                                    <span>{!! $choice->choice_text !!}</span>
                                                    @if($choice->choice_image)
                                                        <div class="mt-2">
                                                            <img src="{{ (str_starts_with($choice->choice_image, 'storage/') || str_starts_with($choice->choice_image, 'uploads/') || str_starts_with($choice->choice_image, 'http')) ? asset($choice->choice_image) : asset('storage/' . $choice->choice_image) }}"
                                                                 class="img-thumbnail" style="max-height: 120px;" alt="รูปตัวเลือก">
                                                        </div>
                                                    @endif
                                                    <span class="correct-badge d-none badge badge-success ml-2 px-2 py-1 font-weight-bold">
                                                        <i class="fas fa-check mr-1"></i>ข้อที่ถูก
                                                    </span>
                                                    <span class="incorrect-badge d-none badge badge-danger ml-2 px-2 py-1 font-weight-bold">
                                                        <i class="fas fa-times mr-1"></i>ข้อที่คุณตอบ
                                                    </span>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    {{-- Navigation buttons between sections --}}
                    @if($hasSections)
                        <div class="d-flex justify-content-between mt-3 mb-4">
                            @if(!$isFirst)
                                <button type="button" class="btn btn-outline-secondary font-weight-bold prev-section-btn">
                                    <i class="fas fa-chevron-left mr-1"></i>ตอนก่อนหน้า
                                </button>
                            @else
                                <div></div>
                            @endif

                            @if(!$isLast)
                                <button type="button" class="btn btn-info font-weight-bold next-section-btn">
                                    ตอนถัดไป <i class="fas fa-chevron-right ml-1"></i>
                                </button>
                            @else
                                <button type="button" class="btn btn-success font-weight-bold px-4 submit-test-btn">
                                    <i class="fas fa-paper-plane mr-1"></i>ส่งข้อสอบจำลอง
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- ===== RIGHT: Sticky Palette & Timer Sidebar ===== --}}
        <div class="col-lg-4 col-md-5 order-1 order-md-2 mb-3">
            <div class="sticky-sidebar">
                

                <!-- Palette Card -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                        <h6 class="card-title font-weight-bold text-dark mb-0">
                            <i class="fas fa-th mr-1 text-primary"></i>กระดานข้อสอบ
                        </h6>
                        <span class="badge badge-light border text-sm" id="answeredCountBadge">0 / {{ $totalQuestionCount }} ข้อ</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="d-flex flex-wrap" style="gap: 6px;">
                            @foreach($questions as $idx => $q)
                                <button type="button" class="btn btn-outline-secondary palette-btn" id="palette-btn-{{ $q->id }}"
                                        data-qid="{{ $q->id }}" data-idx="{{ $idx }}"
                                        title="ข้อที่ {{ $idx + 1 }}">
                                    {{ $idx + 1 }}
                                </button>
                            @endforeach
                        </div>

                        <div class="border-top mt-3 pt-2 small text-muted d-flex justify-content-around">
                            <span><i class="fas fa-square text-success mr-1"></i>ตอบแล้ว</span>
                            <span><i class="far fa-square text-secondary mr-1"></i>ยังไม่ตอบ</span>
                        </div>

                        <div class="mt-3">
                            <button type="button" class="btn btn-success btn-block font-weight-bold py-2 shadow-xs submit-test-btn">
                                <i class="fas fa-check-circle mr-1"></i>ส่งข้อสอบจำลองเพื่อตรวจคะแนน
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-block btn-sm font-weight-bold mt-2" id="resetTestBtn">
                                <i class="fas fa-redo mr-1"></i>ล้างคำตอบเพื่อเริ่มทำใหม่
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Test Run Result Modal -->
<div class="modal fade" id="testResultModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 520px;">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px;">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-award mr-2"></i>ผลการทดลองทำข้อสอบ (Preview Mode)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="mb-3">
                    <span class="display-4 font-weight-bold text-success" id="resultScoreText">0.00</span>
                    <span class="text-muted" style="font-size: 1.2rem;" id="resultTotalScoreText">/ 0.00 คะแนน</span>
                </div>
                <div class="alert alert-info py-2 px-3 text-left small mb-3">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>หมายเหตุ:</strong> ระบบคำนวณคะแนนเฉพาะข้อสอบแบบปรนัย (ข้อกา) ทันที สำหรับข้อเขียน (อัตนัย) สามารถตรวจดูแนวทางเฉลยได้ในหน้าข้อสอบ
                    <strong class="d-block mt-1 text-danger">* ไม่มีการบันทึกผลสอบนี้ลงฐานข้อมูลจริง</strong>
                </div>
                <div class="row text-center mb-3">
                    <div class="col-4 border-right">
                        <div class="text-muted small">ตอบถูก</div>
                        <div class="font-weight-bold text-success h5 mb-0" id="resultCorrectCount">0 ข้อ</div>
                    </div>
                    <div class="col-4 border-right">
                        <div class="text-muted small">ตอบผิด</div>
                        <div class="font-weight-bold text-danger h5 mb-0" id="resultIncorrectCount">0 ข้อ</div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted small">ไม่ได้ตอบ</div>
                        <div class="font-weight-bold text-secondary h5 mb-0" id="resultUnansweredCount">0 ข้อ</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary font-weight-bold" data-dismiss="modal" id="revealAnswersBtn">
                    <i class="fas fa-eye mr-1"></i>ดูเฉลยในหน้าข้อสอบ
                </button>
                <div>
                    <button type="button" class="btn btn-outline-primary font-weight-bold mr-1" id="retryTestBtn">
                        <i class="fas fa-redo mr-1"></i>ทำใหม่อีกครั้ง
                    </button>
                    <a href="{{ route('admin.exams.index', ['subject_id' => $exam->subject_id]) }}" class="btn btn-secondary font-weight-bold">
                        <i class="fas fa-arrow-left mr-1"></i>กลับหน้าข้อสอบ
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
$(document).ready(function() {
    var totalQuestions = {{ $totalQuestionCount }};
    var currentSection = 0;
    var sectionCount = {{ $sectionCount }};
    var answeredQids = new Set();

    // Choice selection handler
    $('.choice-radio').on('change', function() {
        var qid = $(this).data('qid');
        var card = $('#question-card-' + qid);
        
        card.find('.choice-item').removeClass('selected');
        $(this).closest('.choice-item').addClass('selected');

        answeredQids.add(qid);
        $('#palette-btn-' + qid).removeClass('btn-outline-secondary').addClass('btn-success text-white');
        updateProgress();
    });

    // Essay input handler
    $('.essay-input').on('input', function() {
        var qid = $(this).data('qid');
        var val = $(this).val().trim();
        if (val.length > 0) {
            answeredQids.add(qid);
            $('#palette-btn-' + qid).removeClass('btn-outline-secondary').addClass('btn-success text-white');
        } else {
            answeredQids.delete(qid);
            $('#palette-btn-' + qid).removeClass('btn-success text-white').addClass('btn-outline-secondary');
        }
        updateProgress();
    });

    function updateProgress() {
        $('#answeredCountBadge').text(answeredQids.size + ' / ' + totalQuestions + ' ข้อ');
        if (sectionCount > 0) {
            var pct = Math.round(((currentSection + 1) / sectionCount) * 100);
            $('#section-progress-pct').text(pct + '%');
            $('#section-progress-fill').css('width', pct + '%');
        }
    }
    updateProgress();

    // Section navigation
    $('.next-section-btn').on('click', function() {
        if (currentSection < sectionCount - 1) {
            $('.exam-section-panel[data-section-index="' + currentSection + '"]').addClass('d-none');
            currentSection++;
            $('.exam-section-panel[data-section-index="' + currentSection + '"]').removeClass('d-none');
            $('#section-progress-label').text('ตอนที่ ' + (currentSection + 1) + ' / ' + sectionCount);
            updateProgress();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });

    $('.prev-section-btn').on('click', function() {
        if (currentSection > 0) {
            $('.exam-section-panel[data-section-index="' + currentSection + '"]').addClass('d-none');
            currentSection--;
            $('.exam-section-panel[data-section-index="' + currentSection + '"]').removeClass('d-none');
            $('#section-progress-label').text('ตอนที่ ' + (currentSection + 1) + ' / ' + sectionCount);
            updateProgress();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });

    // Palette scroll to question
    $('.palette-btn').on('click', function() {
        var qid = $(this).data('qid');
        var card = $('#question-card-' + qid);
        var sectPanel = card.closest('.exam-section-panel');

        if (sectPanel.length) {
            var sectIdx = parseInt(sectPanel.data('section-index'));
            if (sectIdx !== currentSection) {
                $('.exam-section-panel[data-section-index="' + currentSection + '"]').addClass('d-none');
                currentSection = sectIdx;
                sectPanel.removeClass('d-none');
                $('#section-progress-label').text('ตอนที่ ' + (currentSection + 1) + ' / ' + sectionCount);
                updateProgress();
            }
        }

        $('html, body').animate({
            scrollTop: card.offset().top - 70
        }, 300);
    });

    // Submit Simulated Exam
    $('.submit-test-btn').on('click', function() {
        Swal.fire({
            title: 'ส่งข้อสอบจำลอง?',
            text: 'คุณต้องการส่งข้อสอบจำลองเพื่อตรวจคะแนนใช่หรือไม่? (ไม่มีการบันทึกผลสอบจริงลงฐานข้อมูล)',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ใช่, ตรวจคะแนนจำลอง!',
            cancelButtonText: 'กลับไปทำต่อ'
        }).then(function(result) {
            if (result.isConfirmed) {
                calculateResults();
            }
        });
    });

    function calculateResults() {
        var earnedScore = 0;
        var totalPossibleChoiceScore = 0;
        var correctCount = 0;
        var incorrectCount = 0;
        var unansweredCount = 0;

        $('.question-card[data-type="choice"]').each(function() {
            var qid = $(this).data('qid');
            var selectedRadio = $(this).find('.choice-radio:checked');
            var qScore = parseFloat($(this).find('.choice-radio').first().data('score')) || 1;
            totalPossibleChoiceScore += qScore;

            if (selectedRadio.length === 0) {
                unansweredCount++;
            } else {
                var isCorrect = selectedRadio.data('is-correct') == '1';
                if (isCorrect) {
                    earnedScore += qScore;
                    correctCount++;
                } else {
                    incorrectCount++;
                }
            }
        });

        $('#resultScoreText').text(earnedScore.toFixed(2));
        $('#resultTotalScoreText').text('/ ' + totalPossibleChoiceScore.toFixed(2) + ' คะแนน');
        $('#resultCorrectCount').text(correctCount + ' ข้อ');
        $('#resultIncorrectCount').text(incorrectCount + ' ข้อ');
        $('#resultUnansweredCount').text(unansweredCount + ' ข้อ');

        $('#testResultModal').modal('show');
    }

    // Reveal Answers Handler
    $('#revealAnswersBtn').on('click', function() {
        $('#testResultModal').modal('hide');

        // Highlight choices
        $('.question-card[data-type="choice"]').each(function() {
            $(this).find('.choice-radio').each(function() {
                var isCorrect = $(this).data('is-correct') == '1';
                var isChecked = $(this).is(':checked');
                var label = $(this).closest('.choice-item');

                if (isCorrect) {
                    label.addClass('reveal-correct');
                    label.find('.correct-badge').removeClass('d-none');
                } else if (isChecked) {
                    label.addClass('reveal-incorrect');
                    label.find('.incorrect-badge').removeClass('d-none');
                }
            });
        });

        // Show essay answer keys
        $('.essay-reveal-box').removeClass('d-none');

        // Show all sections so reviewer can view full paper
        $('.exam-section-panel').removeClass('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });

        Swal.fire({
            icon: 'info',
            title: 'แสดงเฉลยแล้ว',
            text: 'ระบบได้ไฮไลท์ข้อที่ถูกต้อง (สีเขียว) และข้อที่คุณตอบผิด (สีแดง) บนหน้าจอเรียบร้อยแล้ว',
            timer: 2500,
            showConfirmButton: false
        });
    });

    // Reset Test Handler
    $('#resetTestBtn, #retryTestBtn').on('click', function() {
        $('#testResultModal').modal('hide');
        $('.choice-radio').prop('checked', false);
        $('.choice-item').removeClass('selected reveal-correct reveal-incorrect');
        $('.correct-badge, .incorrect-badge').addClass('d-none');
        $('.essay-input').val('');
        $('.essay-reveal-box').addClass('d-none');
        $('.palette-btn').removeClass('btn-success text-white').addClass('btn-outline-secondary');
        answeredQids.clear();
        updateProgress();

        // Go back to section 1
        currentSection = 0;
        $('.exam-section-panel').addClass('d-none');
        $('.exam-section-panel[data-section-index="0"]').removeClass('d-none');
        $('#section-progress-label').text('ตอนที่ 1 / ' + sectionCount);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
</script>
@stop
