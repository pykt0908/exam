@extends('adminlte::page')

@section('title', 'ห้องพักเข้าสอบ')

@section('content_header')
    <h1 class="text-dark font-weight-bold">เตรียมตัวเข้าสู่ห้องสอบ</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card card-outline card-primary shadow-lg">
                <div class="card-header bg-primary p-3">
                    <h3 class="card-title font-weight-bold text-white mb-0"><i class="fas fa-info-circle mr-2"></i>รายละเอียดกติกาการสอบ</h3>
                </div>
                <div class="card-body">
                    <div class="text-left mb-3">
                        <div class="font-weight-bold text-dark mb-1" style="font-size: 1.05rem;">{{ $exam->title }}</div>
                        <div class="text-secondary" style="font-size: 1.05rem;">
                            รายวิชา: {{ $exam->subject->code }} - {{ $exam->subject->name }}
                        </div>
                    </div>

                    <hr class="my-2">

                    <div class="text-center my-3 text-secondary" style="font-size: 1.05rem;">
                        <span>เวลา: <strong class="text-dark">{{ $exam->duration_minutes }} นาที</strong></span>
                        <span class="mx-2 text-muted">|</span>
                        <span>จำนวน: <strong class="text-dark">{{ $exam->questions_count }} ข้อ</strong></span>
                        <span class="mx-2 text-muted">|</span>
                        <span><strong class="text-dark">{{ floatval($exam->total_score) }} คะแนน</strong></span>
                    </div>

                    <hr class="my-2">

                    <div class="bg-light p-3 rounded mb-4">
                        <h5 class="font-weight-bold text-dark mb-2"><i class="fas fa-bullhorn mr-2 text-danger"></i>คำชี้แจงรายวิชาสอบ:</h5>
                        <div class="mb-0 text-secondary" style="line-height: 1.6;">{!! $exam->description !!}</div>
                    </div>

                    <!-- Start Exam Form -->
                    <form action="{{ route('student.exam.start', $exam->id) }}" method="post" class="confirm-start-form">
                        @csrf

                        @if($exam->passcode)
                            <div class="card p-3 bg-light border-warning mb-4 mx-auto" style="max-width: 400px; border-left: 5px solid #ffc107;">
                                <div class="form-group text-left mb-0">
                                    <label class="font-weight-bold text-dark"><i class="fas fa-key mr-2 text-warning"></i>กรอกรหัสเข้าห้องสอบ (Passcode)</label>
                                    <input type="text" name="passcode" class="form-control text-center font-weight-bold @error('passcode') is-invalid @enderror" style="letter-spacing: 2px; font-size: 1.2rem;" placeholder="รหัสผ่านเข้าห้องสอบ" required autocomplete="off">
                                    @error('passcode')
                                        <span class="invalid-feedback text-center d-block font-weight-bold mt-2" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        @endif

                        <div class="d-flex align-items-start text-left mt-4">
                            <i class="fas fa-exclamation-triangle fa-2x mr-3 text-warning mt-1"></i>
                            <div class="text-secondary">
                                <h6 class="font-weight-bold text-dark mb-2">หมายเหตุการสอบ:</h6>
                                <ul class="pl-3 mb-0" style="line-height: 1.6;">
                                    <li>หลังจากกดปุ่ม <strong>"เริ่มทำข้อสอบ"</strong> ระบบจะเริ่มนับเวลาถอยหลังทันทีและไม่สามารถหยุดเวลาได้</li>
                                    <li><strong>ไม่อนุญาตให้คัดลอก (Copy)</strong> ข้อความหรือเนื้อหาใดๆ ในข้อสอบ</li>
                                    @if($exam->force_fullscreen)
                                        <li>ข้อสอบนี้บังคับให้เข้าทำในโหมด <strong>เต็มหน้าจอ (Fullscreen)</strong> เท่านั้น</li>
                                    @endif
                                    @if($exam->max_focus_escapes > 0)
                                        <li>หากคุณ <strong>สลับแท็บ เปลี่ยนหน้าจอ หรือออกจากโหมดเต็มหน้าจอ เกิน {{ $exam->max_focus_escapes }} ครั้ง</strong> ระบบจะส่งข้อสอบและยุติการทำข้อสอบโดยอัตโนมัติทันที</li>
                                    @endif
                                </ul>
                            </div>
                        </div>
                </div>
                <div class="card-footer bg-light text-center py-4 d-flex flex-column flex-sm-row justify-content-center align-items-stretch align-items-sm-center">
                        <button type="submit" class="btn btn-primary btn-lg font-weight-bold shadow mb-3 mb-sm-0 mx-sm-2 px-5">
                            <i class="fas fa-play mr-2"></i> เริ่มทำข้อสอบ
                        </button>
                        <a href="{{ route('student.dashboard') }}" class="btn btn-secondary btn-lg font-weight-bold shadow mx-sm-2 px-4">
                            <i class="fas fa-times mr-2"></i> ยกเลิก
                        </a>
                </div>
                    </form>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            $('.confirm-start-form').on('submit', function(e) {
                e.preventDefault();
                var form = this;
                Swal.fire({
                    title: 'ยืนยันการเริ่มทำข้อสอบ?',
                    text: "คุณแน่ใจแล้วใช่ไหมที่จะเริ่มทำข้อสอบนี้? เวลาจะเริ่มนับถอยหลังทันทีและไม่สามารถหยุดได้!",
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#007bff',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'ตกลง, เริ่มทำข้อสอบ!',
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

