<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เซสชันหมดอายุ (419 Page Expired) - ระบบสอบออนไลน์</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #0a192f;
            color: #333;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .error-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            max-width: 480px;
            width: 100%;
            padding: 35px 25px;
            text-align: center;
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #fff3cd;
            color: #856404;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="icon-circle">
            <i class="fas fa-hourglass-end"></i>
        </div>
        <h4 class="font-weight-bold text-dark mb-2">หน้าเว็บหมดอายุ (Session Expired)</h4>
        <p class="text-muted text-sm mb-4" style="line-height: 1.6;">
            เซสชันการเชื่อมต่อของคุณหมดอายุเนื่องจากเปิดหน้าทิ้งไว้นาน หรือระบบมีการรีเฟรชความปลอดภัย กรุณากดปุ่มด้านล่างเพื่อเข้าสู่ระบบใหม่อีกครั้ง
        </p>
        <a href="{{ route('login') }}" class="btn btn-primary btn-block py-2 font-weight-bold shadow-sm" style="border-radius: 8px;">
            <i class="fas fa-redo-alt mr-2"></i> กลับสู่หน้าเข้าสู่ระบบ
        </a>
    </div>
</body>
</html>
