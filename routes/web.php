<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\ExamController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ClassroomController;
use App\Http\Controllers\Student\StudentExamController;

// Root route redirects to login or dashboard
Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->isStaff() 
            ? redirect()->route('admin.dashboard') 
            : redirect()->route('student.dashboard');
    }
    return redirect()->route('login');
});

// Fallback redirect for /home
Route::get('/home', function () {
    return redirect('/');
});

// Authentication routes (Guest)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Authentication routes (Authenticated)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Admin/Teacher Group
    Route::prefix('admin')->name('admin.')->middleware('role:admin,teacher')->group(function () {
        Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');
        
        // Subjects
        Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
        Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::put('/subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
        Route::get('/subjects/{subject}', [SubjectController::class, 'index'])->name('subjects.show'); // fallback or matching standard
        Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');
        Route::get('/subjects/{subject}/students', [\App\Http\Controllers\Admin\SubjectStudentController::class, 'index'])->name('subjects.students.index');
        Route::post('/subjects/{subject}/students', [\App\Http\Controllers\Admin\SubjectStudentController::class, 'store'])->name('subjects.students.store');
        Route::delete('/subjects/{subject}/students/{student}', [\App\Http\Controllers\Admin\SubjectStudentController::class, 'destroy'])->name('subjects.students.destroy');
        Route::delete('/subjects/{subject}/classrooms/{classroom}', [\App\Http\Controllers\Admin\SubjectStudentController::class, 'destroyClassroom'])->name('subjects.classrooms.destroy');
        Route::post('/subjects/{subject}/students/bulk-destroy', [\App\Http\Controllers\Admin\SubjectStudentController::class, 'bulkDestroy'])->name('subjects.students.bulk-destroy');

        // Exams
        Route::get('/exams', [ExamController::class, 'index'])->name('exams.index');
        Route::get('/exams/create', [ExamController::class, 'create'])->name('exams.create');
        Route::post('/exams', [ExamController::class, 'store'])->name('exams.store');
        Route::put('/exams/{exam}', [ExamController::class, 'update'])->name('exams.update');
        Route::delete('/exams/{exam}', [ExamController::class, 'destroy'])->name('exams.destroy');
        Route::post('/exams/{exam}/toggle-status', [ExamController::class, 'toggleStatus'])->name('exams.toggle-status');
        Route::post('/exams/{exam}/submit-approval', [\App\Http\Controllers\Admin\ExamApprovalController::class, 'submit'])->name('exams.submit-approval');
        Route::post('/exams/{exam}/recall-approval', [\App\Http\Controllers\Admin\ExamApprovalController::class, 'recall'])->name('exams.recall-approval');
        Route::post('/exams/{exam}/duplicate', [ExamController::class, 'duplicate'])->name('exams.duplicate');

        // Exam Student Attempts (จัดการสิทธิ์เปิดให้สอบเพิ่มรายคน)
        Route::get('/exams/{exam}/student-attempts', [\App\Http\Controllers\Admin\ExamStudentAttemptController::class, 'index'])->name('exams.student-attempts.index');
        Route::post('/exams/{exam}/student-attempts/{student}/quick-add', [\App\Http\Controllers\Admin\ExamStudentAttemptController::class, 'quickAdd'])->name('exams.student-attempts.quick-add');
        Route::put('/exams/{exam}/student-attempts/{student}', [\App\Http\Controllers\Admin\ExamStudentAttemptController::class, 'update'])->name('exams.student-attempts.update');
        Route::delete('/exams/{exam}/student-attempts/{student}', [\App\Http\Controllers\Admin\ExamStudentAttemptController::class, 'reset'])->name('exams.student-attempts.reset');
        Route::post('/exams/{exam}/student-attempts/bulk', [\App\Http\Controllers\Admin\ExamStudentAttemptController::class, 'bulkUpdate'])->name('exams.student-attempts.bulk');

        // Approvals (หัวหน้าหมวด, หัวหน้าวัดผล, รองวิชาการ, Admin)
        Route::get('/approvals', [\App\Http\Controllers\Admin\ExamApprovalController::class, 'index'])->name('approvals.index');
        Route::get('/approvals/{exam}/preview', [\App\Http\Controllers\Admin\ExamApprovalController::class, 'preview'])->name('approvals.preview');
        Route::post('/approvals/{exam}/approve', [\App\Http\Controllers\Admin\ExamApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('/approvals/{exam}/reject', [\App\Http\Controllers\Admin\ExamApprovalController::class, 'reject'])->name('approvals.reject');

        // Questions & Choices
        Route::get('/exams/{exam}/questions', [QuestionController::class, 'index'])->name('exams.questions.index');
        Route::post('/exams/{exam}/questions', [QuestionController::class, 'store'])->name('exams.questions.store');
        Route::get('/exams/{exam}/questions/{question}/edit', [QuestionController::class, 'edit'])->name('exams.questions.edit');
        Route::put('/exams/{exam}/questions/{question}', [QuestionController::class, 'update'])->name('exams.questions.update');
        Route::delete('/exams/{exam}/questions/{question}', [QuestionController::class, 'destroy'])->name('exams.questions.destroy');
        Route::post('/exams/questions/upload-image', [QuestionController::class, 'uploadImage'])->name('exams.questions.upload-image');
        Route::post('/exams/{exam}/recalculate-scores', [QuestionController::class, 'recalculateScores'])->name('exams.recalculate-scores');

        // Exam Sections
        Route::post('/exams/{exam}/sections', [\App\Http\Controllers\Admin\ExamSectionController::class, 'store'])->name('exams.sections.store');
        Route::put('/exams/{exam}/sections/{section}', [\App\Http\Controllers\Admin\ExamSectionController::class, 'update'])->name('exams.sections.update');
        Route::delete('/exams/{exam}/sections/{section}', [\App\Http\Controllers\Admin\ExamSectionController::class, 'destroy'])->name('exams.sections.destroy');

        // Exam Grading (ตรวจข้อสอบ)
        Route::get('grading', [\App\Http\Controllers\Admin\ExamGradingController::class, 'index'])->name('grading.index');
        Route::get('grading/{attempt}', [\App\Http\Controllers\Admin\ExamGradingController::class, 'show'])->name('grading.show');
        Route::put('grading/{attempt}', [\App\Http\Controllers\Admin\ExamGradingController::class, 'update'])->name('grading.update');

        // Reports (Accessible by both Admin and Teacher)
        Route::get('/reports', [UserController::class, 'reports'])->name('reports.index');
        Route::get('/reports/export', [UserController::class, 'exportReports'])->name('reports.export');

        // Admin-only management (Users & Classrooms)
        Route::middleware('role:admin')->group(function () {
            // Users
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::post('/users/bulk-destroy', [UserController::class, 'bulkDestroy'])->name('users.bulk-destroy');
            Route::post('/users/bulk-toggle-exam-eligibility', [UserController::class, 'bulkToggleExamEligibility'])->name('users.bulk-toggle-exam-eligibility');
            Route::post('/users/{user}/toggle-exam-eligibility', [UserController::class, 'toggleExamEligibility'])->name('users.toggle-exam-eligibility');
            Route::get('/users/import-template', [UserController::class, 'downloadTemplate'])->name('users.import-template');
            Route::post('/users/import', [UserController::class, 'import'])->name('users.import');
            Route::get('/users/import-teacher-template', [UserController::class, 'downloadTeacherTemplate'])->name('users.import-teacher-template');
            Route::post('/users/import-teachers', [UserController::class, 'importTeachers'])->name('users.import-teachers');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}/photo', [UserController::class, 'destroyPhoto'])->name('users.destroy-photo');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');

            // Classrooms
            Route::get('/classrooms', [ClassroomController::class, 'index'])->name('classrooms.index');
            Route::post('/classrooms', [ClassroomController::class, 'store'])->name('classrooms.store');
            Route::put('/classrooms/{classroom}', [ClassroomController::class, 'update'])->name('classrooms.update');
            Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy'])->name('classrooms.destroy');
            Route::get('/classrooms/{classroom}', [ClassroomController::class, 'show'])->name('classrooms.show');
            Route::post('/classrooms/{classroom}/toggle-student-eligibility/{student}', [ClassroomController::class, 'toggleStudentEligibility'])->name('classrooms.toggle-student-eligibility');
            Route::post('/classrooms/{classroom}/bulk-toggle-eligibility', [ClassroomController::class, 'bulkToggleEligibility'])->name('classrooms.bulk-toggle-eligibility');
            Route::post('/classrooms/{classroom}/toggle-all-eligibility', [ClassroomController::class, 'toggleAllEligibility'])->name('classrooms.toggle-all-eligibility');

            // Departments (หมวดวิชา / แผนกวิชา)
            Route::get('/departments', [\App\Http\Controllers\Admin\DepartmentController::class, 'index'])->name('departments.index');
            Route::post('/departments', [\App\Http\Controllers\Admin\DepartmentController::class, 'store'])->name('departments.store');
            Route::put('/departments/{department}', [\App\Http\Controllers\Admin\DepartmentController::class, 'update'])->name('departments.update');
            Route::delete('/departments/{department}', [\App\Http\Controllers\Admin\DepartmentController::class, 'destroy'])->name('departments.destroy');
            Route::get('/departments/{department}', [\App\Http\Controllers\Admin\DepartmentController::class, 'show'])->name('departments.show');

            // Exam Attempts Deletion (Admin only)
            Route::delete('/exam-attempts/{attempt}', [\App\Http\Controllers\Admin\ExamStudentAttemptController::class, 'destroyAttempt'])->name('exam-attempts.destroy');
            Route::delete('/exams/{exam}/students/{student}/attempts', [\App\Http\Controllers\Admin\ExamStudentAttemptController::class, 'destroyStudentAttempts'])->name('exams.student-attempts.destroy-all');
        });
    });

    // Student Group
    Route::prefix('student')->name('student.')->middleware('role:student')->group(function () {
        Route::get('/dashboard', [StudentExamController::class, 'index'])->name('dashboard');
        
        // Exam Taking Flow
        Route::get('/exam/{exam}/lobby', [StudentExamController::class, 'lobby'])->name('exam.lobby');
        Route::post('/exam/{exam}/start', [StudentExamController::class, 'start'])->name('exam.start');
        Route::get('/exam/{exam}/take/{attempt}', [StudentExamController::class, 'take'])->name('exam.take');
        Route::post('/exam/attempt/{attempt}/save', [StudentExamController::class, 'saveAnswer'])->name('exam.saveAnswer');
        Route::post('/exam/attempt/{attempt}/check-eligibility', [StudentExamController::class, 'checkEligibility'])->name('exam.checkEligibility');
        Route::post('/exam/attempt/{attempt}/section-score', [StudentExamController::class, 'sectionScore'])->name('exam.sectionScore');
        Route::post('/exam/attempt/{attempt}/focus-escape', [StudentExamController::class, 'recordFocusEscape'])->name('exam.focusEscape');
        Route::post('/exam/attempt/{attempt}/submit', [StudentExamController::class, 'submitAttempt'])->name('exam.submit');
        Route::get('/exam/attempt/{attempt}/result', [StudentExamController::class, 'result'])->name('exam.result');
    });
});
