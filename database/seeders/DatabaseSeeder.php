<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Subject;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Choice;
use App\Models\Classroom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Classrooms
        $class1 = Classroom::create(['name' => 'ปวช. 1/1']);
        $class2 = Classroom::create(['name' => 'ปวช. 1/2']);
        $class3 = Classroom::create(['name' => 'ปวส. 1/1']);
        $class4 = Classroom::create(['name' => 'ปวส. 2/3']);

        // 2. Create Users (Staff & Students)
        $admin = User::create([
            'name' => 'อาจารย์ใจดี มีสุข',
            'email' => 'admin@exam.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'teacher_code' => 't69001',
        ]);

        $teacher = User::create([
            'name' => 'อาจารย์วันดี เรียนสอน',
            'email' => 'teacher@exam.com',
            'password' => Hash::make('password'),
            'role' => 'teacher',
            'teacher_code' => 't69005',
        ]);

        $student = User::create([
            'name' => 'สมชาย เรียนดี',
            'email' => null,
            'student_code' => '66309010001',
            'classroom_id' => $class1->id,
            'citizen_id' => '1234567890123',
            'password' => Hash::make('1234567890123'),
            'role' => 'student',
        ]);

        // 3. Create Subjects
        $subject1 = Subject::create([
            'code' => 'BC-301',
            'name' => 'การพัฒนาเว็บแอปพลิเคชัน (Web Application Development)',
        ]);

        $subject2 = Subject::create([
            'code' => 'BC-302',
            'name' => 'ระบบจัดการฐานข้อมูล (Database Management Systems)',
        ]);

        // 4. Create Exam for Subject 1
        $exam = Exam::create([
            'subject_id' => $subject1->id,
            'title' => 'สอบกลางภาควิชาการพัฒนาเว็บแอปพลิเคชัน',
            'description' => 'ข้อสอบกลางภาค 5 ข้อ เวลาทำข้อสอบ 10 นาที',
            'duration_minutes' => 10,
            'passing_percentage' => 60,
            'is_active' => true,
        ]);

        // 5. Create Questions & Choices
        $q1 = Question::create([
            'exam_id' => $exam->id,
            'question_text' => 'PHP ย่อมาจากอะไร?',
            'score' => 1.0,
        ]);
        Choice::create(['question_id' => $q1->id, 'choice_text' => 'Personal Home Page', 'is_correct' => false]);
        Choice::create(['question_id' => $q1->id, 'choice_text' => 'PHP: Hypertext Preprocessor', 'is_correct' => true]);
        Choice::create(['question_id' => $q1->id, 'choice_text' => 'Private Host Page', 'is_correct' => false]);
        Choice::create(['question_id' => $q1->id, 'choice_text' => 'Pre-Hypertext Processor', 'is_correct' => false]);

        $q2 = Question::create([
            'exam_id' => $exam->id,
            'question_text' => 'ภาษาใดที่ทำงานฝั่งไคลเอนต์ (Client-side) ในเว็บเบราว์เซอร์เป็นหลัก?',
            'score' => 1.0,
        ]);
        Choice::create(['question_id' => $q2->id, 'choice_text' => 'PHP', 'is_correct' => false]);
        Choice::create(['question_id' => $q2->id, 'choice_text' => 'Python', 'is_correct' => false]);
        Choice::create(['question_id' => $q2->id, 'choice_text' => 'JavaScript', 'is_correct' => true]);
        Choice::create(['question_id' => $q2->id, 'choice_text' => 'SQL', 'is_correct' => false]);

        $q3 = Question::create([
            'exam_id' => $exam->id,
            'question_text' => 'คำสั่งใดใน Laravel ที่ใช้สำหรับการสร้างไฟล์ Migration ใหม่?',
            'score' => 1.0,
        ]);
        Choice::create(['question_id' => $q3->id, 'choice_text' => 'php artisan make:migration', 'is_correct' => true]);
        Choice::create(['question_id' => $q3->id, 'choice_text' => 'php artisan create:migration', 'is_correct' => false]);
        Choice::create(['question_id' => $q3->id, 'choice_text' => 'php artisan migration:make', 'is_correct' => false]);
        Choice::create(['question_id' => $q3->id, 'choice_text' => 'php artisan run:migration', 'is_correct' => false]);

        $q4 = Question::create([
            'exam_id' => $exam->id,
            'question_text' => 'ในการเชื่อมต่อฐานข้อมูลใน Laravel ไฟล์กำหนดค่า (Configuration) หลักคือไฟล์ใด?',
            'score' => 1.0,
        ]);
        Choice::create(['question_id' => $q4->id, 'choice_text' => 'config/database.php', 'is_correct' => false]);
        Choice::create(['question_id' => $q4->id, 'choice_text' => '.env', 'is_correct' => true]);
        Choice::create(['question_id' => $q4->id, 'choice_text' => 'routes/web.php', 'is_correct' => false]);
        Choice::create(['question_id' => $q4->id, 'choice_text' => 'composer.json', 'is_correct' => false]);

        $q5 = Question::create([
            'exam_id' => $exam->id,
            'question_text' => 'CSS ย่อมาจากอะไร?',
            'score' => 1.0,
        ]);
        Choice::create(['question_id' => $q5->id, 'choice_text' => 'Creative Style Sheets', 'is_correct' => false]);
        Choice::create(['question_id' => $q5->id, 'choice_text' => 'Cascading Style Sheets', 'is_correct' => true]);
        Choice::create(['question_id' => $q5->id, 'choice_text' => 'Computer Style Sheets', 'is_correct' => false]);
        Choice::create(['question_id' => $q5->id, 'choice_text' => 'Colorful Style Sheets', 'is_correct' => false]);
    }
}
