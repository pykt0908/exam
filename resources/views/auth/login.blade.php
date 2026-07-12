@extends('adminlte::master')

@section('adminlte_css')
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <style>
        html, body.login-page {
            height: 100%;
            overflow: hidden;
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
            /* Prevent iOS bounce/overscroll */
            overscroll-behavior: none;
            -webkit-overflow-scrolling: auto;
        }
        body.login-page {
            background: url('{{ asset('images/bg.jpg') }}') no-repeat center center fixed !important;
            background-size: cover !important;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        body.login-page::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(10, 25, 47, 0.55);
            z-index: 0;
        }
        .login-box {
            position: relative;
            z-index: 1;
            width: 430px;
            max-width: 92%;
            /* Allow internal scroll if content is taller than screen (small devices) */
            max-height: 100dvh;
            overflow-y: auto;
            overscroll-behavior: contain;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.92) !important;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 18px !important;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.35) !important;
            overflow: hidden;
        }
        .login-logo img {
            filter: drop-shadow(0px 4px 8px rgba(0,0,0,0.3));
            transition: transform 0.3s ease;
        }
        .login-logo img:hover {
            transform: scale(1.05);
        }
        .form-control {
            border-radius: 10px !important;
            height: 48px;
            border: 1px solid #ced4da;
            padding-left: 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
        }
        .input-group .form-control {
            border-top-right-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
            border-top-left-radius: 10px !important;
            border-bottom-left-radius: 10px !important;
        }
        .input-group-text {
            border-left: none;
            background-color: transparent !important;
        }
        .input-group-append .input-group-text {
            border: 1px solid #ced4da;
            border-left: none;
            border-top-left-radius: 0 !important;
            border-bottom-left-radius: 0 !important;
            border-top-right-radius: 10px !important;
            border-bottom-right-radius: 10px !important;
        }
        .btn-login {
            border-radius: 10px !important;
            height: 48px;
            font-weight: 600;
            font-size: 1.05rem;
            background-color: #007bff;
            border: none;
            box-shadow: 0 4px 10px rgba(0, 123, 255, 0.25);
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            background-color: #0069d9;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 123, 255, 0.4);
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .custom-control-label {
            cursor: pointer;
            user-select: none;
        }

        /* Mobile: shrink logo slightly so form fits without scrolling */
        @media (max-height: 700px) {
            .login-logo img { width: 60px !important; height: 60px !important; }
            .login-logo .font-weight-bold { font-size: 1rem !important; }
            .login-card-body { padding: 1rem !important; }
            .mb-4 { margin-bottom: 0.75rem !important; }
        }
    </style>

@stop

@section('classes_body', 'login-page')

@section('body')
    <div class="login-box">
        <!-- Glassmorphism Card -->
        <div class="card glass-card border-0 py-2">
            <div class="card-body login-card-body bg-transparent">
                <!-- Institution Logo -->
                <div class="login-logo text-center mb-4 mt-2">
                    <a href="{{ url('/') }}">
                        <img src="{{ asset('images/logo.png') }}" alt="Logo" class="img-fluid mb-2" style="width: 90px; height: 90px;"><br>
                        <span class="font-weight-bold text-dark" style="font-size: 1.25rem; letter-spacing: 0.5px;">วิทยาลัยเทคโนโลยีศรีราชา</span>
                    </a>
                </div>

                <p class="login-box-msg text-secondary text-sm mb-4">กรุณาเข้าสู่ระบบเพื่อเข้าใช้งานระบบสอบออนไลน์</p>

                <!-- Login Form -->
                <form action="{{ route('login') }}" method="post">
                    @csrf

                    {{-- Login Identifier field --}}
                    <div class="input-group mb-3">
                        <input type="text" name="login_identifier" class="form-control @error('login_identifier') is-invalid @enderror"
                               value="{{ old('login_identifier') }}" placeholder="รหัสประจำตัว หรือ รหัสนักศึกษา" required autofocus>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-user text-primary"></span>
                            </div>
                        </div>
                        @error('login_identifier')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    {{-- Password field --}}
                    <div class="input-group mb-3">
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                               placeholder="รหัสผ่าน" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-lock text-primary"></span>
                            </div>
                        </div>
                        @error('password')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    {{-- Remember me & Login button --}}
                    <div class="row align-items-center mt-4 mb-3">
                        <div class="col-7">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" name="remember" class="custom-control-input" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                <label class="custom-control-label text-secondary text-sm font-weight-medium" for="remember">จดจำฉันไว้ในระบบ</label>
                            </div>
                        </div>
                        <div class="col-5 text-right">
                            <!-- Empty but keeps spacing alignment if needed -->
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-login font-weight-bold">
                        <span class="fas fa-sign-in-alt mr-2"></span> เข้าสู่ระบบ
                    </button>
                </form>


            </div>
        </div>
    </div>
@stop

@section('adminlte_js')
<script>
    // Prevent page from jumping/scrolling when keyboard opens on mobile
    document.querySelectorAll('input').forEach(function(input) {
        input.addEventListener('focus', function() {
            // Small delay to let keyboard animation start, then lock position
            setTimeout(function() {
                window.scrollTo(0, 0);
                document.documentElement.scrollTop = 0;
                document.body.scrollTop = 0;
            }, 100);
        });
        input.addEventListener('blur', function() {
            window.scrollTo(0, 0);
            document.documentElement.scrollTop = 0;
            document.body.scrollTop = 0;
        });
    });

    // Prevent any touchmove scroll on body itself
    document.body.addEventListener('touchmove', function(e) {
        // Allow scroll only inside .login-box (in case content overflows on small screens)
        if (!e.target.closest('.login-box')) {
            e.preventDefault();
        }
    }, { passive: false });
</script>
@stop