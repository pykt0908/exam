@extends('adminlte::page')

@section('title', 'สร้างข้อสอบใหม่')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="text-dark font-weight-bold mb-1">
                <i class="fas fa-plus-circle text-primary mr-2"></i>สร้างข้อสอบใหม่
            </h1>
            <p class="text-muted mb-0 text-sm">กรอกข้อมูลและตั้งค่าสำหรับชุดข้อสอบใหม่</p>
        </div>
        <a href="{{ route('admin.exams.index') }}" class="btn btn-secondary font-weight-bold shadow-sm">
            <i class="fas fa-arrow-left mr-2"></i>กลับหน้ารายการข้อสอบ
        </a>
    </div>
@stop

@section('content')
    <form action="{{ route('admin.exams.store') }}" method="POST" id="create-exam-form">
        @csrf

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show shadow-sm">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h5 class="font-weight-bold mb-2"><i class="fas fa-exclamation-triangle mr-2"></i>กรุณาตรวจสอบข้อมูลที่กรอก</h5>
                <ul class="mb-0 pl-3">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row">
            <!-- Left Column: Main Exam Details -->
            <div class="col-lg-8">
                <!-- General Info Card -->
                <div class="card card-primary card-outline shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h3 class="card-title font-weight-bold text-dark mb-0">
                            <i class="fas fa-info-circle text-primary mr-2"></i>ข้อมูลทั่วไปของข้อสอบ
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="font-weight-bold text-dark">
                                รายวิชา <span class="text-danger">*</span>
                            </label>
                            <select name="subject_id" class="form-control form-control-lg @error('subject_id') is-invalid @enderror" required>
                                <option value="" disabled {{ !old('subject_id', $selectedSubjectId ?? '') ? 'selected' : '' }}>-- เลือกรายวิชา --</option>
                                @foreach($subjects as $subj)
                                    <option value="{{ $subj->id }}" {{ old('subject_id', $selectedSubjectId ?? '') == $subj->id ? 'selected' : '' }}>
                                        [{{ $subj->code }}] {{ $subj->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('subject_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold text-dark">
                                ชื่อข้อสอบ <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="title" class="form-control form-control-lg @error('title') is-invalid @enderror"
                                   value="{{ old('title') }}" placeholder="เช่น สอบกลางภาค ภาคเรียนที่ 1/2567" required>
                            @error('title')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group mb-0">
                            <label class="font-weight-bold text-dark">
                                คำชี้แจง / รายละเอียดข้อสอบ
                            </label>
                            <textarea name="description" class="form-control summernote-editor @error('description') is-invalid @enderror"
                                      rows="5" placeholder="ระบุกฎกติกา ข้อตกลง หรือคำอธิบายเพิ่มเติมสำหรับการสอบ...">{{ old('description') }}</textarea>
                            @error('description')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Security & Exam Delivery Card -->
                <div class="card card-outline card-secondary shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h3 class="card-title font-weight-bold text-dark mb-0">
                            <i class="fas fa-shield-alt text-secondary mr-2"></i>ความปลอดภัยและการเข้าสอบ
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold text-dark">
                                        จำนวนครั้งที่เข้าสอบได้เริ่มต้น <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" name="max_attempts" class="form-control @error('max_attempts') is-invalid @enderror"
                                           value="{{ old('max_attempts', 1) }}" min="1" required>
                                    <small class="form-text text-muted">
                                        ค่าเริ่มต้นสำหรับทุกคน (ปกติคือ 1 ครั้ง) — สามารถเปิดให้สอบเพิ่ม/สอบซ่อมเป็นรายคนได้ในภายหลัง
                                    </small>
                                    @error('max_attempts')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold text-dark">
                                        รหัสผ่านเข้าห้องสอบ (Passcode)
                                    </label>
                                    <input type="text" name="passcode" class="form-control @error('passcode') is-invalid @enderror"
                                           value="{{ old('passcode') }}" placeholder="เช่น 123456 (เว้นว่างไว้หากไม่ต้องใส่รหัส)">
                                    <small class="form-text text-muted">ผู้สอบต้องกรอกรหัสนี้ก่อนเริ่มทำข้อสอบ</small>
                                    @error('passcode')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold text-dark">
                                จำกัดการสลับแท็บ / ออกจากหน้าจอ (ครั้ง) <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="max_focus_escapes" class="form-control @error('max_focus_escapes') is-invalid @enderror"
                                   value="{{ old('max_focus_escapes', 0) }}" min="0" required>
                            <small class="form-text text-muted">
                                0 = ไม่จำกัด (หากระบุมากกว่า 0 เมื่อผู้สอบสลับหน้าจอเกินจำนวนที่กำหนด ระบบจะส่งข้อสอบอัตโนมัติทันที)
                            </small>
                            @error('max_focus_escapes')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <hr class="my-3">

                        <h6 class="font-weight-bold text-dark mb-3">
                            <i class="fas fa-random mr-2 text-muted"></i>การสลับลำดับ & การแสดงผลหน้าจอ
                        </h6>
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" name="shuffle_questions" class="custom-control-input" id="shuffle_questions" value="1" {{ old('shuffle_questions') ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-normal text-dark" for="shuffle_questions">
                                สลับลำดับข้อสอบแบบสุ่มสำหรับผู้สอบแต่ละคน
                            </label>
                        </div>
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" name="shuffle_choices" class="custom-control-input" id="shuffle_choices" value="1" {{ old('shuffle_choices') ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-normal text-dark" for="shuffle_choices">
                                สลับตัวเลือกคำตอบแบบสุ่มสำหรับผู้สอบแต่ละคน
                            </label>
                        </div>
                        <div class="custom-control custom-checkbox mb-0">
                            <input type="checkbox" name="force_fullscreen" class="custom-control-input" id="force_fullscreen" value="1" {{ old('force_fullscreen') ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-normal text-dark" for="force_fullscreen">
                                บังคับให้สอบแบบเต็มหน้าจอ (Force Fullscreen)
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Settings, Timing, Score & Actions -->
            <div class="col-lg-4">
                <!-- Time & Scoring Card -->
                <div class="card card-outline card-info shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h3 class="card-title font-weight-bold text-dark mb-0">
                            <i class="fas fa-clock text-info mr-2"></i>กำหนดการและเวลาสอบ
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="font-weight-bold text-dark">
                                เวลาที่ใช้สอบ (นาที) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" name="duration_minutes" class="form-control @error('duration_minutes') is-invalid @enderror"
                                       value="{{ old('duration_minutes', 60) }}" min="1" required>
                                <div class="input-group-append">
                                    <span class="input-group-text font-weight-bold">นาที</span>
                                </div>
                                @error('duration_minutes')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            <small class="form-text text-muted">ระยะเวลานับถอยหลังเมื่อผู้สอบกดเริ่มทำข้อสอบ</small>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold text-dark">
                                วัน-เวลาเริ่มเปิดสอบ (Starts At)
                            </label>
                            <input type="datetime-local" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror"
                                   value="{{ old('starts_at') }}">
                            <small class="form-text text-muted">เว้นว่างไว้หากต้องการเปิดให้เข้าสอบได้ทันที</small>
                            @error('starts_at')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold text-dark">
                                วัน-เวลาปิดระบบสอบ (Ends At)
                            </label>
                            <input type="datetime-local" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror"
                                   value="{{ old('ends_at') }}">
                            <small class="form-text text-muted">เว้นว่างไว้หากไม่จำกัดเวลาสิ้นสุดการสอบ</small>
                            @error('ends_at')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <hr class="my-3">

                        <div class="form-group">
                            <label class="font-weight-bold text-dark">
                                คะแนนเต็มข้อสอบ <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" name="total_score" step="0.01" class="form-control @error('total_score') is-invalid @enderror"
                                       value="{{ old('total_score', 100.00) }}" min="0.01" required>
                                <div class="input-group-append">
                                    <span class="input-group-text font-weight-bold">คะแนน</span>
                                </div>
                                @error('total_score')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group mb-0">
                            <label class="font-weight-bold text-dark">
                                เกณฑ์การสอบผ่าน (%) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" name="passing_percentage" class="form-control @error('passing_percentage') is-invalid @enderror"
                                       value="{{ old('passing_percentage', 60) }}" min="0" max="100" required>
                                <div class="input-group-append">
                                    <span class="input-group-text font-weight-bold">%</span>
                                </div>
                                @error('passing_percentage')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Result & Review Policy Card -->
                <div class="card card-outline card-success shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h3 class="card-title font-weight-bold text-dark mb-0">
                            <i class="fas fa-poll text-success mr-2"></i>การแสดงผลคะแนนและเฉลย
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="custom-control custom-checkbox mb-3">
                            <input type="checkbox" name="show_score" class="custom-control-input" id="show_score" value="1" {{ old('show_score', '1') ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-bold text-dark" for="show_score">
                                แสดงคะแนนทันที
                            </label>
                            <div class="text-muted text-xs pl-0">แสดงผลคะแนนหลังส่งข้อสอบเสร็จสิ้น</div>
                        </div>

                        <div class="custom-control custom-checkbox mb-3">
                            <input type="checkbox" name="show_answers" class="custom-control-input" id="show_answers" value="1" {{ old('show_answers', '1') ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-bold text-dark" for="show_answers">
                                แสดงเฉลยทันที
                            </label>
                            <div class="text-muted text-xs pl-0">แสดงเฉลยข้อถูก/ผิดหลังส่งข้อสอบเสร็จสิ้น</div>
                        </div>

                        <div class="custom-control custom-checkbox mb-0">
                            <input type="checkbox" name="allow_review" class="custom-control-input" id="allow_review" value="1" {{ old('allow_review', '1') ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-bold text-dark" for="allow_review">
                                ดูผลคะแนนย้อนหลังได้
                            </label>
                            <div class="text-muted text-xs pl-0">อนุญาตให้ผู้สอบกลับมาดูผลการสอบย้อนหลังได้ตลอดเวลา</div>
                        </div>
                    </div>
                </div>

                <!-- Status & Publish Card -->
                <div class="card card-outline card-warning shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h3 class="card-title font-weight-bold text-dark mb-0">
                            <i class="fas fa-toggle-on text-warning mr-2"></i>สถานะข้อสอบ
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" name="is_active" class="custom-control-input" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-bold text-dark" for="is_active">
                                เปิดใช้งานข้อสอบทันที
                            </label>
                        </div>
                        <small class="form-text text-muted mt-2">
                            หากเปิดใช้งาน นักศึกษาจะสามารถมองเห็นข้อสอบนี้ในหน้าระบบได้ (หากปิดไว้จะไม่สามารถเข้าสอบได้)
                        </small>
                    </div>
                </div>

                <!-- Submit Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body p-3">
                        <button type="submit" class="btn btn-primary btn-block btn-lg font-weight-bold shadow-sm mb-2">
                            <i class="fas fa-save mr-2"></i>บันทึกและสร้างข้อสอบ
                        </button>
                        <a href="{{ route('admin.exams.index') }}" class="btn btn-outline-secondary btn-block font-weight-bold">
                            <i class="fas fa-times mr-2"></i>ยกเลิก
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            // Initialize Summernote editor
            $('.summernote-editor').summernote({
                height: 200,
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
        });
    </script>
@stop
