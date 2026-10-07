<?php
// ตั้งค่า Session สำหรับ HTTPS (ngrok) และป้องกันข้ามโดเมน แต่รองรับการเปิดผ่าน LINE
$session_params = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 28800, // กำหนดอายุ Session 8 ชั่วโมง
    'path' => '/',
    'domain' => '', // ปล่อยว่างเพื่อรองรับโดเมนปัจจุบันที่เปลี่ยนไปมาของ ngrok
    'secure' => true, // บังคับส่ง Cookie ผ่าน HTTPS เท่านั้น
    'httponly' => true, // ป้องกันการดึง Cookie ผ่าน JavaScript (XSS)
    'samesite' => 'None' // จำเป็นต้องใช้ None เพื่อให้เปิดหน้าเว็บซ้อนใน LINE (LIFF/In-App Browser) ได้
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบและตั้งเวลาหมดอายุของ Session (8 ชั่วโมง)
$timeout_duration = 28800;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout_duration)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// ตรวจสอบเบื้องต้นว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['user_id'])) {
    // ฝาก URL ปัจจุบันไว้ เพื่อให้หลังล็อกอินสำเร็จ เด้งกลับมาหน้าเดิมได้
    $_SESSION['redirect_url'] = "http" . (isset($_SERVER['HTTPS']) ? "s" : "") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    header("Location: login.php");
    exit();
}

require_once 'db_connect.php';

// ตรวจสอบฐานข้อมูลปัจจุบันว่าบัญชียังมีอยู่ เปิดใช้งาน และข้อมูลตัวตนตรงกับ Session ปัจจุบัน
$user_id = $_SESSION['user_id'];
// ✨ ข้อ 2.11: ดึงค่า must_change_password ออกมาด้วย
$stmt = $conn->prepare("SELECT role, technician_id, is_active, auth_version, must_change_password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // กรณีถูกลบบัญชีออกจากฐานข้อมูล
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// ปฏิเสธการเข้าถึงหากบัญชีถูกระงับ หรือมีการบังคับยกเลิก Session (auth_version ไม่ตรงกัน)
// ✨ ข้อ 2.11: เพิ่มกฎการระงับการเข้าถึง หากเพิ่งถูกแอดมินแก้ไขอีเมลแล้วยกเลิกสถานะยืนยันตัวตน
if ($user['is_active'] != 1 || $user['auth_version'] != $_SESSION['auth_version']) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// ✨ ข้อ 2.11: บังคับเปลี่ยนรหัสผ่านชั่วคราว หากถูกแอดมินรีเซ็ตมา
if ($user['must_change_password'] == 1) {
    // ดึงชื่อไฟล์ปัจจุบัน เพื่อไม่ให้เกิดลูปรีไดเร็กต์ (Infinite Redirect Loop)
    $current_page = basename($_SERVER['PHP_SELF']);
    // ถ้าไม่ได้อยู่หน้าเปลี่ยนรหัส หรือหน้าล็อกเอาต์ ให้เตะไปหน้าเปลี่ยนรหัสทันที
    if ($current_page !== 'force_change_password.php' && $current_page !== 'logout.php') {
        header("Location: force_change_password.php");
        exit();
    }
}

// อัปเดตข้อมูลสิทธิ์และรหัสช่างล่าสุดลง Session เพื่อป้องกันข้อมูลคลาดเคลื่อน
$_SESSION['role'] = $user['role'];
if (strtolower($user['role']) === 'technician') {
    // กรณีเป็นช่าง ต้องตรวจสอบว่ามีการผูกรหัสช่างไว้จริง
    if (empty($user['technician_id'])) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    // ✨ ข้อ 3: ตรวจสอบว่าแอดมินเปลี่ยนการเชื่อมช่างระหว่างล็อกอินค้างไว้หรือไม่ (ต้องใช้ Session เดิมไม่ได้)
    if (isset($_SESSION['technician_id']) && $_SESSION['technician_id'] != $user['technician_id']) {
        session_unset();
        session_destroy();
        header("Location: login.php?error=tech_link_changed");
        exit();
    }
    
    $_SESSION['technician_id'] = $user['technician_id'];
} else {
    // กรณีบัญชีไม่ใช่ช่าง ให้ล้างค่ารหัสช่างที่อาจตกค้างทิ้ง
    unset($_SESSION['technician_id']);
}
?>