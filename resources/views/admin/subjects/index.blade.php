@extends('adminlte::page')

@section('title', 'จัดการรายวิชา')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="text-dark font-weight-bold">รายวิชา</h1>
        @if(auth()->user()->isStaff())
        <button type="button" class="btn btn-primary font-weight-bold shadow-sm" data-toggle="modal" data-target="#addSubjectModal">
            <i class="fas fa-plus mr-2"></i>เพิ่มรายวิชาใหม่
        </button>
        @endif
    </div>
@stop

@section('content')
    @if(auth()->user()->isStaff())
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-light">
                <h3 class="card-title font-weight-bold text-dark mb-0"><i class="fas fa-filter mr-2"></i>ตัวกรองและค้นหา</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.subjects.index') }}" method="GET" class="form-row">
                    <input type="hidden" name="search" value="1">
                    <div class="form-group col-md-9 mb-2 mb-md-0">
                        <input type="text" name="q" class="form-control w-100" value="{{ request('q') }}" placeholder="ค้นหารหัสวิชา หรือ ชื่อรายวิชา...">
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

    <div class="card shadow-sm mt-3">
        <div class="card-body p-3">
            @if(auth()->user()->isAdmin() && !$searchPerformed)
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted font-weight-bold">กรุณากรอกข้อมูลค้นหาด้านบนเพื่อเริ่มต้นแสดงข้อมูลรายวิชา</h5>
                </div>
            @else
                <div class="table-responsive">
                    <table id="subjects-table" class="table table-bordered table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 10%">#</th>
                                <th style="width: 25%">รหัสวิชา</th>
                                <th style="width: 45%">ชื่อรายวิชา</th>
                                <th style="width: 1%; white-space: nowrap;" class="text-center">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($subjects as $index => $subject)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $subject->code }}</td>
                                    <td>{{ $subject->name }}</td>
                                    <td class="text-center" style="white-space: nowrap;">
                                        <a href="{{ route('admin.subjects.students.index', $subject->id) }}" class="btn btn-sm btn-info font-weight-bold shadow-xs mr-1">
                                            <i class="fas fa-user-graduate mr-1"></i> นักศึกษา ({{ $subject->students_count }})
                                        </a>
                                        @if(auth()->user()->isAdmin() || $subject->teachers->contains(auth()->id()))
                                        <button type="button" class="btn btn-sm btn-warning font-weight-bold text-white shadow-xs mr-1" 
                                                data-toggle="modal" data-target="#editSubjectModal{{ $subject->id }}">
                                            <i class="fas fa-edit mr-1"></i> แก้ไข
                                        </button>
                                        @endif
                                        @if(auth()->user()->isAdmin())
                                        <form action="{{ route('admin.subjects.destroy', $subject->id) }}" method="post" class="d-inline confirm-delete"
                                              data-text="คุณแน่ใจหรือไม่ที่จะลบรายวิชานี้? ข้อสอบทั้งหมดในวิชานี้จะถูกลบไปด้วย!">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="btn btn-sm btn-danger font-weight-bold shadow-xs">
                                                <i class="fas fa-trash-alt mr-1"></i> ลบ
                                            </button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
        
                                @if(auth()->user()->isAdmin() || $subject->teachers->contains(auth()->id()))
                                <!-- Edit Subject Modal -->
                                <div class="modal fade" id="editSubjectModal{{ $subject->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header bg-warning">
                                                <h5 class="modal-title text-white font-weight-bold"><i class="fas fa-edit mr-2"></i>แก้ไขรายวิชา</h5>
                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <form action="{{ route('admin.subjects.update', $subject->id) }}" method="post">
                                                @csrf
                                                @method('put')
                                                <div class="modal-body">
                                                    <div class="form-group">
                                                        <label for="code" class="font-weight-bold">รหัสวิชา</label>
                                                        <input type="text" name="code" class="form-control" value="{{ old('code', $subject->code) }}" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="name" class="font-weight-bold">ชื่อรายวิชา</label>
                                                        <input type="text" name="name" class="form-control" value="{{ old('name', $subject->name) }}" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">ยกเลิก</button>
                                                    <button type="submit" class="btn btn-warning text-white font-weight-bold">บันทึกการแก้ไข</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">ไม่มีข้อมูลรายวิชาในระบบ</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if(auth()->user()->isStaff())
    <!-- Add Subject Modal -->
    <div class="modal fade" id="addSubjectModal" tabindex="-1" role="dialog" aria-labelledby="addSubjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white font-weight-bold" id="addSubjectModalLabel"><i class="fas fa-plus mr-2"></i>เพิ่มรายวิชาใหม่</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.subjects.store') }}" method="post">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="code" class="font-weight-bold">รหัสวิชา</label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" 
                                   value="{{ old('code') }}" placeholder="เช่น BC-301" required>
                            @error('code')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="name" class="font-weight-bold">ชื่อรายวิชา</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name') }}" placeholder="เช่น การพัฒนาเว็บแอปพลิเคชัน" required>
                            @error('name')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary font-weight-bold">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
@stop

@section('js')
    <script>
        $(document).ready(function() {
            $('#subjects-table').DataTable({
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
        });
    </script>
@stop
