@extends('adminlte::page')

@section('title', 'หน้าหลัก')

@section('content_header')
    <h1 class="text-dark font-weight-bold">หน้าหลัก</h1>
@stop

@section('content')
    <!-- Small boxes (Stat box) -->
    <div class="row">
        <div class="col-lg-3 col-6">
            <!-- small box -->
            <div class="small-box bg-info shadow">
                <div class="inner">
                    <h3>{{ $stats['subjects'] }}</h3>
                    <p>รายวิชาทั้งหมด</p>
                </div>
                <div class="icon">
                    <i class="fas fa-book"></i>
                </div>
                <a href="{{ route('admin.subjects.index') }}" class="small-box-footer">รายวิชา <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <!-- ./col -->
        <div class="col-lg-3 col-6">
            <!-- small box -->
            <div class="small-box bg-success shadow">
                <div class="inner">
                    <h3>{{ $stats['exams'] }}</h3>
                    <p>ข้อสอบทั้งหมด</p>
                </div>
                <div class="icon">
                    <i class="fas fa-file-signature"></i>
                </div>
                <a href="{{ route('admin.exams.index') }}" class="small-box-footer">ข้อสอบ <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <!-- ./col -->
        <div class="col-lg-3 col-6">
            <!-- small box -->
            <div class="small-box bg-warning shadow">
                <div class="inner text-white">
                    <h3>{{ $stats['users'] }}</h3>
                    <p>ผู้ใช้งานในระบบทั้งหมด</p>
                </div>
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
                <a href="{{ route('admin.users.index') }}" class="small-box-footer text-white">ผู้ใช้งาน <i class="fas fa-arrow-circle-right text-white"></i></a>
            </div>
        </div>
        <!-- ./col -->
        <div class="col-lg-3 col-6">
            <!-- small box -->
            <div class="small-box bg-danger shadow">
                <div class="inner">
                    <h3>{{ $stats['attempts'] }}</h3>
                    <p>ประวัติการสอบ (เสร็จสิ้น)</p>
                </div>
                <div class="icon">
                    <i class="fas fa-poll"></i>
                </div>
                <a href="{{ route('admin.reports.index') }}" class="small-box-footer">ดูรายงานผลคะแนน <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <!-- ./col -->
    </div>

    <!-- Recent exam attempts -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-history mr-2"></i>ประวัติการเข้าสอบล่าสุด
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>นักศึกษา</th>
                                    <th>รหัสนักศึกษา</th>
                                    <th>ข้อสอบ</th>
                                    <th>คะแนนที่ได้</th>
                                    <th>สถานะ</th>
                                    <th>วันที่/เวลาสอบ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentAttempts as $attempt)
                                    <tr>
                                        <td>{{ $attempt->user->name }}</td>
                                        <td><code>{{ $attempt->user->student_code }}</code></td>
                                        <td>{{ $attempt->exam->title }}</td>
                                        <td>
                                            <strong class="text-primary">{{ $attempt->score }}</strong> / {{ $attempt->exam->questions()->sum('score') }} คะแนน
                                        </td>
                                        <td>
                                            @if($attempt->is_passed)
                                                <span class="badge badge-success px-3 py-2"><i class="fas fa-check-circle mr-1"></i> ผ่านเกณฑ์</span>
                                            @else
                                                <span class="badge badge-danger px-3 py-2"><i class="fas fa-times-circle mr-1"></i> ไม่ผ่านเกณฑ์</span>
                                            @endif
                                        </td>
                                        <td>{{ $attempt->completed_at->format('d/m/Y H:i น.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">ยังไม่มีประวัติการเข้าสอบในระบบ</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
