@extends('adminlte::page')

@section('title', 'แก้ไขคำถาม')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="text-dark font-weight-bold">แก้ไขคำถาม</h1>
            <h5 class="text-muted mt-1">ข้อสอบ: <strong>{{ $exam->title }}</strong></h5>
        </div>
        <a href="{{ route('admin.exams.questions.index', $exam->id) }}" class="btn btn-secondary font-weight-bold shadow-sm">
            <i class="fas fa-arrow-left mr-2"></i>ยกเลิกและย้อนกลับ
        </a>
    </div>
@stop

@section('content')
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card shadow-sm">
                <div class="card-header bg-warning">
                    <h3 class="card-title font-weight-bold text-white mb-0"><i class="fas fa-question-circle mr-2"></i>ฟอร์มแก้ไขโจทย์คำถามและตัวเลือก</h3>
                </div>
                <form action="{{ route('admin.exams.questions.update', [$exam->id, $question->id]) }}" method="post">
                    @csrf
                    @method('put')
                    <div class="card-body">
                        <div class="form-group">
                            <label class="font-weight-bold">โจทย์คำถาม</label>
                            <textarea name="question_text" class="form-control @error('question_text') is-invalid @enderror" rows="4" required>{{ old('question_text', $question->question_text) }}</textarea>
                            @error('question_text')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">คะแนนประจำข้อ</label>
                            <input type="number" name="score" step="0.5" class="form-control @error('score') is-invalid @enderror" value="{{ old('score', $question->score) }}" min="0.1" required>
                            @error('score')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-bold">ประเภทคำถาม</label>
                                    <select name="type" class="form-control question-type-select">
                                        <option value="choice" {{ old('type', $question->type ?? 'choice') === 'choice' ? 'selected' : '' }}>คำถามแบบปรนัย (มีตัวเลือก)</option>
                                        <option value="essay" {{ old('type', $question->type ?? 'choice') === 'essay' ? 'selected' : '' }}>คำถามแบบอัตนัย (พิมพ์ตอบ)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label class="font-weight-bold">ตอนของข้อสอบ</label>
                                    <select name="exam_section_id" class="form-control select2" required>
                                        @foreach($exam->sections as $sect)
                                            <option value="{{ $sect->id }}" {{ old('exam_section_id', $question->exam_section_id) == $sect->id ? 'selected' : '' }}>
                                                {{ $sect->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="essay-section {{ old('type', $question->type ?? 'choice') === 'essay' ? '' : 'd-none' }}">
                            <div class="form-group">
                                <label class="font-weight-bold">เฉลย/แนวการตอบ (มีหรือไม่มีก็ได้)</label>
                                <textarea name="essay_answer" class="form-control" rows="2" placeholder="กรอกแนวคำตอบ/คีย์เวิร์ดคำสำคัญ">{{ old('essay_answer', $question->essay_answer) }}</textarea>
                            </div>
                        </div>

                        <div class="choices-section {{ old('type', $question->type ?? 'choice') === 'choice' ? '' : 'd-none' }}">
                            <hr>
                            <h6 class="font-weight-bold text-warning mb-3"><i class="fas fa-tasks mr-2"></i>แก้ไขตัวเลือกคำตอบ</h6>

                        @foreach($question->choices as $index => $choice)
                            <div class="form-group choice-row">
                                <label class="font-weight-bold">ตัวเลือก {{ chr(65 + $index) }}</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text bg-light">
                                            <input type="radio" name="correct_choice" value="{{ $index }}" {{ $choice->is_correct ? 'checked' : '' }} aria-label="Radio button for correct choice">
                                            <span class="ml-2 font-weight-bold text-sm text-secondary">เฉลย</span>
                                        </div>
                                    </div>
                                    <input type="text" name="choices[]" class="form-control @error('choices.'.$index) is-invalid @enderror" value="{{ old('choices.'.$index, $choice->choice_text) }}" required>
                                    
                                    <!-- Hidden input for choice image -->
                                    <input type="hidden" name="choice_images[]" class="choice-image-path" value="{{ $choice->choice_image ? asset($choice->choice_image) : '' }}">
                                    
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-info upload-choice-image-btn" title="อัปโหลดรูปภาพ"><i class="far fa-image"></i></button>
                                    </div>
                                    
                                    <!-- Hidden file input -->
                                    <input type="file" class="choice-image-file" style="display: none;" accept="image/*">
                                </div>
                                
                                <!-- Image preview container -->
                                <div class="choice-image-preview-container mt-1 ml-5 {{ $choice->choice_image ? '' : 'd-none' }}">
                                    <div class="position-relative d-inline-block mt-1">
                                        <img src="{{ $choice->choice_image ? asset($choice->choice_image) : '' }}" class="img-thumbnail choice-image-preview" style="max-height: 80px;">
                                        <button type="button" class="btn btn-xs btn-danger position-absolute delete-choice-image-btn" style="top: -5px; right: -5px; border-radius: 50%; width: 20px; height: 20px; padding: 0;" title="ลบรูปภาพ">
                                            <i class="fas fa-times" style="font-size: 10px;"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        </div>
                    </div>
                    <div class="card-footer bg-light text-right">
                        <button type="submit" class="btn btn-warning font-weight-bold text-white shadow-sm">
                            <i class="fas fa-save mr-2"></i>บันทึกการแก้ไข
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        .choice-image-preview {
            max-height: 80px;
        }
    </style>
@stop

@section('js')
    <script>
        $(document).ready(function() {
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

            // Trigger hidden file input click
            $(document).on('click', '.upload-choice-image-btn', function() {
                $(this).closest('.choice-row').find('.choice-image-file').click();
            });

            // Handle file selection and upload
            $(document).on('change', '.choice-image-file', function() {
                var fileInput = $(this);
                var choiceRow = fileInput.closest('.choice-row');
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
                
                choiceRow.find('.choice-image-path').val('');
                choiceRow.find('.choice-image-file').val('');
                choiceRow.find('.choice-image-preview-container').addClass('d-none');
                choiceRow.find('.choice-image-preview').attr('src', '');
                
                showToast('ลบรูปภาพตัวเลือกแล้ว', 'info');
            });

            // Toggle choice vs essay section
            $(document).on('change', '.question-type-select', function() {
                var type = $(this).val();
                if (type === 'essay') {
                    $('.choices-section').addClass('d-none');
                    $('.essay-section').removeClass('d-none');
                } else {
                    $('.choices-section').removeClass('d-none');
                    $('.essay-section').addClass('d-none');
                }
            });
        });
    </script>
@stop
