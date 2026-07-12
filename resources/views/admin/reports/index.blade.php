@extends('adminlte::page')

@section('title', 'รายงานผลคะแนน')

@section('plugins.Chartjs', true)
@section('plugins.Datatables', true)

@section('content_header')
    <h1 class="text-dark font-weight-bold">รายงานสรุปผลคะแนนและสถิติ</h1>
@stop

@section('content')
    @if(auth()->user()->isAdmin())
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h3 class="card-title font-weight-bold text-dark mb-0"><i class="fas fa-filter mr-2"></i>ตัวกรองผลคะแนนและสถิติ</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.reports.index') }}" method="GET" class="form-row">
                    <input type="hidden" name="search" value="1">
                    <div class="form-group col-md-4 mb-2 mb-md-0">
                        <select name="exam_id" class="form-control">
                            <option value="">-- เลือกชุดข้อสอบทั้งหมด --</option>
                            @foreach($exams as $ex)
                                <option value="{{ $ex->id }}" {{ request('exam_id') == $ex->id ? 'selected' : '' }}>
                                    {{ $ex->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-5 mb-2 mb-md-0">
                        <input type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="ค้นหาชื่อ หรือ รหัสนักศึกษา...">
                    </div>
                    <div class="form-group col-md-3 mb-0">
                        <button type="submit" class="btn btn-primary btn-block font-weight-bold shadow-sm">
                            <i class="fas fa-search mr-2"></i>ค้นหาข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if(auth()->user()->isAdmin() && !$searchPerformed)
        <div class="card shadow-sm py-5">
            <div class="card-body text-center">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5 class="text-muted font-weight-bold">กรุณาเลือกตัวกรองหรือกรอกข้อมูลค้นหาด้านบนเพื่อแสดงรายงานสรุปผลคะแนนและสถิติ</h5>
            </div>
        </div>
    @else
        <div class="row">
        <!-- Statistics Cards -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-table mr-2"></i>สรุปผลสถิติรายวิชาสอบ</h3>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="statsTable" class="table table-bordered table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>วิชาสอบ</th>
                                    <th class="text-center">จำนวนเข้าสอบ</th>
                                    <th class="text-center">สอบผ่าน (คน)</th>
                                    <th class="text-center">อัตราผ่าน (%)</th>
                                    <th class="text-center">คะแนนเฉลี่ย</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($examStats as $exam)
                                    <tr>
                                        <td>
                                            <span class="badge badge-secondary mb-1">{{ $exam->subject->code }}</span><br>
                                            <strong>{{ $exam->title }}</strong>
                                        </td>
                                        <td class="text-center font-weight-bold text-dark">{{ $exam->total_attempts }}</td>
                                        <td class="text-center font-weight-bold text-success">{{ $exam->passed_attempts }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ $exam->passed_rate >= 60 ? 'badge-success' : 'badge-warning' }} px-2 py-1">
                                                {{ $exam->passed_rate }}%
                                            </span>
                                        </td>
                                        <td class="text-center font-weight-bold text-primary">{{ $exam->average_score }} คะแนน</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">ไม่มีข้อมูลสถิติรายวิชา</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart.js Graph -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-chart-pie mr-2"></i>กราฟเปรียบเทียบอัตราการสอบผ่าน (%)</h3>
                </div>
                <div class="card-body">
                    <div style="position: relative; height:250px; width:100%">
                        <canvas id="passingRateChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Scores Data Table -->
    <div class="card shadow-sm mt-4">
        <div class="card-header">
            <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-list-alt mr-2"></i>ผลการสอบอย่างละเอียดของนักศึกษาทุกคน</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="reportsTable" class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th>รหัสนักศึกษา</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>รายวิชาสอบ</th>
                            <th class="text-center">คะแนนดิบ</th>
                            <th class="text-center">ร้อยละที่ได้</th>
                            <th class="text-center">ผลสอบ</th>
                            <th class="text-center">วันที่สอบเสร็จ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reports as $rep)
                            @php
                                $totalScore = $rep->exam->questions()->sum('score');
                                $percentage = $totalScore > 0 ? round(($rep->score / $totalScore) * 100, 2) : 0;
                            @endphp
                            <tr>
                                <td><code>{{ $rep->user->student_code }}</code></td>
                                <td>{{ $rep->user->name }}</td>
                                <td>
                                    <span class="badge badge-secondary mr-1">{{ $rep->exam->subject->code }}</span>
                                    {{ $rep->exam->title }}
                                </td>
                                <td class="text-center font-weight-bold text-primary">
                                    {{ $rep->score }} / {{ $totalScore }}
                                </td>
                                <td class="text-center font-weight-bold">{{ $percentage }}%</td>
                                <td class="text-center">
                                    @if($rep->is_passed)
                                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> ผ่านเกณฑ์</span>
                                    @else
                                        <span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i> ไม่ผ่าน</span>
                                    @endif
                                </td>
                                <td class="text-center text-sm">{{ $rep->completed_at->format('d/m/Y H:i') }} น.</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
@stop

@section('js')
    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('#reportsTable').DataTable({
                "pageLength": -1,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "ทั้งหมด"]],
                "responsive": true, 
                "lengthChange": true, 
                "autoWidth": false,
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Thai.json"
                }
            });

            $('#statsTable').DataTable({
                "pageLength": -1,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "ทั้งหมด"]],
                "responsive": true, 
                "lengthChange": true, 
                "autoWidth": false,
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Thai.json"
                }
            });

            // Chartjs Setup
            var ctx = document.getElementById('passingRateChart').getContext('2d');
            var exams = {!! json_encode($examStats->pluck('title')->toArray()) !!};
            var rates = {!! json_encode($examStats->pluck('passed_rate')->toArray()) !!};

            // Limit label length on chart
            var truncatedExams = exams.map(function(label) {
                return label.length > 25 ? label.substring(0, 25) + '...' : label;
            });

            var chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: truncatedExams,
                    datasets: [{
                        label: 'อัตราสอบผ่าน (%)',
                        data: rates,
                        backgroundColor: 'rgba(40, 167, 69, 0.6)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: true,
                                max: 100
                            }
                        }]
                    }
                }
            });
        });
    </script>
@stop
