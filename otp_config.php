<?php
// ค่าตั้งค่าสำหรับการสร้างคำขอ OTP
// ไม่มีรหัสผ่านหรือข้อมูล SMTP ในไฟล์นี้

return [
    // OTP มีอายุ 5 นาที
    'otp_lifetime_seconds' => 300,

    // เว้นการสร้างคำขอใหม่ของบัญชีเดิม
    // และวัตถุประสงค์เดิมอย่างน้อย 60 วินาที
    'account_cooldown_seconds' => 60,

    // จำกัด 5 คำขอต่อบัญชีต่อวัตถุประสงค์
    // ภายใน 1 ชั่วโมงย้อนหลัง
    'account_window_seconds' => 3600,
    'account_request_limit' => 5,

    // จำกัดตาม IP รวมทั้งการยืนยันอีเมล
    // และการขอรีเซ็ตรหัสผ่าน
    'source_short_window_seconds' => 600,
    'source_short_request_limit' => 20,

    'source_hour_window_seconds' => 3600,
    'source_hour_request_limit' => 100,
];