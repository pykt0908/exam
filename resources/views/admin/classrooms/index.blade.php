@extends('adminlte::page')

@section('title', 'จัดการข้อมูลห้องเรียน / ระดับชั้น')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="text-dark font-weight-bold">ห้องเรียน / ระดับชั้น</h1>
        <button type="button" class="btn btn-primary font-weight-bold shadow-sm" data-toggle="modal" data-target="#addClassroomModal">
            <i class="fas fa-plus mr-2"></i>เพิ่มห้องเรียนใหม่
        </button>
    </div>
@stop

@section('content')
    <div class="card shadow-sm mt-3">
        <div class="card-header bg-light">
            <h3 class="card-title font-weight-bold text-dark mb-0"><i class="fas fa-filter mr-2"></i>ตัวกรองและค้นหาห้องเรียน</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.classrooms.index') }}" method="GET" class="form-row align-items-center">
                <input type="hidden" name="search" value="1">
                <div class="form-group col-md-2 mb-2 mb-md-0">
                    <select name="level" class="form-control" onchange="this.form.submit()">
                        <option value="">-- ทุกระดับชั้น --</option>
                        <option value="ปวช." {{ request('level') === 'ปวช.' ? 'selected' : '' }}>ปวช.</option>
                        <option value="ปวส." {{ request('level') === 'ปวส.' ? 'selected' : '' }}>ปวส.</option>
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2 mb-md-0">
                    <select name="year" class="form-control" onchange="this.form.submit()">
                        <option value="">-- ทุกชั้นปี --</option>
                        <option value="1" {{ request('year') == '1' ? 'selected' : '' }}>ปี 1</option>
                        <option value="2" {{ request('year') == '2' ? 'selected' : '' }}>ปี 2</option>
                        <option value="3" {{ request('year') == '3' ? 'selected' : '' }}>ปี 3</option>
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2 mb-md-0">
                    <select name="shift" class="form-control" onchange="this.form.submit()">
                        <option value="">-- ทุกภาค --</option>
                        <option value="เช้า" {{ request('shift') === 'เช้า' ? 'selected' : '' }}>ภาคเช้า</option>
                        <option value="บ่าย" {{ request('shift') === 'บ่าย' ? 'selected' : '' }}>ภาคบ่าย</option>
                    </select>
                </div>
                <div class="form-group col-md-4 mb-2 mb-md-0">
                    <input type="text" name="q" class="form-control w-100" value="{{ request('q') }}" placeholder="ค้นหาชื่อห้องเรียน หรือระดับชั้น...">
                </div>
                <div class="form-group col-md-2 mb-0 d-flex">
                    <button type="submit" class="btn btn-primary btn-block font-weight-bold shadow-sm">
                        <i class="fas fa-search mr-1"></i>ค้นหา
                    </button>
                    @if(request('shift') || request('level') || request('year') || request('q'))
                        <a href="{{ route('admin.classrooms.index') }}" class="btn btn-outline-secondary ml-2 font-weight-bold" title="ล้างการค้นหา">
                            <i class="fas fa-times"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mt-3">
        <div class="card-header">
            <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-list mr-2"></i>ห้องเรียนทั้งหมดในระบบ ({{ $classrooms->count() }} ห้อง)</h3>
        </div>
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="classrooms-table" class="table table-bordered table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 6%">#</th>
                            <th>ชื่อห้องเรียน / ระดับชั้น</th>
                            <th style="width: 12%" class="text-center">ภาค</th>
                            <th style="width: 14%" class="text-center">จำนวนนักเรียน</th>
                            <th style="width: 24%" class="text-center">สถานะสิทธิ์การสอบ</th>
                            <th class="text-center" style="width: 1%; white-space: nowrap;">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($classrooms as $index => $room)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td class="font-weight-bold text-dark">{{ $room->name }}</td>
                                <td class="text-center align-middle">
                                    <span class="text-dark font-weight-bold">{{ $room->shift_text }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light border px-2 py-1 font-weight-bold">{{ $room->students_count }} คน</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-success px-2 py-1 font-weight-normal mr-1">
                                        <i class="fas fa-check-circle mr-1"></i>มีสิทธิ์: {{ $room->eligible_students_count }}
                                    </span>
                                    @if($room->ineligible_students_count > 0)
                                        <span class="badge badge-danger px-2 py-1 font-weight-normal">
                                            <i class="fas fa-ban mr-1"></i>ระงับสิทธิ์: {{ $room->ineligible_students_count }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center" style="white-space: nowrap;">
                                    <a href="{{ route('admin.classrooms.show', $room->id) }}" class="btn btn-sm btn-primary font-weight-bold shadow-xs mr-1" title="จัดการสิทธิ์สอบและดูรายชื่อนักศึกษา">
                                        <i class="fas fa-user-check mr-1"></i> จัดการสิทธิ์สอบ
                                    </a>
                                    <button type="button" class="btn btn-sm btn-warning font-weight-bold text-white shadow-xs mr-1" 
                                            data-toggle="modal" data-target="#editClassroomModal{{ $room->id }}" title="แก้ไขชื่อห้อง">
                                        <i class="fas fa-edit mr-1"></i> แก้ไข
                                    </button>
                                    <form action="{{ route('admin.classrooms.destroy', $room->id) }}" method="post" class="d-inline confirm-delete"
                                          data-text="คุณแน่ใจหรือไม่ที่จะลบห้องเรียนนี้? (นักเรียนในห้องนี้จะเปลี่ยนเป็นสถานะไม่ได้ระบุห้องเรียน)">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="btn btn-sm btn-outline-danger font-weight-bold shadow-xs" title="ลบ">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
    
                            <!-- Edit Classroom Modal -->
                            <div class="modal fade" id="editClassroomModal{{ $room->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content text-left">
                                        <div class="modal-header bg-warning">
                                            <h5 class="modal-title text-white font-weight-bold"><i class="fas fa-edit mr-2"></i>แก้ไขชื่อห้องเรียน / ระดับชั้น</h5>
                                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <form action="{{ route('admin.classrooms.update', $room->id) }}" method="post">
                                            @csrf
                                            @method('put')
                                            <div class="modal-body">
                                                <div class="form-group">
                                                    <label class="font-weight-bold">ชื่อห้องเรียน / ระดับชั้น</label>
                                                    <input type="text" name="name" class="form-control" value="{{ old('name', $room->name) }}" placeholder="เช่น ปวช. 1/1 หรือ ปวส. 2/3" required>
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
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">ไม่มีข้อมูลห้องเรียนในระบบ</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Classroom Modal -->
    <div class="modal fade" id="addClassroomModal" tabindex="-1" role="dialog" aria-labelledby="addClassroomModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-left">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white font-weight-bold" id="addClassroomModalLabel"><i class="fas fa-plus mr-2"></i>เพิ่มห้องเรียนใหม่</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.classrooms.store') }}" method="post">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="font-weight-bold">ชื่อห้องเรียน / ระดับชั้น</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name') }}" placeholder="เช่น ปวช. 1/1 หรือ ปวส. 2/3" required>
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
@stop

@section('js')
    <script>
        $(document).ready(function() {
            $('#classrooms-table').DataTable({
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
