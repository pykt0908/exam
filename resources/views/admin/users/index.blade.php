@extends('adminlte::page')

@section('title', $role === 'student' ? 'จัดการข้อมูลนักศึกษา' : ($role === 'staff' ? 'จัดการข้อมูลอาจารย์' : 'จัดการข้อมูลผู้ใช้งาน'))

@section('css')
<style>
    #users-table th, 
    #users-table td {
        padding: 0.4rem 0.6rem !important;
        vertical-align: middle !important;
    }
    #users-table td .badge {
        font-size: 82%;
        padding: 0.3em 0.55em;
    }
    #users-table .btn-sm {
        padding: 0.2rem 0.45rem;
        font-size: 0.8rem;
    }
</style>
@stop

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
            @elseif($role === 'staff')
                <div class="dropdown">
                    <button class="btn btn-primary font-weight-bold shadow-sm dropdown-toggle" type="button" id="addStaffDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-user-plus mr-2"></i>เพิ่มอาจารย์
                    </button>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="addStaffDropdown">
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addUserModal">
                            <i class="fas fa-keyboard mr-2 text-primary text-xs"></i>กรอกข้อมูลทีละคน
                        </a>
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#importTeachersModal">
                            <i class="fas fa-file-import mr-2 text-success text-xs"></i>นำเข้าไฟล์ (Excel / CSV)
                        </a>
                    </div>
                </div>
            @else
                <button type="button" class="btn btn-primary font-weight-bold shadow-sm" data-toggle="modal" data-target="#addUserModal">
                    <i class="fas fa-user-plus mr-2"></i>เพิ่มผู้ใช้งานใหม่
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
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <h3 class="card-title font-weight-bold text-dark mb-1 mb-md-0 mr-3">
                @if($role === 'student')
                    @if($selectedClassroom)
                        รายชื่อนักศึกษา: {{ $selectedClassroom->name }} ({{ $users->count() }} คน)
                    @elseif(request('classroom_id') === 'unassigned')
                        รายชื่อนักศึกษา: ยังไม่มีห้องเรียน ({{ $users->count() }} คน)
                    @else
                        รายชื่อนักศึกษาในระบบ {{ $users->count() }} คน
                    @endif
                @elseif($role === 'staff')
                    รายชื่ออาจารย์และผู้ดูแลในระบบ ({{ $users->count() }} คน)
                @else
                    ผู้ใช้งานทั้งหมดในระบบ ({{ $users->count() }} คน)
                @endif
            </h3>
            <div class="d-flex align-items-center flex-wrap">
                @if($role === 'student')
                    <form action="{{ route('admin.users.index') }}" method="GET" class="form-inline mr-2 my-1">
                        <input type="hidden" name="role" value="student">
                        <label class="font-weight-bold text-dark text-md mr-2 mb-0">
                            กรองตามห้องเรียน:
                        </label>
                        <select name="classroom_id" class="form-control form-control-sm bg-white" onchange="this.form.submit()" style="min-width: 200px;">
                            <option value="">-- ทุกห้องเรียน --</option>
                            @foreach($classrooms as $c)
                                <option value="{{ $c->id }}" {{ request('classroom_id') == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }} ({{ $c->students_count }} คน)
                                </option>
                            @endforeach
                            @if(isset($unassignedStudentsCount) && $unassignedStudentsCount > 0)
                                <option value="unassigned" {{ request('classroom_id') === 'unassigned' ? 'selected' : '' }}>
                                    -- ยังไม่มีห้องเรียน ({{ $unassignedStudentsCount }} คน) --
                                </option>
                            @endif
                        </select>
                        @if(request('classroom_id'))
                            <a href="{{ route('admin.users.index', ['role' => 'student']) }}" class="btn btn-outline-secondary btn-sm ml-2" title="แสดงทั้งหมด / ล้างตัวกรอง">
                                <i class="fas fa-times mr-1"></i>แสดงทั้งหมด
                            </a>
                        @endif
                    </form>
                @endif

                @if($role === 'student' && $users->count() > 0)
                    <button type="button" id="bulk-delete-btn" class="btn btn-danger btn-sm font-weight-bold shadow-sm d-none my-1">
                        <i class="fas fa-trash-alt mr-2"></i>ลบนักศึกษาที่เลือก (<span id="selected-count">0</span>)
                    </button>
                @endif
            </div>
        </div>
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="users-table" class="table table-bordered table-striped table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            @if($role === 'student')
                                <th style="width: 3%" class="text-center">
                                    <input type="checkbox" id="select-all">
                                </th>
                            @endif
                            <th style="width: 5%">#</th>
                            @if($role === 'student')
                                <th style="width: 8%">รหัสนักศึกษา</th>
                                <th style="width: 14%">เลขประจำตัวประชาชน</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th style="width: 30%">ระดับชั้น</th>
                            @elseif($role === 'staff')
                                <th style="width: 15%">รหัสประจำตัวครู</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th style="width: 22%">หมวดวิชา / แผนกวิชา</th>
                                <th style="width: 25%">บทบาท / หน้าที่เสริม</th>
                            @else
                                <th>ชื่อ-นามสกุล</th>
                                <th style="width: 15%">บทบาท (Role)</th>
                                <th style="width: 15%">หมวดวิชา</th>
                                <th style="width: 15%">รหัสประจำตัว / Email</th>
                                <th style="width: 15%">ระดับชั้น</th>
                                <th style="width: 15%">เลขประจำตัวประชาชน</th>
                            @endif
                            <th class="text-center" style="width: 1%; white-space: nowrap;">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $index => $u)
                            <tr>
                                @if($role === 'student')
                                    <td class="text-center">
                                        <input type="checkbox" name="user_ids[]" value="{{ $u->id }}" class="user-checkbox">
                                    </td>
                                @endif
                                <td>{{ $index + 1 }}</td>
                                
                                @if($role === 'student')
                                    <td>{{ $u->student_code }}</td>
                                    <td>{{ $u->citizen_id }}</td>
                                    <td>{{ $u->name }}</td>
                                    <td>{{ $u->classroom ? $u->classroom->name : 'ไม่ได้ระบุ' }}</td>
                                @elseif($role === 'staff')
                                    <td>{{ $u->teacher_code ?? '-' }}</td>
                                    <td>{{ $u->name }}</td>
                                    <td>{{ $u->department ? $u->department->name : '-' }}</td>
                                    <td>
                                        @if($u->isAdmin())
                                            ผู้ดูแลระบบ
                                        @elseif($u->isTeacher())
                                            อาจารย์
                                        @else
                                            -
                                        @endif
                                        @if($u->academic_role)
                                            ({{ $u->academic_role_label }})
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
                                        @if($u->academic_role)
                                            ({{ $u->academic_role_label }})
                                        @endif
                                    </td>
                                    <td>{{ $u->department ? $u->department->name : '-' }}</td>
                                    <td>
                                        @if($u->isStudent())
                                            {{ $u->student_code }}
                                        @else
                                            {{ $u->teacher_code ?? '-' }} / {{ $u->email }}
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
                                          data-text="คุณแน่ใจหรือไม่ที่จะลบ {{ $u->name }}? {{ auth()->id() === $u->id ? '(หากลบบัญชีที่กำลังใช้งานอยู่ ระบบจะทำการออกจากระบบทันที)' : ($u->isStudent() ? 'ประวัติการสอบทั้งหมดจะถูกลบไปด้วย!' : '') }}">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="btn btn-sm btn-danger font-weight-bold shadow-xs" title="ลบ">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $role === 'student' ? '7' : ($role === 'staff' ? '6' : '7') }}" class="text-center text-muted py-4">ไม่มีข้อมูลผู้ใช้งานตามกลุ่มที่เลือกในระบบ</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-left">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white font-weight-bold" id="addUserModalLabel">
                        @if($role === 'student')
                            <i class="fas fa-user-plus mr-2"></i>เพิ่มข้อมูลนักศึกษาใหม่
                        @elseif($role === 'staff')
                            <i class="fas fa-user-plus mr-2"></i>เพิ่มอาจารย์ / ผู้ดูแลระบบ
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
                                <label class="font-weight-bold">หมวดวิชา / แผนกวิชา</label>
                                <select name="department_id" class="form-control">
                                    <option value="" selected>-- ไม่ระบุหมวดวิชา / แผนกวิชา --</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                            {{ $dept->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold d-block">ตำแหน่ง / บทบาทหน้าที่เสริม <span class="text-muted font-weight-normal text-xs">(เลือกได้มากกว่า 1 ตำแหน่ง)</span></label>
                                <div class="d-flex flex-wrap" style="gap: 15px;">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="add_role_academic_deputy" name="academic_roles[]" value="academic_deputy" 
                                               {{ (is_array(old('academic_roles')) && in_array('academic_deputy', old('academic_roles'))) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-normal" for="add_role_academic_deputy">รองผู้อำนวยการฝ่ายวิชาการ</label>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="add_role_evaluation_head" name="academic_roles[]" value="evaluation_head" 
                                               {{ (is_array(old('academic_roles')) && in_array('evaluation_head', old('academic_roles'))) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-normal" for="add_role_evaluation_head">หัวหน้างานวัดและประเมินผล</label>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="add_role_department_head" name="academic_roles[]" value="department_head" 
                                               {{ (is_array(old('academic_roles')) && in_array('department_head', old('academic_roles'))) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-normal" for="add_role_department_head">หัวหน้าสาขา</label>
                                    </div>
                                </div>
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
                    <h5 class="modal-title text-white font-weight-bold" id="importStudentsModalLabel"><i class="fas fa-file-import mr-2"></i>นำเข้าไฟล์ข้อมูลนักศึกษา (Excel / CSV)</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.users.import') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info py-2 px-3 mb-3 text-sm">
                            <i class="fas fa-info-circle mr-2"></i>
                            ระบบรองรับไฟล์ <strong>Excel (.xlsx, .xls)</strong> และ <strong>CSV (.csv)</strong> ขนาดไฟล์ไม่เกิน 20MB
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
                            <label class="font-weight-bold">เลือกไฟล์ข้อมูลนักศึกษา (.xlsx, .xls, .csv)</label>
                            <div class="custom-file">
                                <input type="file" name="file" class="custom-file-input" id="importFile" accept=".csv, .xlsx, .xls" required>
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
                        <button type="submit" class="btn btn-success font-weight-bold" id="btnSubmitImport">เริ่มนำเข้าข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Teachers Modal -->
    <div class="modal fade" id="importTeachersModal" tabindex="-1" role="dialog" aria-labelledby="importTeachersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-left">
                <div class="modal-header bg-success">
                    <h5 class="modal-title text-white font-weight-bold" id="importTeachersModalLabel"><i class="fas fa-file-import mr-2"></i>นำเข้าไฟล์ข้อมูลอาจารย์ (Excel / CSV)</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('admin.users.import-teachers') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-warning py-2 px-3 mb-3 text-sm">
                            <i class="fas fa-info-circle mr-2"></i>
                            ระบบรองรับไฟล์ (.xlsx, .xls, .csv) ขนาดไฟล์ไม่เกิน 20MB
                        </div>

                        <div class="form-group mb-4">
                            <label class="font-weight-bold">ดาวน์โหลดไฟล์ต้นแบบ (Template)</label>
                            <div>
                                <a href="{{ route('admin.users.import-teacher-template') }}" class="btn btn-outline-success btn-sm font-weight-bold">
                                    <i class="fas fa-download mr-1"></i> ดาวน์โหลดเทมเพลต CSV
                                </a>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">เลือกไฟล์ข้อมูลอาจารย์ (.xlsx, .xls, .csv)</label>
                            <div class="custom-file">
                                <input type="file" name="file" class="custom-file-input" id="importTeacherFile" accept=".csv, .xlsx, .xls" required>
                                <label class="custom-file-label" for="importTeacherFile">คลิกเลือกไฟล์...</label>
                            </div>
                            <small class="form-text text-muted mt-2">
                                * คอลัมน์ในไฟล์: <code>ชื่อ-นามสกุล</code>, <code>รหัสประจำตัวครู</code>, <code>แผนกวิชา/หมวดวิชา</code>, <code>รหัสผ่าน</code> (เว้นว่างได้ ค่าเริ่มต้นคือรหัสครูหรือ 123456), <code>บทบาทเสริม</code> (เช่น หัวหน้าสาขา, หัวหน้างานวัดและประเมินผล, รองผู้อำนวยการฝ่ายวิชาการ)<br>
                                * หากมีรหัสประจำตัวครูเดิมอยู่ในระบบแล้ว ระบบจะอัปเดตข้อมูลของอาจารย์ท่านนั้นโดยอัตโนมัติ
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-success font-weight-bold" id="btnSubmitImportTeacher">เริ่มนำเข้าข้อมูล</button>
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
                "autoWidth": false,
                "order": [],
                @if($role === 'student')
                "columnDefs": [
                    {
                        "targets": 0,
                        "orderable": false,
                        "searchable": false
                    }
                ]
                @endif
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

            @if($role === 'student')
            // Select All Checkbox Handler
            $('#select-all').on('click', function() {
                var rows = $('#users-table').DataTable().rows({ 'search': 'applied' }).nodes();
                $('input[type="checkbox"].user-checkbox', rows).prop('checked', this.checked);
                toggleBulkDeleteButton();
            });

            // Individual Checkbox Handler
            $('#users-table tbody').on('change', 'input[type="checkbox"].user-checkbox', function() {
                var rows = $('#users-table').DataTable().rows({ 'search': 'applied' }).nodes();
                var allChecked = true;
                $('input[type="checkbox"].user-checkbox', rows).each(function() {
                    if (!this.checked) {
                        allChecked = false;
                    }
                });
                $('#select-all').prop('checked', allChecked);
                toggleBulkDeleteButton();
            });

            function toggleBulkDeleteButton() {
                var checkedCount = 0;
                var rows = $('#users-table').DataTable().rows({ 'search': 'applied' }).nodes();
                $('input[type="checkbox"].user-checkbox', rows).each(function() {
                    if (this.checked) {
                        checkedCount++;
                    }
                });

                if (checkedCount > 0) {
                    $('#selected-count').text(checkedCount);
                    $('#bulk-delete-btn').removeClass('d-none');
                } else {
                    $('#bulk-delete-btn').addClass('d-none');
                }
            }

            // Bulk Delete Button Click Handler
            $('#bulk-delete-btn').on('click', function() {
                var selectedIds = [];
                var rows = $('#users-table').DataTable().rows({ 'search': 'applied' }).nodes();
                $('input[type="checkbox"].user-checkbox', rows).each(function() {
                    if (this.checked) {
                        selectedIds.push($(this).val());
                    }
                });

                if (selectedIds.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'คำเตือน',
                        text: 'กรุณาเลือกนักศึกษาที่ต้องการลบอย่างน้อย 1 คน',
                        confirmButtonText: 'ตกลง'
                    });
                    return;
                }

                Swal.fire({
                    title: 'คุณแน่ใจหรือไม่?',
                    text: 'นักศึกษาที่เลือกจำนวน ' + selectedIds.length + ' คน และประวัติการสอบทั้งหมดจะถูกลบออกถาวร!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'ใช่, ต้องการลบ!',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Create a temporary form to submit the bulk delete
                        var form = $('<form>', {
                            'action': '{{ route("admin.users.bulk-destroy") }}',
                            'method': 'POST'
                        });

                        form.append($('<input>', {
                            'type': 'hidden',
                            'name': '_token',
                            'value': '{{ csrf_token() }}'
                        }));

                        selectedIds.forEach(function(id) {
                            form.append($('<input>', {
                                'type': 'hidden',
                                'name': 'user_ids[]',
                                'value': id
                            }));
                        });

                        $('body').append(form);
                        form.submit();
                    }
                });
            });
            @endif

            // Update file input label when file selected
            $('#importFile').on('change', function() {
                var fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').addClass("selected").html(fileName || 'คลิกเลือกไฟล์...');
            });

            // Prevent double submission and show progress
            $('#importStudentsModal form').on('submit', function() {
                var btn = $('#btnSubmitImport');
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> กำลังนำเข้าข้อมูล...');
            });

            // Update teacher file input label when file selected
            $('#importTeacherFile').on('change', function() {
                var fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').addClass("selected").html(fileName || 'คลิกเลือกไฟล์...');
            });

            // Prevent double submission and show progress for teachers import
            $('#importTeachersModal form').on('submit', function() {
                var btn = $('#btnSubmitImportTeacher');
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> กำลังนำเข้าข้อมูล...');
            });
        });
    </script>
@stop
