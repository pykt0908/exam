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
                    'class' => 'text-white font-weight-bold',
                ]);

                if ($user->isStaff()) {
                    $event->menu->add([
                        'text' => 'หน้าหลัก',
                        'route' => 'admin.dashboard',
                        'icon' => 'fas fa-fw fa-tachometer-alt',
                    ]);
                    $event->menu->add(['header' => 'ระบบการสอบ']);
                    $event->menu->add([
                        'text' => 'ข้อสอบ',
                        'icon' => 'fas fa-fw fa-file-signature',
                        'submenu' => [
                            [
                                'text' => 'รายการข้อสอบ',
                                'route' => 'admin.exams.index',
                                'icon' => 'fas fa-fw fa-copy',
                            ],
                            [
                                'text' => 'รายวิชา',
                                'route' => 'admin.subjects.index',
                                'icon' => 'fas fa-fw fa-book',
                            ],
                        ]
                    ]);
                    if ($user->isAdmin()) {
                        $event->menu->add([
                            'text' => 'ผู้ใช้งาน',
                            'icon' => 'fas fa-fw fa-users-cog',
                            'submenu' => [
                                [
                                    'text' => 'อาจารย์',
                                    'url' => 'admin/users?role=staff',
                                    'icon' => 'fas fa-fw fa-user-tie',
                                ],
                                [
                                    'text' => 'นักศึกษา',
                                    'url' => 'admin/users?role=student',
                                    'icon' => 'fas fa-fw fa-user-graduate',
                                ],
                                [
                                    'text' => 'ห้องเรียน',
                                    'route' => 'admin.classrooms.index',
                                    'icon' => 'fas fa-fw fa-school',
                                ],
                            ],
                        ]);
                    }
                    $event->menu->add([
                        'text' => 'รายงาน',
                        'route' => 'admin.reports.index',
                        'icon' => 'fas fa-fw fa-chart-bar',
                    ]);
                } else {
                    $event->menu->add([
                        'text' => 'หน้าแรกนักศึกษา',
                        'route' => 'student.dashboard',
                        'icon' => 'fas fa-fw fa-home',
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
