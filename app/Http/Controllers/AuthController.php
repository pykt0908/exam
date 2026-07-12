<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectUser();
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login_identifier' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $loginIdentifier = $request->login_identifier;
        if (filter_var($loginIdentifier, FILTER_VALIDATE_EMAIL)) {
            $loginField = 'email';
        } else {
            // Check if user exists with this teacher_code (for admin/teacher)
            $userExistsWithTeacherCode = User::where('teacher_code', $loginIdentifier)->exists();
            $loginField = $userExistsWithTeacherCode ? 'teacher_code' : 'student_code';
        }

        $credentials = [
            $loginField => $loginIdentifier,
            'password' => $request->password,
        ];

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return $this->redirectUser();
        }

        return back()->withErrors([
            'login_identifier' => 'ข้อมูลผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง',
        ])->onlyInput('login_identifier');
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return $this->redirectUser();
        }
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'student_code' => ['required', 'string', 'max:50', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.unique' => 'อีเมลนี้ถูกใช้งานแล้ว',
            'student_code.unique' => 'รหัสนักศึกษานี้ถูกใช้งานแล้ว',
            'password.confirmed' => 'รหัสผ่านยืนยันไม่ตรงกัน',
            'password.min' => 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'student_code' => $request->student_code,
            'password' => Hash::make($request->password),
            'role' => 'student', // Registration is only for students
        ]);

        Auth::login($user);

        return redirect()->route('student.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    private function redirectUser()
    {
        if (Auth::user()->isStaff()) {
            return redirect()->route('admin.dashboard');
        }
        return redirect()->route('student.dashboard');
    }
}
