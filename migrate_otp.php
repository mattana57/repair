<?php
require_once 'db_connect.php';

echo "<h2>เริ่มการตรวจสอบและอัปเดตฐานข้อมูลสำหรับระบบ OTP...</h2>";

// ==========================================
// 1. เพิ่มคอลัมน์สำหรับการยืนยันอีเมลในตาราง users
// ==========================================
$check_cols = $conn->query("SHOW COLUMNS FROM users LIKE 'verified_email'");
if ($check_cols && $check_cols->num_rows === 0) {
    $sql_alter_users = "ALTER TABLE users 
                        ADD COLUMN verified_email VARCHAR(255) NULL COMMENT 'อีเมลที่ยืนยันจริง ใช้ตรวจว่าตรงกับอีเมลปัจจุบัน',
                        ADD COLUMN email_verified_at DATETIME NULL COMMENT 'เวลาที่เจ้าของบัญชียืนยันอีเมลสำเร็จ'";
    
    if ($conn->query($sql_alter_users)) {
        echo "<p style='color:green;'>✅ เพิ่มคอลัมน์ verified_email และ email_verified_at ในตาราง users สำเร็จ</p>";
    } else {
        echo "<p style='color:red;'>❌ เกิดข้อผิดพลาดในการแก้ตาราง users: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:blue;'>ℹ️ คอลัมน์การยืนยันอีเมลในตาราง users มีอยู่แล้ว (ข้ามการทำงาน)</p>";
}

// ==========================================
// 2. สร้างตาราง auth_requests สำหรับจัดการ OTP แยกออกจากตารางบัญชี
// ==========================================
$sql_create_table = "
CREATE TABLE IF NOT EXISTS auth_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_token VARCHAR(64) NOT NULL UNIQUE COMMENT 'รหัสคำขอแบบสุ่ม ระบุคำขอยืนยัน/รีเซ็ต โดยไม่ใช้เลขบัญชีเป็นโทเคน',
    user_id INT NOT NULL COMMENT 'บัญชีเจ้าของคำขอ',
    purpose ENUM('email_verification', 'password_reset') NOT NULL COMMENT 'แยกการยืนยันอีเมลออกจากการรีเซ็ตรหัสผ่าน',
    target_email VARCHAR(255) NOT NULL COMMENT 'อีเมลปลายทางของคำขอ เพื่อตรวจว่าไม่ถูกเปลี่ยนกลางทาง',
    otp_hash VARCHAR(255) NOT NULL COMMENT 'เก็บค่าตรวจสอบ OTP โดยไม่เก็บรหัสตรงๆ (ใช้ password_hash)',
    failed_attempts INT DEFAULT 0 COMMENT 'จำนวนครั้งที่กรอกผิด จำกัดการลอง OTP',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'เวลาที่สร้างคำขอ',
    last_sent_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'เวลาส่งล่าสุด ควบคุมอายุคำขอและการส่งซ้ำ',
    expires_at DATETIME NOT NULL COMMENT 'เวลาหมดอายุของคำขอ/OTP',
    verified_at DATETIME NULL COMMENT 'เวลาที่ยืนยัน OTP สำเร็จ',
    used_at DATETIME NULL COMMENT 'เวลาที่ใช้สิทธิ์สำเร็จ (เปลี่ยนรหัส/ยืนยัน) ป้องกันการใช้ซ้ำ',
    is_canceled TINYINT(1) DEFAULT 0 COMMENT 'สถานะยกเลิก (1=ยกเลิก)',
    auth_version INT NOT NULL COMMENT 'ณ เวลาออกคำขอ ใช้ยกเลิกคำขอหากบัญชีถูกเปลี่ยนสิทธิ์/รีเซ็ตระหว่างทาง',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_request_token (request_token),
    INDEX idx_expires_at (expires_at),
    INDEX idx_purpose (purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางเก็บคำขอและ OTP';
";

if ($conn->query($sql_create_table)) {
    echo "<p style='color:green;'>✅ สร้างตาราง auth_requests สำเร็จ หรือตารางนี้มีอยู่แล้ว</p>";
} else {
    echo "<p style='color:red;'>❌ เกิดข้อผิดพลาดในการสร้างตาราง auth_requests: " . $conn->error . "</p>";
}

// ==========================================
// 3. กำหนดวิธี/คิวรี่ลบข้อมูลคำขอที่หมดอายุ (Cleanup Strategy)
// ==========================================
// คิวรี่นี้สามารถนำไปตั้ง Cron Job หรือรันเบื้องหลังเพื่อลบข้อมูลที่หมดอายุเกิน 24 ชั่วโมง และถูกใช้/ยกเลิกไปแล้ว
$cleanup_query = "DELETE FROM auth_requests 
                  WHERE (expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)) 
                  OR (is_canceled = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)) 
                  OR (used_at IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))";

echo "<div style='background:#f1f5f9; padding:15px; border-radius:8px; margin-top:20px;'>
        <strong>🧹 วิธีการเคลียร์ข้อมูลขยะ (Cleanup):</strong><br>
        <span style='font-size:14px; color:#475569;'>เพื่อไม่ให้ตารางรกเกินไป คุณสามารถใช้คำสั่ง SQL ด้านล่างนี้ตั้งค่าใน Event Scheduler ของ MySQL หรือเขียนไฟล์ cron job มารันทุกวันได้ครับ:</span>
        <pre style='background:#1e293b; color:#e2e8f0; padding:10px; border-radius:5px;'>{$cleanup_query};</pre>
      </div>";

echo "<h2>🎉 Migration สิ้นสุดเรียบร้อย!</h2>";
?>