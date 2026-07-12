@extends('adminlte::page')
@title($title ?? 'เพิ่มนักศึกษาใหม่')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="text-dark font-weight-bold">เพิ่มนักศึกษาใหม่</h1>
        <a href="{{ route('admin.users.index', ['role' => 'student']) }}" class="btn btn-secondary font-weight-bold shadow-sm">
            <i class="fas fa-arrow-left mr-2"></i>กลับรายชื่อนักศึกษา
        </a>
    </div>
@stop

@section('content')
<div class="card shadow mt-3">
            <div class="card-header">
                <h3 class="card-title font-weight-bold text-dark">
                    <i class="fas fa-user-graduate mr-2"></i>ข้อมูลนักศึกษา
                </h3>
            </div>
            <form action="{{ route('admin.users.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="role" value="student">

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
                        {{-- Photo Upload --}}
                        <div class="col-md-4 text-center mb-4">
                            <div id="photo-preview-wrapper" class="mb-3">
                                <img id="photo-preview"
                                     src="{{ asset('images/default-avatar.png') }}"
                                     class="rounded-circle shadow"
                                     style="width:160px;height:160px;object-fit:cover;border:3px solid #dee2e6;">
                            </div>
                            <label class="btn btn-outline-primary btn-sm font-weight-bold">
                                <i class="fas fa-camera mr-1"></i> เลือกรูปภาพ
                                <input type="file" name="photo" id="photo-input" accept="image/*" class="d-none">
                            </label>
                            <p class="text-muted text-xs mt-2">JPG, PNG, WEBP ไม่เกิน 2MB</p>
                        </div>

                        {{-- Form Fields --}}
                        <div class="col-md-8">
                            <div class="form-group">
                                <label class="font-weight-bold">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" placeholder="เช่น นายสมชาย ดีงาม" required>
                                @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>

                            <div class="form-group">
                                <label class="font-weight-bold">รหัสนักศึกษา <span class="text-danger">*</span></label>
                                <input type="text" name="student_code" class="form-control @error('student_code') is-invalid @enderror"
                                       value="{{ old('student_code') }}" placeholder="เช่น 66309010001" maxlength="13" required>
                                <small class="text-muted">ใช้เป็นชื่อผู้ใช้เข้าสู่ระบบ (สูงสุด 13 หลัก)</small>
                                @error('student_code')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>

                            <div class="form-group">
                                <label class="font-weight-bold">เลขบัตรประจำตัวประชาชน <span class="text-danger">*</span></label>
                                <input type="text" name="citizen_id" class="form-control @error('citizen_id') is-invalid @enderror"
                                       value="{{ old('citizen_id') }}" placeholder="13 หลัก" maxlength="13" minlength="13" required>
                                <small class="text-muted">ใช้เป็นรหัสผ่านเริ่มต้น</small>
                                @error('citizen_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>

                            <div class="form-group">
                                <label class="font-weight-bold">ระดับชั้น / ห้องเรียน <span class="text-danger">*</span></label>
                                <select name="classroom_id" class="form-control @error('classroom_id') is-invalid @enderror" required>
                                    <option value="" disabled selected>-- เลือกห้องเรียน --</option>
                                    @foreach($classrooms as $room)
                                        <option value="{{ $room->id }}" {{ old('classroom_id') == $room->id ? 'selected' : '' }}>
                                            {{ $room->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('classroom_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-end">
                    <a href="{{ route('admin.users.index', ['role' => 'student']) }}" class="btn btn-secondary font-weight-bold mr-2">
                        <i class="fas fa-times mr-1"></i>ยกเลิก
                    </a>
                    <button type="submit" class="btn btn-primary font-weight-bold">
                        <i class="fas fa-save mr-1"></i>บันทึกข้อมูล
                    </button>
                </div>
            </form>
</div>
@stop

@section('js')
<script>
    document.getElementById('photo-input').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(ev) {
            document.getElementById('photo-preview').src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });

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
</script>
@stop
