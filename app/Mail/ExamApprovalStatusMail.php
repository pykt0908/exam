<?php

namespace App\Mail;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExamApprovalStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public ?Exam $exam;
    public string $type;
    public User $recipient;
    public ?string $comment;
    public ?string $actionBy;
    public ?string $stageName;
    public string $actionUrl;

    /**
     * Create a new message instance.
     *
     * @param Exam|null $exam
     * @param string $type submitted | approved_step | approved_final | rejected | test
     * @param User $recipient
     * @param string|null $comment
     * @param string|null $actionBy
     * @param string|null $stageName
     * @param string|null $actionUrl
     */
    public function __construct(
        ?Exam $exam,
        string $type,
        User $recipient,
        ?string $comment = null,
        ?string $actionBy = null,
        ?string $stageName = null,
        ?string $actionUrl = null
    ) {
        $this->exam = $exam;
        $this->type = $type;
        $this->recipient = $recipient;
        $this->comment = $comment;
        $this->actionBy = $actionBy;
        $this->stageName = $stageName;
        $this->actionUrl = $actionUrl ?: url('/');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subjectCode = $this->exam?->subject?->code ?? '';
        $subjectName = $this->exam?->subject?->name ?? 'วิชา';
        $examTitle = $this->exam?->title ?? '';

        $subject = match ($this->type) {
            'submitted' => "[ระบบสอบ] มีข้อสอบใหม่รอการอนุมัติ: {$subjectCode} {$subjectName} ({$examTitle})",
            'approved_step' => "[ระบบสอบ] ข้อสอบผ่านการอนุมัติขั้น {$this->stageName}: {$subjectName}",
            'approved_final' => "[ระบบสอบ] ข้อสอบได้รับการอนุมัติเสร็จสมบูรณ์: {$subjectCode} {$subjectName}",
            'rejected' => "[ระบบสอบ] ข้อสอบถูกส่งกลับแก้ไข: {$subjectCode} {$subjectName} ({$examTitle})",
            'test' => "[ระบบสอบ] ทดสอบการส่งอีเมลแจ้งเตือน - วิทยาลัยเทคโนโลยีศรีราชา",
            default => "[ระบบสอบ] แจ้งเตือนสถานะข้อสอบ: {$subjectName}",
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.exam_approval_notification',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
