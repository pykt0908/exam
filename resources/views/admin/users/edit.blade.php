@extends('adminlte::page')
@section('title', 'แก้ไขข้อมูล' . ($user->isStudent() ? 'นักศึกษา' : ($user->isTeacher() ? 'อาจารย์' : 'ผู้ดูแลระบบ')) . ' - ' . $user->name)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="text-dark font-weight-bold">แก้ไขข้อมูล{{ $user->isStudent() ? 'นักศึกษา' : ($user->isTeacher() ? 'อาจารย์' : 'ผู้ดูแลระบบ') }}</h1>
        <a href="{{ route('admin.users.index', ['role' => $user->isStudent() ? 'student' : 'staff']) }}" class="btn btn-secondary font-weight-bold shadow-sm">
            <i class="fas fa-arrow-left mr-2"></i>กลับรายชื่อ{{ $user->isStudent() ? 'นักศึกษา' : 'อาจารย์/ผู้ดูแล' }}
        </a>
    </div>
@stop

@section('content')

{{-- Hidden forms OUTSIDE main form (no nesting) --}}
<form id="delete-photo-form" action="{{ route('admin.users.destroy-photo', $user->id) }}" method="POST" style="display:none">
    @csrf
    @method('DELETE')
</form>
<form id="delete-user-form" action="{{ route('admin.users.destroy', $user->id) }}" method="POST" style="display:none">
    @csrf
    @method('DELETE')
</form>

<div class="card shadow mt-3">
            <div class="card-header">
                <h3 class="card-title font-weight-bold text-dark">
                    <i class="fas {{ $user->isStudent() ? 'fa-user-graduate' : ($user->isTeacher() ? 'fa-user-tie' : 'fa-user-shield') }} mr-2"></i>ข้อมูล: {{ $user->name }}
                </h3>
            </div>
            <form action="{{ route('admin.users.update', $user->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @if($user->isStudent())
                    <input type="hidden" name="role" value="{{ $user->role }}">
                @endif

                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row">
                        {{-- Photo Management --}}
                        <div class="col-md-4 text-center mb-4">
                            <div class="mb-3">
                                @if($user->photo)
                                    <img id="photo-preview"
                                         src="{{ asset('storage/' . $user->photo) }}"
                                         class="rounded-circle"
                                         style="width:160px;height:160px;object-fit:cover;border:3px solid #ffc107;">
                                @else
                                    <img id="photo-preview"
                                         src="{{ asset('images/default-avatar.png') }}"
                                         class="rounded-circle"
                                         style="width:160px;height:160px;object-fit:cover;border:3px solid #dee2e6;">
                                @endif
                            </div>

                            {{-- Upload new photo (inside main form = correct) --}}
                            <label class="btn btn-outline-warning btn-sm font-weight-bold mb-2">
                                <i class="fas fa-camera mr-1"></i> เปลี่ยนรูปภาพ
                                <input type="file" name="photo" id="photo-input" accept="image/*" class="d-none">
                            </label>
                            <p class="text-muted text-xs mb-2">JPG, PNG, WEBP ไม่เกิน 2MB</p>

                            {{-- Delete photo button (triggers outside form) --}}
                            @if($user->photo)
                                <button type="button" id="btn-delete-photo" class="btn btn-outline-danger btn-sm font-weight-bold">
                                    <i class="fas fa-trash mr-1"></i> ลบรูปภาพ
                                </button>
                            @endif
                        </div>

                        {{-- Form Fields --}}
                        <div class="col-md-8">
                            @if($user->isStudent())
                                <div class="form-group">
                                    <label class="font-weight-bold">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                           value="{{ old('name', $user->name) }}" required>
                                    @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold">รหัสนักศึกษา <span class="text-danger">*</span></label>
                                    <input type="text" name="student_code" class="form-control @error('student_code') is-invalid @enderror"
                                           value="{{ old('student_code', $user->student_code) }}" maxlength="13" required>
                                    <small class="text-muted">ใช้เป็นชื่อผู้ใช้เข้าสู่ระบบ</small>
                                    @error('student_code')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold">เลขบัตรประจำตัวประชาชน <span class="text-danger">*</span></label>
                                    <input type="text" name="citizen_id" class="form-control @error('citizen_id') is-invalid @enderror"
                                           value="{{ old('citizen_id', $user->citizen_id) }}" maxlength="13" minlength="13" required>
                                    <small class="text-muted">หากเปลี่ยนเลขบัตร รหัสผ่านจะถูกอัปเดตตามอัตโนมัติ</small>
                                    @error('citizen_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold">ระดับชั้น / ห้องเรียน <span class="text-danger">*</span></label>
                                    <select name="classroom_id" class="form-control @error('classroom_id') is-invalid @enderror" required>
                                        <option value="" disabled>-- เลือกห้องเรียน --</option>
                                        @foreach($classrooms as $room)
                                            <option value="{{ $room->id }}" {{ old('classroom_id', $user->classroom_id) == $room->id ? 'selected' : '' }}>
                                                {{ $room->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('classroom_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                </div>

                            @else
                                {{-- Staff (admin/teacher) fields --}}
                                <div class="form-group">
                                    <label class="font-weight-bold">ประเภทบทบาท <span class="text-danger">*</span></label>
                                    <select name="role" id="role-select-edit" class="form-control" required>
                                        <option value="teacher" {{ old('role', $user->role) === 'teacher' ? 'selected' : '' }}>อาจารย์ (Teacher)</option>
                                        <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>ผู้ดูแลระบบ (Admin)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold">หมวดวิชา / แผนกวิชา</label>
                                    <select name="department_id" class="form-control @error('department_id') is-invalid @enderror">
                                        <option value="">-- ไม่ระบุหมวดวิชา / แผนกวิชา --</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}" {{ old('department_id', $user->department_id) == $dept->id ? 'selected' : '' }}>
                                                {{ $dept->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('department_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                </div>
                                <div class="form-group" id="academic-role-group-edit">
                                    <label class="font-weight-bold d-block">ตำแหน่ง / บทบาทหน้าที่เสริม <span class="text-muted font-weight-normal text-xs">(สามารถเลือกได้มากกว่า 1 ตำแหน่ง)</span></label>
                                    <div class="d-flex flex-wrap" style="gap: 15px;">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="role_academic_deputy" name="academic_roles[]" value="academic_deputy" 
                                                   {{ (is_array(old('academic_roles', $user->academic_roles)) && in_array('academic_deputy', old('academic_roles', $user->academic_roles))) ? 'checked' : '' }}>
                                            <label class="custom-control-label font-weight-normal" for="role_academic_deputy">รองผู้อำนวยการฝ่ายวิชาการ</label>
                                        </div>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="role_evaluation_head" name="academic_roles[]" value="evaluation_head" 
                                                   {{ (is_array(old('academic_roles', $user->academic_roles)) && in_array('evaluation_head', old('academic_roles', $user->academic_roles))) ? 'checked' : '' }}>
                                            <label class="custom-control-label font-weight-normal" for="role_evaluation_head">หัวหน้างานวัดและประเมินผล</label>
                                        </div>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="role_department_head" name="academic_roles[]" value="department_head" 
                                                   {{ (is_array(old('academic_roles', $user->academic_roles)) && in_array('department_head', old('academic_roles', $user->academic_roles))) ? 'checked' : '' }}>
                                            <label class="custom-control-label font-weight-normal" for="role_department_head">หัวหน้าสาขา</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold">รหัสประจำตัวครู <span class="text-danger">*</span></label>
                                    <input type="text" name="teacher_code" class="form-control @error('teacher_code') is-invalid @enderror"
                                           value="{{ old('teacher_code', $user->teacher_code) }}" required>
                                    @error('teacher_code')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                           value="{{ old('name', $user->name) }}" required>
                                    @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold">รหัสผ่านใหม่ <span class="text-muted text-xs">(เว้นว่างไว้หากไม่เปลี่ยน)</span></label>
                                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                                           placeholder="อย่างน้อย 6 ตัวอักษร">
                                    @error('password')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <div>
                        {{-- Delete user button (triggers outside form) --}}
                        <button type="button" id="btn-delete-user" class="btn btn-danger font-weight-bold"
                                data-text="คุณแน่ใจหรือไม่ที่จะลบ {{ $user->name }}? {{ auth()->id() === $user->id ? '(หากลบบัญชีที่กำลังใช้งานอยู่ ระบบจะทำการออกจากระบบทันที)' : ($user->isStudent() ? 'ประวัติการสอบทั้งหมดจะถูกลบไปด้วย!' : '') }}">
                            <i class="fas fa-trash mr-1"></i>ลบ{{ $user->isStudent() ? 'นักศึกษา' : 'ผู้ใช้งาน' }}
                        </button>
                    </div>
                    <div>
                        <a href="{{ route('admin.users.index', ['role' => $user->isStudent() ? 'student' : 'staff']) }}" class="btn btn-secondary font-weight-bold mr-2">
                            <i class="fas fa-times mr-1"></i>ยกเลิก
                        </a>
                        <button type="submit" class="btn btn-warning text-white font-weight-bold">
                            <i class="fas fa-save mr-1"></i>บันทึกการแก้ไข
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Exam History --}}
        @if($user->isStudent())
        <div class="card shadow mt-4">
            <div class="card-header">
                <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-history mr-2"></i>ประวัติการสอบ</h3>
            </div>
            <div class="card-body p-0">
                <a href="{{ route('admin.users.show', $user->id) }}" class="btn btn-info btn-sm font-weight-bold m-3">
                    <i class="fas fa-external-link-alt mr-1"></i>ดูประวัติการสอบทั้งหมด
                </a>
            </div>
        </div>
        @endif

@stop


@section('js')
<script>
    // Photo preview on file change
    var photoInput = document.getElementById('photo-input');
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(ev) {
                document.getElementById('photo-preview').src = ev.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    @if(session('success'))
    Swal.fire({
        icon: 'success',
        title: 'สำเร็จ!',
        text: {!! json_encode(session('success')) !!},
        confirmButtonText: 'ตกลง',
        confirmButtonColor: '#28a745',
        timer: 3000,
        timerProgressBar: true,
    });
    @endif

    @if(session('error'))
    Swal.fire({
        icon: 'error',
        title: 'เกิดข้อผิดพลาด!',
        text: {!! json_encode(session('error')) !!},
        confirmButtonText: 'ตกลง',
        confirmButtonColor: '#d33',
    });
    @endif
    // Delete photo button
    @if($user->photo)
    var btnDeletePhoto = document.getElementById('btn-delete-photo');
    if (btnDeletePhoto) {
        btnDeletePhoto.addEventListener('click', function() {
            Swal.fire({
                title: 'ลบรูปภาพ?',
                text: 'ต้องการลบรูปภาพของผู้ใช้งานคนนี้ใช่ไหม?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-photo-form').submit();
                }
            });
        });
    }
    @endif

    // Delete user button
    var btnDeleteUser = document.getElementById('btn-delete-user');
    if (btnDeleteUser) {
        btnDeleteUser.addEventListener('click', function() {
            var text = this.getAttribute('data-text') || 'ข้อมูลนี้จะถูกลบและไม่สามารถกู้คืนได้!';
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
                    document.getElementById('delete-user-form').submit();
                }
            });
        });
    }
</script>
@stop

