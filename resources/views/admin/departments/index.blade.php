@extends('adminlte::page')

@section('title', 'จัดการข้อมูลหมวดวิชา / แผนกวิชา')

@section('css')
<style>
    #departments-table th, 
    #departments-table td {
        padding: 0.45rem 0.6rem !important;
        vertical-align: middle !important;
    }
    #departments-table .btn-sm {
        padding: 0.2rem 0.45rem;
        font-size: 0.8rem;
    }
</style>
@stop

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="text-dark font-weight-bold">หมวดวิชา / แผนกวิชา</h1>
        <button type="button" class="btn btn-primary font-weight-bold shadow-sm" data-toggle="modal" data-target="#addDepartmentModal">
            <i class="fas fa-plus mr-2"></i>เพิ่มหมวดวิชาใหม่
        </button>
    </div>
@stop

@section('content')
    <div class="card shadow-sm mt-3">
        <div class="card-header">
            <h3 class="card-title font-weight-bold text-dark mb-0"><i class="fas fa-layer-group mr-2"></i>หมวดวิชาทั้งหมดในระบบ ({{ $departments->count() }} หมวด)</h3>
        </div>
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="departments-table" class="table table-bordered table-striped table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th style="width: 5%">#</th>
                            <th style="width: 35%">ชื่อหมวดวิชา / แผนกวิชา</th>
                            <th style="width: 35%">หัวหน้าสาขา</th>
                            <th style="width: 15%" class="text-center">จำนวนอาจารย์</th>
                            <th class="text-center" style="width: 1%; white-space: nowrap;">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($departments as $index => $dept)
                            @php
                                $head = $dept->headTeacher();
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $dept->name }}</td>
                                <td>{{ $head ? $head->name : '-' }}</td>
                                <td class="text-center">{{ $dept->teachers_count }} คน</td>
                                <td class="text-center" style="white-space: nowrap;">
                                    <a href="{{ route('admin.departments.show', $dept->id) }}" class="btn btn-sm btn-info font-weight-bold shadow-xs mr-1" title="ดูรายชื่ออาจารย์">
                                        <i class="fas fa-users mr-1"></i> ดูรายชื่อ
                                    </a>
                                    <button type="button" class="btn btn-sm btn-warning font-weight-bold text-white shadow-xs mr-1" 
                                            data-toggle="modal" data-target="#editDepartmentModal{{ $dept->id }}" title="แก้ไขหมวด">
                                        <i class="fas fa-edit mr-1"></i> แก้ไข
                                    </button>
                                    <form action="{{ route('admin.departments.destroy', $dept->id) }}" method="post" class="d-inline confirm-delete"
                                          data-text="คุณแน่ใจหรือไม่ที่จะลบหมวดวิชานี้? (อาจารย์ในหมวดนี้จะเปลี่ยนเป็นสถานะไม่ได้ระบุหมวด)">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="btn btn-sm btn-danger font-weight-bold shadow-xs" title="ลบ">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Department Modal -->
                            <div class="modal fade" id="editDepartmentModal{{ $dept->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content text-left">
                                        <div class="modal-header bg-warning">
                                            <h5 class="modal-title text-white font-weight-bold"><i class="fas fa-edit mr-2"></i>แก้ไขข้อมูลหมวดวิชา</h5>
                                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <form action="{{ route('admin.departments.update', $dept->id) }}" method="post">
                                            @csrf
                                            @method('put')
                                            <div class="modal-body">
                                                <div class="form-group mb-0">
                                                    <label class="font-weight-bold">ชื่อหมวดวิชา / แผนกวิชา <span class="text-danger">*</span></label>
                                                    <input type="text" name="name" class="form-control" value="{{ old('name', $dept->name) }}" placeholder="เช่น หมวดวิชาภาษาไทย, หมวดวิชาช่างยนต์" required>
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
                                <td colspan="5" class="text-center text-muted py-4">ไม่มีข้อมูลหมวดวิชาในระบบ</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div class="modal fade" id="addDepartmentModal" tabindex="-1" role="dialog" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-left">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white font-weight-bold" id="addDepartmentModalLabel"><i class="fas fa-plus mr-2"></i>เพิ่มหมวดวิชาใหม่</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.departments.store') }}" method="post">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group mb-0">
                            <label class="font-weight-bold">ชื่อหมวดวิชา / แผนกวิชา <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="เช่น หมวดวิชาภาษาไทย, หมวดวิชาเทคโนโลยีสารสนเทศ" required>
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
            $('#departments-table').DataTable({
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

            // SweetAlert Confirm Delete
            $('.confirm-delete').on('submit', function(e) {
                e.preventDefault();
                var form = this;
                var text = $(this).data('text') || 'ข้อมูลนี้จะถูกลบและไม่สามารถกู้คืนได้!';
                
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
