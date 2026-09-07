@extends('adminlte::page')

@section('title', 'ผลการสอบ')

@section('content_header')
<div class="d-none d-md-block">
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="text-dark font-weight-bold">ผลการสอบ</h1>
        <a href="{{ route('student.dashboard') }}" class="btn btn-sm btn-danger font-weight-bold">
            กลับหน้าหลัก
        </a>
    </div>
</div>
<div class="d-block d-md-none">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="text-dark font-weight-bold mb-0">ผลการสอบ</h4>
        <a href="{{ route('student.dashboard') }}" class="btn btn-sm btn-danger font-weight-bold">
            กลับหน้าหลัก
        </a>
    </div>
</div>
@stop

@section('content')
@php
    $isPending = $attempt->isPendingGrading();
    $totalExamScore = (float) ($attempt->exam->total_score ?? $attempt->exam->questions->sum('score'));
    $totalRawScore = (float) ($attempt->total_raw_score ?? $attempt->exam->questions->sum('score'));
    $rawScore = $attempt->raw_score;
    $isScaled = $attempt->isScaled() || ($rawScore !== null && $totalRawScore > 0 && abs($totalRawScore - $totalExamScore) > 0.001);
    $passingScore = round($totalExamScore * ($attempt->exam->passing_percentage / 100), 2);
@endphp

<div class="row">
    <div class="col-lg-8 col-md-10 mx-auto py-2">
        <!-- Exam Title & Info -->
        <p class="text-dark font-weight-bold mb-1" style="font-size: 1.15rem;">{{ $attempt->exam->title }}</p>
        <p class="text-dark font-weight-bold mb-3" style="font-size: 1.15rem;">
            รายวิชา {{ $attempt->exam->subject->code }} - {{ $attempt->exam->subject->name }}
        </p>

        <hr class="my-3">

        <!-- Results Summary in Text Format -->
        <h5 class="font-weight-bold text-dark mb-3">สรุปผลการสอบ</h5>
        <div class="mb-4" style="font-size: 1.05rem; line-height: 2;">
            @if($attempt->exam->show_score)
                <div>
                    คะแนนที่ได้:
                    @if($isPending)
                        <strong class="text-dark">{{ floatval($attempt->score ?? 0) }} / {{ floatval($totalExamScore) }}
                            คะแนน</strong>
                        <span class="text-muted">(คะแนนเบื้องต้นเฉพาะข้อปรนัย)</span>
                    @else
                        <strong class="text-dark">{{ floatval($attempt->score ?? 0) }} / {{ floatval($totalExamScore) }}
                            คะแนน</strong>
                        @php
                            $percent = ($totalRawScore > 0 && $rawScore !== null)
                                ? round(($rawScore / $totalRawScore) * 100, 2)
                                : ($totalExamScore > 0 ? round(($attempt->score / $totalExamScore) * 100, 2) : 0);
                        @endphp
                        <span class="text-muted">({{ $percent }}%)</span>
                    @endif

                    @if($isScaled && $rawScore !== null)
                        <div class="text-sm text-muted">
                            <i class="fas fa-info-circle mr-1"></i>คะแนนดิบที่ทำได้: <strong>{{ floatval($rawScore) }}</strong>
                            / {{ floatval($totalRawScore) }} {{ $totalRawScore == $attempt->total_questions ? 'ข้อ' : 'คะแนน' }}
                        </div>
                    @endif
                </div>
                <div>
                    เกณฑ์คะแนนผ่าน: <strong>{{ $passingScore }} คะแนน</strong> ({{ $attempt->exam->passing_percentage }}%)
                </div>
                <div>
                    ผลการประเมิน:
                    @if($isPending)
                        <strong class="text-warning">รอตรวจข้อเขียน (ยังไม่ระบุผลการสอบผ่าน/ไม่ผ่าน
                            จนกว่าผู้สอนจะตรวจเสร็จสิ้น)</strong>
                    @elseif($attempt->is_passed)
                        <strong class="text-success">ผ่านเกณฑ์การสอบ</strong>
                    @else
                        <strong class="text-danger">ไม่ผ่านเกณฑ์การสอบ</strong>
                    @endif
                </div>
            @else
                <div>
                    สถานะการส่ง: <strong class="text-success">ส่งข้อสอบเรียบร้อยแล้ว</strong>
                </div>
            @endif

            @php
                $diff = $attempt->started_at && $attempt->completed_at
                    ? $attempt->started_at->diffInSeconds($attempt->completed_at)
                    : 0;
                $mins = floor($diff / 60);
                $secs = $diff % 60;
            @endphp
            <div>
                เวลาที่ใช้สอบ: <strong>{{ $mins }} นาที {{ $secs }} วินาที</strong>
            </div>
            <div>
                ส่งข้อสอบเมื่อ:
                <strong>{{ $attempt->completed_at ? $attempt->completed_at->format('d/m/Y H:i') . ' น.' : '-' }}</strong>
            </div>
        </div>

        @if($attempt->exam->show_answers)
            <hr class="my-4">
            <h5 class="font-weight-bold text-dark mb-3">เฉลยและผลการตรวจข้อสอบ</h5>

            @php $currentSectionId = null; @endphp
            @foreach($attempt->exam->questions as $index => $question)
                @php
                    $sectVal = $question->exam_section_id;
                    $sectTitleVal = $question->examSection ? $question->examSection->title : null;
                    $sectInstructionVal = $question->examSection ? $question->examSection->instruction : null;
                @endphp

                @if($sectVal !== null && $currentSectionId !== $sectVal)
                    @php $currentSectionId = $sectVal; @endphp
                    <div class="mt-4 mb-3 pt-3 border-top">
                        <h6 class="font-weight-bold text-dark mb-1">{{ $sectTitleVal }}</h6>
                        @if($sectInstructionVal)
                            <p class="text-muted small mb-0">{{ $sectInstructionVal }}</p>
                        @endif
                    </div>
                @endif

                @php
                    $isEssay = ($question->type === 'essay');
                    if ($isEssay) {
                        $isCorrect = $correctness[$question->id] ?? false;
                        $studentAnswerText = $savedTextAnswers[$question->id] ?? null;
                        $awardedScore = $awardedScores[$question->id] ?? null;
                        $feedback = $teacherFeedbacks[$question->id] ?? null;
                    } else {
                        $studentChoiceId = $savedAnswers[$question->id] ?? null;
                        $studentChoice = $question->choices->firstWhere('id', $studentChoiceId);
                        $isCorrect = $studentChoice ? $studentChoice->is_correct : false;
                    }
                @endphp

                <div class="mb-4 pb-3 border-bottom" style="line-height: 1.8; font-size: 1.05rem;">
                    <!-- Question Text -->
                    <div class="font-weight-bold text-dark mb-2">
                        ข้อ {{ $index + 1 }}. {!! $question->question_text !!}
                        <span class="text-muted font-weight-normal text-sm">({{ floatval($question->score) }}
                            {{ $isScaled ? 'คะแนนดิบ' : 'คะแนน' }})</span>
                    </div>

                    @if($question->question_image)
                        <div class="mb-3">
                            <img src="{{ asset($question->question_image) }}" class="img-fluid border" style="max-height: 200px;">
                        </div>
                    @endif

                    @if($isEssay)
                        <!-- Essay Answer Review in Text -->
                        <div class="pl-3 mb-2" style="border-left: 3px solid #dee2e6;">
                            <div>
                                <span class="text-muted">คำตอบของคุณ:</span>
                                <strong>{{ $studentAnswerText ?: '(ไม่ได้ตอบ)' }}</strong>
                            </div>
                            <div>
                                <span class="text-muted">เฉลย/แนวคำตอบ:</span>
                                <span class="text-dark">{{ $question->essay_answer ?: '(ไม่ได้ระบุแนวคำตอบไว้)' }}</span>
                            </div>
                            <div>
                                <span class="text-muted">คะแนนที่ได้:</span>
                                @if($isPending)
                                    <strong class="text-warning">รอผู้สอนตรวจให้คะแนน</strong>
                                @else
                                    <strong>{{ $awardedScore !== null ? floatval($awardedScore) : 0 }} /
                                        {{ floatval($question->score) }} คะแนน</strong>
                                @endif
                            </div>
                            @if(!empty($feedback))
                                <div class="mt-1 text-info">
                                    <span>คำแนะนำจากผู้สอน:</span> <strong>{{ $feedback }}</strong>
                                </div>
                            @endif
                        </div>
                    @else
                        <!-- Choice Answer Review in Text -->
                        <div class="pl-3 mb-2" style="border-left: 3px solid #dee2e6;">
                            @foreach($question->choices as $choiceIndex => $choice)
                                @php
                                    $letter = chr(65 + $choiceIndex);
                                    $isChosen = ($studentChoiceId == $choice->id);
                                    $isChoiceCorrect = $choice->is_correct;
                                @endphp
                                <div>
                                    <span class="font-weight-bold mr-1">{{ $letter }}.</span> {{ $choice->choice_text }}
                                    @if($choice->choice_image)
                                        <div class="my-1">
                                            <img src="{{ asset($choice->choice_image) }}" class="img-fluid border"
                                                style="max-height: 100px;">
                                        </div>
                                    @endif
                                    @if($isChoiceCorrect && $isChosen)
                                        <strong class="text-success ml-2">(คำตอบของคุณ - ถูกต้อง)</strong>
                                    @elseif($isChoiceCorrect)
                                        <strong class="text-success ml-2">(คำตอบที่ถูกต้อง)</strong>
                                    @elseif($isChosen)
                                        <strong class="text-danger ml-2">(คำตอบที่คุณเลือก - ไม่ถูกต้อง)</strong>
                                    @endif
                                </div>
                            @endforeach

                            <div class="mt-2 pt-1">
                                <span class="text-muted">ผลการตอบ:</span>
                                @if($studentChoiceId === null)
                                    <span class="text-muted">ไม่ได้ตอบ (0 คะแนน)</span>
                                @elseif($isCorrect)
                                    <strong class="text-success">ถูกต้อง ({{ floatval($question->score) }}
                                        {{ $isScaled ? 'คะแนนดิบ' : 'คะแนน' }})</strong>
                                @else
                                    <strong class="text-danger">ไม่ถูกต้อง (0 คะแนน)</strong>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        @else
            <hr class="my-4">
            <p class="text-muted">ผู้สอนไม่อนุญาตให้แสดงเฉลยข้อสอบ</p>
        @endif

        <!-- <div class="pt-3 mb-5">
            <a href="{{ route('student.dashboard') }}" class="btn btn-secondary font-weight-bold px-4">
                กลับหน้าหลัก
            </a>
        </div> -->
    </div>
</div>
@stop