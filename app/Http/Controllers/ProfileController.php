<?php

namespace App\Http\Controllers;

use App\Mail\ExamApprovalStatusMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class ProfileController extends Controller
{
    /**
     * Display user profile and notification settings.
     */
    public function index()
    {
        $user = Auth::user();
        return view('profile.index', compact('user'));
    }

    /**
     * Update user profile and notification settings.
     */
    public function update(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notification_email' => ['nullable', 'email', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id)
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'notify_email_approval' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'กรุณากรอกชื่อ-นามสกุล',
            'notification_email.email' => 'รูปแบบอีเมลสำหรับแจ้งเตือนไม่ถูกต้อง',
            'email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'email.unique' => 'อีเมลนี้ถูกใช้งานในระบบแล้วโดยผู้ใช้อื่น',
        ]);

        $data = [
            'name' => trim($request->name),
            'notification_email' => $request->notification_email ? trim($request->notification_email) : null,
            'phone' => $request->phone ? trim($request->phone) : null,
            'notify_email_approval' => $request->boolean('notify_email_approval'),
        ];

        // หากผู้ใช้ระบุ email หลักใหม่
        if ($request->filled('email')) {
            $data['email'] = trim($request->email);
        }

        $user->update($data);

        return back()->with('success', 'บันทึกข้อมูลส่วนตัวและการตั้งค่าการแจ้งเตือนเรียบร้อยแล้ว');
    }

    /**
     * Send test email to verify user's notification email address.
     */
    public function sendTestEmail(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $targetEmail = $request->input('test_email') 
            ? trim($request->input('test_email')) 
            : $user->getEffectiveNotificationEmail();

        if (empty($targetEmail) || !filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', 'กรุณาระบุอีเมลที่ถูกต้องก่อนทดสอบการส่ง');
        }

        try {
            $mailable = new ExamApprovalStatusMail(
                null,
                'test',
                $user,
                'นี่คือข้อความทดสอบจากระบบสอบออนไลน์ หากท่านได้รับอีเมลนี้ แสดงว่าการตั้งค่าอีเมลของท่านพร้อมใช้งานสำหรับการแจ้งเตือนการอนุมัติข้อสอบแล้ว',
                'ระบบสอบออนไลน์ วิทยาลัยเทคโนโลยีศรีราชา',
                'ทดสอบระบบ',
                route('profile.index')
            );

            Mail::to($targetEmail)->send($mailable);

            return back()->with('success', "ส่งอีเมลทดสอบไปยัง {$targetEmail} เรียบร้อยแล้ว! กรุณาตรวจสอบในกล่องข้อความ (Inbox หรือ Junk/Spam)");
        } catch (Throwable $e) {
            return back()->with('error', 'ส่งอีเมลทดสอบไม่สำเร็จ: ' . $e->getMessage());
        }
    }
}
