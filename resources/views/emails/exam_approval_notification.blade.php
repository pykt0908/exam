<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แจ้งเตือนสถานะข้อสอบ - วิทยาลัยเทคโนโลยีศรีราชา</title>
</head>
<body style="margin: 0; padding: 20px 10px; background-color: #ffffff; font-family: 'Sarabun', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1f2937; line-height: 1.6;">

    <div style="max-width: 580px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 28px 24px;">

        <!-- Header: Logo & College Name (Clean, No colored background) -->
        <div style="text-align: center; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb;">
            <img src="{{ url('images/logo.png') }}" alt="โลโก้วิทยาลัยเทคโนโลยีศรีราชา" width="56" height="56" style="display: block; margin: 0 auto 10px auto; width: 56px; height: 56px; object-fit: contain;">
            <div style="font-size: 17px; font-weight: 700; color: #111827; margin: 0 0 2px 0;">วิทยาลัยเทคโนโลยีศรีราชา</div>
            <div style="font-size: 13px; color: #6b7280; margin: 0;">ระบบบริหารจัดการการสอบออนไลน์</div>
        </div>

        <!-- Body Content -->
        <div style="padding-top: 24px;">
            <div style="font-size: 15px; color: #374151; margin-bottom: 16px;">
                เรียน <strong>{{ $recipient->name }}</strong>,
            </div>

            <!-- Status Message (Simple Clean Text) -->
            <div style="margin-bottom: 20px;">
                @if($type === 'submitted')
                    <div style="font-size: 16px; font-weight: 700; color: #111827; margin-bottom: 4px;">มีข้อสอบใหม่รอการตรวจสอบและอนุมัติ</div>
                    <div style="font-size: 14px; color: #4b5563;">ครูผู้สอนได้ส่งข้อสอบเพื่อขออนุมัติในระดับ: <strong>{{ $stageName ?? 'พิจารณาข้อสอบ' }}</strong></div>
                @elseif($type === 'approved_step')
                    <div style="font-size: 16px; font-weight: 700; color: #111827; margin-bottom: 4px;">ข้อสอบผ่านการอนุมัติในขั้น: {{ $stageName }}</div>
                    <div style="font-size: 14px; color: #4b5563;">ข้อสอบได้รับการอนุมัติเรียบร้อย และกำลังส่งต่อไปยังขั้นตอนถัดไป</div>
                @elseif($type === 'approved_final')
                    <div style="font-size: 16px; font-weight: 700; color: #16a34a; margin-bottom: 4px;">ข้อสอบได้รับการอนุมัติเสร็จสมบูรณ์</div>
                    <div style="font-size: 14px; color: #4b5563;">ข้อสอบผ่านการพิจารณาครบทุกระดับเรียบร้อยแล้ว พร้อมเปิดให้นักเรียนเข้าสอบตามกำหนดการ</div>
                @elseif($type === 'rejected')
                    <div style="font-size: 16px; font-weight: 700; color: #dc2626; margin-bottom: 4px;">ข้อสอบถูกส่งกลับเพื่อให้แก้ไขปรับปรุง</div>
                    <div style="font-size: 14px; color: #4b5563;">ผู้อนุมัติได้พิจารณาและขอให้ครูผู้สอนทำการแก้ไขข้อสอบตามข้อเสนอแนะ</div>
                @elseif($type === 'test')
                    <div style="font-size: 16px; font-weight: 700; color: #111827; margin-bottom: 4px;">ทดสอบการส่งอีเมลแจ้งเตือนสำเร็จ</div>
                    <div style="font-size: 14px; color: #4b5563;">ระบบสามารถส่งอีเมลแจ้งเตือนมายังกล่องข้อความของคุณได้ถูกต้องเรียบร้อย</div>
                @endif
            </div>

            <!-- Exam Details Table -->
            @if($exam)
                <table style="width: 100%; border-collapse: collapse; margin: 16px 0 20px 0; font-size: 14px;">
                    <tr style="border-bottom: 1px solid #f3f4f6;">
                        <td style="padding: 8px 0; color: #6b7280; width: 140px; vertical-align: top;">ชื่อข้อสอบ:</td>
                        <td style="padding: 8px 0; color: #111827; font-weight: 600;">{{ $exam->title }}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f3f4f6;">
                        <td style="padding: 8px 0; color: #6b7280; vertical-align: top;">รหัสวิชา - รายวิชา:</td>
                        <td style="padding: 8px 0; color: #111827;">{{ $exam->subject?->code ?? '-' }} {{ $exam->subject?->name ?? '-' }}</td>
                    </tr>
                    @if($exam->subject?->department)
                    <tr style="border-bottom: 1px solid #f3f4f6;">
                        <td style="padding: 8px 0; color: #6b7280; vertical-align: top;">หมวดวิชา/แผนก:</td>
                        <td style="padding: 8px 0; color: #111827;">{{ $exam->subject->department->name }}</td>
                    </tr>
                    @endif
                    <tr style="border-bottom: 1px solid #f3f4f6;">
                        <td style="padding: 8px 0; color: #6b7280; vertical-align: top;">ครูผู้จัดทำ:</td>
                        <td style="padding: 8px 0; color: #111827;">{{ $exam->creator_name }}</td>
                    </tr>
                    @if($actionBy)
                    <tr style="border-bottom: 1px solid #f3f4f6;">
                        <td style="padding: 8px 0; color: #6b7280; vertical-align: top;">ผู้ดำเนินการ:</td>
                        <td style="padding: 8px 0; color: #111827; font-weight: 600;">{{ $actionBy }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding: 8px 0; color: #6b7280; vertical-align: top;">วันที่และเวลา:</td>
                        <td style="padding: 8px 0; color: #4b5563;">{{ now()->locale('th')->isoFormat('D MMMM YYYY เวลา HH:mm น.') }}</td>
                    </tr>
                </table>
            @endif

            <!-- Comment / Rejection Note Box -->
            @if(!empty($comment))
                <div style="margin: 18px 0; padding: 12px 14px; border-left: 3px solid {{ $type === 'rejected' ? '#dc2626' : '#6b7280' }}; background-color: #fafafa; font-size: 14px;">
                    <div style="font-weight: 600; color: {{ $type === 'rejected' ? '#dc2626' : '#374151' }}; margin-bottom: 4px;">
                        {{ $type === 'rejected' ? 'ข้อเสนอแนะ / เหตุผลที่ส่งกลับแก้ไข:' : 'ข้อคิดเห็นเพิ่มเติม:' }}
                    </div>
                    <div style="color: #1f2937; white-space: pre-line;">{{ $comment }}</div>
                </div>
            @endif

            <!-- Action Button -->
            <div style="text-align: center; margin: 26px 0 16px 0;">
                <a href="{{ $actionUrl }}" style="display: inline-block; background-color: #1f2937; color: #ffffff; text-decoration: none; padding: 10px 24px; border-radius: 6px; font-size: 14px; font-weight: 600;">
                    @if($type === 'submitted')
                        เข้าตรวจสอบและอนุมัติข้อสอบ
                    @elseif($type === 'rejected')
                        เข้าสู่ระบบและแก้ไขข้อสอบ
                    @else
                        ดูรายละเอียดข้อสอบ
                    @endif
                </a>
            </div>

            <div style="font-size: 12px; color: #9ca3af; text-align: center; margin-top: 10px;">
                หากคลิกปุ่มไม่ได้ สามารถเปิดลิงก์: <a href="{{ $actionUrl }}" style="color: #4b5563; word-break: break-all;">{{ $actionUrl }}</a>
            </div>
        </div>

        <!-- Footer -->
        <div style="border-top: 1px solid #e5e7eb; margin-top: 28px; padding-top: 16px; text-align: center; font-size: 12px; color: #9ca3af; line-height: 1.5;">
            <div>วิทยาลัยเทคโนโลยีศรีราชา (Technological College Sriracha)</div>
            <div>อีเมลนี้เป็นการแจ้งเตือนอัตโนมัติจากระบบสอบออนไลน์ กรุณาอย่าตอบกลับ</div>
        </div>

    </div>

</body>
</html>
