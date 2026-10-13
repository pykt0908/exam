@extends('adminlte::page')

@section('title', 'ข้อมูลส่วนตัวและการแจ้งเตือน')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="text-dark font-weight-bold" style="font-size: 1.5rem;">
            <i class="fas fa-user-circle text-primary mr-2"></i>ข้อมูลส่วนตัวและการตั้งค่าการแจ้งเตือน
        </h1>
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">หน้าหลัก</a></li>
            <li class="breadcrumb-item active">ข้อมูลส่วนตัว</li>
        </ol>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <ul class="mb-0 pl-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(!$user->hasConfiguredEmail() && $user->isStaff())
        <div class="alert alert-warning alert-dismissible fade show shadow-sm border-warning" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-bell fa-2x mr-3 text-warning"></i>
                <div>
                    <h5 class="alert-heading font-weight-bold mb-1">แจ้งเตือน: คุณยังไม่ได้ระบุอีเมลสำหรับรับการแจ้งเตือน!</h5>
                    <p class="mb-0">
                        กรุณาระบุ <strong>"อีเมลสำหรับรับการแจ้งเตือน"</strong> ด้านล่างนี้ เพื่อให้ระบบสามารถส่งเมลแจ้งเมื่อมีข้อสอบรออนุมัติ หรือแจ้งเตือนเมื่อข้อสอบของคุณถูกส่งกลับให้แก้ไข
                    </p>
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <!-- ฝั่งซ้าย: ข้อมูลผู้ใช้งานเบื้องต้น -->
        <div class="col-lg-4 col-md-5">
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-body box-profile text-center">
                    <div class="mb-3 position-relative d-inline-block">
                        <img class="profile-user-img img-fluid img-circle shadow-sm"
                             src="{{ $user->photo_url }}"
                             alt="รูปภาพประจำตัว"
                             style="width: 110px; height: 110px; object-fit: cover; border: 3px solid #007bff;">
                    </div>

                    <h3 class="profile-username font-weight-bold text-dark mb-1">{{ $user->name }}</h3>
                    <p class="text-muted mb-2">
                        @if($user->isAdmin())
                            <span class="badge badge-danger px-2 py-1"><i class="fas fa-user-shield mr-1"></i>ผู้ดูแลระบบ</span>
                        @elseif($user->isTeacher())
                            <span class="badge badge-primary px-2 py-1"><i class="fas fa-chalkboard-teacher mr-1"></i>ครูผู้สอน</span>
                        @else
                            <span class="badge badge-secondary px-2 py-1"><i class="fas fa-user-graduate mr-1"></i>นักเรียน</span>
                        @endif
                    </p>

                    <ul class="list-group list-group-unbordered mb-3 text-left">
                        <li class="list-group-item">
                            <b><i class="fas fa-id-badge text-muted mr-1"></i>รหัสผู้ใช้ / ครู</b>
                            <span class="float-right font-weight-bold">{{ $user->teacher_code ?: $user->username }}</span>
                        </li>
                        <li class="list-group-item">
                            <b><i class="fas fa-building text-muted mr-1"></i>แผนก / หมวดวิชา</b>
                            <span class="float-right text-muted">{{ $user->department?->name ?? 'ไม่ระบุ' }}</span>
                        </li>
                        <li class="list-group-item">
                            <b><i class="fas fa-user-tag text-muted mr-1"></i>บทบาทวิชาการ</b>
                            <span class="float-right text-right" style="max-width: 60%;">
                                @if(!empty($user->academic_roles))
                                    @foreach($user->academic_roles as $roleKey)
                                        @if($roleKey === 'academic_deputy')
                                            <span class="badge badge-info px-1 mb-1">รองฝ่ายวิชาการ</span>
                                        @elseif($roleKey === 'evaluation_head')
                                            <span class="badge badge-primary px-1 mb-1">หัวหน้างานวัดผล</span>
                                        @elseif($roleKey === 'department_head')
                                            <span class="badge badge-warning text-dark px-1 mb-1">หัวหน้าแผนก</span>
                                        @endif
                                    @endforeach
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </span>
                        </li>
                        <li class="list-group-item">
                            <b><i class="fas fa-sync text-muted mr-1"></i>MisSchool</b>
                            <span class="float-right">
                                @if($user->mis_synced_at)
                                    <span class="badge badge-success" title="{{ $user->mis_synced_at }}"><i class="fas fa-check mr-1"></i>เชื่อมต่อแล้ว</span>
                                @else
                                    <span class="badge badge-light text-muted">บัญชีท้องถิ่น</span>
                                @endif
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- กล่องสถานะการแจ้งเตือนทางอีเมล -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title font-weight-bold mb-0">
                        <i class="fas fa-envelope-open-text text-info mr-2"></i>สถานะการรับแจ้งเตือน
                    </h5>
                </div>
                <div class="card-body">
                    @if($user->hasConfiguredEmail())
                        <div class="text-success mb-2 font-weight-bold">
                            <i class="fas fa-check-circle fa-lg mr-1"></i> พร้อมรับอีเมลแจ้งเตือน
                        </div>
                        <p class="text-muted small mb-0">
                            อีเมลที่ระบบจะส่งไป: <br>
                            <strong class="text-dark font-weight-bold" style="font-size: 0.95rem;">
                                {{ $user->getEffectiveNotificationEmail() }}
                            </strong>
                        </p>
                    @else
                        <div class="text-warning mb-2 font-weight-bold">
                            <i class="fas fa-exclamation-circle fa-lg mr-1"></i> ยังไม่มีอีเมลแจ้งเตือน
                        </div>
                        <p class="text-muted small mb-0">
                            ระบบยังไม่สามารถส่งเมลหาท่านได้ กรุณากรอกช่องอีเมลแจ้งเตือนด้านขวาแล้วกดบันทึก
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <!-- ฝั่งขวา: แบบฟอร์มแก้ไขข้อมูลและการแจ้งเตือน -->
        <div class="col-lg-8 col-md-7">
            <!-- แบบฟอร์มหลัก -->
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title font-weight-bold mb-0">
                        <i class="fas fa-sliders-h text-primary mr-2"></i>ตั้งค่าข้อมูลส่วนตัวและการแจ้งเตือน
                    </h5>
                </div>
                <form action="{{ route('profile.update') }}" method="POST">
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
                            <label for="notification_email" class="font-weight-bold text-primary">
                                <i class="fas fa-envelope mr-1"></i>อีเมลสำหรับรับการแจ้งเตือน (Notification Email)
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-primary text-white"><i class="fas fa-at"></i></span>
                                </div>
                                <input type="email" 
                                       name="notification_email" 
                                       id="notification_email" 
                                       class="form-control @error('notification_email') is-invalid @enderror" 
                                       placeholder="เช่น teacher@gmail.com หรือ teacher@technosriracha.ac.th"
                                       value="{{ old('notification_email', $user->notification_email ?: (!str_ends_with(strtolower($user->email ?? ''), '@exam.local') ? $user->email : '')) }}">
                            </div>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle text-info mr-1"></i>
                                ใส่อีเมลส่วนตัว (Gmail) หรืออีเมลวิทยาลัยที่คุณเปิดเช็กเป็นประจำ ระบบจะส่งการแจ้งเตือนเมื่อมีการส่งข้อสอบให้อนุมัติ หรือส่งกลับแก้ไขมาที่อีเมลนี้
                            </small>
                            @error('notification_email')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone" class="font-weight-bold">
                                        <i class="fas fa-phone-alt text-muted mr-1"></i>เบอร์โทรศัพท์ติดต่อ
                                    </label>
                                    <input type="text" 
                                           name="phone" 
                                           id="phone" 
                                           class="form-control @error('phone') is-invalid @enderror" 
                                           placeholder="เช่น 0812345678"
                                           value="{{ old('phone', $user->phone) }}">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="font-weight-bold">
                                        <i class="fas fa-user-lock text-muted mr-1"></i>อีเมลบัญชีผู้ใช้ (Account Email)
                                    </label>
                                    <input type="email" 
                                           name="email" 
                                           id="email" 
                                           class="form-control @error('email') is-invalid @enderror" 
                                           value="{{ old('email', $user->email) }}">
                                    <small class="form-text text-muted">ใช้อ้างอิงในระบบสอบ (หากล็อกอินผ่าน MisSchool จะใช้ username/รหัสครู)</small>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
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
                                    เปิดรับอีเมลแจ้งเตือนทุกขั้นตอนของการอนุมัติข้อสอบ
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1 pl-4">
                                • เมื่อครูกดส่งข้อสอบ ระบบจะส่งเมลแจ้งเตือนผู้อนุมัติ<br>
                                • เมื่อผู้อนุมัติแต่ละระดับเห็นชอบ จะส่งเมลแจ้งเตือนไปยังระดับถัดไปและแจ้งครูผู้สอน<br>
                                • เมื่อข้อสอบถูกส่งกลับแก้ไข จะส่งเมลแจ้งเตือนครูผู้สอนพร้อมเหตุผลทันที
                            </small>
                        </div>
                    </div>
                    <div class="card-footer bg-light text-right">
                        <button type="submit" class="btn btn-primary px-4 shadow-sm">
                            <i class="fas fa-save mr-1"></i> บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>

            <!-- กล่องทดสอบส่งอีเมล (Test Email) -->
            <div class="card card-outline card-info shadow-sm">
                <div class="card-header">
                    <h5 class="card-title font-weight-bold text-info mb-0">
                        <i class="fas fa-paper-plane mr-2"></i>ทดสอบการส่งอีเมลแจ้งเตือน
                    </h5>
                </div>
                <form action="{{ route('profile.test-email') }}" method="POST">
                    @csrf
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            คุณสามารถทดสอบส่งอีเมลจากระบบสอบจำลองไปยังกล่องข้อความของคุณ เพื่อตรวจสอบว่าระบบสามารถส่งเมลได้สำเร็จและตรวจดูรูปแบบของอีเมลที่มีโลโก้วิทยาลัยเทคโนโลยีศรีราชาได้ทันที
                        </p>
                        <div class="form-group mb-0">
                            <label for="test_email" class="font-weight-bold">อีเมลปลายทางที่จะทดสอบส่ง:</label>
                            <div class="input-group">
                                <input type="email" 
                                       name="test_email" 
                                       id="test_email" 
                                       class="form-control" 
                                       placeholder="ใส่อีเมลที่ต้องการทดสอบรับข้อความ" 
                                       value="{{ $user->getEffectiveNotificationEmail() ?: '' }}" 
                                       required>
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-info px-4">
                                        <i class="fas fa-paper-plane mr-1"></i> ส่งอีเมลทดสอบ
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
