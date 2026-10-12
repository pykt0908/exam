<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบปะหน้าข้อสอบ - {{ $exam->title }}</title>
    <!-- Google Fonts & Font Awesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <style>
        /* TH Sarabun New Local Fonts */
        @font-face {
            font-family: 'TH Sarabun New';
            src: url("{{ asset('fonts/TH-Sarabun-New/THSarabunNew.ttf') }}") format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'TH Sarabun New';
            src: url("{{ asset('fonts/TH-Sarabun-New/THSarabunNew%20Bold.ttf') }}") format('truetype'),
                url("{{ asset('fonts/TH-Sarabun-New/THSarabunNew Bold.ttf') }}") format('truetype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'TH Sarabun New';
            src: url("{{ asset('fonts/TH-Sarabun-New/THSarabunNew%20Italic.ttf') }}") format('truetype'),
                url("{{ asset('fonts/TH-Sarabun-New/THSarabunNew Italic.ttf') }}") format('truetype');
            font-weight: 400;
            font-style: italic;
            font-display: swap;
        }

        @font-face {
            font-family: 'TH Sarabun New';
            src: url("{{ asset('fonts/TH-Sarabun-New/THSarabunNew%20BoldItalic.ttf') }}") format('truetype'),
                url("{{ asset('fonts/TH-Sarabun-New/THSarabunNew BoldItalic.ttf') }}") format('truetype');
            font-weight: 700;
            font-style: italic;
            font-display: swap;
        }

        @font-face {
            font-family: 'THSarabunNew';
            src: url("{{ asset('fonts/TH-Sarabun-New/THSarabunNew.ttf') }}") format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'THSarabunNew';
            src: url("{{ asset('fonts/TH-Sarabun-New/THSarabunNew%20Bold.ttf') }}") format('truetype'),
                url("{{ asset('fonts/TH-Sarabun-New/THSarabunNew Bold.ttf') }}") format('truetype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'THSarabunNew';
            src: url("{{ asset('fonts/TH-Sarabun-New/THSarabunNew%20Italic.ttf') }}") format('truetype'),
                url("{{ asset('fonts/TH-Sarabun-New/THSarabunNew Italic.ttf') }}") format('truetype');
            font-weight: 400;
            font-style: italic;
            font-display: swap;
        }

        @font-face {
            font-family: 'THSarabunNew';
            src: url("{{ asset('fonts/TH-Sarabun-New/THSarabunNew%20BoldItalic.ttf') }}") format('truetype'),
                url("{{ asset('fonts/TH-Sarabun-New/THSarabunNew BoldItalic.ttf') }}") format('truetype');
            font-weight: 700;
            font-style: italic;
            font-display: swap;
        }

        :root {
            --font-main: 'TH Sarabun New', 'THSarabunNew', Tahoma, sans-serif;
        }

        body {
            font-family: var(--font-main);
            background-color: #525659;
            color: #111;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* App Layout: Left Sidebar + Right Preview */
        .app-layout {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        /* Left Sidebar Controls */
        .sidebar-controls {
            width: 310px;
            min-width: 310px;
            max-width: 310px;
            background: #ffffff;
            height: 100vh;
            position: sticky;
            top: 0;
            overflow-y: auto;
            border-right: 1px solid #ced4da;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.06);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            padding: 14px 14px;
        }

        /* Right Preview Area */
        .preview-area {
            flex: 1;
            padding: 20px 16px;
            overflow: auto;
            display: flex;
            justify-content: center;
            background-color: #525659;
        }

        /* Printable Sheet Container */
        .sheets-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .sheet-page {
            background: #ffffff;
            width: 210mm;
            min-height: 297mm;
            box-sizing: border-box;
            padding: 5mm 8mm 6mm 8mm;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.35);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            page-break-after: always;
            break-after: page;
        }

        .page-footer-indicator {
            margin-top: 8px;
            padding-top: 2px;
            text-align: right;
            font-size: 13.5px;
            color: #555;
            width: 100%;
        }

        /* Header Styling */
        .college-logo {
            height: 68px;
            width: auto;
            object-fit: contain;
            margin-bottom: 2px;
            display: inline-block;
        }

        .college-title {
            font-size: 38px;
            font-weight: 700;
            text-align: center;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            color: #000;
            line-height: 1.15;
        }

        .college-title-en {
            font-size: 17px;
            font-weight: 700;
            text-align: center;
            letter-spacing: 1.2px;
            color: #2b2b2b;
            margin-top: -2px;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        /* Combined Exam Header Line (ชื่อข้อสอบ, รายวิชา, วันสอบ, ห้องสอบ บรรทัดเดียวกัน ไม่มีกรอบ) */
        .exam-header-line {
            display: flex;
            align-items: baseline;
            justify-content: center;
            flex-wrap: wrap;
            gap: 6px 12px;
            font-size: 21.5px;
            padding: 0;
            margin: 2px auto 10px auto;
            width: 100%;
            border: none;
            background: transparent;
        }

        .exam-header-line .exam-title-inline {
            font-size: 24px;
            font-weight: 700;
            color: #000;
            white-space: nowrap;
        }

        .exam-header-line .keyinfo-item {
            display: inline-flex;
            align-items: baseline;
            gap: 4px;
            white-space: nowrap;
        }

        .exam-header-line .keyinfo-label {
            font-weight: 700;
            color: #000;
        }

        .exam-header-line .keyinfo-val {
            color: #000;
        }

        .exam-header-line .keyinfo-sep {
            color: #555;
            font-weight: 700;
            user-select: none;
        }

        .page2-header-college {
            font-size: 15px;
            line-height: 1.15;
            letter-spacing: 0.3px;
        }

        /* Clean Document Layout for Page 1 (No outer border) */
        .doc-card {
            border: none;
            padding: 4px 0;
            background: transparent;
            margin-bottom: 6px;
            width: 100%;
            box-sizing: border-box;
        }

        .doc-row {
            display: flex;
            align-items: baseline;
            margin-bottom: 10px;
            font-size: 25.5px;
            line-height: 1.4;
        }

        .doc-label {
            font-weight: 700;
            white-space: nowrap;
            margin-right: 8px;
            color: #000;
            font-size: 25.5px;
        }

        .doc-value {
            border-bottom: 1px dotted #222;
            padding: 0 8px;
            font-weight: 500;
            min-height: 33px;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
            font-size: 25.5px;
        }

        .doc-unit {
            font-weight: 400;
            margin-left: 8px;
            white-space: nowrap;
            font-size: 25.5px;
        }

        .doc-section-divider {
            border-top: 1.5px dashed #444;
            margin: 10px 0 8px 0;
        }

        .doc-section-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #000;
            display: flex;
            align-items: center;
        }

        .signature-group {
            margin-top: 8px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            font-size: 24.5px;
            line-height: 2.1;
        }

        .signature-block {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-start;
            min-width: 450px;
        }

        .signature-row {
            display: flex;
            align-items: baseline;
            white-space: nowrap;
            width: 100%;
        }

        .sig-label {
            display: inline-block;
            min-width: 58px;
            white-space: nowrap;
        }

        .sig-role {
            white-space: nowrap;
            margin-left: 6px;
        }

        .signature-line-dotted {
            display: inline-block;
            border-bottom: 1px dotted #000;
            min-width: 240px;
            width: 240px;
            text-align: center;
            font-weight: 600;
            padding: 0 4px;
            transition: all 0.2s ease;
            font-size: 24.5px;
            box-sizing: border-box;
        }

        .page2-sig-block {
            min-width: 335px;
        }

        .page2-sig-block .sig-label {
            min-width: 48px;
        }

        /* Highlight Missing / Required Inputs */
        .highlight-needed {
            background-color: #fff3cd !important;
            color: #856404 !important;
            padding: 1px 8px !important;
            border-radius: 3px !important;
            border: 1px dashed #ffeeba !important;
            font-weight: 700 !important;
            display: inline-block !important;
        }

        /* Two Column Student Attendance Table for Page 2 */
        .student-table-container {
            display: flex;
            justify-content: space-between;
            width: 100%;
            box-sizing: border-box;
            padding-right: 1.5px;
        }

        .student-sub-table {
            width: 49.6%;
            border-collapse: collapse;
            font-size: 21px;
            table-layout: fixed;
            box-sizing: border-box;
            border: 1.5px solid #000;
        }

        .student-sub-table th {
            border: 1px solid #000;
            padding: 2px 2px;
            text-align: center;
            font-weight: 700;
            background: #f7f7f7;
            line-height: 1.1;
            font-size: 20px;
        }

        .student-sub-table td {
            border: 1px solid #000;
            padding: 0 3px;
            line-height: 1.05;
            height: 22px;
            vertical-align: middle;
            font-size: 20.5px;
            white-space: nowrap;
        }

        .student-sub-table th:first-child,
        .student-sub-table td:first-child {
            border-left: 1.5px solid #000 !important;
        }

        .student-sub-table th:last-child,
        .student-sub-table td:last-child {
            border-right: 1.5px solid #000 !important;
        }

        .dotted-line {
            border-bottom: 1px dotted #000;
            height: 21px;
            margin-bottom: 3px;
        }

        .page2-header-title {
            font-size: 27px;
        }

        .page2-exam-title {
            font-size: 22px;
        }

        .page2-info-box {
            font-size: 21px;
            line-height: 1.25;
            margin-bottom: 2px;
        }

        .page2-footer {
            font-size: 21px;
            line-height: 1.65;
            margin-top: 2px;
            padding-top: 2px;
        }

        .page2-sig-line {
            min-width: 175px !important;
            width: 175px !important;
            font-size: 21px !important;
        }

        /* Print Media Query */
        @media print {

            html,
            body {
                background: #ffffff !important;
                color: #000 !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
            }

            /* Completely remove scrollbars from all elements when printing */
            * {
                -ms-overflow-style: none !important;
                scrollbar-width: none !important;
            }

            *::-webkit-scrollbar,
            html::-webkit-scrollbar,
            body::-webkit-scrollbar,
            div::-webkit-scrollbar {
                display: none !important;
                width: 0 !important;
                height: 0 !important;
            }

            .sidebar-controls {
                display: none !important;
            }

            .app-layout {
                display: block !important;
                background: #fff !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
            }

            .preview-area {
                padding: 0 !important;
                margin: 0 !important;
                display: block !important;
                background: #fff !important;
                width: 100% !important;
                overflow: visible !important;
                height: auto !important;
            }

            .sheets-container {
                padding: 0 !important;
                gap: 0 !important;
                width: 100% !important;
                display: block !important;
                overflow: visible !important;
                height: auto !important;
            }

            .sheet-page {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                min-width: 100% !important;
                height: auto !important;
                min-height: auto !important;
                max-height: none !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: flex-start !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                box-sizing: border-box !important;
                overflow: visible !important;
            }

            .sheet-page+.sheet-page {
                page-break-before: always !important;
                break-before: page !important;
            }

            .sheet-page:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }

            .page-footer-indicator {
                margin-top: 8px !important;
                padding-top: 2px !important;
                text-align: right !important;
                font-size: 13.5px !important;
                color: #444 !important;
                width: 100% !important;
            }

            .doc-card {
                border: none !important;
                width: 100% !important;
                box-sizing: border-box !important;
                padding: 4px 0 !important;
            }

            .college-logo {
                height: 68px !important;
                display: inline-block !important;
            }

            .college-title {
                font-size: 38px !important;
                margin-bottom: 2px !important;
                line-height: 1.15 !important;
            }

            .college-title-en {
                font-size: 17px !important;
                font-weight: 700 !important;
                letter-spacing: 1.2px !important;
                color: #000 !important;
                margin-top: -2px !important;
                margin-bottom: 4px !important;
                text-transform: uppercase !important;
                display: block !important;
            }

            .exam-header-line {
                display: flex !important;
                align-items: baseline !important;
                justify-content: center !important;
                flex-wrap: wrap !important;
                gap: 5px 10px !important;
                font-size: 21.5px !important;
                padding: 0 !important;
                margin: 2px auto 10px auto !important;
                width: 100% !important;
                border: none !important;
                background: transparent !important;
                box-sizing: border-box !important;
            }

            .exam-header-line .exam-title-inline {
                font-size: 24px !important;
                font-weight: 700 !important;
                color: #000 !important;
                white-space: nowrap !important;
            }

            .exam-header-line .keyinfo-item {
                display: inline-flex !important;
                align-items: baseline !important;
                gap: 3px !important;
                white-space: nowrap !important;
            }

            .exam-header-line .keyinfo-label {
                font-weight: 700 !important;
                color: #000 !important;
            }

            .exam-header-line .keyinfo-val {
                font-weight: 700 !important;
                color: #000 !important;
            }

            .exam-header-line .keyinfo-sep {
                color: #000 !important;
                font-weight: 700 !important;
            }

            .page2-header-college {
                font-size: 15px !important;
                line-height: 1.15 !important;
                letter-spacing: 0.3px !important;
            }

            .doc-row {
                font-size: 25.5px !important;
                margin-bottom: 10px !important;
                line-height: 1.4 !important;
            }

            .doc-label,
            .doc-value,
            .doc-unit {
                font-size: 25.5px !important;
            }

            .doc-value {
                min-height: 33px !important;
            }

            .doc-section-divider {
                margin: 10px 0 8px 0 !important;
            }

            .doc-section-title {
                font-size: 28px !important;
                margin-bottom: 8px !important;
            }

            .signature-group {
                font-size: 24.5px !important;
                line-height: 2.1 !important;
                margin-top: 8px !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-end !important;
            }

            .signature-block {
                display: inline-flex !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                min-width: 450px !important;
            }

            .signature-row {
                display: flex !important;
                align-items: baseline !important;
                white-space: nowrap !important;
                width: 100% !important;
            }

            .sig-label {
                display: inline-block !important;
                min-width: 58px !important;
                white-space: nowrap !important;
            }

            .sig-role {
                white-space: nowrap !important;
                margin-left: 6px !important;
            }

            .signature-line-dotted {
                font-size: 24.5px !important;
                min-width: 240px !important;
                width: 240px !important;
                display: inline-block !important;
                border-bottom: 1px dotted #000 !important;
                text-align: center !important;
                padding: 0 4px !important;
                box-sizing: border-box !important;
            }

            .page2-sig-block {
                min-width: 335px !important;
            }

            .page2-sig-block .sig-label {
                min-width: 48px !important;
            }

            .student-table-container {
                display: flex !important;
                justify-content: space-between !important;
                width: 100% !important;
                box-sizing: border-box !important;
                padding-right: 1.5px !important;
            }

            .student-sub-table {
                width: 49.6% !important;
                border-collapse: collapse !important;
                font-size: 21px !important;
                table-layout: fixed !important;
                box-sizing: border-box !important;
                border: 1.5px solid #000 !important;
            }

            .student-sub-table th {
                font-size: 20px !important;
                padding: 2px 2px !important;
                font-weight: 700 !important;
                line-height: 1.1 !important;
                border: 1px solid #000 !important;
                background: #f7f7f7 !important;
            }

            .student-sub-table td {
                font-size: 20.5px !important;
                line-height: 1.05 !important;
                height: 22px !important;
                padding: 0 3px !important;
                white-space: nowrap !important;
                border: 1px solid #000 !important;
                vertical-align: middle !important;
            }

            .student-sub-table th:first-child,
            .student-sub-table td:first-child {
                border-left: 1.5px solid #000 !important;
            }

            .student-sub-table th:last-child,
            .student-sub-table td:last-child {
                border-right: 1.5px solid #000 !important;
            }

            .dotted-line {
                border-bottom: 1px dotted #000 !important;
                height: 21px !important;
                margin-bottom: 3px !important;
            }

            .page2-header-title {
                font-size: 27px !important;
            }

            .page2-header-college {
                font-size: 16px !important;
                line-height: 1.15 !important;
                letter-spacing: 0.3px !important;
            }

            .page2-exam-title {
                font-size: 22px !important;
            }

            .page2-info-box {
                font-size: 21px !important;
                line-height: 1.25 !important;
                margin-bottom: 2px !important;
                padding-bottom: 2px !important;
            }

            .page2-footer {
                font-size: 21px !important;
                line-height: 1.65 !important;
                margin-top: 2px !important;
                padding-top: 2px !important;
            }

            .page2-sig-line {
                min-width: 175px !important;
                width: 175px !important;
                font-size: 21px !important;
            }

            /* Remove yellow highlight completely on actual print */
            .highlight-needed {
                background-color: transparent !important;
                color: #000 !important;
                border: none !important;
                padding: 0 !important;
            }

            @page {
                size: A4 portrait;
                margin: 5mm 8mm 5mm 8mm;
            }
        }
    </style>
</head>

<body>

    <div class="app-layout">
        <!-- ============================================== -->
        <!-- ฝั่งซ้าย: ฟอร์มการกรอกข้อมูลและตั้งค่าก่อนพิมพ์ -->
        <!-- ============================================== -->
        <aside class="sidebar-controls">
            <!-- Top Actions -->
            <div class="d-flex justify-content-between align-items-center mb-2">
                <a href="{{ route('admin.exams.index') }}" class="btn btn-outline-secondary btn-xs font-weight-bold">
                    <i class="fas fa-arrow-left mr-1"></i>กลับ
                </a>
                <span class="text-xs text-muted">A4 แนวตั้ง (2 หน้า/กลุ่ม)</span>
            </div>

            <div class="mb-2">
                <h6 class="font-weight-bold text-dark mb-0">
                    <i class="fas fa-print text-primary mr-1"></i>พิมพ์ใบปะหน้าข้อสอบ
                </h6>
                <div class="text-xs text-muted text-truncate"
                    title="[{{ $exam->subject->code ?? '-' }}] {{ $exam->subject->name ?? '-' }}">
                    [{{ $exam->subject->code ?? '-' }}] {{ $exam->subject->name ?? '-' }}
                </div>
                <div class="text-xs font-weight-bold text-dark text-truncate" title="{{ $exam->title }}">
                    {{ $exam->title }}
                </div>
            </div>

            <!-- Print Button (Prominent) -->
            <button type="button" onclick="window.print()"
                class="btn btn-primary btn-block font-weight-bold shadow-sm mb-2 py-2">
                <i class="fas fa-print mr-1"></i>สั่งพิมพ์ (Print A4)
            </button>

            <!-- Classroom Selector -->
            <div class="form-group mb-2">
                <label class="font-weight-bold text-dark text-xs mb-1">
                    <i class="fas fa-users mr-1 text-primary"></i>กลุ่มเรียน:
                </label>
                <select id="classroom_filter" class="form-control form-control-sm"
                    onchange="filterClassroom(this.value)">
                    <option value="all" {{ !$selectedClassroomId ? 'selected' : '' }}>
                        พิมพ์ทุกกลุ่มเรียน ({{ count($availableClassrooms) }} กลุ่ม)
                    </option>
                    @foreach($availableClassrooms as $ac)
                        <option value="{{ $ac->id }}" {{ $selectedClassroomId == $ac->id ? 'selected' : '' }}>
                            {{ $ac->name }} ({{ $ac->eligible_count }} คน)
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="border-top pt-2 mb-1">
                <div class="text-xs font-weight-bold text-secondary mb-1">
                    <i class="fas fa-edit mr-1"></i>ข้อมูลที่ต้องกรอกหน้างาน
                </div>

                <!-- Row 1: Room & Session side-by-side -->
                <div class="form-row">
                    <div class="col-6 form-group mb-2">
                        <label class="font-weight-bold text-dark text-xs mb-1">ห้องที่สอบ:</label>
                        <input type="text" id="input_room" class="form-control form-control-sm" placeholder="" value="">
                    </div>
                    <div class="col-6 form-group mb-2">
                        <label class="font-weight-bold text-dark text-xs mb-1">ภาค:</label>
                        <select id="input_session" class="form-control form-control-sm">
                            <option value="" selected>-- เลือก --</option>
                            <option value="บ่าย">บ่าย</option>
                            <option value="เช้า">เช้า</option>

                        </select>
                    </div>
                </div>

                @php
                    $thaiDays = ['Sunday' => 'วันอาทิตย์', 'Monday' => 'วันจันทร์', 'Tuesday' => 'วันอังคาร', 'Wednesday' => 'วันพุธ', 'Thursday' => 'วันพฤหัสบดี', 'Friday' => 'วันศุกร์', 'Saturday' => 'วันเสาร์'];
                    $thaiMonths = [1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'];

                    $defaultDateStr = '';
                    $defaultTimeStr = '';
                    $defaultEndTimeStr = '';

                    if ($exam->starts_at) {
                        $dName = $thaiDays[$exam->starts_at->format('l')] ?? '';
                        $mName = $thaiMonths[(int) $exam->starts_at->format('n')] ?? '';
                        $yearBe = (int) $exam->starts_at->format('Y') + 543;
                        $defaultDateStr = "{$dName}ที่ {$exam->starts_at->format('j')} {$mName} {$yearBe}";
                        $defaultTimeStr = $exam->starts_at->format('H.i');
                        if ($exam->ends_at) {
                            $defaultEndTimeStr = $exam->ends_at->format('H.i');
                        }
                    }
                @endphp

                <!-- Exam Date Input -->
                <div class="form-group mb-2">
                    <label class="font-weight-bold text-dark text-xs mb-1">สอบวันที่:</label>
                    <input type="text" id="input_date" class="form-control form-control-sm"
                        value="{{ $defaultDateStr }}" placeholder="เช่น วันอาทิตย์ที่ 13 กันยายน 2569">
                </div>

                <!-- Exam Time Input -->
                <div class="form-group mb-2">
                    <label class="font-weight-bold text-dark text-xs mb-1">เวลาสอบ:</label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="input_time_start" class="form-control form-control-sm"
                            value="{{ $defaultTimeStr }}" placeholder="14.00">
                        <div class="input-group-prepend input-group-append">
                            <span class="input-group-text px-1 text-xs">ถึง</span>
                        </div>
                        <input type="text" id="input_time_end" class="form-control form-control-sm"
                            value="{{ $defaultEndTimeStr }}" placeholder="15.00">
                    </div>
                </div>

                <!-- Proctor Name Input -->
                <div class="form-group mb-2">
                    <label class="font-weight-bold text-dark text-xs mb-1">กรรมการควบคุมสอบ:</label>
                    <input type="text" id="input_proctor" class="form-control form-control-sm" placeholder="" value="">
                </div>

                <!-- Receiver Name Input -->
                <div class="form-group mb-2">
                    <label class="font-weight-bold text-dark text-xs mb-1">ชื่อผู้รับข้อสอบ:</label>
                    <input type="text" id="input_receiver" class="form-control form-control-sm"
                        placeholder="(เว้นว่างไว้ลงชื่อ หรือระบุชื่อ)">
                </div>

                <!-- Teacher Name Input -->
                <div class="form-group mb-2">
                    <label class="font-weight-bold text-dark text-xs mb-1">อาจารย์ประจำวิชา / ผู้ตรวจ:</label>
                    <input type="text" id="input_teacher" class="form-control form-control-sm"
                        value="{{ $teacherName && $teacherName !== '-' ? $teacherName : '' }}"
                        placeholder="ระบุชื่ออาจารย์ประจำวิชา">
                </div>
            </div>

            <div class="mt-auto pt-2 border-top text-muted text-xs text-center">
                <i class="fas fa-check-circle text-success mr-1"></i>ซิงค์ข้อมูลลงเอกสารอัตโนมัติ
            </div>
        </aside>

        <!-- ============================================== -->
        <!-- ฝั่งขวา: พรีวิวเอกสารพิมพ์ขนาด A4 แนวตั้ง -->
        <!-- ============================================== -->
        <main class="preview-area">
            <div class="sheets-container">

                @forelse($displayClassrooms as $classroomId => $group)
                    @php
                        $classroom = $group['classroom'];
                        $students = $group['students'];
                        $eligibleStudents = $group['eligible_students'];
                        $ineligibleStudents = $group['ineligible_students'];
                        $eligibleCount = $eligibleStudents->count();
                        $hasTeacher = $teacherName && $teacherName !== '-';
                    @endphp

                    <!-- ========================================================= -->
                    <!-- หน้าที่ 1: ใบปะหน้าข้อสอบและรายงานการคุมสอบ (แบบเอกสารทางการสะอาดตา) -->
                    <!-- ========================================================= -->
                    <div class="sheet-page">
                        <!-- College Title & Exam Title Heading -->
                        <div class="text-center">
                            <div>
                                <img src="{{ asset('images/logo.png') }}" alt="โลโก้วิทยาลัยเทคโนโลยีศรีราชา"
                                    class="college-logo">
                            </div>
                            <div class="college-title">
                                วิทยาลัยเทคโนโลยีศรีราชา
                            </div>
                            <div class="college-title-en">
                                SRIRACHA TECHNOLOGICAL COLLEGE
                            </div>
                            <!-- หัวข้อสอบ พร้อมข้อมูลรายวิชา วันสอบ ห้องสอบ ในบรรทัดเดียวกัน (ไม่มีกรอบ) -->
                            <div class="exam-header-line">
                                <span class="exam-title-inline">{{ $exam->title }}</span>

                                <span class="keyinfo-item">
                                    <span class="keyinfo-label">วิชา</span>
                                    <span class="keyinfo-val font-weight-bold">{{ $exam->subject->name ?? '-' }}</span>
                                    <span class="keyinfo-code font-weight-bold">({{ $exam->subject->code ?? '-' }})</span>
                                </span>
                                <span class="keyinfo-item">

                                    <span
                                        class="keyinfo-val font-weight-bold sync-date {{ !$defaultDateStr ? 'highlight-needed' : '' }}">{{ $defaultDateStr ?: '-' }}</span>
                                    @if($defaultTimeStr)
                                        <span class="keyinfo-time font-weight-bold">({{ $defaultTimeStr }} -
                                            {{ $defaultEndTimeStr }})</span>
                                    @endif
                                </span>
                                <span class="keyinfo-item">
                                    <span class="keyinfo-label">ห้อง </span>
                                    <span class="keyinfo-val font-weight-bold sync-room highlight-needed">-</span>
                                    <span class="keyinfo-sub font-weight-bold">{{ $classroom->name }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Main Form Document Box (Clean Layout) -->
                        <div class="doc-card">
                            <!-- Row 1: Teacher & Subject info -->
                            <div class="doc-row">
                                <span class="doc-label">อาจารย์ประจำวิชา:</span>
                                <span class="doc-value flex-grow-1">
                                    <span
                                        class="sync-teacher {{ !$hasTeacher ? 'highlight-needed' : '' }}">{{ $hasTeacher ? $teacherName : '-' }}</span>
                                </span>
                                <span class="doc-label ml-3">รายวิชา:</span>
                                <span
                                    class="doc-value flex-grow-1 font-weight-bold">{{ $exam->subject->name ?? '-' }}</span>
                                <span class="doc-label ml-3">รหัส:</span>
                                <span class="doc-value text-center font-weight-bold"
                                    style="min-width: 95px;">{{ $exam->subject->code ?? '-' }}</span>
                            </div>

                            <!-- Row 2: Room, Class, Session, Numbers -->
                            <div class="doc-row">
                                <span class="doc-label">ห้องที่สอบ:</span>
                                <span class="doc-value text-center font-weight-bold" style="min-width: 70px;">
                                    <span class="sync-room highlight-needed">-</span>
                                </span>
                                <span class="doc-label ml-3">ชั้น:</span>
                                <span class="doc-value font-weight-bold flex-grow-1">{{ $classroom->name }}</span>
                                <span class="doc-label ml-3">ภาค:</span>
                                <span class="doc-value text-center" style="min-width: 60px;">
                                    <span class="sync-session highlight-needed">-</span>
                                </span>
                                <span class="doc-label ml-3">เลขที่:</span>
                                <span class="doc-value text-center" style="min-width: 80px;">
                                    1 &nbsp;ถึง&nbsp; {{ $eligibleCount > 0 ? $eligibleCount : '-' }}
                                </span>
                            </div>

                            <!-- Row 3: เดินเรียน, รวมผู้เข้าสอบ, จำนวนข้อสอบ -->
                            <div class="doc-row">
                                <span class="doc-label">เดินเรียน:</span>
                                <span class="doc-value text-center" style="min-width: 55px;">-</span>
                                <span class="doc-unit mr-4">คน</span>
                                <span class="doc-label ml-2">รวมผู้เข้าสอบ:</span>
                                <span class="doc-value text-center font-weight-bold"
                                    style="min-width: 65px;">{{ $eligibleCount }}</span>
                                <span class="doc-unit mr-4">คน</span>
                                <span class="doc-label ml-2">จำนวนข้อสอบ:</span>
                                <span class="doc-value text-center font-weight-bold"
                                    style="min-width: 65px;">{{ $eligibleCount }}</span>
                                <span class="doc-unit">ชุด</span>
                            </div>

                            <!-- Row 4: Exam Date & Time -->
                            <div class="doc-row">
                                <span class="doc-label">สอบวันที่:</span>
                                <span class="doc-value flex-grow-1 font-weight-bold">
                                    <span
                                        class="sync-date {{ !$defaultDateStr ? 'highlight-needed' : '' }}">{{ $defaultDateStr ?: '-' }}</span>
                                </span>
                                <span class="doc-label ml-3">เวลา:</span>
                                <span class="doc-value text-center font-weight-bold" style="min-width: 145px;">
                                    @if($defaultTimeStr)
                                        <span class="sync-time-range">{{ $defaultTimeStr }} ถึง {{ $defaultEndTimeStr }}</span>
                                    @else
                                        <span class="sync-time-range highlight-needed">-</span>
                                    @endif
                                </span>
                            </div>

                            <!-- Row 5: Ineligible Students (ดึงจากระบบอัตโนมัติ) -->
                            <div class="doc-row align-items-start mt-2">
                                <div class="d-flex align-items-center">
                                    <span class="doc-label">ไม่มีสิทธิ์สอบ:</span>
                                    <span class="doc-value text-center font-weight-bold" style="min-width: 45px;">
                                        {{ $ineligibleStudents->count() }}
                                    </span>
                                    <span class="doc-unit mr-3">คน</span>
                                </div>
                                <div class="flex-grow-1 ml-2">
                                    @if($ineligibleStudents->count() > 0)
                                        <div class="row no-gutters">
                                            @foreach($ineligibleStudents->values() as $idx => $st)
                                                <div class="col-6 pr-2 mb-1">
                                                    <span class="text-danger font-weight-bold mr-1">{{ $idx + 1 }}.</span>
                                                    <span class="doc-value d-inline-block w-75 font-weight-bold text-dark">
                                                        {{ $st->name }} ({{ $st->student_code }})
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-muted ml-1">-</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Signatures for Part 1 -->
                            <div class="signature-group mt-3">
                                <div class="signature-block">
                                    <div class="signature-row">
                                        <span class="sig-label">ลงชื่อ</span>
                                        <span class="signature-line-dotted"><span
                                                class="sync-teacher {{ !$hasTeacher ? 'highlight-needed' : '' }}">{{ $hasTeacher ? $teacherName : '-' }}</span></span>
                                        <span class="sig-role">ผู้ส่งข้อสอบ</span>
                                    </div>
                                    <div class="signature-row mt-1">
                                        <span class="sig-label">ลงชื่อ</span>
                                        <span class="signature-line-dotted"><span class="sync-receiver">&nbsp;</span></span>
                                        <span class="sig-role">ผู้รับข้อสอบ</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Dashed Divider -->
                            <div class="doc-section-divider"></div>

                            <!-- Section 2: ตอนที่ 2 กรรมการควบคุมสอบ -->
                            <div class="doc-section-title">
                                ตอนที่ 2 กรรมการควบคุมสอบ
                            </div>

                            <div class="doc-row">
                                <span class="doc-label">ชื่อกรรมการควบคุมสอบ:</span>
                                <span class="doc-value flex-grow-1">
                                    <span class="sync-proctor highlight-needed">-</span>
                                </span>
                                <span class="doc-label ml-3">จำนวนนักเรียนเข้าห้องสอบนี้เต็ม:</span>
                                <span class="doc-value text-center font-weight-bold"
                                    style="min-width: 65px;">{{ $eligibleCount }}</span>
                                <span class="doc-unit">คน</span>
                            </div>

                            <div class="doc-row">
                                <span class="doc-label">เข้าสอบวันนี้:</span>
                                <span class="doc-value text-center" style="min-width: 80px;">&nbsp;</span>
                                <span class="doc-unit mr-5">คน</span>
                                <span class="doc-label ml-4">ขาดสอบ:</span>
                                <span class="doc-value text-center" style="min-width: 80px;">&nbsp;</span>
                                <span class="doc-unit">คน</span>
                            </div>

                            <div class="doc-row align-items-start mt-1">
                                <span class="doc-label">คือ:</span>
                                <div class="flex-grow-1 ml-2">
                                    <div class="row no-gutters">
                                        <div class="col-6 pr-2 mb-1">
                                            <span class="text-muted mr-1">1.</span>
                                            <span class="doc-value d-inline-block w-75">&nbsp;</span>
                                        </div>
                                        <div class="col-6 pr-2 mb-1">
                                            <span class="text-muted mr-1">2.</span>
                                            <span class="doc-value d-inline-block w-75">&nbsp;</span>
                                        </div>
                                        <div class="col-6 pr-2 mb-1">
                                            <span class="text-muted mr-1">3.</span>
                                            <span class="doc-value d-inline-block w-75">&nbsp;</span>
                                        </div>
                                        <div class="col-6 pr-2 mb-1">
                                            <span class="text-muted mr-1">4.</span>
                                            <span class="doc-value d-inline-block w-75">&nbsp;</span>
                                        </div>
                                        <div class="col-6 pr-2">
                                            <span class="text-muted mr-1">5.</span>
                                            <span class="doc-value d-inline-block w-75">&nbsp;</span>
                                        </div>
                                        <div class="col-6 pr-2">
                                            <span class="text-muted mr-1">6.</span>
                                            <span class="doc-value d-inline-block w-75">&nbsp;</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="doc-row mt-2">
                                <span class="doc-label">จำนวนข้อสอบ:</span>
                                <span class="doc-value text-center font-weight-bold"
                                    style="min-width: 65px;">{{ $eligibleCount }}</span>
                                <span class="doc-unit mr-5">ชุด</span>
                                <span class="doc-label ml-4">จำนวนกระดาษคำตอบ:</span>
                                <span class="doc-value text-center" style="min-width: 80px;">&nbsp;</span>
                                <span class="doc-unit">แผ่น</span>
                            </div>

                            <!-- Signatures for Part 2 -->
                            <div class="signature-group mt-3">
                                <div class="signature-block">
                                    <div class="signature-row">
                                        <span class="sig-label">ลงชื่อ</span>
                                        <span class="signature-line-dotted"><span
                                                class="sync-proctor highlight-needed">-</span></span>
                                        <span class="sig-role">กรรมการควบคุมสอบ</span>
                                    </div>
                                    <div class="signature-row mt-1">
                                        <span class="sig-label">ลงชื่อ</span>
                                        <span class="signature-line-dotted"><span class="sync-receiver">&nbsp;</span></span>
                                        <span class="sig-role">กรรมการรับข้อสอบ</span>
                                    </div>
                                    <div class="signature-row mt-1">
                                        <span class="sig-label">ลงชื่อ</span>
                                        <span class="signature-line-dotted"><span
                                                class="sync-teacher {{ !$hasTeacher ? 'highlight-needed' : '' }}">{{ $hasTeacher ? $teacherName : '-' }}</span></span>
                                        <span class="sig-role">กรรมการตรวจข้อสอบ</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Page indicator bottom -->
                        <div class="page-footer-indicator">
                            กลุ่มเรียน: {{ $classroom->name }} (หน้าที่ 1/2)
                        </div>
                    </div>

                    <!-- ==================================================== -->
                    <!-- หน้าที่ 2: รายชื่อนักศึกษาเข้าห้องสอบและเซ็นชื่อ (A4) -->
                    <!-- ==================================================== -->
                    <div class="sheet-page">
                        <!-- Header: Title & Subject Details -->
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-1">
                            <div class="d-flex align-items-center">
                                <img src="{{ asset('images/logo.png') }}" alt="โลโก้วิทยาลัย"
                                    style="height: 38px; width: auto; margin-right: 8px;">
                                <div>
                                    <div class="page2-header-college font-weight-bold text-dark">
                                        วิทยาลัยเทคโนโลยีศรีราชา &bull; SRIRACHA TECHNOLOGICAL COLLEGE
                                    </div>
                                    <h5 class="font-weight-bold mb-0 text-dark page2-header-title">
                                        รายชื่อนักศึกษาเข้าห้องสอบ
                                    </h5>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-weight-bold text-dark page2-exam-title">
                                    {{ $exam->title }}
                                </div>
                            </div>
                        </div>

                        <!-- Clean Info Header (No border) -->
                        <div class="page2-info-box mb-1 pb-1">
                            <div class="row no-gutters mb-1">
                                <div class="col-7">
                                    <span class="font-weight-bold">ชื่อวิชา:</span> <span
                                        class="font-weight-bold text-dark">{{ $exam->subject->name ?? '-' }}</span>
                                </div>
                                <div class="col-5">
                                    <span class="font-weight-bold">รหัสวิชา:</span> <span
                                        class="font-weight-bold text-dark">{{ $exam->subject->code ?? '-' }}</span>
                                </div>
                            </div>
                            <div class="row no-gutters mb-1">
                                <div class="col-7">
                                    <span class="font-weight-bold">ชั้น:</span> <strong>{{ $classroom->name }}</strong>
                                    &nbsp;&nbsp; ภาค<span class="sync-session highlight-needed font-weight-bold">-</span>
                                </div>
                                <div class="col-5">
                                    <span class="font-weight-bold">ห้องสอบ:</span> <span
                                        class="sync-room highlight-needed font-weight-bold">-</span>
                                </div>
                            </div>
                            <div class="row no-gutters">
                                <div class="col-7">
                                    <span class="font-weight-bold">วันที่สอบ:</span>
                                    <span
                                        class="sync-date font-weight-bold {{ !$defaultDateStr ? 'highlight-needed' : '' }}">{{ $defaultDateStr ?: '-' }}</span>
                                </div>
                                <div class="col-5">
                                    <span class="font-weight-bold">ผู้คุมสอบ:</span>
                                    <span class="sync-proctor highlight-needed font-weight-bold">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Student List Dual Table (1-30 on left, 31-60 on right) -->
                        @php
                            $leftStudents = $eligibleStudents->slice(0, 30)->values();
                            $rightStudents = $eligibleStudents->slice(30, 30)->values();
                            $rowCount = 30; // Always 30 rows to match the official pre-printed form
                        @endphp

                        <div class="student-table-container">
                            <!-- Left Side Table (1-30) -->
                            <table class="student-sub-table">
                                <thead>
                                    <tr>
                                        <th style="width: 6%;">#</th>
                                        <th style="width: 26%;">รหัสนักศึกษา</th>
                                        <th style="width: 41%;">รายชื่อนักเรียนที่เข้าสอบ</th>
                                        <th style="width: 17%;">ลายเซ็นต์ส่งข้อสอบ</th>
                                        <th style="width: 10%;">คะแนน</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @for($i = 0; $i < $rowCount; $i++)
                                        @php $st = $leftStudents->get($i); @endphp
                                        <tr>
                                            <td class="text-center font-weight-bold">{{ $i + 1 }}</td>
                                            <td class="text-center font-weight-bold">{{ $st ? $st->student_code : '' }}</td>
                                            <td class="font-weight-bold pl-1 text-truncate">{{ $st ? $st->name : '' }}</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>

                            <!-- Right Side Table (31-60) -->
                            <table class="student-sub-table">
                                <thead>
                                    <tr>
                                        <th style="width: 6%;">#</th>
                                        <th style="width: 26%;">รหัสนักศึกษา</th>
                                        <th style="width: 41%;">รายชื่อนักเรียนที่เข้าสอบ</th>
                                        <th style="width: 17%;">ลายเซ็นต์ส่งข้อสอบ</th>
                                        <th style="width: 10%;">คะแนน</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @for($i = 0; $i < $rowCount; $i++)
                                        @php $st = $rightStudents->get($i); @endphp
                                        <tr>
                                            <td class="text-center font-weight-bold">{{ $i + 31 }}</td>
                                            <td class="text-center font-weight-bold">{{ $st ? $st->student_code : '' }}</td>
                                            <td class="font-weight-bold pl-1 text-truncate">{{ $st ? $st->name : '' }}</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>
                        </div>

                        <!-- Footer Section: Absent Students Lines & Signatures -->
                        <div
                            class="page2-footer d-flex justify-content-between align-items-end mt-1 pt-1 border-top border-dark">
                            <!-- Left: Absent Students Box -->
                            <div style="width: 48%;">
                                <div class="font-weight-bold mb-1">รายชื่อนักเรียนที่ขาดสอบ</div>
                                <div class="dotted-line"></div>
                                <div class="dotted-line"></div>
                                <div class="dotted-line"></div>
                            </div>

                            <!-- Right: Signatures -->
                            <div style="width: 50%;" class="d-flex justify-content-end">
                                <div class="signature-block page2-sig-block">
                                    <div class="signature-row">
                                        <span class="sig-label">ลงชื่อ</span>
                                        <span class="signature-line-dotted page2-sig-line"><span
                                                class="sync-proctor highlight-needed">-</span></span>
                                        <span class="sig-role">กรรมการคุมสอบ</span>
                                    </div>
                                    <div class="signature-row" style="margin-top: 8px;">
                                        <span class="sig-label">ลงชื่อ</span>
                                        <span class="signature-line-dotted page2-sig-line"><span
                                                class="sync-teacher {{ !$hasTeacher ? 'highlight-needed' : '' }}">{{ $hasTeacher ? $teacherName : '-' }}</span></span>
                                        <span class="sig-role">ผู้ตรวจข้อสอบ</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Page indicator bottom -->
                        <div class="page-footer-indicator">
                            กลุ่มเรียน: {{ $classroom->name }} (หน้าที่ 2/2)
                        </div>
                    </div>

                @empty
                    <div class="card p-5 text-center bg-white shadow-sm" style="max-width: 600px;">
                        <i class="fas fa-exclamation-circle text-warning fa-3x mb-3"></i>
                        <h4>ไม่พบกลุ่มเรียนหรือนักศึกษาที่ลงทะเบียนในรายวิชานี้</h4>
                        <p class="text-muted">กรุณาเพิ่มนักศึกษาหรือกลุ่มเรียนเข้าสู่รายวิชานี้ก่อนทำการพิมพ์ใบปะหน้าข้อสอบ
                        </p>
                        <a href="{{ route('admin.exams.index') }}" class="btn btn-secondary mt-2">
                            กลับหน้ารายการข้อสอบ
                        </a>
                    </div>
                @endforelse

            </div>
        </main>
    </div>

    <!-- Scripts for Interactivity & Live Sync -->
    <script>
        function filterClassroom(classroomId) {
            var currentUrl = new URL(window.location.href);
            if (classroomId === 'all') {
                currentUrl.searchParams.delete('classroom_id');
            } else {
                currentUrl.searchParams.set('classroom_id', classroomId);
            }
            window.location.href = currentUrl.toString();
        }

        // Live Synchronize Sidebar inputs to all sheets
        function syncField(inputId, targetClass, defaultVal, isOptional) {
            var inputElem = document.getElementById(inputId);
            if (!inputElem) return;

            var rawVal = inputElem.value.trim();
            var targets = document.querySelectorAll('.' + targetClass);
            targets.forEach(function (el) {
                if (rawVal) {
                    el.textContent = rawVal;
                    el.classList.remove('highlight-needed');
                } else {
                    el.textContent = defaultVal !== undefined ? defaultVal : '-';
                    if (!isOptional) {
                        el.classList.add('highlight-needed');
                    } else {
                        el.classList.remove('highlight-needed');
                    }
                }
            });
        }

        function syncTime() {
            var start = (document.getElementById('input_time_start')?.value || '').trim();
            var end = (document.getElementById('input_time_end')?.value || '').trim();
            var targets = document.querySelectorAll('.sync-time-range');
            targets.forEach(function (el) {
                if (start || end) {
                    el.textContent = (start || '-') + ' ถึง ' + (end || '-');
                    el.classList.remove('highlight-needed');
                } else {
                    el.textContent = '-';
                    el.classList.add('highlight-needed');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            var roomInput = document.getElementById('input_room');
            var sessionInput = document.getElementById('input_session');
            var dateInput = document.getElementById('input_date');
            var timeStartInput = document.getElementById('input_time_start');
            var timeEndInput = document.getElementById('input_time_end');
            var proctorInput = document.getElementById('input_proctor');
            var receiverInput = document.getElementById('input_receiver');
            var teacherInput = document.getElementById('input_teacher');

            if (roomInput) {
                roomInput.addEventListener('input', function () { syncField('input_room', 'sync-room', '-'); });
                syncField('input_room', 'sync-room', '-');
            }

            if (sessionInput) {
                sessionInput.addEventListener('change', function () { syncField('input_session', 'sync-session', '-'); });
                syncField('input_session', 'sync-session', '-');
            }

            if (dateInput) {
                dateInput.addEventListener('input', function () { syncField('input_date', 'sync-date', '-'); });
                syncField('input_date', 'sync-date', '-');
            }

            if (timeStartInput && timeEndInput) {
                timeStartInput.addEventListener('input', syncTime);
                timeEndInput.addEventListener('input', syncTime);
                syncTime();
            }

            if (proctorInput) {
                proctorInput.addEventListener('input', function () { syncField('input_proctor', 'sync-proctor', '-'); });
                syncField('input_proctor', 'sync-proctor', '-');
            }

            if (receiverInput) {
                receiverInput.addEventListener('input', function () { syncField('input_receiver', 'sync-receiver', '\u00A0', true); });
                syncField('input_receiver', 'sync-receiver', '\u00A0', true);
            }

            if (teacherInput) {
                teacherInput.addEventListener('input', function () { syncField('input_teacher', 'sync-teacher', '-'); });
                syncField('input_teacher', 'sync-teacher', '-');
            }
        });
    </script>
</body>

</html>