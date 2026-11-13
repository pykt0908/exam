<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // ดักจับกรณี Session หมดอายุ / CSRF Token ไม่ตรง (Error 419 Page Expired)
        // แทนที่จะแสดงหน้าขาวดำ Error 419 ให้ Redirect กลับมาที่หน้า Login พร้อมข้อความภาษาไทยเข้าใจง่าย
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, Request $request) {
            if ($request->is('login') || $request->routeIs('login')) {
                return redirect()->route('login')
                    ->withInput($request->except('password', '_token'))
                    ->with('error', 'เซสชันหมดอายุเนื่องจากเปิดหน้าจอนานเกินไป กรุณากรอกรหัสผ่านเพื่อเข้าสู่ระบบอีกครั้ง');
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'error' => 'session_expired',
                    'message' => 'เซสชันหมดอายุ กรุณารีเฟรชหน้าเว็บหรือเข้าสู่ระบบใหม่',
                ], 419);
            }

            return redirect()->route('login')
                ->with('error', 'เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
        });
    })->create();
