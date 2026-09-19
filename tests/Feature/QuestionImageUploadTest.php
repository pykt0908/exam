<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class QuestionImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Clean up any test images created in public/uploads/questions
        $testUploadsDir = public_path('uploads/questions');
        if (File::isDirectory($testUploadsDir)) {
            $files = File::files($testUploadsDir);
            foreach ($files as $file) {
                if (str_contains($file->getFilename(), 'test_img_')) {
                    File::delete($file->getPathname());
                }
            }
        }

        parent::tearDown();
    }

    public function test_admin_can_upload_question_image_and_access_via_serve_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $fakeImage = UploadedFile::fake()->image('test_img_sample.png', 300, 200);

        $response = $this->actingAs($admin)
            ->post(route('admin.exams.questions.upload-image'), [
                'image' => $fakeImage,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['url']);

        $url = $response->json('url');
        $this->assertNotEmpty($url);

        // Extract path or filename
        $filename = basename(parse_url($url, PHP_URL_PATH));

        // Test that GET /uploads/questions/{filename} returns 200 with image/png
        $imageResponse = $this->get('/uploads/questions/' . $filename);
        $imageResponse->assertStatus(200);
        $this->assertEquals('image/png', $imageResponse->headers->get('content-type'));

        // Clean up
        $filePath = public_path('uploads/questions/' . $filename);
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    public function test_guest_cannot_upload_image(): void
    {
        $fakeImage = UploadedFile::fake()->image('sample.jpg');

        $response = $this->post(route('admin.exams.questions.upload-image'), [
            'image' => $fakeImage,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_serve_image_returns_404_for_non_existent_file(): void
    {
        $response = $this->get('/uploads/questions/non_existent_file_99999.png');
        $response->assertStatus(404);
    }

    public function test_serve_image_prevents_directory_traversal(): void
    {
        $response = $this->get('/uploads/questions/../../.env');
        $response->assertStatus(404);
    }
}
