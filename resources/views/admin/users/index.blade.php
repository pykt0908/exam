@extends('adminlte::page')

@section('title', $role === 'student' ? 'จัดการข้อมูลนักศึกษา' : ($role === 'staff' ? 'จัดการข้อมูลอาจารย์' : 'จัดการข้อมูลผู้ใช้งาน'))

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="text-dark font-weight-bold">
            @if($role === 'student')
                ข้อมูลนักศึกษา
            @elseif($role === 'staff')
                ข้อมูลอาจารย์
            @else
                ข้อมูลผู้ใช้งาน
            @endif
        </h1>
        <div class="d-flex align-items-center">
            @if($role === 'student')
                <div class="dropdown mr-2">
                    <button class="btn btn-primary font-weight-bold shadow-sm dropdown-toggle" type="button" id="addStudentDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-user-plus mr-2"></i>เพิ่มนักศึกษาใหม่
                    </button>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="addStudentDropdown">
                        <a class="dropdown-item" href="{{ route('admin.users.create') }}">
                            <i class="fas fa-keyboard mr-2 text-primary text-xs"></i>กรอกข้อมูลทีละคน
                        </a>
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#importStudentsModal">
                            <i class="fas fa-file-import mr-2 text-success text-xs"></i>นำเข้าไฟล์ (Excel / CSV)
                        </a>
                    </div>
                </div>
            @else
                <button type="button" class="btn btn-primary font-weight-bold shadow-sm" data-toggle="modal" data-target="#addUserModal">
                    @if($role === 'staff')
                        <i class="fas fa-user-plus mr-2"></i>เพิ่มอาจารย์
                    @else
                        <i class="fas fa-user-plus mr-2"></i>เพิ่มผู้ใช้งานใหม่
                    @endif
                </button>
            @endif
            @if($role === 'student')
                <a href="{{ route('admin.classrooms.index') }}" class="btn btn-info font-weight-bold shadow-sm">
                    <i class="fas fa-school mr-2"></i>จัดการห้องเรียน
                </a>
            @endif
        </div>
    </div>
@stop

@section('content')
    <div class="card shadow-sm mt-3">
        <div class="card-header bg-light">
            <h3 class="card-title font-weight-bold text-dark mb-0"><i class="fas fa-filter mr-2"></i>ตัวกรองและค้นหาผู้ใช้งาน</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.users.index') }}" method="GET" class="form-row">
                <input type="hidden" name="search" value="1">
                <input type="hidden" name="role" value="{{ $role }}">
                
                @if($role === 'student')
                    <div class="form-group col-md-4 mb-2 mb-md-0">
                        <select name="classroom_id" class="form-control">
                            <option value="">-- เลือกห้องเรียนทั้งหมด --</option>
                            @foreach($classrooms as $room)
                                <option value="{{ $room->id }}" {{ request('classroom_id') == $room->id ? 'selected' : '' }}>
                                    {{ $room->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-5 mb-2 mb-md-0">
                @else
                    <div class="form-group col-md-9 mb-2 mb-md-0">
                @endif
                    <input type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="ค้นหาชื่อ, รหัสนักศึกษา หรือ อีเมล...">
                </div>
                <div class="form-group col-md-3 mb-0">
                    <button type="submit" class="btn btn-primary btn-block font-weight-bold shadow-sm">
                        <i class="fas fa-search mr-2"></i>ค้นหาข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mt-3">
        @if(!$searchPerformed)
            <div class="card-body text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5 class="text-muted font-weight-bold">กรุณาเลือกตัวกรองหรือกรอกข้อมูลค้นหาด้านบนเพื่อแสดงข้อมูลผู้ใช้งาน</h5>
            </div>
        @else
            <div class="card-header">
                <h3 class="card-title font-weight-bold text-dark">
                    @if($role === 'student')
                        รายชื่อนักศึกษาในระบบ ({{ $users->count() }} คน)
                    @elseif($role === 'staff')
                        รายชื่ออาจารย์และผู้ดูแลในระบบ ({{ $users->count() }} คน)
                    @else
                        ผู้ใช้งานทั้งหมดในระบบ ({{ $users->count() }} คน)
                    @endif
                </h3>
            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table id="users-table" class="table table-bordered table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 5%">#</th>
                                @if($role === 'student')
                                    <th style="width: 14%">รหัสนักศึกษา</th>
                                    <th style="width: 14%">เลขประจำตัวประชาชน</th>
                                    <th>ชื่อ-นามสกุล</th>
                                    <th style="width: 15%">ระดับชั้น</th>
                                @elseif($role === 'staff')
                                    <th style="width: 25%">รหัสประจำตัวครู</th>
                                    <th>ชื่อ-นามสกุล</th>
                                    <th style="width: 25%">บทบาท (Role)</th>
                                @else
                                    <th>ชื่อ-นามสกุล</th>
                                    <th style="width: 15%">บทบาท (Role)</th>
                                    <th style="width: 20%">รหัสประจำตัว / Email</th>
                                    <th style="width: 15%">ระดับชั้น</th>
                                    <th style="width: 15%">เลขประจำตัวประชาชน</th>
                                @endif
                                <th class="text-center" style="width: 1%; white-space: nowrap;">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $index => $u)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    
                                    @if($role === 'student')
                                        <td>{{ $u->student_code }}</td>
                                        <td>{{ $u->citizen_id }}</td>
                                        <td>{{ $u->name }}</td>
                                        <td>{{ $u->classroom ? $u->classroom->name : 'ไม่ได้ระบุ' }}</td>
                                    @elseif($role === 'staff')
                                        <td><code>{{ $u->teacher_code ?? '-' }}</code></td>
                                        <td>{{ $u->name }}</td>
                                        <td>
                                            @if($u->isAdmin())
                                                ผู้ดูแลระบบ
                                            @elseif($u->isTeacher())
                                                อาจารย์
                                            @else
                                                -
                                            @endif
                                        </td>
                                    @else
                                        <td>{{ $u->name }}</td>
                                        <td>
                                            @if($u->isAdmin())
                                                ผู้ดูแลระบบ
                                            @elseif($u->isTeacher())
                                                อาจารย์
                                            @else
                                                นักศึกษา
                                            @endif
                                        </td>
                                        <td>
                                            @if($u->isStudent())
                                                {{ $u->student_code }}
                                            @else
                                                <code>{{ $u->teacher_code ?? '-' }}</code> / {{ $u->email }}
                                            @endif
                                        </td>
                                        <td>{{ $u->isStudent() && $u->classroom ? $u->classroom->name : '-' }}</td>
                                        <td>{{ $u->isStudent() ? $u->citizen_id : '-' }}</td>
                                    @endif
                                    <td class="text-center" style="white-space: nowrap;">
                                        @if($u->isStudent())
                                            <a href="{{ route('admin.users.show', $u->id) }}" class="btn btn-sm btn-info font-weight-bold shadow-xs mr-1" title="ดูประวัติการสอบ">
                                                <i class="fas fa-history"></i>
                                            </a>
                                        @endif
                                        <a href="{{ route('admin.users.edit', $u->id) }}" class="btn btn-sm btn-warning font-weight-bold text-white shadow-xs mr-1" title="แก้ไข">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.users.destroy', $u->id) }}" method="post" class="d-inline confirm-delete"
                                              data-text="คุณแน่ใจหรือไม่ที่จะลบผู้ใช้งานรายนี้? หากเป็นนักศึกษาประวัติการสอบทั้งหมดจะถูกลบไปด้วย!">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="btn btn-sm btn-danger font-weight-bold shadow-xs" title="ลบ" {{ auth()->id() === $u->id ? 'disabled' : '' }}>
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $role === 'student' ? '5' : ($role === 'staff' ? '5' : '6') }}" class="text-center text-muted py-4">ไม่มีข้อมูลผู้ใช้งานตามกลุ่มที่เลือกในระบบ</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-left">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white font-weight-bold" id="addUserModalLabel">
                        @if($role === 'student')
                            <i class="fas fa-user-plus mr-2"></i>เพิ่มข้อมูลนักศึกษาใหม่
                        @elseif($role === 'staff')
                            <i class="fas fa-user-plus mr-2"></i>เพิ่มอาจารย์
                        @else
                            <i class="fas fa-user-plus mr-2"></i>เพิ่มผู้ใช้งานใหม่
                        @endif
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.users.store') }}" method="post">
                    @csrf
                    <div class="modal-body">
                        @if($role === 'student')
                            <!-- Hardcoded role for Student submenu -->
                            <input type="hidden" name="role" value="student">
                        @elseif($role === 'staff')
                            <!-- Restrict roles to staff for Staff submenu -->
                            <div class="form-group">
                                <label class="font-weight-bold">ประเภทบทบาท</label>
                                <select name="role" class="form-control" required>
                                    <option value="teacher" selected>อาจารย์ (Teacher)</option>
                                    <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                                </select>
                            </div>
                        @else
                            <!-- General selection for all roles -->
                            <div class="form-group">
                                <label class="font-weight-bold">ประเภทผู้ใช้งาน (บทบาท)</label>
                                <select name="role" id="role-select-add" class="form-control" required>
                                    <option value="" disabled selected>-- เลือกบทบาท --</option>
                                    <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>ผู้ดูแลระบบ (Admin)</option>
                                    <option value="teacher" {{ old('role') === 'teacher' ? 'selected' : '' }}>อาจารย์ (Teacher)</option>
                                    <option value="student" {{ old('role') === 'student' ? 'selected' : '' }}>นักศึกษา (Student)</option>
                                </select>
                            </div>
                        @endif

                        <div class="form-group">
                            <label class="font-weight-bold">ชื่อ-นามสกุล</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name') }}" placeholder="เช่น นายสมชาย ดีงาม" required>
                            @error('name')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <!-- Fields for Staff (Admin/Teacher) -->
                        <div id="staff-fields-add" style="{{ ($role === 'student') ? 'display:none;' : '' }}">
                            <div class="form-group">
                                <label class="font-weight-bold">รหัสประจำตัวครู (Username)</label>
                                <input type="text" name="teacher_code" class="form-control @error('teacher_code') is-invalid @enderror" 
                                       value="{{ old('teacher_code') }}" placeholder="เช่น t69005" {{ ($role === 'staff') ? 'required' : '' }}>
                                @error('teacher_code')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold">รหัสผ่านการเข้าใช้ระบบ</label>
                                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" 
                                       placeholder="รหัสผ่านเข้าใช้อย่างน้อย 6 ตัวอักษร" {{ ($role === 'staff') ? 'required' : '' }}>
                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <!-- Fields for Student -->
                        <div id="student-fields-add" style="{{ ($role !== 'student') ? 'display:none;' : '' }}">
                            <div class="form-group">
                                <label class="font-weight-bold">รหัสนักศึกษา (Username)</label>
                                <input type="text" name="student_code" class="form-control @error('student_code') is-invalid @enderror" 
                                       value="{{ old('student_code') }}" placeholder="เช่น 66309010001 (สูงสุด 13 หลัก)" maxlength="13" {{ ($role === 'student') ? 'required' : '' }}>
                                @error('student_code')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold">ห้องเรียน / ระดับชั้น</label>
                                <select name="classroom_id" class="form-control @error('classroom_id') is-invalid @enderror" {{ ($role === 'student') ? 'required' : '' }}>
                                    <option value="" disabled selected>-- เลือกห้องเรียน --</option>
                                    @foreach($classrooms as $room)
                                        <option value="{{ $room->id }}" {{ old('classroom_id') == $room->id ? 'selected' : '' }}>
                                            {{ $room->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('classroom_id')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold">เลขประจำตัวประชาชน (Password)</label>
                                <input type="text" name="citizen_id" class="form-control @error('citizen_id') is-invalid @enderror" 
                                       value="{{ old('citizen_id') }}" placeholder="เลขบัตรประชาชน 13 หลัก" maxlength="13" minlength="13" {{ ($role === 'student') ? 'required' : '' }}>
                                @error('citizen_id')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
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

    </div>

    <!-- Import Students Modal -->
    <div class="modal fade" id="importStudentsModal" tabindex="-1" role="dialog" aria-labelledby="importStudentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-left">
                <div class="modal-header bg-success">
                    <h5 class="modal-title text-white font-weight-bold" id="importStudentsModalLabel"><i class="fas fa-file-import mr-2"></i>นำเข้าไฟล์ข้อมูลนักศึกษา (CSV)</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.users.import') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info py-2 px-3 mb-3 text-sm">
                            <i class="fas fa-info-circle mr-2"></i>
                            ระบบรองรับการนำเข้าไฟล์รูปแบบ <strong>CSV (Comma Separated Values)</strong> เท่านั้น หากใช้โปรแกรม Excel ให้เลือกจัดเก็บเป็น <code>CSV UTF-8 (Comma delimited) (*.csv)</code>
                        </div>

                        <div class="form-group mb-4">
                            <label class="font-weight-bold">ดาวน์โหลดไฟล์ต้นแบบ (Template)</label>
                            <div>
                                <a href="{{ route('admin.users.import-template') }}" class="btn btn-outline-success btn-sm font-weight-bold">
                                    <i class="fas fa-download mr-1"></i> ดาวน์โหลดเทมเพลต CSV
                                </a>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">เลือกไฟล์ข้อมูลนักศึกษา (.csv)</label>
                            <div class="custom-file">
                                <input type="file" name="file" class="custom-file-input" id="importFile" accept=".csv" required>
                                <label class="custom-file-label" for="importFile">คลิกเลือกไฟล์...</label>
                            </div>
                            <small class="form-text text-muted mt-2">
                                * คอลัมน์ในไฟล์ต้องประกอบไปด้วย: <code>ชื่อ-นามสกุล</code>, <code>รหัสนักศึกษา</code>, <code>เลขประจำตัวประชาชน</code>, <code>ระดับชั้น</code><br>
                                * หากมีรายชื่อเดิมอยู่ในระบบแล้ว ระบบจะอัปเดตข้อมูลของรายชื่อเดิมให้โดยอัตโนมัติ
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-success font-weight-bold">เริ่มนำเข้าข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            $('#users-table').DataTable({
                "pageLength": -1,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "ทั้งหมด"]],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.19/i18n/Thai.json"
                },
                "responsive": true,
                "autoWidth": false
            });

            // Function to toggle add fields
            function toggleAddFields(role) {
                if (role === 'student') {
                    $('#student-fields-add').show().find('input, select').prop('required', true);
                    $('#staff-fields-add').hide().find('input').prop('required', false);
                } else if (role === 'admin' || role === 'teacher') {
                    $('#student-fields-add').hide().find('input, select').prop('required', false);
                    $('#staff-fields-add').show().find('input').prop('required', true);
                } else {
                    $('#student-fields-add, #staff-fields-add').hide().find('input, select').prop('required', false);
                }
            }

            $('#role-select-add').change(function() {
                toggleAddFields($(this).val());
            });

            // Trigger on add form reload validation fails
            if ($('#role-select-add').val()) {
                toggleAddFields($('#role-select-add').val());
            }

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

            // Display selected file name in bootstrap custom-file-input
            $(document).on('change', '.custom-file-input', function(e) {
                var fileName = e.target.files[0] ? e.target.files[0].name : 'คลิกเลือกไฟล์...';
                $(this).next('.custom-file-label').html(fileName);
            });
        });
    </script>
@stop
