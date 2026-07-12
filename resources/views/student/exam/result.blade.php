@extends('adminlte::page')

@section('title', 'ผลการสอบ')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="text-dark font-weight-bold">ผลการสอบและการเฉลย</h1>
        <a href="{{ route('student.dashboard') }}" class="btn btn-primary font-weight-bold shadow-sm">
            <i class="fas fa-home mr-2"></i>กลับหน้าหลัก
        </a>
    </div>
@stop

@section('content')
    <div class="row">
        <!-- Results Card Summary -->
        <div class="col-md-8 offset-md-2">
            <div class="card card-outline {{ !$attempt->exam->show_score ? 'card-info' : ($attempt->is_passed ? 'card-success' : 'card-danger') }} shadow-lg mb-4 text-center py-4">
                <div class="card-body">
                    <span class="text-muted text-uppercase font-weight-bold" style="font-size: 0.9rem;">คะแนนสอบวิชา: {{ $attempt->exam->subject->code }} - {{ $attempt->exam->subject->name }}</span>
                    <h2 class="font-weight-bold text-dark mt-1">{{ $attempt->exam->title }}</h2>
                    
                    @if($attempt->exam->show_score)
                        <div class="my-4">
                            <div class="display-4 font-weight-bold {{ $attempt->is_passed ? 'text-success' : 'text-danger' }}">
                                {{ $attempt->score }} <span style="font-size: 1.8rem; font-weight: normal; color: #6c757d;">/ {{ $attempt->exam->questions->sum('score') }}</span>
                            </div>
                            <p class="text-muted mt-2 font-weight-bold">คะแนนที่ได้ / คะแนนเต็ม</p>
                        </div>

                        @if($attempt->is_passed)
                            <div class="alert alert-success d-inline-block px-5 shadow-sm" role="alert" style="border-radius: 50px;">
                                <h4 class="alert-heading font-weight-bold mb-0"><i class="fas fa-check-circle mr-2"></i>สอบผ่านเกณฑ์ ({{ round($attempt->exam->questions->sum('score') * ($attempt->exam->passing_percentage / 100), 2) }} คะแนน)</h4>
                            </div>
                        @else
                            <div class="alert alert-danger d-inline-block px-5 shadow-sm" role="alert" style="border-radius: 50px;">
                                <h4 class="alert-heading font-weight-bold mb-0"><i class="fas fa-times-circle mr-2"></i>ไม่ผ่านเกณฑ์การสอบ</h4>
                            </div>
                        @endif
                    @else
                        <div class="my-4">
                            <div class="alert alert-info d-inline-block px-5 shadow-sm" role="alert" style="border-radius: 50px;">
                                <h4 class="alert-heading font-weight-bold mb-0"><i class="fas fa-check-circle mr-2"></i>ส่งข้อสอบสำเร็จ</h4>
                            </div>
                            <p class="text-muted mt-2 font-weight-bold">ระบบได้บันทึกคำตอบของท่านเรียบร้อยแล้ว</p>
                        </div>
                    @endif

                    <div class="row mt-4 px-4">
                        <div class="col-6 border-right">
                            <span class="text-muted d-block text-sm">เวลาที่ใช้สอบ</span>
                            @php
                                $diff = $attempt->started_at->diffInSeconds($attempt->completed_at);
                                $mins = floor($diff / 60);
                                $secs = $diff % 60;
                            @endphp
                            <strong class="text-dark">{{ $mins }} นาที {{ $secs }} วินาที</strong>
                        </div>
                        <div class="col-6">
                            @if($attempt->exam->show_score)
                                <span class="text-muted d-block text-sm">เกณฑ์ผ่านวิชานี้</span>
                                <strong class="text-dark">{{ round($attempt->exam->questions->sum('score') * ($attempt->exam->passing_percentage / 100), 2) }} คะแนน</strong>
                            @else
                                <span class="text-muted d-block text-sm">จำนวนข้อสอบ</span>
                                <strong class="text-dark">{{ $attempt->exam->questions->count() }} ข้อ</strong>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @if($attempt->exam->show_answers)
                <!-- Detailed Answer Key Review -->
                <h3 class="font-weight-bold text-dark mb-4 mt-5"><i class="fas fa-list-ol mr-2 text-primary"></i>เฉลยข้อสอบอย่างละเอียด</h3>

                @php
                    $currentSectionId = null;
                @endphp
                @foreach($attempt->exam->questions as $index => $question)
                    @php
                        $sectVal = $question->exam_section_id;
                        $sectTitleVal = $question->examSection ? $question->examSection->title : 'ไม่มีกลุ่มตอน';
                        $sectInstructionVal = $question->examSection ? $question->examSection->instruction : ($question->type === 'essay' ? 'คำชี้แจง: คำถามอัตนัย (พิมพ์ตอบ)' : 'คำชี้แจง: คำถามปรนัย (เลือกตอบ)');
                    @endphp
                    @if($currentSectionId !== $sectVal)
                        @php
                            $currentSectionId = $sectVal;
                        @endphp
                        <div class="alert alert-dark shadow-sm mt-4 mb-3 py-3" style="border-radius: 8px; background-color: #343a40; color: #fff;">
                            <h5 class="font-weight-bold mb-1"><i class="fas fa-layer-group mr-2 text-info"></i>{{ $sectTitleVal }}</h5>
                            <p class="text-xs mb-0 text-light">{{ $sectInstructionVal }}</p>
                        </div>
                    @endif
                    @php
                        if ($question->type === 'essay') {
                            $isCorrect = $correctness[$question->id] ?? false;
                            $studentAnswerText = $savedTextAnswers[$question->id] ?? null;
                        } else {
                            $studentChoiceId = $savedAnswers[$question->id] ?? null;
                            $studentChoice = $question->choices->firstWhere('id', $studentChoiceId);
                            $isCorrect = $studentChoice ? $studentChoice->is_correct : false;
                        }
                    @endphp
                    <div class="card card-outline {{ $isCorrect ? 'card-success' : 'card-danger' }} shadow-sm mb-4">
                        <div class="card-header bg-light">
                            <h5 class="card-title font-weight-bold mb-0">
                                ข้อที่ {{ $index + 1 }}
                                @if($isCorrect)
                                    <span class="badge badge-success ml-2 px-2 py-1"><i class="fas fa-check mr-1"></i> ถูกต้อง</span>
                                @else
                                    <span class="badge badge-danger ml-2 px-2 py-1"><i class="fas fa-times mr-1"></i> ผิดพลาด</span>
                                @endif
                            </h5>
                            <div class="card-tools">
                                <span class="badge badge-primary px-2 py-1">{{ $question->score }} คะแนน</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="font-weight-bold text-md text-dark mb-4">{!! $question->question_text !!}</div>

                            @if($question->type === 'essay')
                                <div class="form-group mb-3">
                                    <label class="font-weight-bold text-dark text-sm">คำตอบของคุณ:</label>
                                    <div class="p-3 border rounded {{ $isCorrect ? 'bg-success-light border-success text-success font-weight-bold' : 'bg-danger-light border-danger text-danger font-weight-bold' }}">
                                        {{ $studentAnswerText ?? '(ไม่มีคำตอบ)' }}
                                    </div>
                                </div>
                                <div class="form-group mb-0">
                                    <label class="font-weight-bold text-dark text-sm">เฉลยแนวคำตอบที่กำหนด:</label>
                                    <div class="p-3 border rounded bg-success-light border-success text-success font-weight-bold">
                                        {{ $question->essay_answer ?? '(ไม่มีการระบุแนวคำตอบไว้)' }}
                                    </div>
                                </div>
                            @else
                                <div class="row">
                                    @foreach($question->choices as $choiceIndex => $choice)
                                        @php
                                            $class = 'bg-light border-secondary-light';
                                            $icon = '';
                                            
                                            if ($choice->is_correct) {
                                                // Highlight correct answer in green
                                                $class = 'bg-success-light border-success text-success font-weight-bold';
                                                $icon = '<i class="fas fa-check-circle ml-auto text-success"></i>';
                                            } elseif ($studentChoiceId == $choice->id && !$choice->is_correct) {
                                                // Highlight student's wrong selection in red
                                                $class = 'bg-danger-light border-danger text-danger font-weight-bold';
                                                $icon = '<i class="fas fa-times-circle ml-auto text-danger"></i>';
                                            }
                                        @endphp
                                        <div class="col-12 mb-2">
                                            <div class="p-3 border rounded d-flex align-items-center {{ $class }}">
                                                <span class="mr-3 badge {{ $choice->is_correct ? 'badge-success' : ($studentChoiceId == $choice->id ? 'badge-danger' : 'badge-secondary') }} px-2 py-1">
                                                    {{ chr(65 + $choiceIndex) }}
                                                </span>
                                                <div class="d-flex flex-column flex-grow-1">
                                                    <span>{{ $choice->choice_text }}</span>
                                                    @if($choice->choice_image)
                                                        <div class="choice-image-container mt-2">
                                                            <img src="{{ asset($choice->choice_image) }}" class="img-fluid img-thumbnail" style="max-height: 120px; border-radius: 8px;">
                                                        </div>
                                                    @endif
                                                </div>
                                                {!! $icon !!}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <!-- Summary Feedback message below options -->
                                @if($studentChoiceId === null)
                                    <div class="alert alert-warning py-2 px-3 mt-3 mb-0 text-sm">
                                        <i class="fas fa-exclamation-circle mr-1"></i> คุณไม่ได้เลือกคำตอบสำหรับคำถามข้อนี้
                                    </div>
                                @elseif(!$isCorrect)
                                    <div class="alert alert-danger py-2 px-3 mt-3 mb-0 text-sm">
                                        <i class="fas fa-times-circle mr-1"></i> คำตอบที่คุณเลือก: <strong>{{ $studentChoice->choice_text }}</strong> ซึ่งไม่ใช่คำตอบที่ถูกต้อง
                                    </div>
                                @else
                                    <div class="alert alert-success py-2 px-3 mt-3 mb-0 text-sm">
                                        <i class="fas fa-check-circle mr-1"></i> ยินดีด้วยคุณตอบข้อนี้ถูกต้อง!
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            @else
                <div class="card card-outline card-secondary shadow-sm mb-4 mt-5 text-center p-4">
                    <div class="card-body">
                        <i class="fas fa-lock fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted font-weight-bold">ผู้สอนไม่อนุญาตให้แสดงเฉลยข้อสอบ</h5>
                        <p class="text-secondary text-sm mb-0">หากมีข้อสงสัยเกี่ยวกับเนื้อหาข้อสอบ กรุณาติดต่ออาจารย์ผู้สอนรายวิชาโดยตรง</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@stop

@section('css')
    <style>
        .bg-success-light {
            background-color: rgba(40, 167, 69, 0.08) !important;
        }
        .bg-danger-light {
            background-color: rgba(220, 53, 69, 0.08) !important;
        }
        .border-secondary-light {
            border-color: #e9ecef !important;
        }
    </style>
@stop
