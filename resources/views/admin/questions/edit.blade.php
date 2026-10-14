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
                            <textarea name="question_text" class="form-control summernote-question @error('question_text') is-invalid @enderror" rows="4" required>{{ old('question_text', $question->question_text) }}</textarea>
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
                            <div class="choice-row card card-outline card-light border mb-3 shadow-none {{ $choice->is_correct ? 'is-correct-choice' : '' }}">
                                <div class="card-header py-1 px-3 bg-light d-flex align-items-center justify-content-between">
                                    <label class="mb-0 d-flex align-items-center font-weight-bold text-dark" style="cursor: pointer;">
                                        <input type="radio" name="correct_choice" class="correct-radio mr-2" value="{{ $index }}" {{ $choice->is_correct ? 'checked' : '' }} style="transform: scale(1.25); cursor:pointer;">
                                        <span class="choice-label badge {{ $choice->is_correct ? 'badge-success' : 'badge-secondary' }} mr-2" style="font-size: 0.95rem;">{{ chr(65 + $index) }}</span>
                                        <span class="choice-status-text {{ $choice->is_correct ? 'text-success font-weight-bold' : 'text-muted' }}">
                                            {{ $choice->is_correct ? '✓ ตัวเลือกที่ถูกต้อง (เฉลย)' : 'คลิกเพื่อตั้งเป็นเฉลย' }}
                                        </span>
                                    </label>
                                </div>
                                <div class="card-body p-2">
                                    <textarea name="choices[]" class="form-control choice-summernote @error('choices.'.$index) is-invalid @enderror" placeholder="ตัวเลือก {{ chr(65 + $index) }}" required>{{ old('choices.'.$index, $choice->choice_text) }}</textarea>
                                    <input type="hidden" name="choice_images[]" class="choice-image-path" value="{{ $choice->choice_image ? asset($choice->choice_image) : '' }}">

                                    @if($choice->choice_image)
                                        <div class="choice-image-preview-container mt-2 ml-2">
                                            <div class="position-relative d-inline-block">
                                                <img src="{{ asset($choice->choice_image) }}" class="img-thumbnail choice-image-preview" style="max-height: 80px;">
                                                <button type="button" class="btn btn-xs btn-danger position-absolute delete-choice-image-btn" style="top: -5px; right: -5px; border-radius: 50%; width: 20px; height: 20px; padding: 0;" title="ลบรูปภาพ">
                                                    <i class="fas fa-times" style="font-size: 10px;"></i>
                                                </button>
                                            </div>
                                        </div>
                                    @endif
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
        .choice-row {
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e0e6ed;
            background-color: #fff;
            transition: all 0.2s ease;
        }
        .choice-row:hover {
            border-color: #b0c4de;
        }
        .choice-row .card-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #edf2f7;
            padding: 8px 14px;
        }
        .choice-row.is-correct-choice {
            border-color: #28a745;
        }
        .choice-row.is-correct-choice .card-header {
            background-color: #eafaf1;
            border-bottom-color: #d4edda;
        }
        .choice-row .note-editor.note-frame {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 0;
        }
        .choice-image-preview {
            max-height: 80px;
        }
        .note-dropdown-menu {
            max-height: 280px;
            overflow-y: auto;
        }
    </style>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            // Font configuration for Summernote editors
            var customFontNames = [
                'Google Sans',
                'Noto Sans Thai',
                'Sarabun',
                'TH Sarabun New',
                'LINESeedSansTH',
                'Arial',
                'Courier New',
                'Tahoma',
                'Times New Roman',
                'Verdana'
            ];
            var customFontNamesIgnoreCheck = [
                'Google Sans',
                'Noto Sans Thai',
                'Sarabun',
                'TH Sarabun New',
                'LINESeedSansTH'
            ];
            var customFontSizes = ['10', '12', '14', '16', '18', '20', '22', '24', '28', '32', '36', '48'];

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

            // Initialize Summernote for question text
            $('.summernote-question').summernote({
                height: 180,
                tabsize: 2,
                toolbar: [
                    ['style', ['style']],
                    ['fontname', ['fontname']],
                    ['fontsize', ['fontsize']],
                    ['font', ['bold', 'italic', 'underline', 'strikethrough', 'superscript', 'subscript', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture', 'video', 'hr', 'math']],
                    ['view', ['fullscreen', 'codeview']]
                ],
                fontNames: customFontNames,
                fontNamesIgnoreCheck: customFontNamesIgnoreCheck,
                fontSizes: customFontSizes,
                callbacks: {
                    onImageUpload: function(files) {
                        uploadSummernoteImage(files[0], this);
                    },
                    onPaste: function(e) {
                        if (window.handleSummernotePaste) {
                            window.handleSummernotePaste(e, this);
                        }
                    },
                    onChange: function(contents, $editable) {
                        $(this).val(contents);
                    }
                }
            });

            // Initialize Summernote for choices
            $('.choice-summernote').each(function() {
                $(this).summernote({
                    height: 90,
                    tabsize: 2,
                    toolbar: [
                        ['fontname', ['fontname']],
                        ['fontsize', ['fontsize']],
                        ['font', ['bold', 'italic', 'underline', 'strikethrough', 'superscript', 'subscript', 'clear']],
                        ['color', ['color']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['insert', ['picture', 'link', 'table', 'hr', 'math']],
                        ['view', ['codeview']]
                    ],
                    fontNames: customFontNames,
                    fontNamesIgnoreCheck: customFontNamesIgnoreCheck,
                    fontSizes: customFontSizes,
                    callbacks: {
                        onImageUpload: function(files) {
                            uploadSummernoteImage(files[0], this);
                        },
                        onPaste: function(e) {
                            if (window.handleSummernotePaste) {
                                window.handleSummernotePaste(e, this);
                            }
                        },
                        onChange: function(contents, $editable) {
                            $(this).val(contents);
                        }
                    }
                });
            });

            // Handle radio button selection change
            $(document).on('change', '.correct-radio', function() {
                $('.choice-row').each(function() {
                    var radio = $(this).find('.correct-radio');
                    var badge = $(this).find('.choice-label');
                    var status = $(this).find('.choice-status-text');
                    if (radio.is(':checked')) {
                        $(this).addClass('is-correct-choice');
                        badge.removeClass('badge-secondary').addClass('badge-success');
                        status.removeClass('text-muted').addClass('text-success font-weight-bold').text('✓ ตัวเลือกที่ถูกต้อง (เฉลย)');
                    } else {
                        $(this).removeClass('is-correct-choice');
                        badge.removeClass('badge-success').addClass('badge-secondary');
                        status.removeClass('text-success font-weight-bold').addClass('text-muted').text('คลิกเพื่อตั้งเป็นเฉลย');
                    }
                });
            });

            // Delete choice image
            $(document).on('click', '.delete-choice-image-btn', function() {
                var btn = $(this);
                var choiceRow = btn.closest('.choice-row');
                choiceRow.find('.choice-image-path').val('');
                choiceRow.find('.choice-image-preview-container').remove();
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
