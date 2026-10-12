<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionChoiceRichTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_question_with_html_pinyin_and_math_choices(): void
    {
        $teacher = User::create([
            'name' => 'Teacher Somchai',
            'teacher_code' => 'T2001',
            'password' => bcrypt('password'),
            'role' => 'teacher',
        ]);

        $subject = Subject::create([
            'code' => 'CN101',
            'name' => 'ภาษาจีนพื้นฐาน',
        ]);
        $subject->teachers()->attach($teacher->id);

        $exam = Exam::create([
            'subject_id' => $subject->id,
            'title' => 'แบบทดสอบพินอินและคณิตศาสตร์',
            'status' => 'draft',
            'duration_minutes' => 60,
            'passing_percentage' => 50,
            'total_score' => 10,
        ]);
        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'ตอนที่ 1',
            'sort_order' => 1,
        ]);

        $pinyinChoice = '<p><ruby>汉语<rt>hànyǔ</rt></ruby></p>';
        $mathChoice = '<p>f(x) = x<sup>2</sup> + 2x + 1</p>';

        $response = $this->actingAs($teacher)->postJson(route('admin.exams.questions.store', $exam->id), [
            'exam_section_id' => $section->id,
            'question_text' => '<p>จงเลือกคำตอบที่มีพินอินถูกต้อง</p>',
            'score' => 1.0,
            'type' => 'choice',
            'choices' => [
                $pinyinChoice,
                $mathChoice,
            ],
            'choice_images' => ['', ''],
            'correct_choice' => 0,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $question = Question::with('choices')->first();
        $this->assertNotNull($question);
        $this->assertCount(2, $question->choices);
        $this->assertEquals($pinyinChoice, $question->choices[0]->choice_text);
        $this->assertTrue((bool)$question->choices[0]->is_correct);
        $this->assertEquals($mathChoice, $question->choices[1]->choice_text);
        $this->assertFalse((bool)$question->choices[1]->is_correct);
    }
}
