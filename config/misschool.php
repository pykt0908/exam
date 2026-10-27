<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MisSchool Integration Enabled
    |--------------------------------------------------------------------------
    | กำหนดเปิดหรือปิดการตรวจสอบผู้ใช้ผ่านระบบ MisSchool
    | หากตั้งเป็น true: ระบบจะส่งข้อมูลไปตรวจสอบกับ MisSchool ก่อนเสมอ
    | หากตั้งเป็น false: ระบบจะใช้การตรวจสอบในฐานข้อมูลระบบสอบเดิม
    */
    'enabled' => env('MIS_AUTH_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Connection Mode
    |--------------------------------------------------------------------------
    | รูปแบบการเชื่อมต่อไปยังฐานข้อมูล MisSchool:
    | - 'bridge': เรียกผ่าน HTTP Bridge API (แนะนำสำหรับ Shared Hosting ที่ถูกบล็อกพอร์ต)
    | - 'sqlsrv': เชื่อมต่อไปยัง Microsoft SQL Server โดยตรงผ่านไดรเวอร์ sqlsrv
    */
    'connection_mode' => env('MIS_CONNECTION_MODE', 'bridge'),

    /*
    |--------------------------------------------------------------------------
    | Bridge API Settings (โหมด 'bridge')
    |--------------------------------------------------------------------------
    | ข้อมูลการเชื่อมต่อไปยัง Bridge Server ตัวกลาง
    */
    'bridge' => [
        'url' => env('MIS_BRIDGE_URL', ''),
        'key' => env('MIS_BRIDGE_KEY', ''),
        'timeout' => env('MIS_BRIDGE_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | SQL Server Direct Settings (โหมด 'sqlsrv')
    |--------------------------------------------------------------------------
    | ข้อมูลการเชื่อมต่อไปยัง Microsoft SQL Server ของวิทยาลัยโดยตรง
    */
    'sqlsrv' => [
        'host' => env('DB_MISSCHOOL_HOST', '127.0.0.1'),
        'port' => env('DB_MISSCHOOL_PORT', '1433'),
        'database_student' => env('DB_MISSCHOOL_DATABASE_STUDENT', 'Tabain'),
        'database_teacher' => env('DB_MISSCHOOL_DATABASE_TEACHER', 'Personal'),
        'username' => env('DB_MISSCHOOL_USERNAME', ''),
        'password' => env('DB_MISSCHOOL_PASSWORD', ''),
        'encrypt' => env('DB_MISSCHOOL_ENCRYPT', 'no'),
        'trust_server_certificate' => env('DB_MISSCHOOL_TRUST_SERVER_CERTIFICATE', 'true'),
    ],
];
