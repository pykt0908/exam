<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แจ้งเตือนสถานะข้อสอบ - วิทยาลัยเทคโนโลยีศรีราชา</title>
    <style>
        body {
            font-family: 'Sarabun', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f6f9;
            color: #333333;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .email-container {
            max-width: 620px;
            margin: 25px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            border: 1px solid #e1e8ed;
        }
        .header {
            background: linear-gradient(135deg, #1a365d 0%, #2b6cb0 100%);
            padding: 24px 20px;
            text-align: center;
            color: #ffffff;
        }
        .logo-wrap {
            margin-bottom: 12px;
        }
        .logo-img {
            max-height: 80px;
            width: auto;
            border-radius: 50%;
            background: #ffffff;
            padding: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .header h1 {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 4px 0;
            letter-spacing: 0.5px;
        }
        .header p {
            font-size: 13px;
            margin: 0;
            opacity: 0.9;
            color: #e2e8f0;
        }
        .content {
            padding: 28px 24px;
        }
        .status-badge-box {
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 22px;
            text-align: center;
        }
        .status-submitted {
            background-color: #fefcbf;
            border-left: 5px solid #d69e2e;
            color: #744210;
        }
        .status-approved_step {
            background-color: #ebf8ff;
            border-left: 5px solid #3182ce;
            color: #2b6cb0;
        }
        .status-approved_final {
            background-color: #c6f6d5;
            border-left: 5px solid #38a169;
            color: #22543d;
        }
        .status-rejected {
            background-color: #fed7d7;
            border-left: 5px solid #e53e3e;
            color: #9b2c2c;
        }
        .status-test {
            background-color: #e9d8fd;
            border-left: 5px solid #805ad5;
            color: #44337a;
        }
        .status-title {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .status-desc {
            font-size: 14px;
            margin: 0;
        }
        .greeting {
            font-size: 15px;
            margin-bottom: 18px;
            color: #2d3748;
        }
        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 18px;
            margin-bottom: 22px;
        }
        .info-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 14px;
        }
        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .info-label {
            width: 140px;
            font-weight: 600;
            color: #4a5568;
            flex-shrink: 0;
        }
        .info-value {
            color: #1a202c;
            flex-grow: 1;
        }
        .comment-box {
            background-color: #fffaf0;
            border: 1px solid #feebc8;
            border-left: 4px solid #dd6b20;
            border-radius: 6px;
            padding: 14px 16px;
            margin-bottom: 24px;
        }
        .comment-box.rejected {
            background-color: #fff5f5;
            border: 1px solid #fed7d7;
            border-left: 4px solid #e53e3e;
        }
        .comment-title {
            font-weight: 700;
            font-size: 14px;
            color: #c53030;
            margin-bottom: 6px;
        }
        .comment-body {
            font-size: 14px;
            color: #2d3748;
            white-space: pre-line;
            line-height: 1.5;
        }
        .btn-wrap {
            text-align: center;
            margin: 30px 0 20px 0;
        }
        .btn-action {
            display: inline-block;
            background: linear-gradient(135deg, #2b6cb0 0%, #1a365d 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(43, 108, 176, 0.3);
            transition: all 0.2s ease;
        }
        .footer {
            background-color: #edf2f7;
            padding: 18px 20px;
            text-align: center;
            font-size: 12px;
            color: #718096;
            border-top: 1px solid #e2e8f0;
        }
        .footer a {
            color: #3182ce;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header with College Logo -->
        <div class="header">
            <div class="logo-wrap">
                @if(file_exists(public_path('images/logo.png')))
                    <img src="{{ $message->embed(public_path('images/logo.png')) }}" alt="โลโก้วิทยาลัยเทคโนโลยีศรีราชา" class="logo-img">
                @else
                    <div style="font-size: 32px; font-weight: bold; color: #fff;">TECHNO</div>
                @endif
            </div>
            <h1>วิทยาลัยเทคโนโลยีศรีราชา</h1>
            <p>ระบบบริหารจัดการการสอบออนไลน์ (Online Examination System)</p>
        </div>

        <!-- Body Content -->
        <div class="content">
            <div class="greeting">
                เรียน <strong>{{ $recipient->name }}</strong>,
            </div>

            <!-- Status Banner -->
            @if($type === 'submitted')
                <div class="status-badge-box status-submitted">
                    <div class="status-title">📥 มีข้อสอบใหม่รอการตรวจสอบและอนุมัติ</div>
                    <div class="status-desc">ครูผู้สอนได้ส่งข้อสอบเพื่อขออนุมัติในระดับ: <strong>{{ $stageName ?? 'พิจารณาข้อสอบ' }}</strong></div>
                </div>
            @elseif($type === 'approved_step')
                <div class="status-badge-box status-approved_step">
                    <div class="status-title">✅ ข้อสอบผ่านการอนุมัติในขั้น: {{ $stageName }}</div>
                    <div class="status-desc">ข้อสอบได้รับการอนุมัติเรียบร้อย และกำลังส่งต่อไปยังขั้นตอนถัดไป</div>
                </div>
            @elseif($type === 'approved_final')
                <div class="status-badge-box status-approved_final">
                    <div class="status-title">🎉 ข้อสอบได้รับการอนุมัติขั้นสุดท้ายเสร็จสมบูรณ์</div>
                    <div class="status-desc">ข้อสอบผ่านการพิจารณาครบทุกระดับเรียบร้อยแล้ว พร้อมเปิดให้นักเรียนเข้าสอบตามกำหนดการ</div>
                </div>
            @elseif($type === 'rejected')
                <div class="status-badge-box status-rejected">
                    <div class="status-title">⚠️ ข้อสอบถูกส่งกลับเพื่อให้แก้ไขปรับปรุง</div>
                    <div class="status-desc">ผู้อนุมัติได้พิจารณาและขอให้ครูผู้สอนทำการแก้ไขข้อสอบตามข้อเสนอแนะ</div>
                </div>
            @elseif($type === 'test')
                <div class="status-badge-box status-test">
                    <div class="status-title">🔔 ทดสอบการแจ้งเตือนทางอีเมลสำเร็จ</div>
                    <div class="status-desc">ระบบสามารถส่งอีเมลแจ้งเตือนมายังกล่องข้อความของคุณได้ถูกต้องเรียบร้อย</div>
                </div>
            @endif

            <!-- Exam Details Card -->
            @if($exam)
                <div class="info-card">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 6px 0; width: 140px; font-weight: 600; color: #4a5568; vertical-align: top;">ชื่อข้อสอบ:</td>
                            <td style="padding: 6px 0; font-weight: 700; color: #1a365d;">{{ $exam->title }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; font-weight: 600; color: #4a5568; vertical-align: top;">รหัสวิชา - รายวิชา:</td>
                            <td style="padding: 6px 0; color: #2d3748;">{{ $exam->subject?->code ?? '-' }} {{ $exam->subject?->name ?? '-' }}</td>
                        </tr>
                        @if($exam->subject?->department)
                        <tr>
                            <td style="padding: 6px 0; font-weight: 600; color: #4a5568; vertical-align: top;">หมวดวิชา/แผนก:</td>
                            <td style="padding: 6px 0; color: #2d3748;">{{ $exam->subject->department->name }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td style="padding: 6px 0; font-weight: 600; color: #4a5568; vertical-align: top;">ครูผู้จัดทำ:</td>
                            <td style="padding: 6px 0; color: #2d3748;">{{ $exam->creator_name }}</td>
                        </tr>
                        @if($actionBy)
                        <tr>
                            <td style="padding: 6px 0; font-weight: 600; color: #4a5568; vertical-align: top;">ผู้ดำเนินการ:</td>
                            <td style="padding: 6px 0; color: #2b6cb0; font-weight: 600;">{{ $actionBy }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td style="padding: 6px 0; font-weight: 600; color: #4a5568; vertical-align: top;">วันที่และเวลา:</td>
                            <td style="padding: 6px 0; color: #718096;">{{ now()->locale('th')->isoFormat('D MMMM YYYY เวลา HH:mm น.') }}</td>
                        </tr>
                    </table>
                </div>
            @endif

            <!-- Comment / Rejection Note Box -->
            @if(!empty($comment))
                <div class="comment-box {{ $type === 'rejected' ? 'rejected' : '' }}">
                    <div class="comment-title">
                        @if($type === 'rejected')
                            📌 ข้อเสนอแนะ / เหตุผลที่ส่งกลับแก้ไข:
                        @else
                            💬 ข้อคิดเห็นเพิ่มเติม:
                        @endif
                    </div>
                    <div class="comment-body">"{{ $comment }}"</div>
                </div>
            @endif

            <!-- Action Button -->
            <div class="btn-wrap">
                <a href="{{ $actionUrl }}" class="btn-action" target="_blank">
                    @if($type === 'submitted')
                        คลิกเพื่อเข้าตรวจสอบและอนุมัติข้อสอบ
                    @elseif($type === 'rejected')
                        คลิกเพื่อเข้าสู่ระบบและแก้ไขข้อสอบ
                    @else
                        คลิกเพื่อดูรายละเอียดข้อสอบ
                    @endif
                </a>
            </div>
            
            <p style="font-size: 13px; color: #718096; text-align: center; margin-top: 15px;">
                หากปุ่มด้านบนกดไม่ได้ สามารถคัดลอกลิงก์นี้ไปเปิดในเบราว์เซอร์:<br>
                <a href="{{ $actionUrl }}" style="color: #3182ce; word-break: break-all;">{{ $actionUrl }}</a>
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p style="margin: 0 0 6px 0; font-weight: 600; color: #4a5568;">วิทยาลัยเทคโนโลยีศรีราชา (Technological College Sriracha)</p>
            <p style="margin: 0 0 8px 0;">อีเมลนี้เป็นการแจ้งเตือนอัตโนมัติจากระบบสอบออนไลน์ กรุณาอย่าตอบกลับอีเมลนี้โดยตรง</p>
            <p style="margin: 0;">ท่านสามารถจัดการการรับอีเมลแจ้งเตือนได้ที่ <a href="{{ route('profile.index') }}">ข้อมูลส่วนตัวของฉัน</a></p>
        </div>
    </div>
</body>
</html>
