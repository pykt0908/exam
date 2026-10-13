<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::useBootstrapFour();

        if (str_starts_with(config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        \Illuminate\Support\Facades\Event::listen(
            \JeroenNoten\LaravelAdminLte\Events\BuildingMenu::class,
            function (\JeroenNoten\LaravelAdminLte\Events\BuildingMenu $event) {
                $user = \Illuminate\Support\Facades\Auth::user();
                
                if (!$user) {
                    return;
                }

                // Add real-time clock to top navbar right side
                $event->menu->add([
                    'text' => 'กำลังโหลดเวลา...',
                    'topnav_right' => true,
                    'icon' => 'far fa-clock',
                    'id' => 'navbar-clock',
                    'url' => '#',
                    'classes' => 'text-white font-weight-bold',
                ]);

                // Add refresh button to top navbar right side
                $event->menu->add([
                    'text' => '',
                    'topnav_right' => true,
                    'icon' => 'fas fa-sync-alt',
                    'id' => 'navbar-refresh-btn',
                    'url' => '#',
                    'classes' => 'text-white navbar-refresh-link',
                    'title' => 'รีเฟรชหน้าจอ (Reload)',
                    'onclick' => "var i=this.querySelector('i');if(i){i.classList.add('fa-spin');}window.location.reload();return false;",
                ]);

                if ($user->isStaff()) {
                    $event->menu->add([
                        'text' => 'หน้าหลัก',
                        'route' => 'admin.dashboard',
                        'icon' => 'fas fa-fw fa-tachometer-alt',
                        'active' => ['admin/dashboard*'],
                    ]);
                    // Check pending approvals count for this user
                    $pendingCount = 0;
                    if ($user->isAdmin()) {
                        $pendingCount = \App\Models\Exam::whereIn('approval_status', ['pending_dept', 'pending_eval', 'pending_academic'])->count();
                    } elseif ($user->isAcademicDeputy()) {
                        $pendingCount = \App\Models\Exam::where('approval_status', 'pending_academic')->count();
                    } elseif ($user->isEvaluationHead()) {
                        $pendingCount = \App\Models\Exam::where('approval_status', 'pending_eval')->count();
                    } elseif ($user->isDepartmentHead() && $user->department_id) {
                        $pendingCount = \App\Models\Exam::where('approval_status', 'pending_dept')
                            ->whereHas('subject', function($q) use ($user) {
                                $q->where(function($subQ) use ($user) {
                                    $subQ->where('department_id', $user->department_id)
                                         ->orWhereNull('department_id');
                                });
                            })->count();
                    }

                    $approvalMenuItem = [
                        'text' => 'อนุมัติข้อสอบ',
                        'route' => 'admin.approvals.index',
                        'icon' => 'fas fa-fw fa-clipboard-check',
                        'active' => ['admin/approvals*'],
                    ];
                    if ($pendingCount > 0) {
                        $approvalMenuItem['label'] = $pendingCount;
                        $approvalMenuItem['label_color'] = 'warning';
                    }

                    // Check pending grading count for this user
                    $gradingQuery = \App\Models\ExamAttempt::where('status', 'completed')
                        ->where('grading_status', 'pending_grading');
                    if ($user->isTeacher()) {
                        $gradingQuery->whereHas('exam.subject.teachers', function ($q) use ($user) {
                            $q->where('user_id', $user->id);
                        });
                    }
                    $pendingGradingCount = $gradingQuery->count();

                    $gradingMenuItem = [
                        'text' => 'ตรวจข้อสอบ',
                        'route' => 'admin.grading.index',
                        'icon' => 'fas fa-fw fa-marker',
                        'active' => ['admin/grading*'],
                    ];
                    if ($pendingGradingCount > 0) {
                        $gradingMenuItem['label'] = $pendingGradingCount;
                        $gradingMenuItem['label_color'] = 'warning';
                    }

                    $event->menu->add([
                        'text' => 'ข้อสอบ',
                        'icon' => 'fas fa-fw fa-file-signature',
                        'submenu' => array_filter([
                            [
                                'text' => 'รายวิชา',
                                'route' => 'admin.subjects.index',
                                'icon' => 'fas fa-fw fa-book',
                                'active' => ['admin/subjects*'],
                            ],
                            [
                                'text' => 'รายการข้อสอบ',
                                'route' => 'admin.exams.index',
                                'icon' => 'fas fa-fw fa-copy',
                                'active' => ['admin/exams*'],
                            ],
                            $gradingMenuItem,
                            ($user->isAdmin() || !empty($user->academic_roles)) ? $approvalMenuItem : null,
                        ])
                    ]);
                    if ($user->isAdmin()) {
                        $userParam = request()->route('user');
                        $isStudentUser = false;
                        if ($userParam instanceof \App\Models\User) {
                            $isStudentUser = $userParam->isStudent();
                        } elseif (is_numeric($userParam)) {
                            $foundUser = \App\Models\User::find($userParam);
                            $isStudentUser = $foundUser && $foundUser->isStudent();
                        }

                        $isStudentActive = request()->is('admin/users*') && (
                            request('role') === 'student' ||
                            $isStudentUser
                        );
                        $isStaffActive = request()->is('admin/users*') && !$isStudentActive;

                        $event->menu->add([
                            'text' => 'ผู้ใช้งาน',
                            'icon' => 'fas fa-fw fa-users-cog',
                            'submenu' => [
                                [
                                    'text' => 'อาจารย์',
                                    'url' => 'admin/users?role=staff',
                                    'icon' => 'fas fa-fw fa-user-tie',
                                    'active' => $isStaffActive,
                                ],
                                [
                                    'text' => 'หมวดวิชา / แผนกวิชา',
                                    'route' => 'admin.departments.index',
                                    'icon' => 'fas fa-fw fa-layer-group',
                                    'active' => ['admin/departments*'],
                                ],
                                [
                                    'text' => 'นักศึกษา',
                                    'url' => 'admin/users?role=student',
                                    'icon' => 'fas fa-fw fa-user-graduate',
                                    'active' => $isStudentActive,
                                ],
                                [
                                    'text' => 'ห้องเรียน',
                                    'route' => 'admin.classrooms.index',
                                    'icon' => 'fas fa-fw fa-school',
                                    'active' => ['admin/classrooms*'],
                                ],
                            ],
                        ]);
                    }
                    $event->menu->add([
                        'text' => 'รายงาน',
                        'route' => 'admin.reports.index',
                        'icon' => 'fas fa-fw fa-chart-bar',
                        'active' => ['admin/reports*'],
                    ]);
                } else {
                    $event->menu->add([
                        'text' => 'หน้าแรกนักศึกษา',
                        'route' => 'student.dashboard',
                        'icon' => 'fas fa-fw fa-home',
                        'active' => ['student/dashboard*'],
                    ]);
                    $event->menu->add([
                        'text' => 'คู่มือการใช้งานสำหรับนักเรียน',
                        'url' => 'docs/manual_student.pdf',
                        'icon' => 'fas fa-fw fa-book-reader',
                        'target' => '_blank',
                    ]);
                }

                // Add User Profile and Logout items at the bottom of the sidebar
                $event->menu->add([
                    'text' => $user->name,
                    'icon' => 'fas fa-user-circle',
                    'url' => '#',
                    'id' => 'sidebar-user-item',
                    'submenu' => [
                        [
                            'text' => 'ออกจากระบบ',
                            'icon' => 'fas fa-sign-out-alt text-danger',
                            'url' => '#',
                            'id' => 'sidebar-logout-item',
                        ]
                    ]
                ]);
            }
        );
    }
}
