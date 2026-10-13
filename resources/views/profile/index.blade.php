@extends('adminlte::page')

@section('title', 'ข้อมูลส่วนตัว')
@section('plugins.Sweetalert2', true)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="text-dark font-weight-bold" style="font-size: 1.4rem;">ข้อมูลส่วนตัว</h1>
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">หน้าหลัก</a></li>
            <li class="breadcrumb-item active">ข้อมูลส่วนตัว</li>
        </ol>
    </div>
@stop

@section('content')

    @if(!$user->hasConfiguredEmail() && $user->isStaff())
        <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
            <strong>ยังไม่ได้ระบุอีเมลสำหรับรับแจ้งเตือน:</strong> กรุณากรอกอีเมลด้านล่างเพื่อรับการแจ้งเตือนการอนุมัติข้อสอบ
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="row">
        <!-- ฝั่งซ้าย: ข้อมูลผู้ใช้งาน -->
        <div class="col-lg-4 col-md-5">
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-body box-profile text-center">
                    <div class="mb-3 position-relative d-inline-block">
                        <img class="profile-user-img img-fluid img-circle shadow-sm"
                             src="{{ $user->photo_url }}"
                             alt="รูปภาพประจำตัว"
                             style="width: 100px; height: 100px; object-fit: cover; border: 3px solid #007bff;">
                    </div>

                    <h4 class="font-weight-bold text-dark mb-1">{{ $user->name }}</h4>
                    <p class="text-muted mb-3">
                        {{ $user->isAdmin() ? 'ผู้ดูแลระบบ' : ($user->isTeacher() ? 'ครูผู้สอน' : 'นักเรียน') }}
                    </p>

                    <ul class="list-group list-group-unbordered mb-0 text-left">
                        <li class="list-group-item">
                            <b>{{ $user->isStudent() ? 'รหัสนักศึกษา' : 'รหัสผู้ใช้' }}</b>
                            <span class="float-right font-weight-bold">{{ $user->student_code ?: ($user->teacher_code ?: $user->username) }}</span>
                        </li>
                        @if($user->isStudent())
                            <li class="list-group-item">
                                <b>ห้องเรียน / ระดับชั้น</b>
                                <span class="float-right text-dark font-weight-bold">{{ $user->classroom?->name ?? 'ไม่ระบุ' }}</span>
                            </li>
                            <li class="list-group-item">
                                <b>ภาค</b>
                                <span class="float-right text-dark font-weight-bold">{{ $user->classroom ? ($user->classroom->shift_text === 'บ่าย' ? 'ภาคบ่าย' : 'ภาคเช้า') : '-' }}</span>
                            </li>
                        @else
                            <li class="list-group-item">
                                <b>แผนกวิชา</b>
                                <span class="float-right text-muted">{{ $user->department?->name ?? 'ไม่ระบุ' }}</span>
                            </li>
                            <li class="list-group-item">
                                <b>ตำแหน่ง</b>
                                <span class="float-right text-right font-weight-bold text-dark" style="max-width: 65%;">
                                    @php
                                        $rolesMap = [
                                            'academic_deputy' => 'รองฝ่ายวิชาการ',
                                            'evaluation_head' => 'หัวหน้างานวัดผล',
                                            'department_head' => 'หัวหน้าแผนก',
                                        ];
                                        $positionLabels = [];
                                        if (!empty($user->academic_roles)) {
                                            foreach ($user->academic_roles as $roleKey) {
                                                if (isset($rolesMap[$roleKey])) {
                                                    $positionLabels[] = $rolesMap[$roleKey];
                                                }
                                            }
                                        }
                                    @endphp
                                    {{ !empty($positionLabels) ? implode(', ', $positionLabels) : '-' }}
                                </span>
                            </li>
                        @endif
                        <li class="list-group-item">
                            <b>สถานะ MisSchool</b>
                            <span class="float-right {{ $user->mis_synced_at ? 'text-success font-weight-bold' : 'text-muted' }}">
                                {{ $user->mis_synced_at ? 'เชื่อมต่อแล้ว' : 'บัญชีท้องถิ่น' }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ฝั่งขวา: แบบฟอร์มแก้ไขข้อมูล -->
        <div class="col-lg-8 col-md-7">
            <!-- แบบฟอร์มหลัก -->
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-header py-2">
                    <h5 class="card-title font-weight-bold mb-0 text-dark">ข้อมูลส่วนตัวและการแจ้งเตือน</h5>
                </div>
                <form id="profile-form" action="{{ route('profile.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="form-group">
                            <label for="name" class="font-weight-bold">
                                ชื่อ - นามสกุล <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   name="name" 
                                   id="name" 
                                   class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $user->name) }}" 
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="notification_email" class="font-weight-bold">
                                อีเมลสำหรับรับการแจ้งเตือน
                            </label>
                            <input type="email" 
                                   name="notification_email" 
                                   id="notification_email" 
                                   class="form-control @error('notification_email') is-invalid @enderror" 
                                   placeholder="เช่น teacher@gmail.com หรือ @technosriracha.ac.th"
                                   value="{{ old('notification_email', $user->notification_email ?: (!str_ends_with(strtolower($user->email ?? ''), '@exam.local') ? $user->email : '')) }}">
                            @error('notification_email')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <hr class="my-3">

                        <!-- การเปิด/ปิดรับการแจ้งเตือน -->
                        <div class="form-group mb-0">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" 
                                       class="custom-control-input" 
                                       id="notify_email_approval" 
                                       name="notify_email_approval" 
                                       value="1" 
                                       {{ old('notify_email_approval', $user->notify_email_approval ?? true) ? 'checked' : '' }}>
                                <label class="custom-control-label font-weight-bold" for="notify_email_approval">
                                    เปิดรับอีเมลแจ้งเตือนการอนุมัติข้อสอบ
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light text-right">
                        <button type="submit" class="btn btn-primary px-4 shadow-sm">
                            บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>

            <!-- กล่องทดสอบส่งอีเมล -->
            <div class="card shadow-sm">
                <div class="card-header py-2 bg-light">
                    <h6 class="font-weight-bold mb-0 text-muted">ทดสอบส่งอีเมล</h6>
                </div>
                <form id="test-email-form" action="{{ route('profile.test-email') }}" method="POST">
                    @csrf
                    <div class="card-body py-3">
                        <div class="form-group mb-0">
                            <div class="input-group">
                                <input type="email" 
                                       name="test_email" 
                                       id="test_email" 
                                       class="form-control" 
                                       placeholder="กรอกอีเมลเพื่อทดสอบส่ง..." 
                                       value="{{ $user->getEffectiveNotificationEmail() ?: '' }}" 
                                       required>
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-outline-primary px-3">
                                        ส่งทดสอบ
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
$(function() {
    // SweetAlert ยืนยันก่อนบันทึกข้อมูลส่วนตัว
    $('#profile-form').on('submit', function(e) {
        if ($(this).data('confirmed')) {
            return true;
        }

        if (!this.checkValidity()) {
            return true;
        }

        e.preventDefault();
        const form = this;

        Swal.fire({
            title: 'ยืนยันการบันทึกข้อมูล?',
            text: 'คุณต้องการบันทึกการเปลี่ยนแปลงข้อมูลใช่หรือไม่',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#007bff',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'บันทึกข้อมูล',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'กำลังบันทึกข้อมูล...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                $(form).data('confirmed', true).submit();
            }
        });
    });

    // SweetAlert แจ้งสถานะขณะส่งอีเมลทดสอบ
    $('#test-email-form').on('submit', function(e) {
        if (!this.checkValidity()) {
            return true;
        }
        Swal.fire({
            title: 'กำลังส่งอีเมลทดสอบ...',
            text: 'กรุณารอสักครู่ ระบบกำลังทดสอบส่งอีเมลไปยังปลายทาง',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    });

    @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ!',
            text: {!! json_encode(session('success')) !!},
            confirmButtonText: 'ตกลง',
            confirmButtonColor: '#007bff',
            timer: 3000,
            timerProgressBar: true
        });
    @endif

    @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด!',
            text: {!! json_encode(session('error')) !!},
            confirmButtonText: 'ตกลง',
            confirmButtonColor: '#dc3545'
        });
    @endif

    @if($errors->any())
        Swal.fire({
            icon: 'error',
            title: 'ข้อมูลไม่ถูกต้อง',
            html: {!! json_encode(implode("<br>", $errors->all())) !!},
            confirmButtonText: 'ตกลง',
            confirmButtonColor: '#dc3545'
        });
    @endif
});
</script>
@stop
