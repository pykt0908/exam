@extends('adminlte::page')

@section('title', 'จัดการข้อสอบ')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="text-dark font-weight-bold">ข้อสอบ</h1>
        <button type="button" class="btn btn-primary font-weight-bold shadow-sm" data-toggle="modal" data-target="#addExamModal">
            <i class="fas fa-plus mr-2"></i>สร้างข้อสอบใหม่
        </button>
    </div>
@stop

@section('content')
    @if(auth()->user()->isStaff())
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-light">
                <h3 class="card-title font-weight-bold text-dark mb-0"><i class="fas fa-filter mr-2"></i>ตัวกรองและค้นหาข้อสอบ</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.exams.index') }}" method="GET" class="row">
                    <input type="hidden" name="search" value="1">
                    @if(auth()->user()->isAdmin())
                        <div class="form-group col-md-4">
                            <label class="text-sm font-weight-bold text-dark">รายวิชา</label>
                            <select name="subject_id" class="form-control">
                                <option value="">-- เลือกรายวิชาทั้งหมด --</option>
                                @foreach($subjects as $subj)
                                    <option value="{{ $subj->id }}" {{ request('subject_id') == $subj->id ? 'selected' : '' }}>
                                        [{{ $subj->code }}] {{ $subj->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="text-sm font-weight-bold text-dark">ผู้สร้างข้อสอบ (ครู)</label>
                            <select name="teacher_id" class="form-control">
                                <option value="">-- เลือกคุณครูทั้งหมด --</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" {{ request('teacher_id') == $teacher->id ? 'selected' : '' }}>
                                        {{ $teacher->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="text-sm font-weight-bold text-dark">ชั้นเรียน (นักศึกษา)</label>
                            <select name="classroom_id" class="form-control">
                                <option value="">-- เลือกชั้นเรียนทั้งหมด --</option>
                                @foreach($classrooms as $cls)
                                    <option value="{{ $cls->id }}" {{ request('classroom_id') == $cls->id ? 'selected' : '' }}>
                                        {{ $cls->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div class="form-group col-md-6">
                            <label class="text-sm font-weight-bold text-dark">รายวิชา</label>
                            <select name="subject_id" class="form-control">
                                <option value="">-- เลือกรายวิชาทั้งหมด --</option>
                                @foreach($subjects as $subj)
                                    <option value="{{ $subj->id }}" {{ request('subject_id') == $subj->id ? 'selected' : '' }}>
                                        [{{ $subj->code }}] {{ $subj->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="text-sm font-weight-bold text-dark">ชั้นเรียน (นักศึกษา)</label>
                            <select name="classroom_id" class="form-control">
                                <option value="">-- เลือกชั้นเรียนทั้งหมด --</option>
                                @foreach($classrooms as $cls)
                                    <option value="{{ $cls->id }}" {{ request('classroom_id') == $cls->id ? 'selected' : '' }}>
                                        {{ $cls->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="form-group col-md-8 mb-2 mb-md-0">
                        <label class="text-sm font-weight-bold text-dark">คำค้นหา</label>
                        <input type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="ค้นหาชื่อข้อสอบ หรือรายละเอียด...">
                    </div>
                    <div class="form-group col-md-4 mb-0 align-self-end">
                        <button type="submit" class="btn btn-primary btn-block font-weight-bold shadow-sm">
                            <i class="fas fa-search mr-2"></i>ค้นหาข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card shadow-sm mt-3">
        <div class="card-body p-3">
            @if(auth()->user()->isAdmin() && !$searchPerformed)
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted font-weight-bold">กรุณาเลือกตัวกรองหรือกรอกข้อมูลค้นหาด้านบนเพื่อแสดงรายการข้อสอบ</h5>
                </div>
            @else
                <div class="table-responsive">
                <table id="exams-table" class="table table-bordered table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>รายวิชา</th>
                            <th>ชื่อข้อสอบ</th>
                            <th>เวลา (นาที)</th>
                            <th class="text-center">คะแนนเต็ม</th>
                            <th class="text-center">เกณฑ์ผ่าน</th>
                            <th>จำนวนข้อ</th>
                            <th>สถานะเปิดสอบ</th>
                            <th class="text-center" style="width: 1%; white-space: nowrap;">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($exams as $index => $exam)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $exam->subject->code }} {{ $exam->subject->name }}</td>
                                <td>{{ $exam->title }}</td>
                                <td>{{ $exam->duration_minutes }} นาที</td>
                                <td class="text-center">{{ number_format($exam->total_score, 2) }} คะแนน</td>
                                <td class="text-center">
                                    {{ $exam->passing_percentage }}% ({{ number_format($exam->total_score * $exam->passing_percentage / 100, 2) }} คะแนน)
                                </td>
                                <td>
                                    {{ $exam->questions_count }} ข้อ
                                </td>
                                <td>
                                    <form action="{{ route('admin.exams.toggle-status', $exam->id) }}" method="post">
                                        @csrf
                                        <button type="submit" class="btn btn-xs {{ $exam->is_active ? 'btn-success' : 'btn-secondary' }} px-2 font-weight-bold">
                                            {{ $exam->is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="text-center" style="white-space: nowrap;">
                                    <a href="{{ route('admin.exams.questions.index', $exam->id) }}" class="btn btn-sm btn-info font-weight-bold shadow-xs mr-1">
                                        <i class="fas fa-edit mr-1"></i> แก้ไข & โจทย์ ({{ $exam->questions_count }})
                                    </a>
                                    <form action="{{ route('admin.exams.destroy', $exam->id) }}" method="post" class="d-inline confirm-delete"
                                          data-text="คุณแน่ใจหรือไม่ที่จะลบข้อสอบนี้? ข้อมูลคำถาม คำตอบ และผลการสอบทั้งหมดของวิชานี้จะถูกลบไปด้วย!">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="btn btn-sm btn-danger font-weight-bold shadow-xs">
                                            <i class="fas fa-trash-alt mr-1"></i> ลบ
                                        </button>
                                    </form>
                                </td>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">ไม่มีข้อมูลข้อสอบในระบบ</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    <!-- Add Exam Modal -->
    <div class="modal fade" id="addExamModal" tabindex="-1" role="dialog" aria-labelledby="addExamModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white font-weight-bold" id="addExamModalLabel"><i class="fas fa-plus mr-2"></i>สร้างข้อสอบใหม่</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.exams.store') }}" method="post">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="font-weight-bold">รายวิชา</label>
                            <select name="subject_id" class="form-control @error('subject_id') is-invalid @enderror" required>
                                <option value="" disabled selected>-- เลือกรายวิชา --</option>
                                @foreach($subjects as $sub)
                                    <option value="{{ $sub->id }}" {{ old('subject_id') == $sub->id ? 'selected' : '' }}>
                                        [{{ $sub->code }}] {{ $sub->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('subject_id')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">ชื่อข้อสอบ</label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" 
                                   value="{{ old('title') }}" placeholder="เช่น สอบกลางภาควิชาการเขียนโปรแกรม" required>
                            @error('title')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">คำชี้แจง/รายละเอียด</label>
                            <textarea name="description" class="form-control summernote-editor @error('description') is-invalid @enderror" 
                                      rows="3" placeholder="ชี้แจงกฎกติกาการสอบ เกณฑ์คะแนนการผ่าน...">{{ old('description') }}</textarea>
                            @error('description')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="row">
                            <div class="col-4">
                                <div class="form-group">
                                    <label class="font-weight-bold">เวลาที่ใช้สอบ (นาที)</label>
                                    <input type="number" name="duration_minutes" class="form-control @error('duration_minutes') is-invalid @enderror" 
                                           value="{{ old('duration_minutes', 60) }}" min="1" required>
                                    @error('duration_minutes')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-group">
                                    <label class="font-weight-bold">เกณฑ์การสอบผ่าน (%)</label>
                                    <input type="number" name="passing_percentage" class="form-control @error('passing_percentage') is-invalid @enderror" 
                                           value="{{ old('passing_percentage', 60) }}" min="0" max="100" required>
                                    @error('passing_percentage')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-group">
                                    <label class="font-weight-bold">คะแนนเต็มข้อสอบ</label>
                                    <input type="number" name="total_score" step="0.01" class="form-control @error('total_score') is-invalid @enderror" 
                                           value="{{ old('total_score', 100.00) }}" min="0.01" required>
                                    @error('total_score')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <!-- Advanced / Security Settings -->
                        <div class="mt-3 mb-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm btn-block font-weight-bold shadow-xs" data-toggle="collapse" data-target="#addAdvancedSettingsCollapse">
                                <i class="fas fa-cog mr-1"></i> ตั้งค่าข้อสอบขั้นสูง / ความปลอดภัย
                            </button>
                            
                            <div class="collapse mt-3" id="addAdvancedSettingsCollapse">
                                <div class="card card-body bg-light p-3 mb-0">
                                    <div class="form-group col-12 text-left">
                                        <label class="font-weight-bold text-xs">จำนวนครั้งที่เข้าสอบได้ (รวมสอบซ่อม/สอบแก้ตัว) (ครั้ง)</label>
                                        <input type="number" name="max_attempts" class="form-control form-control-sm" value="1" min="1" required>
                                        <small class="form-text text-muted text-xs">ระบุ 1 = สอบได้ครั้งเดียว (ไม่มีสอบซ่อม), 2 = สอบซ่อมได้ 1 ครั้ง (เข้าสอบได้ทั้งหมด 2 ครั้ง)</small>
                                    </div>
                                    <div class="form-group col-12 text-left">
                                        <label class="font-weight-bold text-xs">รหัสผ่านเข้าห้องสอบ (Passcode)</label>
                                        <input type="text" name="passcode" class="form-control form-control-sm" placeholder="เช่น 123456 (เว้นว่างไว้หากไม่ต้องใส่รหัส)">
                                    </div>
                                    <div class="form-group col-12 text-left">
                                        <label class="font-weight-bold text-xs">จำกัดการสลับแท็บ/ออกหน้าจอ (ครั้ง)</label>
                                        <input type="number" name="max_focus_escapes" class="form-control form-control-sm" value="0" min="0" required>
                                        <small class="form-text text-muted text-xs">0 = ไม่จำกัด (หากเกินจะส่งคำตอบอัตโนมัติ)</small>
                                    </div>
                                    <hr class="w-100">
                                    <div class="form-group col-12 text-left">
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" name="shuffle_questions" class="custom-control-input" id="add_shuffle_questions_toggle" value="1">
                                            <label class="custom-control-label font-weight-bold text-xs" for="add_shuffle_questions_toggle">สลับลำดับข้อสอบสำหรับผู้สอบแต่ละคน</label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" name="shuffle_choices" class="custom-control-input" id="add_shuffle_choices_toggle" value="1">
                                            <label class="custom-control-label font-weight-bold text-xs" for="add_shuffle_choices_toggle">สลับตัวเลือกคำตอบสำหรับผู้สอบแต่ละคน</label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" name="force_fullscreen" class="custom-control-input" id="add_force_fullscreen_toggle" value="1">
                                            <label class="custom-control-label font-weight-bold text-xs" for="add_force_fullscreen_toggle">บังคับให้สอบแบบเต็มหน้าจอ (Force Fullscreen)</label>
                                        </div>
                                        <hr class="my-2">
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" name="show_score" class="custom-control-input" id="add_show_score_toggle" value="1" checked>
                                            <label class="custom-control-label font-weight-bold text-xs" for="add_show_score_toggle">แสดงคะแนนทันทีหลังส่งข้อสอบเสร็จ</label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" name="show_answers" class="custom-control-input" id="add_show_answers_toggle" value="1" checked>
                                            <label class="custom-control-label font-weight-bold text-xs" for="add_show_answers_toggle">แสดงเฉลยทันทีหลังส่งข้อสอบเสร็จ</label>
                                        </div>
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" name="allow_review" class="custom-control-input" id="add_allow_review_toggle" value="1" checked>
                                            <label class="custom-control-label font-weight-bold text-xs" for="add_allow_review_toggle">อนุญาตให้ผู้สอบเข้ามาดูผลคะแนน/เฉลยย้อนหลังได้</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-check mt-2">
                            <input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" checked>
                            <label class="form-check-label font-weight-bold" for="is_active">เปิดใช้งานทันที</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary font-weight-bold">สร้างข้อสอบ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            $('#exams-table').DataTable({
                "pageLength": -1,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "ทั้งหมด"]],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Thai.json"
                },
                "responsive": true,
                "autoWidth": false
            });

            // SweetAlert Flash Success
            @if(session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ!',
                    text: {!! json_encode(session('success')) !!},
                    confirmButtonText: 'ตกลง',
                    timer: 3000,
                    timerProgressBar: true
                });
            @endif

            // SweetAlert Flash Errors
            @if($errors->any())
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด!',
                    html: {!! json_encode(implode("<br>", $errors->all())) !!},
                    confirmButtonText: 'ตกลง'
                });
            @endif

            // Confirm Delete Handler
            $(document).on('submit', '.confirm-delete', function(e) {
                e.preventDefault();
                var form = this;
                var text = $(this).attr('data-text') || "ข้อมูลนี้จะถูกลบและไม่สามารถกู้คืนกลับมาได้!";
                Swal.fire({
                    title: 'คุณแน่ใจหรือไม่?',
                    text: text,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'ใช่, ต้องการลบ!',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });

            // Bulletproof Summernote initialization using visibility polling
            setInterval(function() {
                $('.summernote-editor').each(function() {
                    if ($(this).is(':visible') && !$(this).hasClass('summernote-initialized')) {
                        $(this).summernote({
                            height: 180,
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
                        $(this).addClass('summernote-initialized');
                    }
                });
            }, 300);
        });
    </script>
@stop
