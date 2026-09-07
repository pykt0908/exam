@extends('adminlte::page')

@section('title', 'ห้องพักเข้าสอบ')

@section('meta_tags')
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
@stop

@section('css')
<style>
    /* ป้องกันหน้าจอมือถือซูมเข้าอัตโนมัติเมื่อกดช่องกรอกรหัสผ่าน (iOS Safari / Mobile Auto-zoom Prevention) */
    #passcode_mobile,
    #passcode_desktop,
    input[name="passcode"] {
        font-size: 16px !important;
    }

    @media screen and (max-width: 767.98px) {
        .form-control,
        input[type="text"],
        input[type="password"] {
            font-size: 16px !important;
        }
    }
</style>
@stop

@section('content_header')
    <div class="d-none d-md-block">
        <h1 class="font-weight-bold text-dark">เตรียมตัวเข้าสู่ห้องสอบ</h1>
    </div>
    <div class="d-block d-md-none">
        <h4 class="font-weight-bold text-dark mb-0">เตรียมตัวเข้าสู่ห้องสอบ</h4>
    </div>
@stop

@section('content')
    {{-- ==================== DESKTOP VIEW (SPACIOUS TEXT-ONLY, NO ICONS, NO CARDS) ==================== --}}
    <div class="d-none d-md-block">
        <div class="row">
            <div class="col-lg-8 col-md-10 mx-auto py-3">
                <h3 class="font-weight-bold text-dark mb-1">{{ $exam->title }}</h3>
                <p class="text-muted mb-3" style="font-size: 1.15rem;">
                    รายวิชา: {{ $exam->subject->code }} - {{ $exam->subject->name }}
                </p>

                <hr class="my-3">

                <h5 class="font-weight-bold text-dark mb-3">ข้อมูลการสอบ</h5>
                <div class="row mb-3" style="font-size: 1.05rem; line-height: 2;">
                    <div class="col-sm-6">
                        <div>เวลาในการทำข้อสอบ: <strong>{{ $exam->duration_minutes }} นาที</strong></div>
                        <div>จำนวนข้อสอบ: <strong>{{ $exam->questions_count }} ข้อ</strong></div>
                        @if(isset($completedAttemptsCount))
                            <div>รอบการทำข้อสอบ: <strong class="text-primary">รอบที่ {{ $completedAttemptsCount + 1 }}</strong> (สิทธิ์ทั้งหมด {{ $allowedAttempts }} ครั้ง)</div>
                        @endif
                    </div>
                    <div class="col-sm-6">
                        <div>คะแนนเต็ม: <strong>{{ floatval($exam->total_score) }} คะแนน</strong></div>
                        @if($exam->starts_at || $exam->ends_at)
                            <div>
                                กำหนดการสอบ: 
                                <strong>
                                    @if($exam->starts_at && $exam->ends_at && $exam->starts_at->format('Y-m-d') === $exam->ends_at->format('Y-m-d'))
                                        วันที่ {{ $exam->starts_at->format('d/m/Y') }} เวลา {{ $exam->starts_at->format('H.i') }}-{{ $exam->ends_at->format('H.i') }} น.
                                    @elseif($exam->starts_at && $exam->ends_at)
                                        วันที่ {{ $exam->starts_at->format('d/m/Y H.i น.') }} ถึง วันที่ {{ $exam->ends_at->format('d/m/Y H.i น.') }}
                                    @elseif($exam->starts_at)
                                        วันที่ {{ $exam->starts_at->format('d/m/Y') }} ตั้งแต่เวลา {{ $exam->starts_at->format('H.i น.') }}
                                    @else
                                        สิ้นสุดวันที่ {{ $exam->ends_at->format('d/m/Y H.i น.') }}
                                    @endif
                                </strong>
                            </div>
                        @endif
                    </div>
                </div>

                @if(!empty(strip_tags($exam->description)))
                    <hr class="my-3">
                    <h5 class="font-weight-bold text-dark mb-2">คำชี้แจงรายวิชาสอบ</h5>
                    <div class="text-secondary mb-3" style="line-height: 1.8; font-size: 1.05rem;">
                        {!! $exam->description !!}
                    </div>
                @endif

                <hr class="my-3">

                <h5 class="font-weight-bold text-dark mb-2">ระเบียบและข้อกำหนดการสอบ</h5>
                <ul class="text-secondary pl-3 mb-4" style="line-height: 1.8; font-size: 1.05rem;">
                    <li>หลังจากกดปุ่ม "เริ่มทำข้อสอบ" ระบบจะเริ่มนับเวลาถอยหลังทันทีและไม่สามารถหยุดเวลาได้</li>
                    <li>ไม่อนุญาตให้คัดลอก (Copy) ข้อความหรือเนื้อหาใดๆ ในข้อสอบ</li>
                    @if($exam->force_fullscreen)
                        <li>ข้อสอบนี้บังคับให้เข้าทำในโหมดเต็มหน้าจอ (Fullscreen) เท่านั้น</li>
                    @endif
                    @if($exam->max_focus_escapes > 0)
                        <li>หากคุณสลับแท็บ เปลี่ยนหน้าจอ หรือออกจากโหมดเต็มหน้าจอ เกิน {{ $exam->max_focus_escapes }} ครั้ง ระบบจะส่งข้อสอบและยุติการทำข้อสอบโดยอัตโนมัติทันที</li>
                    @endif
                </ul>

                <!-- Start Exam Form Desktop -->
                <form action="{{ route('student.exam.start', $exam->id) }}" method="post" class="confirm-start-form">
                    @csrf

                    @if($exam->passcode)
                        <div class="form-group mb-4" style="max-width: 350px;">
                            <label for="passcode_desktop" class="font-weight-bold text-dark">รหัสผ่านเข้าห้องสอบ (Passcode):</label>
                            <input type="text" id="passcode_desktop" name="passcode" class="form-control @error('passcode') is-invalid @enderror" style="font-size: 16px;" placeholder="กรอกรหัสผ่านเข้าห้องสอบ" required autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                            @error('passcode')
                                <span class="invalid-feedback d-block font-weight-bold mt-1" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                    @endif

                    <div class="pt-2">
                        <button type="submit" class="btn btn-primary btn-lg font-weight-bold px-5 mr-3">
                            เริ่มทำข้อสอบ
                        </button>
                        <a href="{{ route('student.dashboard') }}" class="btn btn-secondary btn-lg font-weight-bold px-4">
                            ยกเลิก
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ==================== MOBILE VIEW (COMPACT TEXT-ONLY, NO ICONS, NO CARDS) ==================== --}}
    <div class="d-block d-md-none">
        <div class="py-2">
            <!-- Exam Title & Subject -->
            <div class="mb-2">
                <h5 class="font-weight-bold text-dark mb-1">{{ $exam->title }}</h5>
                <div class="text-muted small">
                    รายวิชา: {{ $exam->subject->code }} {{ $exam->subject->name }}
                </div>
            </div>

            <!-- Exam Quick Meta (Inline / Compact Grid) -->
            <div class="py-2 my-2 border-top border-bottom small text-dark">
                <div class="d-flex flex-wrap justify-content-between" style="gap: 4px 14px;">
                    <div>เวลาสอบ: <strong>{{ $exam->duration_minutes }} นาที</strong></div>
                    <div>จำนวน: <strong>{{ $exam->questions_count }} ข้อ</strong></div>
                    <div>คะแนนเต็ม: <strong>{{ floatval($exam->total_score) }} คะแนน</strong></div>
                    @if(isset($completedAttemptsCount))
                        <div>รอบที่: <strong class="text-primary">{{ $completedAttemptsCount + 1 }}/{{ $allowedAttempts }}</strong></div>
                    @endif
                </div>
                @if($exam->starts_at || $exam->ends_at)
                    <div class="text-muted mt-1" style="font-size: 0.85rem;">
                        กำหนดการ: 
                        @if($exam->starts_at && $exam->ends_at && $exam->starts_at->format('Y-m-d') === $exam->ends_at->format('Y-m-d'))
                            วันที่ {{ $exam->starts_at->format('d/m/Y') }} เวลา {{ $exam->starts_at->format('H.i') }}-{{ $exam->ends_at->format('H.i') }} น.
                        @elseif($exam->starts_at && $exam->ends_at)
                            วันที่ {{ $exam->starts_at->format('d/m/Y H.i น.') }} ถึง วันที่ {{ $exam->ends_at->format('d/m/Y H.i น.') }}
                        @elseif($exam->starts_at)
                            วันที่ {{ $exam->starts_at->format('d/m/Y') }} ตั้งแต่เวลา {{ $exam->starts_at->format('H.i น.') }}
                        @else
                            สิ้นสุดวันที่ {{ $exam->ends_at->format('d/m/Y H.i น.') }}
                        @endif
                    </div>
                @endif
            </div>

            <!-- Instructions (if available) -->
            @if(!empty(strip_tags($exam->description)))
                <div class="mb-2 small">
                    <strong class="text-dark">คำชี้แจงรายวิชาสอบ:</strong>
                    <div class="text-secondary mt-1" style="line-height: 1.4; max-height: 120px; overflow-y: auto;">
                        {!! $exam->description !!}
                    </div>
                </div>
                <hr class="my-2">
            @endif

            <!-- Exam Rules (Compact) -->
            <div class="mb-3 small">
                <strong class="text-dark">ระเบียบและข้อกำหนดการสอบ:</strong>
                <ol class="pl-3 mt-1 mb-0 text-secondary" style="line-height: 1.45;">
                    <li>ระบบเริ่มนับเวลาถอยหลังทันทีเมื่อกดปุ่มเริ่มทำข้อสอบ และไม่สามารถหยุดเวลาได้</li>
                    <li>ไม่อนุญาตให้คัดลอก (Copy) ข้อความหรือเนื้อหาใดๆ ในข้อสอบ</li>
                    @if($exam->force_fullscreen)
                        <li>ข้อสอบนี้บังคับให้ทำในโหมดเต็มหน้าจอ (Fullscreen) เท่านั้น</li>
                    @endif
                    @if($exam->max_focus_escapes > 0)
                        <li>หากสลับแท็บ/หน้าจอเกิน {{ $exam->max_focus_escapes }} ครั้ง ระบบจะส่งข้อสอบโดยอัตโนมัติทันที</li>
                    @endif
                </ol>
            </div>

            <!-- Start Exam Form Mobile -->
            <form action="{{ route('student.exam.start', $exam->id) }}" method="post" class="confirm-start-form">
                @csrf

                @if($exam->passcode)
                    <div class="form-group mb-3" style="max-width: 300px;">
                        <label for="passcode_mobile" class="small font-weight-bold text-dark mb-1">
                            รหัสผ่านเข้าห้องสอบ (Passcode):
                        </label>
                        <input type="text" id="passcode_mobile" name="passcode" class="form-control @error('passcode') is-invalid @enderror" style="font-size: 16px !important;" placeholder="กรอกรหัสผ่าน" required autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                        @error('passcode')
                            <span class="invalid-feedback d-block font-weight-bold mt-1 small" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                @endif

                <div class="d-flex align-items-center pt-1 pb-3">
                    <button type="submit" class="btn btn-primary font-weight-bold px-4 mr-2">
                        เริ่มทำข้อสอบ
                    </button>
                    <a href="{{ route('student.dashboard') }}" class="btn btn-secondary px-3">
                        ยกเลิก
                    </a>
                </div>
            </form>
        </div>
    </div>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            // ป้องกัน viewport ซูมเข้าอัตโนมัติบนมือถือ
            var viewport = document.querySelector('meta[name="viewport"]');
            if (viewport) {
                viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no');
            }

            $('.confirm-start-form').on('submit', function(e) {
                e.preventDefault();
                var form = this;
                Swal.fire({
                    title: 'ยืนยันการเริ่มทำข้อสอบ?',
                    text: 'คุณแน่ใจแล้วใช่ไหมที่จะเริ่มทำข้อสอบนี้? เวลาจะเริ่มนับถอยหลังทันทีและไม่สามารถหยุดได้',
                    showCancelButton: true,
                    confirmButtonColor: '#007bff',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'ตกลง, เริ่มทำข้อสอบ',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    </script>
@stop
