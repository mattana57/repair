<?php
// โหลดไลบรารี PHPMailer (ปรับ Path ให้ตรงกับโฟลเดอร์โปรเจกต์ของคุณ)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// ดึงการตั้งค่าลับจากไฟล์ที่ไม่ได้เอาขึ้น GitHub
require_once 'mail_config.php';

function sendOtpEmail($target_email, $otp_code, $purpose_text = "ยืนยันตัวตน") {
    $mail = new PHPMailer(true);
    try {
        // ตั้งค่าเซิร์ฟเวอร์ (ดึงจาก config ที่ซ่อนไว้)
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // ตั้งค่าผู้ส่งและผู้รับ
        $mail->setFrom(SENDER_EMAIL, SENDER_NAME);
        $mail->addAddress($target_email);

        // เนื้อหาอีเมล
        $mail->isHTML(true);
        $mail->Subject = "รหัส OTP สำหรับ{$purpose_text} - ระบบแจ้งซ่อม MBS";
        
        $body = "<div style='font-family: sans-serif; padding: 20px; background-color: #f8fafc; border-radius: 10px;'>";
        $body .= "<h3 style='color: #1e293b;'>สวัสดีครับ</h3>";
        $body .= "<p style='color: #475569;'>มีการร้องขอรหัสผ่านแบบใช้ครั้งเดียว (OTP) สำหรับ <b>{$purpose_text}</b> ในระบบแจ้งซ่อม MBS</p>";
        $body .= "<div style='background-color: #ffffff; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; display: inline-block; margin: 10px 0;'>";
        $body .= "<h2 style='color: #4f46e5; margin: 0; font-size: 28px; letter-spacing: 3px;'>{$otp_code}</h2>";
        $body .= "</div>";
        $body .= "<p style='color: #ef4444; font-size: 12px; font-weight: bold;'>รหัสนี้มีอายุการใช้งานเพียง 5 นาที กรุณาอย่านำรหัสนี้ไปให้บุคคลอื่น</p>";
        $body .= "<p style='color: #94a3b8; font-size: 12px;'>หากคุณไม่ได้ทำรายการนี้ กรุณาเพิกเฉยต่ออีเมลฉบับนี้</p>";
        $body .= "</div>";
        
        $mail->Body = $body;

        // ส่งอีเมล (ถ้าติด Limit โควตา มันจะ throw Exception ตรงนี้)
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        // บันทึก Error ฝั่งเซิร์ฟเวอร์ แต่ไม่บอกรายละเอียด Error ออกไปให้ User ทราบ (ความปลอดภัย)
        error_log("MBS Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>