<?php
// เริ่ม Session ด้วยค่าร่วม ก่อนล้างข้อมูลและออกจากระบบ
require_once __DIR__ . '/session_bootstrap.php';

// ล้างตัวแปร Session ทั้งหมด
$_SESSION = array();

// ทำลาย Cookie ของ Session ในเบราว์เซอร์
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
        setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'],
    ]);
}

// ทำลาย Session ฝั่งเซิร์ฟเวอร์
session_destroy();

// ส่งกลับหน้าเข้าสู่ระบบ
header("Location: login.php");
exit();
?>